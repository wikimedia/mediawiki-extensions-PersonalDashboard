<?php
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices;
use MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Specials\AbstractSpecialDashboard
 * @covers \MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard
 *
 * @group SpecialPage
 * @group Database
 */
class SpecialPersonalDashboardTest extends SpecialPageTestBase {
	protected function newSpecialPage(): SpecialPersonalDashboard {
		$services = $this->getServiceContainer();
		$dashboardServices = PersonalDashboardServices::wrap( $services );
		return new SpecialPersonalDashboard(
			$dashboardServices->getPersonalDashboardPageDependencies()
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

	/**
	 * The survey URL comes from the module group's `betaFeedback`
	 * declaration, and `$1` in it carries the viewer's language code for a
	 * survey tool that takes the language as a parameter.
	 */
	public function testBetaFeedbackUrlSubstitutesTheLanguageCode() {
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups',
			[ 'default' => [
				'betaFeedback' => 'https://example.com?foo=bar&Q_lang=$1',
				'groups' => [],
			] ]
		);

		$sp = TestingAccessWrapper::newFromObject( $this->newSpecialPage() );

		$this->assertSame( 'https://example.com?foo=bar&Q_lang=en', $sp->getBetaFeedbackUrl() );
		// The chip escapes it on the way into the link.
		$this->assertStringContainsString(
			'https://example.com?foo=bar&amp;Q_lang=en',
			$sp->createSurveyLinkBetaChip( $sp->getBetaFeedbackUrl() )
		);
	}

	/**
	 * A URL that names no language parameter is linked as declared.
	 */
	public function testBetaFeedbackUrlWithoutAPlaceholderIsUsedVerbatim() {
		$scope = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PersonalDashboardModuleGroups',
			[ 'default' => [
				'betaFeedback' => 'https://www.mediawiki.org/wiki/Talk:Moderator_Tools/Dashboard',
				'groups' => [],
			] ]
		);

		$sp = TestingAccessWrapper::newFromObject( $this->newSpecialPage() );

		$this->assertSame(
			'https://www.mediawiki.org/wiki/Talk:Moderator_Tools/Dashboard',
			$sp->getBetaFeedbackUrl()
		);
	}

	/**
	 * The render-level half of the rule: the Moderator Tools group declares a
	 * survey, so its dashboard carries the chip. The negative case, and the
	 * reason both are asserted on the real output rather than on
	 * getBetaFeedbackUrl(), are in AbstractSpecialDashboardTest.
	 */
	public function testBetaFeedbackRendersForTheDefaultModuleGroup() {
		$indicators = $this->executeAndGetIndicators();

		$this->assertArrayHasKey( 'mw-ext-personal-dashboard-survey', $indicators );
		// The shipped declaration, with the viewer's language substituted in.
		$this->assertStringContainsString(
			'https://wikimediafoundation.limesurvey.net/179424?lang=en',
			$indicators['mw-ext-personal-dashboard-survey']
		);
	}

	/**
	 * Render the dashboard and return its page indicators.
	 *
	 * The chip is an indicator on every skin but Minerva, and
	 * OutputPage::getHTML() does not include those, so the HTML
	 * executeSpecialPage() returns cannot see it. Passing our own context in
	 * gives us the OutputPage it rendered into.
	 *
	 * @return array Indicator id => HTML
	 */
	private function executeAndGetIndicators(): array {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );
		$context->setLanguage( 'en' );
		$context->setUser( ( new TestUser( 'ASurveyUser' ) )->getUser() );
		$context->setTitle( SpecialPage::getTitleFor( 'PersonalDashboard' ) );

		$this->executeSpecialPage( '', null, null, null, false, $context );

		return $context->getOutput()->getIndicators();
	}
}
