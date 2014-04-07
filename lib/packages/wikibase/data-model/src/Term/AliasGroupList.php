<?php

namespace Wikibase\DataModel\Term;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use OutOfBoundsException;
use Traversable;

/**
 * List of alias groups. Immutable.
 *
 * Only one group per language code. If multiple groups with the same language code
 * are provided, only the last one will be retained.
 *
 * @since 0.7.3
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class AliasGroupList implements Countable, IteratorAggregate {

	private $groups;

	/**
	 * @param AliasGroup[] $aliasGroups
	 * @throws InvalidArgumentException
	 */
	public function __construct( array $aliasGroups ) {
		foreach ( $aliasGroups as $aliasGroup ) {
			if ( !( $aliasGroup instanceof AliasGroup ) ) {
				throw new InvalidArgumentException( 'AliasGroupList can only contain AliasGroup instances' );
			}

			$this->groups[$aliasGroup->getLanguageCode()] = $aliasGroup;
		}
	}

	/**
	 * @see Countable::count
	 * @return int
	 */
	public function count() {
		return count( $this->groups );
	}

	/**
	 * @see IteratorAggregate::getIterator
	 * @return Traversable
	 */
	public function getIterator() {
		return new \ArrayIterator( $this->groups );
	}

	/**
	 * @param $languageCode
	 *
	 * @return AliasGroup
	 * @throws InvalidArgumentException
	 * @throws OutOfBoundsException
	 */
	public function getByLanguage( $languageCode ) {
		if ( !is_string( $languageCode ) ) {
			throw new InvalidArgumentException( '$languageCode should be a string' );
		}

		if ( !array_key_exists( $languageCode, $this->groups ) ) {
			throw new OutOfBoundsException(
				'There is no AliasGroup with language code "' . $languageCode . '" in the list'
			);
		}

		return $this->groups[$languageCode];
	}

}