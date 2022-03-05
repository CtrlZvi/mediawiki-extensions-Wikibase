<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Notifications;

use Wikibase\Lib\Changes\Change;
use Wikibase\Lib\Changes\EntityChange;

/**
 * Notification channel based on a database table.
 *
 * @license GPL-2.0-or-later
 */
class ChangeHolder implements ChangeTransmitter {

	/**
	 * @var Change[]
	 */
	private $changes;

	public function __construct() {
		$this->changes = [];
	}

	/**
	 * @see ChangeNotificationChannel::sendChangeNotification()
	 * Holds the change to be stored later.
	 *
	 * @param Change $change
	 */
	public function transmitChange( Change $change ) {
		$this->changes[] = $change;
	}

	public function getChanges(): array {
		return $this->changes;
	}

	/**
	 * Retrieves changes by their revision ID.
	 *
	 * @param int $revisionId
	 */
	public function getChangesByRevisionId( int $revisionId ): array {
		$changes = [];
		foreach ( $this->changes as $change ) {
			if ( !$change instanceof EntityChange ) {
				continue;
			}

			if ( ( $change->getMetadata()['rev_id'] ?? 0 ) != $revisionId ) {
				continue;
			}

			$changes[] = $change;
		}

		return $changes;
	}
}
