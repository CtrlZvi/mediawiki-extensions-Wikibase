<?php

namespace Wikibase\Lib\Tests;

use MediaWiki\Http\HttpRequestFactory;
use MediaWikiTestCase;
use Wikibase\Lib\WikibaseContentLanguages;
use Wikibase\Lib\WikibaseSettings;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

/**
 * Test to assert that factory methods of hook service classes (and similar services)
 * don't access the database or do http requests (which would be a performance issue).
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
class GlobalStateFactoryMethodsResourceTest extends MediaWikiTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Factory methods should never access the database or do http requests
		// https://phabricator.wikimedia.org/T243729
		$this->disallowDBAccess();
		$this->disallowHttpAccess();
	}

	public function testWikibaseContentLanguages(): void {
		WikibaseContentLanguages::getDefaultInstance();
		WikibaseContentLanguages::getDefaultMonolingualTextLanguages();
		WikibaseContentLanguages::getDefaultTermsLanguages();
		$this->assertTrue( true );
	}

	public function testWikibaseSettings_clientSettings(): void {
		if ( !WikibaseSettings::isClientEnabled() ) {
			$this->markTestSkipped(
				'Can only get client settings, if client is enabled'
			);
		}
		WikibaseSettings::getClientSettings();
		$this->assertTrue( true );
	}

	public function testWikibaseSettings_repoSettings(): void {
		if ( !WikibaseSettings::isRepoEnabled() ) {
			$this->markTestSkipped(
				'Can only get repo settings, if repo is enabled'
			);
		}
		WikibaseSettings::getRepoSettings();
		$this->assertTrue( true );
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

}
