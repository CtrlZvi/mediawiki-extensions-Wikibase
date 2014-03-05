<?php

namespace Wikibase\DataModel\Test;

use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\SiteLinkList;

/**
 * @covers Wikibase\DataModel\SiteLinkList
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SiteLinkListTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider notSiteLinksProvider
	 */
	public function testGivenNonSiteLinks_constructorThrowsException( array $notSiteLinks ) {
		$this->setExpectedException( 'InvalidArgumentException' );
		new SiteLinkList( $notSiteLinks );
	}

	public function notSiteLinksProvider() {
		return array(
			array(
				array(
					null
				)
			),

			array(
				array(
					42
				)
			),

			array(
				array(
					new SiteLink( 'foo', 'bar' ),
					42,
					new SiteLink( 'baz', 'bah' ),
				)
			),
		);
	}

	/**
	 * @dataProvider siteLinkArrayProvider
	 */
	public function testInputRoundtripsUsingIteratorToArray( array $siteLinkArray ) {
		$list = new SiteLinkList( $siteLinkArray );
		$this->assertEquals( $siteLinkArray, array_values( iterator_to_array( $list ) ) );
	}

	public function siteLinkArrayProvider() {
		return array(
			array(
				array(
				)
			),

			array(
				array(
					new SiteLink( 'foo', 'bar' )
				)
			),

			array(
				array(
					new SiteLink( 'foo', 'bar' ),
					new SiteLink( 'baz', 'bah' ),
					new SiteLink( 'hax', 'bar' ),
				)
			),
		);
	}

	public function testEmptyCollectionHasZeroSize() {
		$list = new SiteLinkList( array() );
		$this->assertCount( 0, $list );
	}

	/**
	 * @dataProvider siteLinkArrayWithDuplicateSiteIdProvider
	 */
	public function testGivenSiteIdTwice_constructorThrowsException( array $siteLinkArray ) {
		$this->setExpectedException( 'InvalidArgumentException' );
		new SiteLinkList( $siteLinkArray );
	}

	public function siteLinkArrayWithDuplicateSiteIdProvider() {
		return array(
			array(
				array(
					new SiteLink( 'foo', 'bar' ),
					new SiteLink( 'foo', 'bar' ),
				)
			),

			array(
				array(
					new SiteLink( 'foo', 'one' ),
					new SiteLink( 'baz', 'two' ),
					new SiteLink( 'foo', 'tree' ),
				)
			),
		);
	}

	public function testGetIteratorReturnsTraversableWithSiteIdKeys() {
		$list = new SiteLinkList( array(
			new SiteLink( 'first', 'one' ),
			new SiteLink( 'second', 'two' ),
			new SiteLink( 'third', 'tree' ),
		) );

		$this->assertEquals(
			array(
				'first' => new SiteLink( 'first', 'one' ),
				'second' => new SiteLink( 'second', 'two' ),
				'third' => new SiteLink( 'third', 'tree' ),
			),
			iterator_to_array( $list )
		);
	}

	public function testGivenNonString_getBySiteIdThrowsException() {
		$list = new SiteLinkList( array() );

		$this->setExpectedException( 'InvalidArgumentException' );
		$list->getBySiteId( 32202 );
	}

	public function testGivenUnknownSiteId_getBySiteIdThrowsException() {
		$link = new SiteLink( 'first', 'one' );

		$list = new SiteLinkList( array( $link ) );

		$this->setExpectedException( 'OutOfBoundsException' );
		$list->getBySiteId( 'foo' );
	}

	public function testGivenKnownSiteId_getBySiteIdReturnsSiteLink() {
		$link = new SiteLink( 'first', 'one' );

		$list = new SiteLinkList( array( $link ) );

		$this->assertEquals( $link, $list->getBySiteId( 'first' ) );
	}

}
