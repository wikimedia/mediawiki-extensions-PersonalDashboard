<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Feed;

use MediaWiki\Extension\PersonalDashboard\Feed\IFeedSource;
use MediaWiki\Extension\PersonalDashboard\Feed\PersonalDashboardFeedSourceFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\PersonalDashboardFeedSourceFactory
 */
class PersonalDashboardFeedSourceFactoryTest extends MediaWikiUnitTestCase {

	private const array REGISTRY = [
		'watchlist' => [ 'class' => 'ExampleWatchlistSource' ],
		'recentchanges' => [ 'class' => 'ExampleRecentChangesSource' ],
	];

	private function newFactory(
		ObjectFactory $objectFactory,
		array $registry = self::REGISTRY,
		?LoggerInterface $logger = null
	) {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'getAttribute' )
			->with( 'PersonalDashboardFeedSources' )
			->willReturn( $registry );

		return new PersonalDashboardFeedSourceFactory(
			$extensionRegistry,
			$objectFactory,
			$logger ?? new NullLogger()
		);
	}

	public function testBuildsTheRegisteredSourceAndNamesIt() {
		$source = $this->createMock( IFeedSource::class );
		// The factory names the source, so a source never declares its own name
		// and the same class can serve under two names.
		$source->expects( $this->once() )->method( 'setName' )->with( 'watchlist' );

		$objectFactory = $this->createMock( ObjectFactory::class );
		$objectFactory->expects( $this->once() )
			->method( 'createObject' )
			->with(
				self::REGISTRY['watchlist'],
				[ 'assertClass' => IFeedSource::class ]
			)
			->willReturn( $source );

		$this->assertSame( $source, $this->newFactory( $objectFactory )->getSource( 'watchlist' ) );
	}

	public function testBuildsEachSourceOnlyOnce() {
		$objectFactory = $this->createMock( ObjectFactory::class );
		$objectFactory->expects( $this->once() )
			->method( 'createObject' )
			->willReturn( $this->createMock( IFeedSource::class ) );

		$factory = $this->newFactory( $objectFactory );

		$this->assertSame( $factory->getSource( 'watchlist' ), $factory->getSource( 'watchlist' ) );
	}

	public function testAnUnregisteredNameReturnsNullAndLogs() {
		// There is no placeholder source: a feed drops the missing source and
		// renders the rest, rather than showing a stand-in among real items. The
		// log entry is the only trace it leaves, so it is part of the contract.
		$objectFactory = $this->createMock( ObjectFactory::class );
		$objectFactory->expects( $this->never() )->method( 'createObject' );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'error' )
			->with( $this->anything(), [ 'name' => 'nosuchsource' ] );

		$factory = $this->newFactory( $objectFactory, self::REGISTRY, $logger );

		$this->assertNull( $factory->getSource( 'nosuchsource' ) );
	}

	public function testReportsTheRegisteredNames() {
		$objectFactory = $this->createMock( ObjectFactory::class );

		$this->assertSame(
			[ 'watchlist', 'recentchanges' ],
			$this->newFactory( $objectFactory )->getSourceNames()
		);
	}

	public function testAnEmptyRegistryReportsNoNames() {
		$objectFactory = $this->createMock( ObjectFactory::class );

		$this->assertSame( [], $this->newFactory( $objectFactory, [] )->getSourceNames() );
	}
}
