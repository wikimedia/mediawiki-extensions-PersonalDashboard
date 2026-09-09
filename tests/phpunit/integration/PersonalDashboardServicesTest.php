<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration;

use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\Feed\IPageDescriptionLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\IRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\PersonalDashboardFeedSourceFactory;
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

	public function testGetPersonalDashboardFeedSourceFactory() {
		$this->assertInstanceOf(
			PersonalDashboardFeedSourceFactory::class,
			$this->newServices()->getPersonalDashboardFeedSourceFactory()
		);
	}

	public function testTheRevisionScoreLookupResolves() {
		// It has no alias on the wrapper, but it is the one service whose wiring
		// branches on another extension being installed, so it is worth proving
		// it builds at all. Without ORES that means the null implementation.
		$this->assertInstanceOf(
			IRevisionScoreLookup::class,
			$this->getServiceContainer()->get( 'PersonalDashboardRevisionScoreLookup' )
		);
	}

	public function testThePageDescriptionLookupResolves() {
		// Like the score lookup, its wiring branches on other extensions being
		// installed — two of them here — so it is worth proving it builds at
		// all. Without Wikibase or ShortDescription that means the null one.
		$this->assertInstanceOf(
			IPageDescriptionLookup::class,
			$this->getServiceContainer()->get( 'PersonalDashboardPageDescriptionLookup' )
		);
	}

	public function testGetPersonalDashboardModuleFactory() {
		$this->assertInstanceOf(
			PersonalDashboardModuleFactory::class,
			$this->newServices()->getPersonalDashboardModuleFactory()
		);
	}
}
