<?php

namespace Wikibase\Repo\Tests\Hooks;

use ChangesList;
use Title;
use TitleFactory;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Repo\Hooks\LabelPrefetchHookHandler;

/**
 * @covers \Wikibase\Repo\Hooks\LabelPrefetchHookHandler
 *
 * @group Wikibase
 * @group Database
 *        ^--- who knows what ChangesList may do internally...
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class LabelPrefetchHookHandlerTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @param Title[] $titles
	 *
	 * @return EntityId[]
	 */
	public function titlesToIds( array $titles ) {
		$entityIds = [];
		$idParser = new BasicEntityIdParser();

		foreach ( $titles as $title ) {
			try {
				// Pretend the article ID is the numeric entity ID.
				$entityId = $idParser->parse( $title->getText() );
				$key = $entityId->getNumericId();

				$entityIds[$key] = $entityId;
			} catch ( EntityIdParsingException $ex ) {
				// skip
			}
		}

		return $entityIds;
	}

	/**
	 * @param callback $prefetchTerms
	 * @param string[] $termTypes
	 * @param string[] $languageCodes
	 *
	 * @return LabelPrefetchHookHandler
	 */
	private function getLabelPrefetchHookHandlers( $prefetchTerms, array $termTypes, array $languageCodes ) {
		$termBuffer = $this->createMock( TermBuffer::class );
		$termBuffer->expects( $this->atLeastOnce() )
			->method( 'prefetchTerms' )
			->will( $this->returnCallback( $prefetchTerms ) );

		$idLookup = $this->createMock( EntityIdLookup::class );
		$idLookup->expects( $this->atLeastOnce() )
			->method( 'getEntityIds' )
			->will( $this->returnCallback( [ $this, 'titlesToIds' ] ) );

		$titleFactory = new TitleFactory();

		return new LabelPrefetchHookHandler(
			$termBuffer,
			$idLookup,
			$titleFactory,
			$termTypes,
			$languageCodes
		);
	}

	public function testDoChangesListInitRows() {
		$rows = [
			(object)[ 'rc_namespace' => NS_MAIN, 'rc_title' => 'XYZ' ],
			(object)[ 'rc_namespace' => NS_MAIN, 'rc_title' => 'Q23' ],
			(object)[ 'rc_namespace' => NS_MAIN, 'rc_title' => 'P55' ],
		];

		$expectedTermTypes = [ 'label', 'description' ];
		$expectedLanguageCodes = [ 'de', 'en', 'it' ];

		$expectedIds = [
			new ItemId( 'Q23' ),
			new PropertyId( 'P55' ),
		];

		$prefetchTerms = function (
			array $entityIds,
			array $termTypes = null,
			array $languageCodes = null
		) use (
			$expectedIds,
			$expectedTermTypes,
			$expectedLanguageCodes
		) {
			$expectedIdStrings = array_map( function( EntityId $id ) {
				return $id->getSerialization();
			}, $expectedIds );
			$entityIdStrings = array_map( function( EntityId $id ) {
				return $id->getSerialization();
			}, $entityIds );

			sort( $expectedIdStrings );
			sort( $entityIdStrings );

			$this->assertEquals( $expectedIdStrings, $entityIdStrings );
			$this->assertEquals( $expectedTermTypes, $termTypes );
			$this->assertEquals( $expectedLanguageCodes, $languageCodes );
		};

		$linkBeginHookHandler = $this->getLabelPrefetchHookHandlers(
			$prefetchTerms,
			$expectedTermTypes,
			$expectedLanguageCodes
		);

		/** @var ChangesList $changesList */
		$changesList = $this->getMockBuilder( ChangesList::class )
			->disableOriginalConstructor()
			->getMock();

		$linkBeginHookHandler->onChangesListInitRows(
			$changesList,
			$rows
		);
	}

}
