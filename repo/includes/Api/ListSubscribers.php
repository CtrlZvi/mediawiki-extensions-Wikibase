<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiResult;
use MediaWiki\MediaWikiServices;
use SiteLookup;
use stdClass;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * API module for getting wikis subscribed to changes to given entities.
 *
 * @license GPL-2.0-or-later
 * @author Amir Sarabadani <ladsgroup@gmail.com>
 */
class ListSubscribers extends ApiQueryBase {

	/**
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	/**
	 * @param ApiQuery $mainModule
	 * @param string $moduleName
	 * @param ApiErrorReporter $errorReporter
	 * @param EntityIdParser $idParser
	 * @param SiteLookup $siteLookup
	 *
	 * @see ApiBase::__construct
	 */
	public function __construct(
		ApiQuery $mainModule,
		string $moduleName,
		ApiErrorReporter $errorReporter,
		EntityIdParser $idParser,
		SiteLookup $siteLookup
	) {
		parent::__construct( $mainModule, $moduleName, 'wbls' );

		$this->errorReporter = $errorReporter;
		$this->idParser = $idParser;
		$this->siteLookup = $siteLookup;
	}

	public static function factory( ApiQuery $apiQuery, string $moduleName ): self {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$mediaWikiServices = MediaWikiServices::getInstance();
		$apiHelper = $wikibaseRepo->getApiHelperFactory( $apiQuery->getContext() );
		return new self(
			$apiQuery,
			$moduleName,
			$apiHelper->getErrorReporter( $apiQuery ),
			$wikibaseRepo->getEntityIdParser(),
			$mediaWikiServices->getSiteLookup()
		);
	}

	public function execute(): void {
		$params = $this->extractRequestParams();

		$idStrings = $params['entities'];

		$entitiyIds = [];
		foreach ( $idStrings as $idString ) {
			try {
				$entitiyIds[] = $this->idParser->parse( $idString );
			} catch ( EntityIdParsingException $e ) {
				$this->errorReporter->dieException( $e, 'param-invalid' );
			}
		}

		// Normalize entity ids
		$idStrings = array_map( function( $entity ) {
			return $entity->getSerialization();
		}, $entitiyIds );

		$res = $this->doQuery( $idStrings, $params['continue'], $params['limit'] );
		$props = $params['prop'];
		$this->formatResult( $res, $params['limit'], $props );
	}

	/**
	 * @param string[] $idStrings
	 * @param string|null $continue
	 * @param int $limit
	 *
	 * @return IResultWrapper
	 */
	public function doQuery( array $idStrings, ?string $continue, int $limit ): IResultWrapper {
		$this->addFields( [
			'cs_entity_id',
			'cs_subscriber_id',
		] );

		$this->addTables( 'wb_changes_subscription' );

		$this->addWhereFld( 'cs_entity_id', $idStrings );

		if ( $continue !== null ) {
			$this->addContinue( $continue );
		}

		$orderBy = [ 'cs_entity_id', 'cs_subscriber_id' ];
		$this->addOption( 'ORDER BY', $orderBy );

		$this->addOption( 'LIMIT', $limit + 1 );
		return $this->select( __METHOD__ );
	}

	/**
	 * @param string $continueParam
	 */
	private function addContinue( string $continueParam ): void {
		$db = $this->getDB();
		$continueParams = explode( '|', $continueParam );
		if ( count( $continueParams ) !== 2 ) {
			$this->errorReporter->dieError(
				'Unable to parse continue param',
				'param-invalid'
			);
		}
		$entityContinueSql = $db->addQuotes( $continueParams[0] );
		$wikiContinueSql = $db->addQuotes( $continueParams[1] );
		// Filtering out results that have been shown already and
		// starting the query from where it ended.
		$this->addWhere(
			"cs_entity_id > $entityContinueSql OR " .
			"(cs_entity_id = $entityContinueSql AND " .
			"cs_subscriber_id >= $wikiContinueSql)"
		);
	}

	/**
	 * @param IResultWrapper $res
	 * @param int $limit
	 * @param array $props
	 */
	private function formatResult( IResultWrapper $res, int $limit, array $props ): void {
		$currentEntity = null;
		$count = 0;
		$result = $this->getResult();
		$props = array_flip( $props );

		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'entity' );
		$result->addArrayType( [ 'query', $this->getModuleName() ], 'kvp', 'id' );

		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				// We've reached the one extra which shows that
				// there are additional pages to be had. Stop here...
				$this->setContinueFromRow( $row );
				break;
			}

			$entry = $this->getSubscriber( $row, isset( $props['url'] ) );
			if ( $row->cs_entity_id !== $currentEntity ) {
				// Let's add the data and check if it needs continuation
				$entry = [ 'subscribers' => [ $entry ] ];
				ApiResult::setIndexedTagName( $entry['subscribers'], 'subscriber' );

				$fit = $result->addValue( [ 'query', 'subscribers' ], $row->cs_entity_id, $entry );
			} else {
				$fit = $result->addValue(
					[ 'query', 'subscribers', $row->cs_entity_id, 'subscribers' ],
					null,
					$entry );
			}

			if ( !$fit ) {
				$this->setContinueFromRow( $row );
				break;
			}

			$currentEntity = $row->cs_entity_id;

		}
	}

	/**
	 * @param stdClass $row
	 */
	private function setContinueFromRow( stdClass $row ): void {
		$this->setContinueEnumParameter(
			'continue',
			"{$row->cs_entity_id}|{$row->cs_subscriber_id}"
		);
	}

	/**
	 * @param stdClass $row
	 * @param bool $url
	 *
	 * @return string[]
	 */
	private function getSubscriber( stdClass $row, bool $url ): array {
		$entry = [
			'site' => $row->cs_subscriber_id,
		];
		if ( $url ) {
			$urlProp = $this->getEntityUsageUrl(
				$row->cs_subscriber_id,
				$row->cs_entity_id
			);
			if ( $urlProp ) {
				$entry['url'] = $urlProp;
			}
		}
		return $entry;
	}

	/**
	 * @inheritDoc
	 */
	protected function getAllowedParams(): array {
		return [
			'entities' => [
				self::PARAM_TYPE => 'string',
				self::PARAM_ISMULTI => true,
				self::PARAM_REQUIRED => true
			],
			'prop' => [
				self::PARAM_TYPE => [
					'url',
				],
				self::PARAM_ISMULTI => true,
				self::PARAM_DFLT => '',
			],
			'limit' => [
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages(): array {
		return [
			"action=query&list=wbsubscribers&wblsentities=Q42" =>
				"apihelp-query+wbsubscribers-example-1",
			"action=query&list=wbsubscribers&wblsentities=Q42&wblsprop=url" =>
				"apihelp-query+wbsubscribers-example-2",
		];
	}

	/**
	 * @param string $subscription
	 * @param string $entityIdString
	 *
	 * @return null|string
	 */
	private function getEntityUsageUrl( string $subscription, string $entityIdString ): ?string {
		$site = $this->siteLookup->getSite( $subscription );
		if ( !$site ) {
			return null;
		}

		return $site->getPageUrl( 'Special:EntityUsage/' . $entityIdString );
	}

	/**
	 * @see ApiQueryBase::getCacheMode
	 * @param array $params
	 * @return string
	 */
	public function getCacheMode( $params ): string {
		return 'public';
	}

}
