<?php

namespace Wikibase\Client\Tests\Integration;

use Deserializers\Deserializer;
use HashSiteStore;
use Language;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiIntegrationTestCase;
use ReflectionClass;
use ReflectionMethod;
use Serializers\Serializer;
use Site;
use SiteLookup;
use Wikibase\Client\Changes\ChangeHandler;
use Wikibase\Client\DataAccess\DataAccessSnakFormatterFactory;
use Wikibase\Client\DataAccess\ParserFunctions\Runner;
use Wikibase\Client\Hooks\LangLinkHandlerFactory;
use Wikibase\Client\Hooks\LanguageLinkBadgeDisplay;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\Client\Hooks\SidebarLinkBadgeDisplay;
use Wikibase\Client\OtherProjectsSitesProvider;
use Wikibase\Client\ParserOutput\ClientParserOutputDataUpdater;
use Wikibase\Client\RecentChanges\RecentChangeFactory;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\Store\ClientStore;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\RestrictedEntityLookup;
use Wikibase\Lib\Changes\EntityChangeFactory;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Lib\DataTypeFactory;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\Formatters\WikibaseSnakFormatterBuilders;
use Wikibase\Lib\Formatters\WikibaseValueFormatterBuilders;
use Wikibase\Lib\Interactors\TermSearchInteractor;
use Wikibase\Lib\LanguageFallbackChain;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\SettingsArray;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\Store\MatchingTermsLookupPropertyLabelResolver;
use Wikibase\Lib\Store\PropertyOrderProvider;
use Wikibase\Lib\Store\Sql\Terms\CachedDatabasePropertyLabelResolver;
use Wikibase\Lib\StringNormalizer;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Wikibase\Client\WikibaseClient
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class WikibaseClientTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// WikibaseClient service getters should never access the database or do http requests
		// https://phabricator.wikimedia.org/T243729
		$this->disallowDBAccess();
		$this->disallowHttpAccess();
	}

	private function disallowDBAccess() {
		$this->setService(
			'DBLoadBalancerFactory',
			function() {
				$lb = $this->createMock( ILoadBalancer::class );
				$lb->expects( $this->never() )
					->method( 'getConnection' );
				$lb->expects( $this->never() )
					->method( 'getConnectionRef' );
				$lb->expects( $this->never() )
					->method( 'getMaintenanceConnectionRef' );
				$lb->expects( $this->any() )
					->method( 'getLocalDomainID' )
					->willReturn( 'banana' );

				$lbFactory = $this->createMock( LBFactory::class );
				$lbFactory->expects( $this->any() )
					->method( 'getMainLB' )
					->willReturn( $lb );

				return $lbFactory;
			}
		);
	}

	private function disallowHttpAccess() {
		$this->setService(
			'HttpRequestFactory',
			function() {
				$factory = $this->createMock( HttpRequestFactory::class );
				$factory->expects( $this->never() )
					->method( 'create' );
				$factory->expects( $this->never() )
					->method( 'request' );
				$factory->expects( $this->never() )
					->method( 'get' );
				$factory->expects( $this->never() )
					->method( 'post' );
				return $factory;
			}
		);
	}

	public function testGetDefaultValueFormatterBuilders() {
		$first = WikibaseClient::getDefaultValueFormatterBuilders();
		$this->assertInstanceOf( WikibaseValueFormatterBuilders::class, $first );

		$second = WikibaseClient::getDefaultValueFormatterBuilders();
		$this->assertSame( $first, $second );
	}

	public function testGetDefaultSnakFormatterBuilders() {
		$first = WikibaseClient::getDefaultSnakFormatterBuilders();
		$this->assertInstanceOf( WikibaseSnakFormatterBuilders::class, $first );

		$second = WikibaseClient::getDefaultSnakFormatterBuilders();
		$this->assertSame( $first, $second );
	}

	public function testGetDataTypeFactoryReturnType() {
		$returnValue = $this->getWikibaseClient()->getDataTypeFactory();
		$this->assertInstanceOf( DataTypeFactory::class, $returnValue );
	}

	public function testGetEntityIdParserReturnType() {
		$returnValue = $this->getWikibaseClient()->getEntityIdParser();
		$this->assertInstanceOf( EntityIdParser::class, $returnValue );
	}

	public function testNewTermSearchInteractor() {
		$interactor = $this->getWikibaseClient()->newTermSearchInteractor( 'en' );
		$this->assertInstanceOf( TermSearchInteractor::class, $interactor );
	}

	public function testGetPropertyDataTypeLookupReturnType() {
		$returnValue = $this->getWikibaseClient()->getPropertyDataTypeLookup();
		$this->assertInstanceOf( PropertyDataTypeLookup::class, $returnValue );
	}

	public function testGetStringNormalizerReturnType() {
		$returnValue = $this->getWikibaseClient()->getStringNormalizer();
		$this->assertInstanceOf( StringNormalizer::class, $returnValue );
	}

	public function testNewRepoLinkerReturnType() {
		$returnValue = $this->getWikibaseClient()->newRepoLinker();
		$this->assertInstanceOf( RepoLinker::class, $returnValue );
	}

	public function testGetLanguageFallbackChainFactoryReturnType() {
		$returnValue = $this->getWikibaseClient()->getLanguageFallbackChainFactory();
		$this->assertInstanceOf( LanguageFallbackChainFactory::class, $returnValue );
	}

	public function testGetLanguageFallbackLabelDescriptionLookupFactory() {
		$instance = $this->getWikibaseClient()->getLanguageFallbackLabelDescriptionLookupFactory();
		$this->assertInstanceOf( LanguageFallbackLabelDescriptionLookupFactory::class, $instance );
	}

	public function testGetStoreReturnType() {
		$returnValue = $this->getWikibaseClient()->getStore();
		$this->assertInstanceOf( ClientStore::class, $returnValue );
	}

	public function testGetContentLanguageReturnType() {
		$returnValue = $this->getWikibaseClient()->getContentLanguage();
		$this->assertInstanceOf( Language::class, $returnValue );
	}

	public function testGetSettingsReturnType() {
		$returnValue = $this->getWikibaseClient()->getSettings();
		$this->assertInstanceOf( SettingsArray::class, $returnValue );
	}

	public function testGetSiteReturnType() {
		$returnValue = $this->getWikibaseClient()->getSite();
		$this->assertInstanceOf( Site::class, $returnValue );
	}

	public function testGetLangLinkHandlerFactoryReturnType() {
		$settings = clone WikibaseClient::getDefaultInstance()->getSettings();

		$settings->setSetting( 'siteGroup', 'wikipedia' );
		$settings->setSetting( 'siteGlobalID', 'enwiki' );
		$settings->setSetting( 'languageLinkSiteGroup', 'wikipedia' );

		$entityTypeDefinitions = new EntityTypeDefinitions( [] );
		$wikibaseClient = new WikibaseClient(
			$settings,
			new DataTypeDefinitions( [] ),
			$entityTypeDefinitions,
			$this->getSiteLookup(),
			$this->getEntitySourceDefinitions()
		);

		$factory = $wikibaseClient->getLangLinkHandlerFactory();
		$this->assertInstanceOf( LangLinkHandlerFactory::class, $factory );
	}

	public function testGetParserOutputDataUpdaterType() {
		$returnValue = $this->getWikibaseClient()->getParserOutputDataUpdater();
		$this->assertInstanceOf( ClientParserOutputDataUpdater::class, $returnValue );
	}

	/**
	 * @dataProvider getLangLinkSiteGroupProvider
	 */
	public function testGetLangLinkSiteGroup( $expected, SettingsArray $settings, SiteLookup $siteLookup ) {
		$entityTypeDefinitions = new EntityTypeDefinitions( [] );
		$client = new WikibaseClient(
			$settings,
			new DataTypeDefinitions( [] ),
			$entityTypeDefinitions,
			$siteLookup,
			new EntitySourceDefinitions( [], $entityTypeDefinitions )
		);

		$this->assertEquals( $expected, $client->getLangLinkSiteGroup() );
	}

	public function getLangLinkSiteGroupProvider() {
		$siteLookup = $this->getSiteLookup();

		$settings = clone WikibaseClient::getDefaultInstance()->getSettings();

		$settings->setSetting( 'siteGroup', 'wikipedia' );
		$settings->setSetting( 'siteGlobalID', 'enwiki' );
		$settings->setSetting( 'languageLinkSiteGroup', null );

		$settings2 = clone $settings;
		$settings2->setSetting( 'siteGroup', 'wikipedia' );
		$settings2->setSetting( 'siteGlobalID', 'enwiki' );
		$settings2->setSetting( 'languageLinkSiteGroup', 'wikivoyage' );

		return [
			[ 'wikipedia', $settings, $siteLookup ],
			[ 'wikivoyage', $settings2, $siteLookup ]
		];
	}

	/**
	 * @dataProvider getSiteGroupProvider
	 */
	public function testGetSiteGroup( $expected, SettingsArray $settings, SiteLookup $siteLookup ) {
		$client = new WikibaseClient(
			$settings,
			new DataTypeDefinitions( [] ),
			new EntityTypeDefinitions( [] ),
			$siteLookup,
			$this->getEntitySourceDefinitions()
		);

		$this->assertEquals( $expected, $client->getSiteGroup() );
	}

	/**
	 * @return SiteLookup
	 */
	private function getSiteLookup() {
		$siteStore = new HashSiteStore();

		$site = new Site();
		$site->setGlobalId( 'enwiki' );
		$site->setGroup( 'wikipedia' );

		$siteStore->saveSite( $site );

		$site = new Site();
		$site->setGlobalId( 'repo' );
		$site->setGroup( 'wikipedia' );
		$site->addInterwikiId( 'repointerwiki' );

		$siteStore->saveSite( $site );

		return $siteStore;
	}

	public function getSiteGroupProvider() {
		$settings = clone WikibaseClient::getDefaultInstance()->getSettings();
		$settings->setSetting( 'siteGroup', null );
		$settings->setSetting( 'siteGlobalID', 'enwiki' );

		$settings2 = clone $settings;
		$settings2->setSetting( 'siteGroup', 'wikivoyage' );
		$settings2->setSetting( 'siteGlobalID', 'enwiki' );

		$siteLookup = $this->getSiteLookup();

		return [
			[ 'wikipedia', $settings, $siteLookup ],
			[ 'wikivoyage', $settings2, $siteLookup ]
		];
	}

	public function testGetLanguageLinkBadgeDisplay() {
		$returnValue = $this->getWikibaseClient()->getLanguageLinkBadgeDisplay();
		$this->assertInstanceOf( LanguageLinkBadgeDisplay::class, $returnValue );
	}

	public function testGetOtherProjectsSidebarGeneratorFactoryReturnType() {
		$instance = $this->getWikibaseClient()->getOtherProjectsSidebarGeneratorFactory();
		$this->assertInstanceOf( OtherProjectsSidebarGeneratorFactory::class, $instance );
	}

	public function testGetOtherProjectsSitesProvider() {
		$returnValue = $this->getWikibaseClient()->getOtherProjectsSitesProvider();
		$this->assertInstanceOf( OtherProjectsSitesProvider::class, $returnValue );
	}

	public function testGetDefaultInstance() {
		$this->assertSame(
			WikibaseClient::getDefaultInstance(),
			WikibaseClient::getDefaultInstance() );
	}

	public function testGetExternalFormatDeserializerFactory() {
		$deserializerFactory = $this->getWikibaseClient()->getBaseDataModelDeserializerFactory();
		$this->assertInstanceOf( DeserializerFactory::class, $deserializerFactory );
	}

	public function testGetInternalFormatStatementDeserializer() {
		$deserializer = $this->getWikibaseClient()->getInternalFormatStatementDeserializer();
		$this->assertInstanceOf( Deserializer::class, $deserializer );
	}

	public function testGetCompactSerializerFactory() {
		$serializerFactory = $this->getWikibaseClient()->getCompactBaseDataModelSerializerFactory();
		$this->assertInstanceOf( SerializerFactory::class, $serializerFactory );
	}

	public function testGetCompactEntitySerializer() {
		$serializer = $this->getWikibaseClient()->getCompactEntitySerializer();
		$this->assertInstanceOf( Serializer::class, $serializer );
	}

	public function testGetChangeHandler() {
		$handler = $this->getWikibaseClient()->getChangeHandler();
		$this->assertInstanceOf( ChangeHandler::class, $handler );
	}

	public function testGetRecentChangeFactory() {
		$settings = new SettingsArray( WikibaseClient::getDefaultInstance()->getSettings()->getArrayCopy() );

		$settings->setSetting( 'localEntitySourceName', 'localrepo' );

		$entityTypeDefinitions = new EntityTypeDefinitions( [] );
		$wikibaseClient = new WikibaseClient(
			$settings,
			new DataTypeDefinitions( [] ),
			$entityTypeDefinitions,
			$this->getSiteLookup(),
			new EntitySourceDefinitions(
				[ new EntitySource(
					'localrepo',
					'repo',
					[ 'item' => [ 'namespaceId' => 123, 'slot' => 'main' ] ],
					'',
					'',
					'',
					'repo'
				) ],
				$entityTypeDefinitions
			)
		);

		$recentChangeFactory = $wikibaseClient->getRecentChangeFactory();
		$this->assertInstanceOf( RecentChangeFactory::class, $recentChangeFactory );

		$recentChangeFactory = TestingAccessWrapper::newFromObject( $recentChangeFactory );
		$this->assertStringStartsWith(
			'repointerwiki>',
			$recentChangeFactory->externalUsernames->addPrefix( 'TestUser' )
		);
	}

	public function testGetPropertyParserFunctionRunner() {
		$runner = $this->getWikibaseClient()->getPropertyParserFunctionRunner();
		$this->assertInstanceOf( Runner::class, $runner );
	}

	public function testGetTermsLanguages() {
		$langs = $this->getWikibaseClient()->getTermsLanguages();
		$this->assertInstanceOf( ContentLanguages::class, $langs );
	}

	public function testGetRestrictedEntityLookup() {
		$restrictedEntityLookup = $this->getWikibaseClient()->getRestrictedEntityLookup();
		$this->assertInstanceOf( RestrictedEntityLookup::class, $restrictedEntityLookup );
	}

	public function testGetEntityChangeFactory() {
		$entityChangeFactory = $this->getWikibaseClient()->getEntityChangeFactory();
		$this->assertInstanceOf( EntityChangeFactory::class, $entityChangeFactory );
	}

	public function propertyOrderUrlProvider() {
		return [
			[ 'page-url' ],
			[ null ]
		];
	}

	/**
	 * @dataProvider propertyOrderUrlProvider
	 */
	public function testGetPropertyOrderProvider_noSortedPropertiesUrl( $propertyOrderUrl ) {
		$wikibaseClient = $this->getWikibaseClient();
		$wikibaseClient->getSettings()->setSetting( 'propertyOrderUrl', $propertyOrderUrl );

		$propertyOrderProvider = $wikibaseClient->getPropertyOrderProvider();
		$this->assertInstanceOf( PropertyOrderProvider::class, $propertyOrderProvider );
	}

	public function testGetDataAccessLanguageFallbackChain() {
		$lang = Language::factory( 'de' );
		$fallbackChain = $this->getWikibaseClient()->getDataAccessLanguageFallbackChain( $lang );

		$this->assertInstanceOf( LanguageFallbackChain::class, $fallbackChain );
		// "de" falls back to "en"
		$this->assertCount( 2, $fallbackChain->getFetchLanguageCodes() );
	}

	public function testGetDataAccessSnakFormatterFactory() {
		$instance = $this->getWikibaseClient()->getDataAccessSnakFormatterFactory();
		$this->assertInstanceOf( DataAccessSnakFormatterFactory::class, $instance );
	}

	public function testGetSidebarLinkBadgeDisplay() {
		$sidebarLinkBadgeDisplay = $this->getWikibaseClient()->getSidebarLinkBadgeDisplay();
		$this->assertInstanceOf( SidebarLinkBadgeDisplay::class, $sidebarLinkBadgeDisplay );
	}

	public function testGetDatabaseDomainNameOfLocalRepo() {
		$settings = new SettingsArray( WikibaseClient::getDefaultInstance()->getSettings()->getArrayCopy() );

		$settings->setSetting( 'localEntitySourceName', 'localrepo' );

		$entityTypeDefinitions = new EntityTypeDefinitions( [] );
		$wikibaseClient = new WikibaseClient(
			$settings,
			new DataTypeDefinitions( [] ),
			$entityTypeDefinitions,
			$this->getSiteLookup(),
			new EntitySourceDefinitions(
				[
					new EntitySource(
						'localrepo',
						'repodb',
						[ 'item' => [ 'namespaceId' => 123, 'slot' => 'main' ] ],
						'',
						'',
						'',
						'repo'
					),
					new EntitySource(
						'otherrepo',
						'otherdb',
						[ 'property' => [ 'namespaceId' => 321, 'slot' => 'main' ] ],
						'',
						'',
						'',
						'other'
					),
				],
				$entityTypeDefinitions
			)
		);

		$this->assertEquals( 'repodb', $wikibaseClient->getDatabaseDomainNameOfLocalRepo() );
	}

	/**
	 * @dataProvider getPropertyLabelResolverClassPerMigrationStage
	 */
	public function testGetPropertyLabelResolver_newSchemaMigrationStage(
		$migrationStage,
		$propertyResolverClassName
	) {
		$settings = clone WikibaseClient::getDefaultInstance()->getSettings();
		$settings->setSetting( 'tmpPropertyTermsMigrationStage', $migrationStage );

		$wikibaseClient = $this->getWikibaseClient( $settings );
		$this->assertInstanceOf(
			$propertyResolverClassName,
			$wikibaseClient->getPropertyLabelResolver()
		);
	}

	public function getPropertyLabelResolverClassPerMigrationStage() {
		return [
			[ MIGRATION_OLD, MatchingTermsLookupPropertyLabelResolver::class ],
			[ MIGRATION_WRITE_BOTH, MatchingTermsLookupPropertyLabelResolver::class ],
			[ MIGRATION_WRITE_NEW, CachedDatabasePropertyLabelResolver::class ],
			[ MIGRATION_NEW, CachedDatabasePropertyLabelResolver::class ]
		];
	}

	/**
	 * @return WikibaseClient
	 */
	private function getWikibaseClient( SettingsArray $settings = null ) {
		if ( $settings === null ) {
			$settings = WikibaseClient::getDefaultInstance()->getSettings();
			$settings->setSetting( 'localEntitySourceName', 'test' );
		}
		return new WikibaseClient(
			new SettingsArray( $settings->getArrayCopy() ),
			new DataTypeDefinitions( [] ),
			new EntityTypeDefinitions( [] ),
			$this->getSiteLookup(),
			$this->getEntitySourceDefinitions()
		);
	}

	/**
	 * @return EntitySourceDefinitions
	 */
	private function getEntitySourceDefinitions() {
		$irrelevantItemNamespaceId = 100;
		$irrelevantItemSlotName = 'main';

		$irrelevantPropertyNamespaceId = 200;
		$irrelevantPropertySlotName = 'main';

		return new EntitySourceDefinitions(
			[ new EntitySource(
				'test',
				false,
				[
					'item' => [ 'namespaceId' => $irrelevantItemNamespaceId, 'slot' => $irrelevantItemSlotName ],
					'property' => [ 'namespaceId' => $irrelevantPropertyNamespaceId, 'slot' => $irrelevantPropertySlotName ],
				],
				'',
				'',
				'',
				''
			) ],
			new EntityTypeDefinitions( [] )
		);
	}

	public function testParameterLessFunctionCalls() {
		// Make sure (as good as we can) that all functions can be called without
		// exceptions/ fatals and nothing accesses the database or does http requests.
		$wbClient = $this->getWikibaseClient();

		$reflectionClass = new ReflectionClass( $wbClient );
		$publicMethods = $reflectionClass->getMethods( ReflectionMethod::IS_PUBLIC );

		foreach ( $publicMethods as $publicMethod ) {
			if ( $publicMethod->getNumberOfRequiredParameters() === 0 ) {
				$publicMethod->invoke( $wbClient );
			}
		}
	}

}
