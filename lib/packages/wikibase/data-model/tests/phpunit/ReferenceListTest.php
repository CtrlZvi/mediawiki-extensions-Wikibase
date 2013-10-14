<?php

namespace Wikibase\Test;

use Hashable;
use Wikibase\PropertyNoValueSnak;
use Wikibase\Reference;
use Wikibase\ReferenceList;
use Wikibase\SnakList;

/**
 * @covers Wikibase\ReferenceList
 *
 * @file
 * @since 0.1
 *
 * @ingroup WikibaseLib
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseDataModel
 * @group WikibaseReference
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ReferenceListTest extends \PHPUnit_Framework_TestCase {

	public function getInstanceClass() {
		return '\Wikibase\ReferenceList';
	}

	public function instanceProvider() {
		$class = $this->getInstanceClass();

		$instances = array();

		foreach ( $this->getConstructorArg() as $arg ) {
			$instances[] = array( new $class( $arg ) );
		}

		return $instances;
	}

	public function getElementInstances() {
		return array(
			new Reference(),
			new Reference( new SnakList( array( new PropertyNoValueSnak( 2 ) ) ) ),
			new Reference( new SnakList( array( new PropertyNoValueSnak( 3 ) ) ) ),
		);
	}

	public function getConstructorArg() {
		return array(
			null,
			array(),
			$this->getElementInstances(),
		);
	}

	public function referenceListProvider() {
		$class = $this->getInstanceClass();

		return array(
			array( new $class( $this->getElementInstances() ) )
		);
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param \Wikibase\ReferenceList $array
	 */
	public function testHasReference( ReferenceList $array ) {
		/**
		 * @var Reference $hashable
		 */
		foreach ( iterator_to_array( $array ) as $hashable ) {
			$this->assertTrue( $array->hasReference( $hashable ) );
			$array->removeReference( $hashable );
			$this->assertFalse( $array->hasReference( $hashable ) );
		}

		$this->assertTrue( true );
	}

	public function testHasReferenceMore() {
		$list = new ReferenceList();

		$reference = new Reference( new \Wikibase\SnakList( array( new \Wikibase\PropertyNoValueSnak( 42 ) ) ) );
		$sameReference = unserialize( serialize( $reference ) );

		$list->addReference( $reference );

		$this->assertTrue(
			$list->hasReference( $sameReference ),
			'hasReference should return true when a reference with the same value is present, even when its another instance'
		);
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param \Wikibase\ReferenceList $array
	 */
	public function testRemoveReference( ReferenceList $array ) {
		$elementCount = count( $array );

		/**
		 * @var Reference $element
		 */
		foreach ( iterator_to_array( $array ) as $element ) {
			$this->assertTrue( $array->hasReference( $element ) );

			$array->removeReference( $element );

			$this->assertFalse( $array->hasReference( $element ) );
			$this->assertEquals( --$elementCount, count( $array ) );
		}

		$elements = $this->getElementInstances();
		$element = array_shift( $elements );

		$array->removeReference( $element );
		$array->removeReference( $element );

		$this->assertTrue( true );
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param \Wikibase\ReferenceList $array
	 */
	public function testAddReference( ReferenceList $array ) {
		// Append object to the end:
		$elementCount = count( $array );

		$elements = $this->getElementInstances();
		$element = array_shift( $elements );
		$array->addReference( $element );

		$this->assertEquals( ++$elementCount, count( $array ) );

		// Insert object at the beginning:
		$elements = $this->getElementInstances();
		$element = array_shift( $elements );
		$array->addReference( $element, 0 );

		$array->rewind();

		$this->assertEquals( ++$elementCount, count( $array ) );
		$this->assertEquals( $array->current(), $element, 'Inserted object at the beginning' );

		// Insert object at another index:
		$elements = $this->getElementInstances();
		$element = array_shift( $elements );
		$array->addReference( $element, 1 );

		$array->rewind();
		$array->next();

		$this->assertEquals( ++$elementCount, count( $array ) );
		$this->assertEquals( $array->current(), $element, 'Inserted object at index 1' );
	}

	/**
	 * @dataProvider referenceListProvider
	 *
	 * @param ReferenceList $array
	 */
	public function testIndexOf( ReferenceList $array ) {
		$this->assertFalse( $array->indexOf( new Reference() ) );

		$i = 0;
		foreach( $array as $reference ) {
			$this->assertEquals( $i++, $array->indexOf( $reference ) );
		}
	}

	/**
	 * @dataProvider instanceProvider
	 * @param ReferenceList $references
	 */
	public function testToArrayRoundtrip( ReferenceList $references ) {
		$serialization = serialize( $references->toArray() );
		$array = $references->toArray();

		$this->assertInternalType( 'array', $array, 'toArray should return array' );

		foreach ( array( $array, unserialize( $serialization ) ) as $data ) {
			$copy = \Wikibase\ReferenceList::newFromArray( $data );

			$this->assertInstanceOf( '\Wikibase\References', $copy, 'newFromArray should return object implementing Snak' );

			$this->assertTrue( $references->equals( $copy ), 'getArray newFromArray roundtrip should work' );
		}
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param \Wikibase\ReferenceList $array
	 */
	public function testEquals( ReferenceList $array ) {
		$this->assertTrue( $array->equals( $array ) );
		$this->assertFalse( $array->equals( 42 ) );
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param \Wikibase\ReferenceList $array
	 */
	public function testGetHash( ReferenceList $array ) {
		$this->assertInternalType( 'string', $array->getValueHash() );

		$copy = ReferenceList::newFromArray( $array->toArray() );
		$this->assertEquals( $array->getValueHash(), $copy->getValueHash() );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param ReferenceList $references
	 */
	public function testHasReferenceHash( ReferenceList $references ) {
		$this->assertFalse( $references->hasReferenceHash( '~=[,,_,,]:3' ) );

		/**
		 * @var Hashable $reference
		 */
		foreach ( $references as $reference ) {
			$this->assertTrue( $references->hasReferenceHash( $reference->getHash() ) );
		}
	}

	/**
	 * @dataProvider instanceProvider
	 * @param ReferenceList $references
	 */
	public function testGetReference( ReferenceList $references ) {
		$this->assertNull( $references->getReference( '~=[,,_,,]:3' ) );

		/**
		 * @var Reference $reference
		 */
		foreach ( $references as $reference ) {
			$this->assertTrue( $reference->equals( $references->getReference( $reference->getHash() ) ) );
		}
	}

	/**
	 * @dataProvider instanceProvider
	 * @param ReferenceList $references
	 */
	public function testRemoveReferenceHash( ReferenceList $references ) {
		$references->removeReferenceHash( '~=[,,_,,]:3' );

		$hashes = array();

		/**
		 * @var Reference $reference
		 */
		foreach ( $references as $reference ) {
			$hashes[] = $reference->getHash();
		}

		foreach( $hashes as $hash ) {
			$references->removeReferenceHash( $hash );
		}

		$this->assertEquals( 0, count( $references ) );
	}

}
