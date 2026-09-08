<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Feed;

use MediaWiki\Extension\PersonalDashboard\Feed\IFeedSource;
use MediaWiki\Extension\PersonalDashboard\Feed\PersonalDashboardFeedSourceFactory;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;

/**
 * @group PersonalDashboard
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\PersonalDashboardFeedSourceFactory
 */
class PersonalDashboardFeedSourceFactoryTest extends MediaWikiIntegrationTestCase {

	private function newFactory(): PersonalDashboardFeedSourceFactory {
		return new PersonalDashboardFeedSourceFactory(
			ExtensionRegistry::getInstance(),
			$this->getServiceContainer()->getObjectFactory(),
			$this->getServiceContainer()->get( 'PersonalDashboardLogger' )
		);
	}

	public function testTheServiceIsWired() {
		$this->assertInstanceOf(
			PersonalDashboardFeedSourceFactory::class,
			PersonalDashboardServices::wrap( $this->getServiceContainer() )
				->getPersonalDashboardFeedSourceFactory()
		);
	}

	public function testEveryRegisteredSourceCanBeBuilt() {
		// ExtensionJsonTestBase validates the ObjectFactory specs under
		// RestRoutes, HookHandlers and SpecialPages, but it does not look at
		// attributes at all. So this test is the only thing that proves the
		// specs shipped in extension.json actually resolve, including the
		// services each one declares.
		$factory = $this->newFactory();
		$names = $factory->getSourceNames();

		foreach ( $names as $name ) {
			$this->assertInstanceOf(
				IFeedSource::class,
				$factory->getSource( $name ),
				"Feed source '$name' failed to build from its extension.json spec"
			);
		}

		$this->addToAssertionCount( 1 );
	}

	public function testBuildsASourceDeclaredInTheAttribute() {
		$source = $this->createMock( IFeedSource::class );
		$source->expects( $this->once() )->method( 'setName' )->with( 'example' );

		// T413223 - $scope is unused, but needed for PHP 8.5's #[NoDiscard] on
		// setAttributeForTest()
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardFeedSources',
			[ 'example' => [ 'factory' => static fn () => $source ] ]
		);

		$factory = $this->newFactory();

		$this->assertSame( [ 'example' ], $factory->getSourceNames() );
		$this->assertSame( $source, $factory->getSource( 'example' ) );
	}
}
