<?php

declare( strict_types = 1 );
namespace Wikibase\Lib\Changes;

use MWException;
use Serializable;

/**
 * Class identifying a repo change so that relevant entries can be easily found in a client's
 * recentchanges table.
 * Contains the entity id changed, the revision's timestamp, and its id.
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
class RepoRevisionIdentifier implements Serializable {

	/**
	 * Increases whenever the array format (self::toArray) changes
	 */
	const ARRAYFORMATVERSION = 1;

	/**
	 * Serialization of the entity id changed.
	 *
	 * @var string
	 */
	private $entityIdSerialization;

	/**
	 * MediaWiki style timestamp of the revision.
	 *
	 * @var string
	 */
	private $revisionTimestamp;

	/**
	 * @var int
	 */
	private $revisionId;

	public function __construct(
		string $entityIdSerialization,
		string $revisionTimestamp,
		int $revisionId
	) {
		$this->entityIdSerialization = $entityIdSerialization;
		$this->revisionTimestamp = $revisionTimestamp;
		$this->revisionId = $revisionId;
	}

	/**
	 * Serialization of the entity id changed.
	 *
	 * @return string
	 */
	public function getEntityIdSerialization(): string {
		return $this->entityIdSerialization;
	}

	/**
	 * MediaWiki style timestamp of the revision.
	 *
	 * @return string
	 */
	public function getRevisionTimestamp(): string {
		return $this->revisionTimestamp;
	}

	/**
	 * @return int
	 */
	public function getRevisionId(): int {
		return $this->revisionId;
	}

	/**
	 * @see Serializable::serialize
	 *
	 * @return string JSON
	 */
	public function serialize(): string {
		return json_encode( $this->toArray() );
	}

	/**
	 * @see Serializable::unserialize
	 *
	 * @param string $serialized JSON
	 *
	 * @throws MWException
	 */
	public function unserialize( $serialized ) {
		$data = json_decode( $serialized );

		if ( $data->arrayFormatVersion !== self::ARRAYFORMATVERSION ) {
			throw new MWException( 'Unsupported format version ' . $data->arrayFormatVersion );
		}

		$this->entityIdSerialization = $data->entityIdSerialization;
		$this->revisionTimestamp = $data->revisionTimestamp;
		$this->revisionId = $data->revisionId;
	}

	/**
	 * @return array
	 */
	public function toArray(): array {
		return [
			'arrayFormatVersion' => self::ARRAYFORMATVERSION,
			'entityIdSerialization' => $this->getEntityIdSerialization(),
			'revisionTimestamp' => $this->revisionTimestamp,
			'revisionId' => $this->revisionId,
		];
	}

}
