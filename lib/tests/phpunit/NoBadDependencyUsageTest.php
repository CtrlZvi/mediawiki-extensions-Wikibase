<?php

namespace Wikibase\Lib\Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class NoBadDependencyUsageTest extends \PHPUnit\Framework\TestCase {

	public function testNoBadUsageInLib() {
		// Increasing these allowances is forbidden
		$this->assertStringsNotInLib(
			[
				'WikibaseRepo' . '::' => 0,
				'Wikibase\\Repo\\' => 3,
				'Wikibase\\\\Repo\\\\' => 1,
				'WikibaseClient' . '::' => 0,
				'Wikibase\\Client\\' => 0,
				'Wikibase\\\\Client\\\\' => 1,
			]
		);
	}

	/**
	 * @param int[] $stringCounts Keys of strings and values of number of allowed occurrences
	 */
	private function assertStringsNotInLib( $stringCounts ) {
		$counts = $this->countMultiStringInDir( array_keys( $stringCounts ), __DIR__ . '/../../' );
		foreach ( $stringCounts as $string => $maxAllowance ) {
			$this->assertLessThanOrEqual(
				$maxAllowance,
				$counts[$string],
				'You are not allowed to use ' . $string . ' in this component'
			);
			$this->assertThat(
				$maxAllowance,
				$this->logicalNot( $this->greaterThan( $counts[$string] ) ),
				'It looks like you successfully reduced the usage of ' .
				$string . ' in this component. Congratulations :) ' .
				'Please lower the threshold in NoBadDependencyUsageTest accordingly, ' .
				'so that no new usages are accidentally introduced in the future.'
			);
		}
	}

	/**
	 * @param string[] $strings
	 * @param string $dir
	 *
	 * @return int[] counts indexed by string
	 */
	private function countMultiStringInDir( $strings, $dir ) {
		$counts = [];
		foreach ( $strings as $string ) {
			$counts[$string] = 0;
		}

		$directoryIterator = new RecursiveDirectoryIterator( $dir );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		foreach ( new RecursiveIteratorIterator( $directoryIterator, RecursiveIteratorIterator::SELF_FIRST ) as $fileInfo ) {
			if ( $fileInfo->isFile() && substr( $fileInfo->getFilename(), -4 ) === '.php' ) {
				$text = file_get_contents( $fileInfo->getPathname() );
				$text = preg_replace( '@/\*.*?\*/@s', '', $text );

				foreach ( $strings as $string ) {
					if ( strpos( $text, $string ) !== false ) {
						$counts[$string]++;
					}
				}
			}
		}

		return $counts;
	}

}
