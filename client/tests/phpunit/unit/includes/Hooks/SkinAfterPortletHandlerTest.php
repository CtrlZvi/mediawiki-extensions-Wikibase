<?php

namespace Wikibase\Client\Tests\Unit\Hooks;

use ConfigFactory;
use ContentHandler;
use FauxRequest;
use IContextSource;
use Language;
use OutputPage;
use PHPUnit\Framework\TestCase;
use Skin;
use Title;
use Wikibase\Client\Hooks\SkinAfterPortletHandler;
use Wikibase\Client\NamespaceChecker;
use Wikibase\Client\RepoItemLinkGenerator;
use Wikibase\Client\RepoLinker;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\Lib\EntityTypeDefinitions;
use WikiPage;

/**
 * @covers \Wikibase\Client\Hooks\SkinAfterPortletHandler
 *
 * @group WikibaseClient
 * @group WikibaseHooks
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Marius Hoch < hoo@online.de >
 */
class SkinAfterPortletHandlerTest extends TestCase {

	public function testDoSkinAfterPortlet_editLink() {
		$handler = $this->getHookHandler();

		$languageUrls = [ 'en' ];
		$noExternalLangLinks = null;

		$result = $handler->doSkinAfterPortlet(
			$this->getSkin( $noExternalLangLinks, $languageUrls )
		);

		$this->assertStringContainsString( wfMessage( 'wikibase-editlinks' )->text(), $result );
	}

	public function testDoSkinAfterPortlet_addLink() {
		$handler = $this->getHookHandler();

		$languageUrls = [];
		$noExternalLangLinks = null;

		$result = $handler->doSkinAfterPortlet(
			$this->getSkin( $noExternalLangLinks, $languageUrls )
		);

		$this->assertStringContainsString( wfMessage( 'wikibase-linkitem-addlinks' )->text(), $result );
	}

	public function testDoSkinAfterPortlet_nonViewAction() {
		$handler = $this->getHookHandler();

		$languageUrls = [ 'en' ];
		$noExternalLangLinks = null;
		$action = 'edit';

		$result = $handler->doSkinAfterPortlet(
			$this->getSkin( $noExternalLangLinks, $languageUrls, $action )
		);

		$this->assertNull( $result );
	}

	public function testDoSkinAfterPortlet_actionLinkSuppressed() {
		$handler = $this->getHookHandler();

		$languageUrls = [ 'en' ];
		$noExternalLangLinks = [ '*' ];

		$result = $handler->doSkinAfterPortlet(
			$this->getSkin( $noExternalLangLinks, $languageUrls )
		);

		$this->assertNull( $result );
	}

	private function getRepoLinker() {
		$baseUrl = 'http://www.example.com';
		$articlePath = '/wiki/$1';
		$scriptPath = '';

		return new RepoLinker(
			new EntitySourceDefinitions( [], new EntityTypeDefinitions( [] ) ),
			$baseUrl,
			$articlePath,
			$scriptPath
		);
	}

	private function getHookHandler() {
		$linkGenerator = new RepoItemLinkGenerator(
			new NamespaceChecker( [] ),
			$this->getRepoLinker(),
			new ItemIdParser(),
			'wikipedia',
			'enwiki'
		);

		return new SkinAfterPortletHandler( $linkGenerator );
	}

	/**
	 * @param string[]|null $noExternalLangLinks
	 * @param string[] $languageUrls
	 * @param string $action
	 * @return Skin
	 */
	private function getSkin( $noExternalLangLinks, $languageUrls, $action = 'view' ) {
		$skin = $this->createMock( Skin::class );

		$output = new OutputPage( $this->getContext( $action ) );
		$output->setProperty( 'wikibase_item', 'Q2013' );
		$output->setProperty( 'noexternallanglinks', $noExternalLangLinks );
		$title = $output->getTitle();

		$skin->method( 'getOutput' )
			->willReturn( $output );
		$skin->method( 'getContext' )
			->willReturn( $output );
		$skin->method( 'getTitle' )
			->willReturn( $title );
		$skin->method( 'getLanguages' )
			->willReturn( $languageUrls );

		return $skin;
	}

	/**
	 * @param string $action
	 * @return IContextSource
	 */
	private function getContext( $action ) {
		$request = new FauxRequest( [ 'action' => $action ] );

		$title = $this->createMock( Title::class );
		$title->method( 'exists' )
			->willReturn( true );
		$title->method( 'canExist' )
			->willReturn( true );
		$title->method( 'getNamespace' )
			->willReturn( 0 );

		$contentHandler = ContentHandler::getForModelID( CONTENT_MODEL_WIKITEXT );

		$wikiPage = $this->getMockBuilder( WikiPage::class )
			->disableOriginalConstructor()
			->getMock();
		$wikiPage->method( 'getActionOverrides' )
			->willReturn( [] );
		$wikiPage->method( 'getContentHandler' )
			->willReturn( $contentHandler );
		$wikiPage->method( 'getTitle' )
			->willReturn( $title );

		$context = $this->createMock( IContextSource::class );
		$context->method( 'canUseWikiPage' )
			->willReturn( true );
		$context->method( 'getWikiPage' )
			->willReturn( $wikiPage );
		$context->method( 'getRequest' )
			->willReturn( $request );
		$context->method( 'getTitle' )
			->willReturn( $title );
		$context->method( 'getLanguage' )
			->willReturn( Language::factory( 'qqx' ) );
		$context->method( 'getConfig' )
			->willReturn(
				ConfigFactory::getDefaultInstance()->makeConfig( 'main' )
			);
		return $context;
	}
}
