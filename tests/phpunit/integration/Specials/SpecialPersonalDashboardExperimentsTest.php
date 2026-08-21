<?php
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices;
use MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentInterface;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\Request\FauxRequest;
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

	/**
	 * @param array $requestData Query params for the FauxRequest
	 * @param ?ExperimentManagerInterface $experimentManager
	 * @return TestingAccessWrapper Wraps a SpecialPersonalDashboard whose context carries the given request
	 */
	private function newWrappedSpecialPageWithRequest(
		array $requestData = [],
		?ExperimentManagerInterface $experimentManager = null
	): TestingAccessWrapper {
		$sp = $this->newSpecialPage( $experimentManager );
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( $requestData ) );
		$sp->setContext( $context );
		return TestingAccessWrapper::newFromObject( $sp );
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

	public function testPdoUrlParamResolvesModuleGroup() {
		$sp = $this->newWrappedSpecialPageWithRequest( [ 'pdo' => 'T426615' ] );

		$this->assertSame( 'T426615', $sp->resolvePdoOverride( self::REGISTRY ) );
	}

	public function testPdoCookieResolvesModuleGroupWithNoUrlParam() {
		$sp = $this->newWrappedSpecialPageWithRequest();
		$sp->getRequest()->setCookie( 'pdo', 'T426615' );

		$this->assertSame( 'T426615', $sp->resolvePdoOverride( self::REGISTRY ) );
	}

	public function testPdoUrlParamAlsoSetsResponseCookie() {
		// FauxResponse::setCookie() stores under $wgCookiePrefix . $name, but
		// getCookie()/getCookieData() read back the bare name: a core
		// inconsistency that only surfaces where CookiePrefix's dynamic
		// default resolves non-empty (e.g. Quibble's real test DB). Pin it
		// empty so this test isn't at the mercy of that environment quirk.
		$this->overrideConfigValue( 'CookiePrefix', '' );

		$sp = $this->newWrappedSpecialPageWithRequest( [ 'pdo' => 'T426615' ] );

		$sp->resolvePdoOverride( self::REGISTRY );

		$response = $sp->getRequest()->response();
		$this->assertSame( 'T426615', $response->getCookie( 'pdo' ) );
		// Regression test: the cookie must not inherit core's HttpOnly default,
		// or a client-side reader couldn't see it.
		$this->assertFalse( $response->getCookieData( 'pdo' )['httpOnly'] );
		// A session cookie, so following someone else's `?pdo=` link doesn't pin
		// the module group (and suppress instrumentation) for $wgCookieExpiration.
		$this->assertSame( 0, $response->getCookieData( 'pdo' )['expire'] );
	}

	public function testInvalidPdoValueFallsThroughWithNoCookieSet() {
		$sp = $this->newWrappedSpecialPageWithRequest( [ 'pdo' => 'not-a-real-group' ] );

		$this->assertNull( $sp->resolvePdoOverride( self::REGISTRY ) );
		$this->assertNull( $sp->getRequest()->response()->getCookie( 'pdo' ) );
	}

	public function testRealEnrollmentWinsOverValidPdoParam() {
		$this->overrideConfigValue( 'PersonalDashboardAllowOverride', true );
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups', self::REGISTRY );

		// A differing 'pdo=default' request param must have no effect: the
		// mocked experiment above resolves enrollment to 'T426615' first.
		$sp = $this->newWrappedSpecialPageWithRequest(
			[ 'pdo' => 'default' ], $this->experimentManagerAssigning( 'treatment' ) );

		$sp->getModuleGroups();

		$this->assertSame( 'T426615', $sp->resolvedModuleGroupName );
		$this->assertFalse( $sp->pdoOverrideActive );
	}

	/**
	 * A non-overriding assignment doesn't claim the module-group slot, so pdo
	 * still applies: pdo previews an in-development group for QA/dev accounts,
	 * and that has to keep working even once a wiki has a live experiment
	 * most accounts get randomly assigned control in. No exposure fires for
	 * the control assignment here, since pdo, not the experiment, decided
	 * what this user sees.
	 */
	public function testPdoAppliesOverNonOverridingEnrollment() {
		$experiment = $this->createMock( ExperimentInterface::class );
		$experiment->expects( $this->never() )->method( 'sendExposure' );
		$experiment->method( 'getAssignedGroup' )->willReturn( 'control' );

		$experimentManager = $this->createMock( ExperimentManagerInterface::class );
		$experimentManager->method( 'getExperiment' )
			->with( 'T426615' )
			->willReturn( $experiment );

		$this->overrideConfigValue( 'PersonalDashboardAllowOverride', true );
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups', self::REGISTRY );

		$sp = $this->newWrappedSpecialPageWithRequest( [ 'pdo' => 'T426615' ], $experimentManager );

		$sp->getModuleGroups();

		$this->assertSame( 'T426615', $sp->resolvedModuleGroupName );
		$this->assertTrue( $sp->pdoOverrideActive );
		$this->assertSame( [], $sp->resolvedExperimentVariants );
	}

	public function testPdoOverrideActiveFlagSetWhenPdoResolves() {
		$this->overrideConfigValue( 'PersonalDashboardAllowOverride', true );
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups', self::REGISTRY );

		$sp = $this->newWrappedSpecialPageWithRequest( [ 'pdo' => 'T426615' ] );

		$sp->getModuleGroups();

		$this->assertSame( 'T426615', $sp->resolvedModuleGroupName );
		$this->assertTrue( $sp->pdoOverrideActive );
	}

	/**
	 * Extension default is `false` (out-of-the-box safe for any install; WMF's
	 * deployment config sets it `true`). Both the explicit-false and the
	 * implicit default land on the same behaviour, so this test covers both.
	 */
	public function testAllowOverrideFalseDisablesPdoEntirely() {
		$this->overrideConfigValue( 'PersonalDashboardAllowOverride', false );
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups', self::REGISTRY );

		$sp = $this->newWrappedSpecialPageWithRequest( [ 'pdo' => 'T426615' ] );

		$sp->getModuleGroups();

		$this->assertSame( 'default', $sp->resolvedModuleGroupName );
		$this->assertFalse( $sp->pdoOverrideActive );
	}
}
