<?php
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices;
use MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard
 *
 * @group SpecialPage
 * @group Database
 */
class SpecialPersonalDashboardTest extends SpecialPageTestBase {
	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		$dashboardServices = PersonalDashboardServices::wrap( $services );
		return new SpecialPersonalDashboard(
			$dashboardServices->getPersonalDashboardModuleFactory(),
			$services->getStatsFactory(),
		);
	}

	public function testGroupedRenderEmitsViewportWrapper() {
		$user = new TestUser( 'ATestUser' );
		$req = new FauxRequest();
		[ $html ] = $this->executeSpecialPage( '', $req, null, $user->getUser() );

		$this->assertStringContainsString( 'personal-dashboard-viewport', $html );
		$this->assertStringContainsString( 'personal-dashboard-container', $html );
		// The viewport wraps the container as its container-query context (see
		// SpecialPersonalDashboard::renderGroupedFrames), so it must open first.
		$this->assertLessThan(
			strpos( $html, 'personal-dashboard-container' ),
			strpos( $html, 'personal-dashboard-viewport' ),
			'viewport wrapper should open before the container it wraps'
		);
	}

	public function testRenderSurveyLink() {
		$sp = $this->newSpecialPage();

		$this->overrideConfigValue( 'PersonalDashboardSurveyLink', 'https://example.com?Q_lang=' );
		$this->assertStringContainsString( 'https://example.com?Q_lang=en', $sp->createSurveyLinkBetaChip() );

		$this->overrideConfigValue( 'PersonalDashboardSurveyLink', 'https://example.com?foo=bar&Q_lang=' );
		$this->assertStringContainsString( 'https://example.com?foo=bar&amp;Q_lang=en',
			$sp->createSurveyLinkBetaChip() );

		$this->overrideConfigValue( 'PersonalDashboardSurveyLink', '' );
		$this->assertStringNotContainsString( 'https://example.com?foo=bar&amp;Q_lang=en',
			$sp->createSurveyLinkBetaChip() );
		$this->assertStringContainsString( 'https://www.mediawiki.org/wiki/Talk:Moderator_Tools/Dashboard',
			$sp->createSurveyLinkBetaChip() );
	}
}
