<?php

namespace Wikibase\Lib\Tests\Interactors;

use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Interactors\TermIndexSearchInteractor;
use Wikibase\Lib\Interactors\TermIndexSearchInteractorFactory;
use Wikibase\DataAccess\PrefetchingTermLookup;
use Wikibase\TermIndex;

/**
 * @covers \Wikibase\Lib\Interactors\TermIndexSearchInteractorFactory
 *
 * @group Wikibase
 * @group WikibaseLib
 *
 * @license GPL-2.0-or-later
 */
class TermIndexSearchInteractorFactoryTest extends \PHPUnit\Framework\TestCase {

	public function testNewInteractorReturnsTermIndexSearchInteractorInstance() {
		$factory = new TermIndexSearchInteractorFactory(
			$this->createMock( TermIndex::class ),
			new LanguageFallbackChainFactory(),
			$this->createMock( PrefetchingTermLookup::class )
		);

		$this->assertInstanceOf( TermIndexSearchInteractor::class, $factory->newInteractor( 'en' ) );
	}

	public function testNewInteractorReturnsFreshInstanceOnMultipleCalls() {
		$factory = new TermIndexSearchInteractorFactory(
			$this->createMock( TermIndex::class ),
			new LanguageFallbackChainFactory(),
			$this->createMock( PrefetchingTermLookup::class )
		);

		$interactorOne = $factory->newInteractor( 'en' );
		$interactorTwo = $factory->newInteractor( 'en' );

		$this->assertNotSame( $interactorTwo, $interactorOne );
	}

}
