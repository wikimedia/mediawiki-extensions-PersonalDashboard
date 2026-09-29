<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Specials;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PersonalDashboard\Experiments;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices;
use MediaWiki\Extension\PersonalDashboard\Specials\DashboardPageDependencies;
use MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentInterface;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use Psr\Log\LoggerInterface;
use TestUser;
use Wikimedia\ScopedCallback;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * Covers the part of AbstractSpecialDashboard that only a second dashboard can
 * show: that the page name, the module group, the StatsFactory component, the
 * back-link target and the experiment manifest all come from the subclass, and
 * that one render pipeline serves both.
 *
 * SpecialPersonalDashboardTest covers the same pipeline for the dashboard
 * Personal Dashboard itself ships.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Specials\AbstractSpecialDashboard
 *
 * @group SpecialPage
 * @group Database
 */
class AbstractSpecialDashboardTest extends SpecialPageTestBase {

	/** A server-rendered module that needs no services. */
	private const MODULE = 'ext.personalDashboard.policiesGuidelines';

	/** A module whose card wraps in a link to its own focused subpage. */
	private const LINKED_MODULE = 'ext.personalDashboard.activeDiscussions';

	private const REGISTRY = [
		'default' => [
			'description' => 'Stands in for the Moderator Tools dashboard',
			'version' => 0,
			'groups' => [],
		],
		'T426615' => [
			'description' => 'Stands in for the module group an experiment routes to',
			'version' => 0,
			'groups' => [],
		],
		'testDouble' => [
			'description' => 'The second dashboard under test',
			'version' => 0,
			'groups' => [
				[
					'name' => 'main',
					'enabled' => true,
					'subgroups' => [
						[
							'name' => 'primary',
							'enabled' => true,
							'modules' => [
								[ 'name' => self::MODULE, 'enabled' => true ],
								[ 'name' => self::LINKED_MODULE, 'enabled' => true ],
							],
						],
					],
				],
			],
		],
	];

	/** The page executeSpecialPage() runs; set by the test that needs a variant. */
	private ?SpecialPage $page = null;

	/**
	 * Keeps the module-group fixture registered for the length of a test.
	 *
	 * This property is never read, and holding the reference is its whole
	 * job: setAttributeForTest() reverts the attribute as soon as the
	 * ScopedCallback it returns is destroyed. A local variable would be
	 * released the moment the registering method returned, and every test
	 * would then silently run against the real extension.json registry.
	 */
	private ?ScopedCallback $moduleGroupsScope = null;

	protected function setUp(): void {
		parent::setUp();

		// Every test here renders or resolves a group from the fixture below.
		$this->moduleGroupsScope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups', self::REGISTRY );
		/*
		 * SpecialPageExecutor calls getPageTitle() before execute(), and
		 * SpecialPageFactory::getLocalNameFor() wfWarn()s for a page name that
		 * no alias file names. Tests run with $wgDevelopmentWarnings on, so
		 * that notice would fail every test here. A real dashboard ships an
		 * alias (see docs/modules.md); a test-only one has nowhere to put one.
		 */
		$this->overrideConfigValue( MainConfigNames::DevelopmentWarnings, false );
	}

	protected function tearDown(): void {
		$this->moduleGroupsScope = null;
		parent::tearDown();
	}

	private function dependencies( ?ExperimentManagerInterface $experimentManager = null ): DashboardPageDependencies {
		$services = $this->getServiceContainer();
		return new DashboardPageDependencies(
			PersonalDashboardServices::wrap( $services )->getPersonalDashboardModuleFactory(),
			$services->getStatsFactory(),
			$experimentManager,
		);
	}

	protected function newSpecialPage(): SpecialPage {
		return $this->page ?? new TestDashboardSpecialPage( $this->dependencies() );
	}

	/** @return TestingAccessWrapper Wraps the page, to read its private memos */
	private function wrap( SpecialPage $page ): TestingAccessWrapper {
		return TestingAccessWrapper::newFromObject( $page );
	}

	/**
	 * @param SpecialPage $page Dashboard to put the request on
	 * @param array $requestData Query params for the FauxRequest
	 * @param array $cookies Cookie name => value, as the browser would send them
	 * @return TestingAccessWrapper Wraps $page, whose context carries the request
	 */
	private function wrapWithRequest(
		SpecialPage $page,
		array $requestData = [],
		array $cookies = []
	): TestingAccessWrapper {
		$request = new FauxRequest( $requestData );
		foreach ( $cookies as $name => $value ) {
			$request->setCookie( $name, $value );
		}
		$context = new RequestContext();
		$context->setRequest( $request );
		$page->setContext( $context );
		return $this->wrap( $page );
	}

	private function experimentManagerAssigning( string $variant ): ExperimentManagerInterface {
		$experiment = $this->createMock( ExperimentInterface::class );
		$experiment->method( 'sendExposure' );
		$experiment->method( 'getAssignedGroup' )->willReturn( $variant );

		$experimentManager = $this->createMock( ExperimentManagerInterface::class );
		$experimentManager->method( 'getExperiment' )->willReturn( $experiment );

		return $experimentManager;
	}

	private function requireTestKitchen(): void {
		// The mocks reflect on TestKitchen's SDK interfaces directly, so they
		// need the real extension even though the dashboard treats it as
		// optional.
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'TestKitchen' ) ) {
			$this->markTestSkipped( 'Requires the TestKitchen extension.' );
		}
	}

	public function testSecondDashboardRendersTheSameFrame() {
		[ $html ] = $this->executeSpecialPage(
			'', null, null, ( new TestUser( 'ADashboardUser' ) )->getUser() );

		$this->assertStringContainsString( 'id="personal-dashboard-root"', $html );
		$this->assertStringContainsString( 'personal-dashboard-viewport', $html );
		$this->assertStringContainsString( 'personal-dashboard-container', $html );
		// The group tree came from the subclass, so its subgroup wrapper is the
		// one on the page.
		$this->assertStringContainsString(
			'personal-dashboard-group-main-subgroup-primary', $html );
	}

	/**
	 * A module's focused subpage hangs off the dashboard that rendered it, so
	 * the card links must carry this page's name and not Personal Dashboard's.
	 */
	public function testModuleLinksPointAtThisDashboard() {
		[ $html ] = $this->executeSpecialPage(
			'', null, null, ( new TestUser( 'ADashboardUser' ) )->getUser() );

		$this->assertStringContainsString(
			'PersonalDashboardTestDouble/' . self::LINKED_MODULE, $html );
		$this->assertStringNotContainsString(
			'/Special:PersonalDashboard/' . self::LINKED_MODULE, $html );
	}

	public function testBaselineModuleGroupAccessorSelectsTheGroup() {
		$dashboard = $this->wrap( new TestDashboardSpecialPage( $this->dependencies() ) );
		$dashboard->getModuleGroups();

		$this->assertSame( 'testDouble', $dashboard->resolvedModuleGroupName );

		// The same registry, the other subclass: proof the group is the
		// subclass's answer and not something the registry decides.
		$personalDashboard = $this->wrap(
			new SpecialPersonalDashboard( $this->dependencies() ) );
		$personalDashboard->getModuleGroups();

		$this->assertSame( 'default', $personalDashboard->resolvedModuleGroupName );
	}

	/**
	 * The manifest names module groups, and a module group belongs to one
	 * dashboard. So a dashboard that declares nothing -- the default -- keeps
	 * its own group even for a user the same enrollment reroutes on another
	 * dashboard.
	 */
	public function testADashboardThatDeclaresNoManifestIsNeverRerouted() {
		$this->requireTestKitchen();
		$experimentManager = $this->experimentManagerAssigning( 'treatment' );

		$dashboard = $this->wrap( new TestDashboardSpecialPage(
			$this->dependencies( $experimentManager ) ) );
		$dashboard->getModuleGroups();

		$this->assertSame( 'testDouble', $dashboard->resolvedModuleGroupName );
		$this->assertSame( [], $dashboard->resolvedExperimentVariants );

		// The same enrollment does reroute the dashboard whose manifest it is,
		// so the assertion above is not passing because nothing is enrolled.
		$personalDashboard = $this->wrap( new SpecialPersonalDashboard(
			$this->dependencies( $experimentManager ) ) );
		$personalDashboard->getModuleGroups();

		$this->assertSame( 'T426615', $personalDashboard->resolvedModuleGroupName );
	}

	/**
	 * The other direction: a dashboard that does declare a manifest is
	 * rerouted by it.
	 */
	public function testADeclaredManifestReroutesTheDashboard() {
		$this->requireTestKitchen();
		$dashboard = $this->wrap( new TestDashboardSpecialPage(
			$this->dependencies( $this->experimentManagerAssigning( 'treatment' ) ),
			'PersonalDashboardTestDouble',
			'testDouble',
			'PersonalDashboardTestDouble',
			Experiments::all()
		) );
		$dashboard->getModuleGroups();

		$this->assertSame( 'T426615', $dashboard->resolvedModuleGroupName );
	}

	public function testStatsComponentAccessorIsHonoured() {
		$statsHelper = StatsFactory::newUnitTestingHelper()
			->withComponent( 'PersonalDashboardTestDouble' );
		$this->page = new TestDashboardSpecialPage( new DashboardPageDependencies(
			PersonalDashboardServices::wrap( $this->getServiceContainer() )
				->getPersonalDashboardModuleFactory(),
			$statsHelper->getStatsFactory(),
		) );

		$this->executeSpecialPage( '', null, null, ( new TestUser( 'ADashboardUser' ) )->getUser() );

		/*
		 * One assertion covers both directions: the helper throws
		 * OutOfBoundsException for a component it never saw, so a render that
		 * had reported under 'PersonalDashboard' fails here rather than
		 * passing quietly.
		 */
		$this->assertSame( 1, $statsHelper->count(
			'special_dashboard_server_side_render_seconds{platform="desktop"}' ) );
	}

	public function testBackLinkTargetDefaultsToThisDashboard() {
		[ $html ] = $this->executeSpecialPage(
			self::MODULE, null, null, ( new TestUser( 'ADashboardUser' ) )->getUser() );

		$this->assertStringContainsString(
			'personal-dashboard-module-header-back-icon', $html );
		$this->assertStringContainsString(
			SpecialPage::getTitleFor( 'PersonalDashboardTestDouble' )->getLinkURL(), $html );
	}

	public function testBackLinkTargetOverrideSendsTheBackLinkElsewhere() {
		$elsewhere = SpecialPage::getTitleFor( 'Blankpage' );
		$this->page = new TestDashboardSpecialPage(
			$this->dependencies(),
			'PersonalDashboardTestDouble',
			'testDouble',
			'PersonalDashboardTestDouble',
			[],
			$elsewhere
		);

		[ $html ] = $this->executeSpecialPage(
			self::MODULE, null, null, ( new TestUser( 'ADashboardUser' ) )->getUser() );

		$this->assertStringContainsString(
			'href="' . htmlspecialchars( $elsewhere->getLinkURL() ) . '" '
				. 'class="personal-dashboard-module-header-back-icon"',
			$html
		);
	}

	/**
	 * The `pdo` cookie pins a QA session to a module group. Two dashboards
	 * share a browser, so one dashboard's pinned session must not reroute the
	 * other: the cookie is named after the page that wrote it.
	 */
	public function testPdoCookieIsNotSharedBetweenDashboards() {
		// What a browser already holding a pinned Special:PersonalDashboard
		// session would send to both pages.
		$cookies = [ 'pdo-PersonalDashboard' => 'T426615' ];

		$personalDashboard = $this->wrapWithRequest(
			new SpecialPersonalDashboard( $this->dependencies() ), [], $cookies );
		$this->assertSame(
			'T426615',
			$personalDashboard->resolvePdoOverride( self::REGISTRY ),
			'the dashboard that wrote the cookie still reads it'
		);

		$dashboard = $this->wrapWithRequest(
			new TestDashboardSpecialPage( $this->dependencies() ), [], $cookies );
		$this->assertNull(
			$dashboard->resolvePdoOverride( self::REGISTRY ),
			'another dashboard does not read it'
		);
	}

	/**
	 * The write side of the same rule: `?pdo=` pins only the page it was given
	 * to, under that page's own cookie name.
	 */
	public function testPdoUrlParamWritesACookieNamedForThisDashboard() {
		// FauxResponse::setCookie() stores under $wgCookiePrefix . $name while
		// getCookie() reads back the bare name, so pin the prefix empty; see
		// SpecialPersonalDashboardExperimentsTest for the full note.
		$this->overrideConfigValue( MainConfigNames::CookiePrefix, '' );

		$dashboard = $this->wrapWithRequest(
			new TestDashboardSpecialPage( $this->dependencies() ), [ 'pdo' => 'testDouble' ] );

		$dashboard->resolvePdoOverride( self::REGISTRY );

		$response = $dashboard->getRequest()->response();
		$this->assertSame( 'testDouble', $response->getCookie( 'pdo-PersonalDashboardTestDouble' ) );
		// The unsuffixed name is what every dashboard used to share.
		$this->assertNull( $response->getCookie( 'pdo' ) );
		$this->assertNull( $response->getCookie( 'pdo-PersonalDashboard' ) );
	}

	/**
	 * The consequence a reader actually cares about: a pinned session changes
	 * the module group of its own dashboard and leaves the other one alone.
	 */
	public function testPdoCookieOverridesOnlyTheDashboardThatSetIt() {
		$this->overrideConfigValue( 'PersonalDashboardAllowOverride', true );

		$cookies = [ 'pdo-PersonalDashboardTestDouble' => 'T426615' ];

		$dashboard = $this->wrapWithRequest(
			new TestDashboardSpecialPage( $this->dependencies() ), [], $cookies );
		$dashboard->getModuleGroups();

		$this->assertSame( 'T426615', $dashboard->resolvedModuleGroupName );
		$this->assertTrue( $dashboard->pdoOverrideActive );

		$personalDashboard = $this->wrapWithRequest(
			new SpecialPersonalDashboard( $this->dependencies() ), [], $cookies );
		$personalDashboard->getModuleGroups();

		$this->assertSame( 'default', $personalDashboard->resolvedModuleGroupName );
		$this->assertFalse( $personalDashboard->pdoOverrideActive );
	}

	/**
	 * A dashboard can name a group the wiki does not have, because the
	 * extension that registers it may be disabled here. Render nothing rather
	 * than fatal, the same way a group naming an unregistered module degrades
	 * to a placeholder card.
	 */
	public function testUnregisteredBaselineModuleGroupRendersEmptyAndLogs() {
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'error' );
		$this->setLogger( 'PersonalDashboard', $logger );

		$this->page = new TestDashboardSpecialPage(
			$this->dependencies(), 'PersonalDashboardTestDouble', 'notARegisteredGroup' );

		[ $html ] = $this->executeSpecialPage(
			'', null, null, ( new TestUser( 'ADashboardUser' ) )->getUser() );

		$this->assertStringContainsString( 'personal-dashboard-container', $html );
		$this->assertStringNotContainsString(
			'personal-dashboard-group-main-subgroup-primary', $html );
	}
}
