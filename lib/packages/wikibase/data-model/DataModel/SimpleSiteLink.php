<?php

namespace Wikibase\DataModel;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\ItemId;

/**
 * Class representing a link to another site.
 *
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseDataModel
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Michał Łazowik
 */
class SimpleSiteLink {

	protected $siteId;
	protected $pageName;

	/**
	 * @var ItemId[]
	 */
	protected $badges;


	/**
	 * @param ItemId[] $badges
	 *
	 * @throws InvalidArgumentException
	 */
	protected function assertBadgesAreValid( $badges ) {
		if ( !is_array( $badges ) ) {
			throw new InvalidArgumentException( '$badges needs to be an array' );
		}

		foreach( $badges as $badge ) {
			if ( !( $badge instanceof ItemId ) ) {
				throw new InvalidArgumentException( 'Each element in $badges needs to be an ItemId' );
			}
		}

		if ( count( $badges ) !== count( array_unique( $badges ) ) ) {
			throw new InvalidArgumentException( '$badges array cannot contain duplicates' );
		}
	}

	/**
	 * @param string $siteId
	 * @param string $pageName
	 * @param ItemId[] $badges
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $siteId, $pageName, $badges = array() ) {
		if ( !is_string( $siteId ) ) {
			throw new InvalidArgumentException( '$siteId needs to be a string' );
		}

		if ( !is_string( $pageName ) ) {
			throw new InvalidArgumentException( '$pageName needs to be a string' );
		}

		$this->assertBadgesAreValid( $badges );

		$this->siteId = $siteId;
		$this->pageName = $pageName;
		$this->badges = array_values( $badges );
	}

	/**
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getSiteId() {
		return $this->siteId;
	}

	/**
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getPageName() {
		return $this->pageName;
	}

	/**
	 * Badges are not order dependent.
	 *
	 * @since 0.5
	 *
	 * @return ItemId[]
	 */
	public function getBadges() {
		return $this->badges;
	}

	/**
	 * Returns an array representing the claim.
	 * Roundtrips with Claim::newFromArray
	 *
	 * @since 0.5
	 *
	 * @return array
	 */
	 public function toArray() {
	 	$array = array(
	 		'name' => $this->pageName,
			'badges' => array()
		);

		foreach ( $this->badges as $badge ) {
			$array['badges'][] = $badge->getSerialization();
		}

		return $array;
	 }

	/**
	 * Constructs a new SimpleSiteLink from an array
	 * in the same format as SimpleSiteLink::toArray returns.
	 *
	 * @since 0.5
	 *
	 * @param string $siteId
	 * @param string|array $data
	 *
	 * @return SimpleSiteLink
	 */
	public static function newFromArray( $siteId, $data ) {
		if ( is_string( $data ) ) {
			// legacy serialization format
			$siteLink = new static( $siteId, $data );
		} else {
			if ( !is_array( $data ) ) {
				throw new InvalidArgumentException( '$data needs to be an array or string (legacy)' );
			}

			if ( !array_key_exists( 'name' , $data ) ) {
				throw new InvalidArgumentException( '$data needs to have a "name" key' );
			}

			$badges = self::getBadgesFromArray( $data );
			$pageName = $data['name'];

			$siteLink = new static( $siteId, $pageName, $badges );
		}

		return $siteLink;
	}

	/**
	 * @since 0.5
	 *
	 * @param array $data
	 *
	 * @return ItemId[]
	 *
	 * @throws InvalidArgumentException
	 */
	protected static function getBadgesFromArray( $data ) {
		if ( !array_key_exists( 'badges', $data ) ) {
			return array();
		}

		if ( !is_array( $data['badges'] ) ) {
			throw new InvalidArgumentException( '$data["badges"] needs to be an array' );
		}

		$badges = array();

		foreach ( $data['badges'] as $badge ) {
			$badges[] = new ItemId( $badge );
		}

		return $badges;
	}

}
