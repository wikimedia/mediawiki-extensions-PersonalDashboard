<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration;

use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardModuleFactory;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;

/**
 * Resolve every service alias.
 *
 * An alias names its service in a string, so nothing connects the two until
 * something calls the alias. getLogger() asked for a service that
 * ServiceWiring.php never defined, and it threw for as long as it existed
 * because no caller ever reached it. One test per alias keeps the next one
 * honest.
 *
 * @group PersonalDashboard
 * @covers \MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices
 */
class PersonalDashboardServicesTest extends MediaWikiIntegrationTestCase {

	private function newServices(): PersonalDashboardServices {
		return PersonalDashboardServices::wrap( $this->getServiceContainer() );
	}

	public function testGetPersonalDashboardConfig() {
		$this->assertInstanceOf( Config::class, $this->newServices()->getPersonalDashboardConfig() );
	}

	public function testGetLogger() {
		$this->assertInstanceOf( LoggerInterface::class, $this->newServices()->getLogger() );
	}

	public function testGetPersonalDashboardModuleFactory() {
		$this->assertInstanceOf(
			PersonalDashboardModuleFactory::class,
			$this->newServices()->getPersonalDashboardModuleFactory()
		);
	}
}
