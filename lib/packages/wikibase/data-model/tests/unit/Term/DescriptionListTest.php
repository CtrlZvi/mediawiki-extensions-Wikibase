<?php

namespace Wikibase\DataModel\Term\Test;

use Wikibase\DataModel\Term\Description;
use Wikibase\DataModel\Term\DescriptionList;

/**
 * @covers Wikibase\DataModel\Term\DescriptionList
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DescriptionListTest extends \PHPUnit_Framework_TestCase {

	public function testGivenNonDescriptions_constructorThrowsException() {
		$this->setExpectedException( 'InvalidArgumentException' );
		new DescriptionList( array( $this->getMock( 'Wikibase\DataModel\Term\Term' ) ) );
	}

	public function testGivenDescriptions_descriptionsAreSet() {
		$descriptions = array(
			'foo' => new Description( 'foo', 'bar' )
		);

		$list = new DescriptionList( $descriptions );

		$this->assertEquals( $descriptions, iterator_to_array( $list ) );
	}

}
