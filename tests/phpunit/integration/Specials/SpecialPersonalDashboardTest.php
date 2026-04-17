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
			$services->getUserOptionsManager(),
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

	public function testSavesPageVisitedUserOptionWhenVisited() {
		$user = new TestUser( 'ATestUser' );
		$req = new FauxRequest();

		$this->executeSpecialPage( '', $req, null, $user->getUser() );
		$this->runDeferredUpdates();

		$actual = $this->getServiceContainer()
			->getUserOptionsManager()
			->getBoolOption( $user->getUser(), 'personaldashboard-visited' );
		$this->assertSame( true, $actual );
	}
}
