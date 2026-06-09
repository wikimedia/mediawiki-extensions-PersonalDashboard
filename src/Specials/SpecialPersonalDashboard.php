<?php

namespace MediaWiki\Extension\PersonalDashboard\Specials;

use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardModuleFactory;
use MediaWiki\Extension\PersonalDashboard\Util;
use MediaWiki\Html\Html;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\Utils\MWCryptRand;
use MediaWiki\WikiMap\WikiMap;
use Throwable;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\Stats\StatsFactory;

class SpecialPersonalDashboard extends SpecialPage {
	/**
	 * @var string Unique identifier for this specific rendering of Special:PersonalDashboard.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private string $pageviewToken;

	private bool $isMobile;

	public function __construct(
		private readonly PersonalDashboardModuleFactory $moduleFactory,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly StatsFactory $statsFactory,
	) {
		parent::__construct( 'PersonalDashboard' );
		$this->pageviewToken = $this->generatePageviewToken();
	}

	/** @inheritDoc */
	public function isListed() {
		return false;
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
		$this->isMobile = Util::isMobile( $out->getSkin() );

		$out->addModuleStyles( 'ext.personalDashboard.styles' );

		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikimediaEvents' ) ) {
			$out->addModules( [
				'ext.wikimediaEvents.personalDashboard'
			] );
		}

		$out->addJsConfigVars( [
			'wgPersonalDashboardPageviewToken' => $this->pageviewToken,
		] );

		$out->addHTML( Html::openElement( 'div', [
			'class' => 'personal-dashboard-container'
		] ) );

		$surveyLink = $this->createSurveyLinkBetaChip();

		if ( $surveyLink ) {
			if ( $this->isMobile ) {
				$out->addHTML( $surveyLink );
			} else {
				$out->setIndicators( [ 'mw-ext-personal-dashboard-survey' => $surveyLink ] );
			}
		}

		$modules = $this->getModules();

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
	 * @param array $moduleConfig
	 * @param IContextSource $context
	 * @return ?IModule
	 */
	private function getRequestedModule( array $moduleConfig, IContextSource $context ) {
		// $moduleConfig['enabled'] may be overriden by URL query param
		$moduleUrlParam = $this->getContext()->getRequest()->getText( $moduleConfig[ 'name' ] );
		if ( $moduleUrlParam !== '' ) {
			$moduleOverride = filter_var( $moduleUrlParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( $moduleOverride !== null ) {
				$moduleConfig['enabled'] = $moduleOverride;
			}
		}
		if ( !$moduleConfig || !array_key_exists( 'enabled', $moduleConfig ) || $moduleConfig['enabled'] !== true ) {
			return;
		}
		return $this->moduleFactory->getModule( $moduleConfig[ 'name' ], [ $context ] );
	}

	/**
	 * @return IModule[]
	 */
	private function getModules() {
		$modules = [];
		$context = $this->getContext();
		foreach ( $this->getModuleGroups()[ 'groups' ] as $groupConfig ) {
			foreach ( (array)$groupConfig[ 'subgroups' ] as $subGroup ) {
				foreach ( $subGroup[ 'modules' ] as $moduleConfig ) {
					/** @var ?IModule $module */
					$module = $this->getRequestedModule( $moduleConfig, $context );
					if ( !$module ) {
						continue;
					}
					$modules[ $moduleConfig[ 'name' ] ] = $module;
				}
			}
		}
		return $modules;
	}

	/**
	 * @param string $name key of registered module group in extension.json
	 * @return string[][][]
	 */
	private function getModuleGroups( $name = 'default' ): array {
		$registry = ExtensionRegistry::getInstance()->getAttribute( 'PersonalDashboardModuleGroups' );
		// $moduleGroup may be overriden by URL query param
		$nameOverride = $this->getContext()->getRequest()->getText( 'moduleGroup' );
		if ( $nameOverride !== '' ) {
			if ( array_key_exists( $nameOverride, $registry ) ) {
				return $registry[ $nameOverride ];
			}
		}
		return $registry[ $name ];
	}

	/**
	 * Returns 32-character random string.
	 * The token is used for client-side logging and can be retrieved on Special:PersonalDashboard via the
	 * wgPersonalDashboardPageviewToken JS variable.
	 * @return string
	 */
	private function generatePageviewToken() {
		return \Wikimedia\base_convert( MWCryptRand::generateHex( 40 ), 16, 32, 32 );
	}

	/**
	 * Create the survey link header HTML if the config value is set and valid
	 * and create an info chip that indicates that this extension is in Beta.
	 */
	public function createSurveyLinkBetaChip(): ?string {
		$surveyLink = $this->getConfig()->get( 'PersonalDashboardSurveyLink' );
		$url = $surveyLink ? $surveyLink . $this->getLanguage()->getCode() :
			'https://www.mediawiki.org/wiki/Talk:Moderator_Tools/Dashboard';

		$cdx = new Codex();
		$betaChip = $cdx->infoChip()
			->setStatus( 'notice' )
			->setIcon( 'personal-dashboard-survey-icon' )
			->setText( $this->msg( 'personal-dashboard-beta-info-chip-text' )->parse() )
			->build()
			->getHtml();

		return Html::rawElement(
			'div',
			[ 'class' => 'personal-dashboard-survey' ],
			$this->msg( 'personal-dashboard-survey-text', $url ) .
			$betaChip
		);
	}

	private function renderDesktop() {
		$out = $this->getContext()->getOutput();
		$out->addBodyClasses( 'personal-dashboard-desktop' );
		$context = $this->getContext();
		foreach ( $this->getModuleGroups()[ 'groups' ] as $group ) {
			$out->addHTML( Html::openElement( 'div', [
				'class' => "personal-dashboard-group-{$group[ 'name' ]}"
			] ) );
			foreach ( (array)$group[ 'subgroups' ] as $subGroup ) {
				$out->addHTML( Html::openElement( 'div', [
					'class' => "personal-dashboard-group-{$group[ 'name' ]}-subgroup-{$subGroup[ 'name' ]}"
				] ) );
				foreach ( $subGroup[ 'modules' ] as $moduleConfig ) {
					/** @var ?IModule $module */
					$module = $this->getRequestedModule( $moduleConfig, $context );
					if ( !$module ) {
						continue;
					}
					$startTime = microtime( true );

					$module->setPageURL( $this->getPageTitle()->getLinkURL() );
					$html = $this->getModuleRenderHtmlSafe( $module, IModule::RENDER_DESKTOP );
					$out->addHTML( $html );

					$this->recordModuleRenderingTime(
						$moduleConfig[ 'name' ],
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
		$modules = $this->getModules();
		$out->addBodyClasses( [
			'personal-dashboard-mobile-summary',
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
		$out->addModules( 'ext.personalDashboard.special.personalDashboard' );

		$data = [];
		foreach ( $modules as $moduleName => $module ) {
			try {
				$data[$moduleName] = $module->getJsData( $mode );
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
		}
	}
}
