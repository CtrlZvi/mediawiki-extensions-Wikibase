<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Api;

use ApiBase;
use ApiMain;
use ApiUsageException;
use DataValues\IllegalValueException;
use Deserializers\Deserializer;
use Diff\Comparer\ComparableComparer;
use Diff\Differ\OrderedListDiffer;
use InvalidArgumentException;
use LogicException;
use MediaWiki\MediaWikiServices;
use OutOfBoundsException;
use Wikibase\DataModel\Entity\StatementListProvidingEntity;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Services\Statement\StatementGuidParsingException;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Summary;
use Wikibase\Repo\ChangeOp\StatementChangeOpFactory;
use Wikibase\Repo\ClaimSummaryBuilder;
use Wikibase\Repo\Diff\ClaimDiffer;
use Wikibase\Repo\FederatedProperties\FederatedPropertiesException;
use Wikibase\Repo\WikibaseRepo;

/**
 * API module for creating or updating an entire Claim.
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Addshore
 */
class SetClaim extends ApiBase {

	/**
	 * @var StatementChangeOpFactory
	 */
	private $statementChangeOpFactory;

	/**
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 * @var Deserializer
	 */
	private $statementDeserializer;

	/**
	 * @var StatementModificationHelper
	 */
	private $modificationHelper;

	/**
	 * @var StatementGuidParser
	 */
	private $guidParser;

	/**
	 * @var ResultBuilder
	 */
	private $resultBuilder;

	/**
	 * @var EntitySavingHelper
	 */
	private $entitySavingHelper;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		ApiErrorReporter $errorReporter,
		Deserializer $statementDeserializer,
		StatementChangeOpFactory $statementChangeOpFactory,
		StatementModificationHelper $modificationHelper,
		StatementGuidParser $guidParser,
		callable $resultBuilderInstantiator,
		callable $entitySavingHelperInstantiator
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->errorReporter = $errorReporter;
		$this->statementDeserializer = $statementDeserializer;
		$this->statementChangeOpFactory = $statementChangeOpFactory;
		$this->modificationHelper = $modificationHelper;
		$this->guidParser = $guidParser;
		$this->resultBuilder = $resultBuilderInstantiator( $this );
		$this->entitySavingHelper = $entitySavingHelperInstantiator( $this );
	}

	public static function factory( ApiMain $mainModule, string $moduleName ): self {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $mainModule->getContext() );
		$changeOpFactoryProvider = $wikibaseRepo->getChangeOpFactoryProvider();

		$modificationHelper = new StatementModificationHelper(
			$wikibaseRepo->getSnakFactory(),
			$wikibaseRepo->getEntityIdParser(),
			$wikibaseRepo->getStatementGuidValidator(),
			$apiHelperFactory->getErrorReporter( $mainModule )
		);

		return new self(
			$mainModule,
			$moduleName,
			$apiHelperFactory->getErrorReporter( $mainModule ),
			$wikibaseRepo->getExternalFormatStatementDeserializer(),
			$changeOpFactoryProvider->getStatementChangeOpFactory(),
			$modificationHelper,
			$wikibaseRepo->getStatementGuidParser(),
			function ( $module ) use ( $apiHelperFactory ) {
				return $apiHelperFactory->getResultBuilder( $module );
			},
			function ( $module ) use ( $apiHelperFactory ) {
				return $apiHelperFactory->getEntitySavingHelper( $module );
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): void {
		try {
			$this->executeInternal();
		} catch ( FederatedPropertiesException $ex ) {
			$this->errorReporter->dieWithError(
				'wikibase-federated-properties-save-api-error-message',
				'failed-save'
			);
		}
	}

	private function executeInternal(): void {
		$params = $this->extractRequestParams();
		$statement = $this->getStatementFromParams( $params );
		$guid = $statement->getGuid();

		if ( $guid === null ) {
			$this->errorReporter->dieError( 'GUID must be set when setting a claim', 'invalid-claim' );
		}

		try {
			$statementGuid = $this->guidParser->parse( $guid );
		} catch ( StatementGuidParsingException $ex ) {
			$this->errorReporter->dieException( $ex, 'invalid-claim' );
			throw new LogicException( 'ApiErrorReporter::dieError did not throw an exception' );
		}

		$entityId = $statementGuid->getEntityId();
		$entity = $this->entitySavingHelper->loadEntity( $entityId );

		if ( !( $entity instanceof StatementListProvidingEntity ) ) {
			$this->errorReporter->dieError( 'The given entity cannot contain statements', 'not-supported' );
			throw new LogicException( 'ApiErrorReporter::dieError did not throw an exception' );
		}

		if ( $params['ignoreduplicatemainsnak'] ) {
			if ( $this->statementMainSnakAlreadyExists( $statement, $entity->getStatements() ) ) {
				$this->addWarning( 'wikibase-setclaim-warning-duplicatemainsnak' );
				return;
			}
		}
		$summary = $this->getSummary( $params, $statement, $entity->getStatements() );

		$index = $params['index'] ?? null;
		$changeop = $this->statementChangeOpFactory->newSetStatementOp( $statement, $index );
		$this->modificationHelper->applyChangeOp( $changeop, $entity, $summary );

		$status = $this->entitySavingHelper->attemptSaveEntity( $entity, $summary );
		$this->resultBuilder->addRevisionIdFromStatusToResult( $status, 'pageinfo' );
		$this->resultBuilder->markSuccess();
		$this->resultBuilder->addStatement( $statement );

		$stats = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$stats->increment( 'wikibase.repo.api.wbsetclaim.total' );
		if ( $index !== null ) {
			$stats->increment( 'wikibase.repo.api.wbsetclaim.index' );
		}
	}

	private function statementMainSnakAlreadyExists(
		Statement $statement,
		StatementList $existingStatements
	) : bool {
		$propertyId = $statement->getPropertyId();
		$mainSnak = $statement->getMainSnak();
		foreach ( $existingStatements as $existingStatement ) {
			if ( $existingStatement->getPropertyId()->equals( $propertyId ) ) {
				if ( $existingStatement->getMainSnak()->equals( $mainSnak ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array $params
	 * @param Statement $statement
	 * @param StatementList $statementList
	 *
	 * @throws InvalidArgumentException
	 * @return Summary
	 *
	 * @todo this summary builder is ugly and summary stuff needs to be refactored
	 */
	private function getSummary( array $params, Statement $statement, StatementList $statementList ): Summary {
		$claimSummaryBuilder = new ClaimSummaryBuilder(
			$this->getModuleName(),
			new ClaimDiffer( new OrderedListDiffer( new ComparableComparer() ) )
		);

		$summary = $claimSummaryBuilder->buildClaimSummary(
			$statementList->getFirstStatementWithGuid( $statement->getGuid() ),
			$statement
		);

		if ( isset( $params['summary'] ) ) {
			$summary->setUserSummary( $params['summary'] );
		}

		return $summary;
	}

	/**
	 * @param array $params
	 *
	 * @throws IllegalValueException
	 * @throws ApiUsageException
	 * @throws LogicException
	 * @return Statement
	 */
	private function getStatementFromParams( array $params ): Statement {
		try {
			$serializedStatement = json_decode( $params['claim'], true );
			if ( !is_array( $serializedStatement ) ) {
				throw new IllegalValueException( 'Failed to get statement from Serialization' );
			}
			$statement = $this->statementDeserializer->deserialize( $serializedStatement );
			if ( !( $statement instanceof Statement ) ) {
				throw new IllegalValueException( 'Failed to get statement from Serialization' );
			}
			return $statement;
		} catch ( InvalidArgumentException $invalidArgumentException ) {
			$this->errorReporter->dieError(
				'Failed to get claim from claim Serialization ' . $invalidArgumentException->getMessage(),
				'invalid-claim'
			);
		} catch ( OutOfBoundsException $outOfBoundsException ) {
			$this->errorReporter->dieError(
				'Failed to get claim from claim Serialization ' . $outOfBoundsException->getMessage(),
				'invalid-claim'
			);
		}

		// Note: since dieUsage() never returns, this should be unreachable!
		throw new LogicException( 'ApiErrorReporter::dieError did not throw an exception' );
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode(): bool {
		return true;
	}

	/**
	 * @see ApiBase::needsToken
	 *
	 * @return string
	 */
	public function needsToken(): string {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	protected function getAllowedParams(): array {
		return array_merge(
			[
				'claim' => [
					self::PARAM_TYPE => 'text',
					self::PARAM_REQUIRED => true,
				],
				'index' => [
					self::PARAM_TYPE => 'integer',
				],
				'summary' => [
					self::PARAM_TYPE => 'string',
				],
				'tags' => [
					self::PARAM_TYPE => 'tags',
					self::PARAM_ISMULTI => true,
				],
				'token' => null,
				'baserevid' => [
					self::PARAM_TYPE => 'integer',
				],
				'bot' => false,
				'ignoreduplicatemainsnak' => false,
			],
			parent::getAllowedParams()
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages(): array {
		return [
			'action=wbsetclaim&claim={"id":"Q2$5627445f-43cb-ed6d-3adb-760e85bd17ee",'
				. '"type":"claim","mainsnak":{"snaktype":"value","property":"P1",'
				. '"datavalue":{"value":"City","type":"string"}}}'
				=> 'apihelp-wbsetclaim-example-1',
			'action=wbsetclaim&claim={"id":"Q2$5627445f-43cb-ed6d-3adb-760e85bd17ee",'
				. '"type":"claim","mainsnak":{"snaktype":"value","property":"P1",'
				. '"datavalue":{"value":"City","type":"string"}}}&index=0'
				=> 'apihelp-wbsetclaim-example-2',
			'action=wbsetclaim&claim={"id":"Q2$5627445f-43cb-ed6d-3adb-760e85bd17ee",'
				. '"type":"statement","mainsnak":{"snaktype":"value","property":"P1",'
				. '"datavalue":{"value":"City","type":"string"}},'
				. '"references":[{"snaks":{"P2":[{"snaktype":"value","property":"P2",'
				. '"datavalue":{"value":"The Economy of Cities","type":"string"}}]},'
				. '"snaks-order":["P2"]}],"rank":"normal"}'
				=> 'apihelp-wbsetclaim-example-3',
		];
	}

}
