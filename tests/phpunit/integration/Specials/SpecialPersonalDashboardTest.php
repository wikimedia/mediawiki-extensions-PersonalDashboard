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
}
