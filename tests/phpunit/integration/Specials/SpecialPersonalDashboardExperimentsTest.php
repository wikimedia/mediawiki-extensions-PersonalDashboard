<?php
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices;
use MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentInterface;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use Wikimedia\TestingAccessWrapper;

/**
 * Covers SpecialPersonalDashboard's wiring around experiment resolution: which
 * branch (experiment override / pdo / default) wins and what it records.
 * Concurrency and conflict-detection behaviour live in ExperimentResolverTest;
 * this suite pins the real, single-experiment Experiments::all() manifest.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard
 *
 * @group Database
 */
class SpecialPersonalDashboardExperimentsTest extends MediaWikiIntegrationTestCase {

	/**
	 * A minimal fixture: only the keys getModuleGroups() checks membership
	 * against, not real extension.json content.
	 */
	private const REGISTRY = [ 'default' => [], 'T426615' => [] ];

	protected function setUp(): void {
		parent::setUp();
		// The mocks below reflect on TestKitchen's SDK interfaces directly, so
		// this suite needs the real extension loaded even though
		// PersonalDashboard's own runtime code treats it as optional.
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'TestKitchen' ) ) {
			$this->markTestSkipped( 'Requires the TestKitchen extension.' );
		}
	}

	private function newSpecialPage( ?ExperimentManagerInterface $experimentManager ): SpecialPersonalDashboard {
		$services = $this->getServiceContainer();
		$dashboardServices = PersonalDashboardServices::wrap( $services );
		return new SpecialPersonalDashboard(
			$dashboardServices->getPersonalDashboardModuleFactory(),
			$services->getStatsFactory(),
			$experimentManager,
		);
	}

	private function experimentManagerAssigning( string $variant ): ExperimentManagerInterface {
		$experiment = $this->createMock( ExperimentInterface::class );
		$experiment->method( 'sendExposure' );
		$experiment->method( 'getAssignedGroup' )->willReturn( $variant );

		$experimentManager = $this->createMock( ExperimentManagerInterface::class );
		$experimentManager->method( 'getExperiment' )
			->with( 'T426615' )
			->willReturn( $experiment );

		return $experimentManager;
	}

	public function testGetModuleGroupsRecordsResolvedNameForEnrolledExperiment() {
		// T413223 - $scope is unused, but needed for PHP 8.5's #[NoDiscard] on setAttributeForTest()
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups', self::REGISTRY );

		$sp = TestingAccessWrapper::newFromObject(
			$this->newSpecialPage( $this->experimentManagerAssigning( 'treatment' ) ) );

		$sp->getModuleGroups();

		$this->assertSame( 'T426615', $sp->resolvedModuleGroupName );
		$this->assertSame( [ 'T426615' => 'treatment' ], $sp->resolvedExperimentVariants );
	}

	public function testGetModuleGroupsRecordsDefaultNameWithNoExperimentManager() {
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups', self::REGISTRY );

		$sp = TestingAccessWrapper::newFromObject( $this->newSpecialPage( null ) );

		$sp->getModuleGroups();

		$this->assertSame( 'default', $sp->resolvedModuleGroupName );
		$this->assertSame( [], $sp->resolvedExperimentVariants );
	}

	/**
	 * A non-overriding assignment (conventionally 'control') never touches
	 * the module group, so the user falls through to the baseline exactly
	 * like an unenrolled user, but is still recorded as exposed and tagged --
	 * that's how analysis tells an enrolled control apart from unenrolled.
	 */
	public function testEnrolledInControlResolvesBaselineAndRecordsVariant() {
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups', self::REGISTRY );

		$sp = TestingAccessWrapper::newFromObject(
			$this->newSpecialPage( $this->experimentManagerAssigning( 'control' ) ) );

		$sp->getModuleGroups();

		$this->assertSame( 'default', $sp->resolvedModuleGroupName );
		$this->assertSame( [ 'T426615' => 'control' ], $sp->resolvedExperimentVariants );
	}
}
