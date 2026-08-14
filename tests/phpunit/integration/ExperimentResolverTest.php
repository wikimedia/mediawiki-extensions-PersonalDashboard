<?php
namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration;

use MediaWiki\Extension\PersonalDashboard\ExperimentResolver;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentInterface;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\ExperimentResolver
 * @covers \MediaWiki\Extension\PersonalDashboard\ExperimentResolution
 *
 * @group Database
 */
class ExperimentResolverTest extends MediaWikiIntegrationTestCase {

	private const REGISTRY = [ 'default' => [], 'T426615' => [], 'T430001' => [] ];

	protected function setUp(): void {
		parent::setUp();
		// The mocks below reflect on TestKitchen's SDK interfaces directly, so
		// this suite needs the real extension loaded even though
		// PersonalDashboard's own runtime code treats it as optional.
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'TestKitchen' ) ) {
			$this->markTestSkipped( 'Requires the TestKitchen extension.' );
		}
	}

	private function mockExperiment( ?string $assignedGroup ): ExperimentInterface {
		$experiment = $this->createMock( ExperimentInterface::class );
		$experiment->method( 'getAssignedGroup' )->willReturn( $assignedGroup );
		return $experiment;
	}

	/**
	 * @param array<string, ExperimentInterface> $experimentsByName
	 */
	private function mockExperimentManager( array $experimentsByName ): ExperimentManagerInterface {
		$experimentManager = $this->createMock( ExperimentManagerInterface::class );
		$experimentManager->method( 'getExperiment' )->willReturnMap(
			array_map(
				static fn ( $name, $experiment ) => [ $name, $experiment ],
				array_keys( $experimentsByName ),
				array_values( $experimentsByName )
			)
		);
		return $experimentManager;
	}

	private function newResolver(
		?ExperimentManagerInterface $experimentManager,
		array $manifest,
		array $registry = self::REGISTRY,
		?StatsFactory $statsFactory = null
	): ExperimentResolver {
		return new ExperimentResolver(
			$experimentManager,
			$statsFactory ?? StatsFactory::newNull(),
			$manifest,
			$registry
		);
	}

	public function testNoExperimentManagerResolvesNothing() {
		$resolution = $this->newResolver( null, [ 'T426615' => [ 'treatment' => 'T426615' ] ] )->resolve();

		$this->assertNull( $resolution->getModuleGroup() );
		$this->assertSame( [], $resolution->getVariants() );
	}

	public function testUnenrolledResolvesNothing() {
		$experimentManager = $this->mockExperimentManager( [
			'T426615' => $this->mockExperiment( null ),
		] );

		$resolution = $this->newResolver( $experimentManager, [ 'T426615' => [ 'treatment' => 'T426615' ] ] )
			->resolve();

		$this->assertNull( $resolution->getModuleGroup() );
		$this->assertSame( [], $resolution->getVariants() );
	}

	public function testResolveNeverFiresExposureItself() {
		// Whether an assignment "took effect" can depend on what the caller
		// does with the resolution (e.g. a pdo override winning instead), so
		// resolve() alone must never call sendExposure() -- only
		// sendExposures() may, and only once the caller decides to.
		$experiment = $this->mockExperiment( 'treatment' );
		$experiment->expects( $this->never() )->method( 'sendExposure' );
		$experimentManager = $this->mockExperimentManager( [ 'T426615' => $experiment ] );

		$this->newResolver( $experimentManager, [ 'T426615' => [ 'treatment' => 'T426615' ] ] )->resolve();
	}

	public function testOverridingVariantResolvesModuleGroupAndQueuesExposure() {
		$experiment = $this->mockExperiment( 'treatment' );
		$experiment->expects( $this->once() )->method( 'sendExposure' );
		$experimentManager = $this->mockExperimentManager( [ 'T426615' => $experiment ] );

		$resolution = $this->newResolver( $experimentManager, [ 'T426615' => [ 'treatment' => 'T426615' ] ] )
			->resolve();

		$this->assertSame( 'T426615', $resolution->getModuleGroup() );
		$this->assertSame( [ 'T426615' => 'treatment' ], $resolution->getVariants() );

		$resolution->sendExposures();
	}

	public function testNonOverridingVariantResolvesNoGroupButQueuesExposure() {
		// A variant absent from the routing map (conventionally 'control')
		// never touches the module group, but the user is still exposed --
		// this decision always takes effect regardless of any other
		// experiment or of a pdo override.
		$experiment = $this->mockExperiment( 'control' );
		$experiment->expects( $this->once() )->method( 'sendExposure' );
		$experimentManager = $this->mockExperimentManager( [ 'T426615' => $experiment ] );

		$resolution = $this->newResolver( $experimentManager, [ 'T426615' => [ 'treatment' => 'T426615' ] ] )
			->resolve();

		$this->assertNull( $resolution->getModuleGroup() );
		$this->assertSame( [ 'T426615' => 'control' ], $resolution->getVariants() );

		$resolution->sendExposures();
	}

	public function testTagOnlyExperimentBehavesLikeANonOverridingVariant() {
		$experiment = $this->mockExperiment( 'treatment' );
		$experiment->expects( $this->once() )->method( 'sendExposure' );
		$experimentManager = $this->mockExperimentManager( [ 'T430001' => $experiment ] );

		$resolution = $this->newResolver( $experimentManager, [ 'T430001' => [] ] )->resolve();

		$this->assertNull( $resolution->getModuleGroup() );
		$this->assertSame( [ 'T430001' => 'treatment' ], $resolution->getVariants() );

		$resolution->sendExposures();
	}

	public function testSendExposuresIsIdempotent() {
		$experiment = $this->mockExperiment( 'treatment' );
		$experiment->expects( $this->once() )->method( 'sendExposure' );
		$experimentManager = $this->mockExperimentManager( [ 'T426615' => $experiment ] );

		$resolution = $this->newResolver( $experimentManager, [ 'T426615' => [ 'treatment' => 'T426615' ] ] )
			->resolve();

		$resolution->sendExposures();
		$resolution->sendExposures();
	}

	public function testUnroutableOverrideSendsNoExposureAndIsCounted() {
		$experiment = $this->mockExperiment( 'treatment' );
		$experiment->expects( $this->never() )->method( 'sendExposure' );
		$experimentManager = $this->mockExperimentManager( [ 'T426615' => $experiment ] );

		$statsHelper = StatsFactory::newUnitTestingHelper()->withComponent( 'PersonalDashboard' );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'error' );
		$this->setLogger( 'PersonalDashboard', $logger );

		$resolution = $this->newResolver(
			$experimentManager,
			[ 'T426615' => [ 'treatment' => 'not-a-registered-group' ] ],
			self::REGISTRY,
			$statsHelper->getStatsFactory()
		)->resolve();
		$resolution->sendExposures();

		$this->assertNull( $resolution->getModuleGroup() );
		$this->assertSame( [], $resolution->getVariants() );
		$this->assertSame(
			1,
			$statsHelper->count( 'special_dashboard_experiment_unroutable_total{experiment="T426615"}' )
		);
	}

	public function testConcurrentOverridesFirstWinsSecondPreemptedAndCounted() {
		$winner = $this->mockExperiment( 'treatment' );
		$winner->expects( $this->once() )->method( 'sendExposure' );
		$loser = $this->mockExperiment( 'treatment' );
		$loser->expects( $this->never() )->method( 'sendExposure' );
		$experimentManager = $this->mockExperimentManager( [
			'T426615' => $winner,
			'T430001' => $loser,
		] );

		$statsHelper = StatsFactory::newUnitTestingHelper()->withComponent( 'PersonalDashboard' );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'error' );
		$this->setLogger( 'PersonalDashboard', $logger );

		$resolution = $this->newResolver(
			$experimentManager,
			[
				'T426615' => [ 'treatment' => 'T426615' ],
				'T430001' => [ 'treatment' => 'T430001' ],
			],
			self::REGISTRY,
			$statsHelper->getStatsFactory()
		)->resolve();
		$resolution->sendExposures();

		$this->assertSame( 'T426615', $resolution->getModuleGroup() );
		$this->assertSame( [ 'T426615' => 'treatment' ], $resolution->getVariants() );
		$this->assertSame(
			1,
			$statsHelper->count(
				'special_dashboard_experiment_routing_conflicts_total{winner="T426615",preempted="T430001"}'
			)
		);
	}

	public function testWinningOverrideAndTagOnlyExperimentBothLandInVariants() {
		$winner = $this->mockExperiment( 'treatment' );
		$winner->expects( $this->once() )->method( 'sendExposure' );
		$tagOnly = $this->mockExperiment( 'treatment' );
		$tagOnly->expects( $this->once() )->method( 'sendExposure' );
		$experimentManager = $this->mockExperimentManager( [
			'T426615' => $winner,
			'T430001' => $tagOnly,
		] );

		$resolution = $this->newResolver(
			$experimentManager,
			[
				'T426615' => [ 'treatment' => 'T426615' ],
				'T430001' => [],
			]
		)->resolve();
		$resolution->sendExposures();

		$this->assertSame( 'T426615', $resolution->getModuleGroup() );
		$this->assertSame(
			[ 'T426615' => 'treatment', 'T430001' => 'treatment' ],
			$resolution->getVariants()
		);
	}
}
