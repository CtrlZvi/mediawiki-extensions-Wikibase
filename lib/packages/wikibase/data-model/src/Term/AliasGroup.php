<?php

namespace Wikibase\DataModel\Term;

use Comparable;

/**
 * Immutable value object.
 *
 * @since 0.7.3
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class AliasGroup implements Comparable {

	private $languageCode;
	private $aliases;

	/**
	 * @param string $languageCode
	 * @param string[] $aliases
	 */
	public function __construct( $languageCode, array $aliases ) {
		$this->languageCode = $languageCode;
		$this->aliases = $aliases;
	}

	/**
	 * @return string
	 */
	public function getLanguageCode() {
		return $this->languageCode;
	}

	/**
	 * @return string[]
	 */
	public function getAliases() {
		return $this->aliases;
	}

	/**
	 * @return boolean
	 */
	public function isEmpty() {
		return empty( $this->aliases );
	}

	/**
	 * @see Comparable::equals
	 *
	 * @param mixed $target
	 *
	 * @return boolean
	 */
	public function equals( $target ) {
		return $target instanceof AliasGroup
			&& $this->languageCode === $target->getLanguageCode()
			&& $this->arraysAreEqual( $this->aliases, $target->getAliases() );
	}

	private function arraysAreEqual( array $a, array $b ) {
		return array_diff( $a, $b ) === array();
	}

}