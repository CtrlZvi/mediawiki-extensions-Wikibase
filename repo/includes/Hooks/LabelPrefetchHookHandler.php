<?php

namespace Wikibase\Repo\Hooks;

use ChangesList;
use MediaWiki\Hook\ChangesListInitRowsHook;
use RequestContext;
use Title;
use TitleFactory;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\TermIndexEntry;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Hook handlers for triggering prefetching of labels.
 *
 * Wikibase uses the HtmlPageLinkRendererEnd hook handler
 *
 * @see HtmlPageLinkRendererEndHookHandler
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class LabelPrefetchHookHandler implements ChangesListInitRowsHook {

	/**
	 * @var TermBuffer
	 */
	private $buffer;

	/**
	 * @var EntityIdLookup
	 */
	private $idLookup;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var string[]
	 */
	private $termTypes;

	/**
	 * @var string[]
	 */
	private $languageCodes;

	/**
	 * @return self
	 */
	public static function factory(): self {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$termBuffer = $wikibaseRepo->getTermBuffer();
		$termTypes = [ TermIndexEntry::TYPE_LABEL, TermIndexEntry::TYPE_DESCRIPTION ];

		// NOTE: keep in sync with fallback chain construction in LinkBeginHookHandler::factory
		$context = RequestContext::getMain();
		$languageFallbackChainFactory = $wikibaseRepo->getLanguageFallbackChainFactory();
		$languageFallbackChain = $languageFallbackChainFactory->newFromContext( $context );

		return new self(
			$termBuffer,
			$wikibaseRepo->getEntityIdLookup(),
			new TitleFactory(),
			$termTypes,
			$languageFallbackChain->getFetchLanguageCodes()
		);
	}

	/**
	 * @param TermBuffer $buffer
	 * @param EntityIdLookup $idLookup
	 * @param TitleFactory $titleFactory
	 * @param string[] $termTypes
	 * @param string[] $languageCodes
	 */
	public function __construct(
		TermBuffer $buffer,
		EntityIdLookup $idLookup,
		TitleFactory $titleFactory,
		array $termTypes,
		array $languageCodes
	) {
		$this->buffer = $buffer;
		$this->idLookup = $idLookup;
		$this->titleFactory = $titleFactory;
		$this->termTypes = $termTypes;
		$this->languageCodes = $languageCodes;
	}

	/**
	 * @param ChangesList $list
	 * @param IResultWrapper|object[] $rows
	 */
	public function onChangesListInitRows( $list, $rows ): void {
		try {
			$titles = $this->getChangedTitles( $rows );
			$entityIds = $this->idLookup->getEntityIds( $titles );
			$this->buffer->prefetchTerms( $entityIds, $this->termTypes, $this->languageCodes );
		} catch ( StorageException $ex ) {
			wfLogWarning( __METHOD__ . ': ' . $ex->getMessage() );
		}
	}

	/**
	 * @param IResultWrapper|object[] $rows
	 *
	 * @return Title[]
	 */
	private function getChangedTitles( $rows ) {
		$titles = [];

		foreach ( $rows as $row ) {
			$titles[] = $this->titleFactory->makeTitle( $row->rc_namespace, $row->rc_title );
		}

		return $titles;
	}

}
