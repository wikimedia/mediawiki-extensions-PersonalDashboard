<?php
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices;
use MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard;
use MediaWiki\Request\FauxRequest;

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
			$services->getUserOptionsManager(),
			$dashboardServices->getPersonalDashboardModuleRegistry(),
			$services->getStatsFactory(),
		);
	}

	public function testSpecialPageDoesNotFatal() {
		$user = new TestUser( 'ATestUser' );
		$req = new FauxRequest();
		$this->executeSpecialPage( '', $req, null, $user->getUser() );
		$this->assertTrue( true );
	}

	public function testRenderSurveyLink() {
		$sp = $this->newSpecialPage();

		$this->overrideConfigValue( 'PersonalDashboardSurveyLink', 'https://example.com?Q_lang=' );
		$this->assertStringContainsString( 'https://example.com?Q_lang=en', $sp->getSurveyLink() );

		$this->overrideConfigValue( 'PersonalDashboardSurveyLink', 'https://example.com?foo=bar&Q_lang=' );
		$this->assertStringContainsString( 'https://example.com?foo=bar&amp;Q_lang=en', $sp->getSurveyLink() );

		$this->overrideConfigValue( 'PersonalDashboardSurveyLink', '' );
		$this->assertNull( $sp->getSurveyLink() );
	}
}
