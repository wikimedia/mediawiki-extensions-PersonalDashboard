<?php

namespace MediaWiki\Extension\PersonalDashboard\Specials;

use MediaWiki\Config\ConfigException;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardModuleRegistry;
use MediaWiki\Extension\PersonalDashboard\Util;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\WikiMap\WikiMap;
use Throwable;
use Wikimedia\Stats\StatsFactory;

class SpecialPersonalDashboard extends SpecialPage {

	private PersonalDashboardModuleRegistry $moduleRegistry;
	private string|null $variant;

	/**
	 * @var string Unique identifier for this specific rendering of Special:PersonalDashboard.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private string $pageviewToken;
	private bool $isMobile;
	private StatsFactory $statsFactory;

	public function __construct(
		PersonalDashboardModuleRegistry $moduleRegistry,
		StatsFactory $statsFactory,
	) {
		parent::__construct( 'PersonalDashboard', '', false );
		$this->moduleRegistry = $moduleRegistry;
		$this->statsFactory = $statsFactory;
		$this->pageviewToken = $this->generatePageviewToken();
		$this->variant = $this->getConfig()->get( 'PersonalDashboardVariant' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'wiki';
	}

	/**
	 * @inheritDoc
	 * @param string $par
	 * @throws ConfigException
	 * @throws ErrorPageError
	 * @throws UserNotLoggedIn
	 */
	public function execute( $par = '' ) {
		$startTime = microtime( true );
		$this->requireNamedUser();
		parent::execute( $par );
		$out = $this->getContext()->getOutput();

		$out->addModuleStyles( 'ext.personalDashboard.styles' );
		$out->addModules( 'ext.personalDashboard.onboarding' );

		$this->isMobile = Util::isMobile( $out->getSkin() );
		$out->addJsConfigVars( [
			'wgPersonalDashboardPageviewToken' => $this->pageviewToken
		] );
		$out->enableOOUI();
		$out->addHTML( Html::element( 'div', [ 'id' => 'personal-dashboard-onboarding' ] ) );

		$surveyLink = $this->getSurveyLink();

		if ( $surveyLink ) {
			$out->addHTML( $surveyLink );
		}

		$out->addHTML( Html::openElement( 'div', [
			'class' => 'personal-dashboard-container ' .
				'personal-dashboard-container-user-variant-' . $this->variant
		] ) );

		$modules = $this->getModules( $this->isMobile, $par );

		if ( $this->isMobile ) {
			if (
				array_key_exists( $par, $modules ) &&
				$modules[$par]->supports( IModule::RENDER_MOBILE_DETAILS )
			) {
				$mode = IModule::RENDER_MOBILE_DETAILS;
				$this->renderMobileDetails( $modules[$par] );
			} else {
				$mode = IModule::RENDER_MOBILE_SUMMARY;
				$this->renderMobileSummary();
			}
		} else {
			$mode = IModule::RENDER_DESKTOP;
			$this->renderDesktop();
		}

		$out->addHTML( Html::closeElement( 'div' ) );
		$this->outputJsData( $mode, $modules );
		$this->getOutput()->addBodyClasses(
			'personal-dashboard-user-variant-' . $this->variant
		);
		$platform = ( $this->isMobile ? 'mobile' : 'desktop' );
		$overallSsrTimeInSeconds = microtime( true ) - $startTime;
		$this->statsFactory->withComponent( 'PersonalDashboard' )
			->getTiming( 'special_dashboard_server_side_render_seconds' )
			->setLabel( 'platform', $platform )
			->observeSeconds( $overallSsrTimeInSeconds );
	}

	/**
	 * Overridden in order to inject the current user's name as message parameter
	 *
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'personal-dashboard-specialpage-title' )
			->params( $this->getUser()->getName() );
	}

	/**
	 * @param bool $isMobile
	 * @param string|null $par Path passed into SpecialPersonalDashboard::execute()
	 * @return IModule[]
	 */
	private function getModules( bool $isMobile, $par = '' ) {
		$moduleConfig = [
			'banner' => true,
			'riskyArticleEdits' => true,
			'impact' => true,
			'policiesGuidelines' => true,
		];

		if ( $isMobile ) {
			// mobile-specific modules can be added here
		}

		switch ( $par ) {
			// subpage-specific modules can be added here
			case '':
				break;
			default:
				break;
		}
		$modules = [];
		foreach ( $moduleConfig as $moduleId => $_ ) {
			$modules[$moduleId] = $this->moduleRegistry->get( $moduleId, $this->getContext() );
		}
		return $modules;
	}

	/**
	 * @return string[][][]
	 */
	private function getModuleGroups(): array {
		return [
			'main' => [
				'primary' => [ 'banner', 'riskyArticleEdits' ],
			],
			'sidebar' => [
				'primary' => [ 'impact' ],
				'secondary' => [ 'policiesGuidelines' ],
			]
		];
	}

	/**
	 * @return string
	 */
	private function getDashboardVariant(): string {
		return $this->getConfig()->get( 'PersonalDashboardVariant' );
	}

	/**
	 * Returns 32-character random string.
	 * The token is used for client-side logging and can be retrieved on Special:PersonalDashboard via the
	 * wgPersonalDashboardPageviewToken JS variable.
	 * @return string
	 */
	private function generatePageviewToken() {
		return \Wikimedia\base_convert( \MWCryptRand::generateHex( 40 ), 16, 32, 32 );
	}

	/**
	 * Get the survey link header HTML if the config value is set and valid.
	 */
	public function getSurveyLink(): ?string {
		$url = $this->getConfig()->get( 'PersonalDashboardSurveyLink' );

		if ( !$url ) {
			return null;
		}

		return Html::rawElement(
			'div',
			[ 'class' => 'personal-dashboard-survey' ],
			Html::element( 'span', [ 'class' => 'personal-dashboard-survey-icon' ] ) .
			$this->msg( 'personal-dashboard-survey-text', $url . $this->getLanguage()->getCode() )
		);
	}

	private function renderDesktop() {
		$out = $this->getContext()->getOutput();
		$modules = $this->getModules( false );
		$out->addBodyClasses( 'personal-dashboard-desktop' );
		foreach ( $this->getModuleGroups() as $group => $subGroups ) {
			$out->addHTML( Html::openElement( 'div', [
				'class' => "personal-dashboard-group-$group " .
					"personal-dashboard-group-$group-user-variant-" . $this->variant
			] ) );
			foreach ( $subGroups as $subGroup => $moduleNames ) {
				$out->addHTML( Html::openElement( 'div', [
					'class' => "personal-dashboard-group-$group-subgroup-$subGroup " .
					"personal-dashboard-group-$group-subgroup-$subGroup-user-variant-" .
					$this->variant
				] ) );
				foreach ( $moduleNames as $moduleName ) {
					/** @var IModule $module */
					$module = $modules[$moduleName] ?? null;
					if ( !$module ) {
						continue;
					}

					$startTime = microtime( true );

					$module->setPageURL( $this->getPageTitle()->getLinkURL() );
					$html = $this->getModuleRenderHtmlSafe( $module, IModule::RENDER_DESKTOP );
					$out->addHTML( $html );

					$this->recordModuleRenderingTime(
						$moduleName,
						IModule::RENDER_DESKTOP,
						microtime( true ) - $startTime
					);
				}
				$out->addHTML( Html::closeElement( 'div' ) );
			}
			$out->addHTML( Html::closeElement( 'div' ) );
		}
	}

	private function recordModuleRenderingTime( string $moduleName, string $mode, float $timeToRecordInSeconds ): void {
		$wiki = WikiMap::getCurrentWikiId();
		$this->statsFactory->withComponent( 'PersonalDashboard' )
			->getTiming( 'special_dashboard_ssr_per_module_seconds' )
			->setLabel( 'wiki', $wiki )
			->setLabel( 'module', $moduleName )
			->setLabel( 'mode', $mode )
			->observeSeconds( $timeToRecordInSeconds );
	}

	private function renderMobileDetails( IModule $module ) {
		$out = $this->getContext()->getOutput();
		$out->addBodyClasses( 'personal-dashboard-mobile-details' );
		$html = $this->getModuleRenderHtmlSafe( $module, IModule::RENDER_MOBILE_DETAILS );
		$this->getOutput()->addHTML( $html );
	}

	private function renderMobileSummary() {
		$out = $this->getContext()->getOutput();
		$modules = $this->getModules( true );
		$isOpeningOverlay = $this->getContext()->getRequest()->getFuzzyBool( 'overlay' );
		$out->addBodyClasses( [
			'personal-dashboard-mobile-summary',
			$isOpeningOverlay ? 'personal-dashboard-mobile-summary--opening-overlay' : ''
		] );
		foreach ( $modules as $moduleName => $module ) {
			$startTime = microtime( true );

			$module->setPageURL( $this->getPageTitle()->getLinkURL() );
			$html = $this->getModuleRenderHtmlSafe( $module, IModule::RENDER_MOBILE_SUMMARY );
			$this->getOutput()->addHTML( $html );

			$this->recordModuleRenderingTime(
				$moduleName,
				IModule::RENDER_MOBILE_SUMMARY,
				microtime( true ) - $startTime
			);
		}
	}

	/**
	 * Get the module render HTML for a particular mode, catching exceptions by default.
	 *
	 * If PersonalDashboardDeveloperSetup is on, then throw the exceptions.
	 * @param IModule $module
	 * @param string $mode
	 * @throws Throwable
	 * @return string
	 */
	private function getModuleRenderHtmlSafe( IModule $module, string $mode ): string {
		$html = '';
		try {
			$html = $module->render( $mode );
		} catch ( Throwable $throwable ) {
			if ( $this->getConfig()->get( 'PersonalDashboardDeveloperSetup' ) ) {
				throw $throwable;
			}
			Util::logException( $throwable, [ 'origin' => __METHOD__ ] );
		}
		return $html;
	}

	/**
	 * @param string $mode One of RENDER_DESKTOP, RENDER_MOBILE_SUMMARY, RENDER_MOBILE_DETAILS
	 * @param IModule[] $modules
	 */
	private function outputJsData( $mode, array $modules ) {
		$out = $this->getContext()->getOutput();

		$data = [];
		$html = '';
		foreach ( $modules as $moduleName => $module ) {
			try {
				$data[$moduleName] = $module->getJsData( $mode );
				if ( isset( $data[$moduleName]['overlay'] ) ) {
					$html .= $data[$moduleName]['overlay'];
					unset( $data[$moduleName]['overlay'] );
				}
			} catch ( Throwable $throwable ) {
				if ( $this->getConfig()->get( 'PersonalDashboardDeveloperSetup' ) ) {
					throw $throwable;
				}
				Util::logException( $throwable, [ 'origin' => __METHOD__ ] );
			}
		}
		$out->addJsConfigVars( 'dashboardmodules', $data );

		if ( $mode === IModule::RENDER_MOBILE_SUMMARY ) {
			$out->addJsConfigVars( 'dashboardmobile', true );
			$out->addModules( 'ext.personalDashboard.mobile' );
			$out->addHTML( Html::rawElement(
				'div',
				[ 'class' => 'personal-dashboard-overlay-container' ],
				$html
			) );
		}
	}
}
