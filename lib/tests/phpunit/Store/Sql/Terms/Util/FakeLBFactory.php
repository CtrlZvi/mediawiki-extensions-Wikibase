<?php

namespace Wikibase\Lib\Tests\Store\Sql\Terms\Util;

use InvalidArgumentException;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

/**
 * @license GPL-2.0-or-later
 */
class FakeLBFactory extends LBFactory {

	/** @var ILoadBalancer */
	private $lb;

	private const LOCAL_DOMAIN_ID = 'local-domain-id';

	/**
	 * @param array $params should contain 'lb' ILoadBalancer instance
	 */
	public function __construct( array $params ) {
		// no parent constructor call, we only use the LBFactory class so we don’t have to
		// override every ILBFactory method – they’ll just crash if someone tries to use them
		$this->lb = $params['lb'];
	}

	public function newMainLB( $domain = false, $owner = null ): ILoadBalancer {
		if ( $domain === false || $domain === self::LOCAL_DOMAIN_ID ) {
			return $this->lb;
		} else {
			throw new InvalidArgumentException( 'only local domain supported' );
		}
	}

	public function getMainLB( $domain = false ): ILoadBalancer {
		return $this->newMainLB( $domain );
	}

	public function waitForReplication( array $ops = [] ) {
		// no-op
	}

	public function newExternalLB( $cluster, $owner = null ): ILoadBalancer {
		throw new InvalidArgumentException( 'no external cluster supported' );
	}

	public function getExternalLB( $cluster ): ILoadBalancer {
		return $this->newExternalLB( $cluster );
	}

	public function forEachLB( $callback, array $params = [] ) {
		( $callback )( $this->lb, ...$params );
	}

	public function getAllMainLBs(): array {
		return [ $this->lb ];
	}

	public function getAllExternalLBs(): array {
		return [];
	}

	public function getLocalDomainID() {
		return self::LOCAL_DOMAIN_ID;
	}

	public function __destruct() {
		// no-op
	}

}
