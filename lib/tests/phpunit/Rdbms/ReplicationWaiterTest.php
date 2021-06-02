<?php

declare( strict_types=1 );

namespace Wikibase\Lib\Tests;

use Wikibase\Lib\Rdbms\ReplicationWaiter;
use Wikimedia\Rdbms\LBFactory;

/**
 * @covers \Wikibase\Lib\Rdbms\ReplicationWaiter
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ReplicationWaiterTest extends \PHPUnit\Framework\TestCase {

	public function testWait() {
		$domain = 'imadomain';
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->expects( $this->once() )
			->method( 'waitForReplication' )
			->with( [ 'domain' => $domain ] );

		$sut = new ReplicationWaiter( $lbFactory, $domain );

		$sut->wait();
	}

	public function testWaitAll() {
		$domain = 'imadomain';
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->expects( $this->once() )
			->method( 'waitForReplication' )
			->with() // TODO this matches anything, it seems not possible to assert that no arguments were passed
			->willReturnCallback( function( $options ) {
				$this->assertSame( [], $options );
			} );

		$sut = new ReplicationWaiter( $lbFactory, $domain );

		$sut->waitForAllAffectedClusters();
	}
}
