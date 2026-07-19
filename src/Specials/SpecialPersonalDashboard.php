<?php

namespace MediaWiki\Extension\PersonalDashboard\Specials;

use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
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
		$platform = $this->isMobile ? IModule::PLATFORM_MOBILE : IModule::PLATFORM_DESKTOP;

		$out->addModules( 'ext.personalDashboard.special' );
		$out->addModuleStyles( 'ext.personalDashboard.styles' );

		$surveyLink = $this->createSurveyLinkBetaChip();

		if ( $surveyLink ) {
			if ( $this->isMobile ) {
				$out->addHTML( $surveyLink );
			} else {
				$out->setIndicators( [ 'mw-ext-personal-dashboard-survey' => $surveyLink ] );
			}
		}

		$groups = $this->getModuleGroups()['groups'];
		$modules = $this->getModules();

		// The Vue app mounts here and teleports each island into its server slot.
		$out->addHTML( Html::element( 'div', [ 'id' => 'personal-dashboard-root' ] ) );

		// A module name in $par focuses that single module as the whole page: the
		// real page a card's in-body link falls through to with no JS, whether a
		// mobile expandable card's anchor or a "see examples" link. With JS the
		// click is intercepted and the module opens in a dialog instead. An unknown
		// or unsupported module has no frame to show, so it falls through to the
		// full grouped dashboard.
		$focused = ( $par !== '' && isset( $modules[$par] )
			&& $modules[$par]->supports( $platform ) ) ? $modules[$par] : null;
		if ( $focused ) {
			$this->renderFocusedFrame( $platform, $par, $focused );
		} else {
			$this->renderGroupedFrames( $platform, $groups, $modules );
		}

		// Client bootstrap: per-module data the dashboard app mounts and routes from.
		foreach ( $groups as &$group ) {
			foreach ( $group['subgroups'] as &$subgroup ) {
				foreach ( $subgroup['modules'] as &$module ) {
					$resolved = $modules[ $module['name'] ] ?? null;
					$module['enabled'] = $resolved?->supports( $platform );

					if ( !$resolved || !$module['enabled'] ) {
						continue;
					}

					foreach ( $this->getModuleJsDataSafe( $resolved, $platform ) as $key => $value ) {
						$module[ $key ] = $value;
					}

					$out->addJsConfigVars( $resolved->getJsConfigVars() );
				}
			}
		}
		unset( $group, $subgroup, $module );

		$out->addJsConfigVars( [
			'wgPersonalDashboardGroups' => $groups,
			'wgPersonalDashboardPageviewToken' => $this->pageviewToken,
			// The rendering platform the server resolved from the skin. The client
			// consumes this rather than re-sniffing the skin, so server and client
			// render for the same platform.
			'wgPersonalDashboardPlatform' => $platform,
			// The module the server rendered as the whole page, or null for the
			// grouped dashboard. Only this module has a mount slot on a focused
			// render, so the dashboard app drops the other card islands.
			'wgPersonalDashboardFocusedModule' => $focused ? $par : null
		] );

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

	/**
	 * Emit the server-owned card frames, grouped into the layout the Vue app
	 * adopts. Each island frame carries an empty slot the client teleports into;
	 * server-rendered modules carry their full body.
	 *
	 * @param string $platform One of the IModule::PLATFORM_* constants
	 * @param array $groups Module group tree
	 * @param IModule[] $modules Resolved modules keyed by name
	 */
	private function renderGroupedFrames( string $platform, array $groups, array $modules ): void {
		$out = $this->getContext()->getOutput();
		$out->addBodyClasses( 'personal-dashboard-' . $platform );
		$out->addHTML( Html::openElement( 'div', [ 'class' => 'personal-dashboard-container' ] ) );
		foreach ( $groups as $group ) {
			$out->addHTML( Html::openElement( 'div', [
				'class' => "personal-dashboard-group-{$group[ 'name' ]}"
			] ) );
			foreach ( (array)$group[ 'subgroups' ] as $subGroup ) {
				$out->addHTML( Html::openElement( 'div', [
					'class' => "personal-dashboard-group-{$group[ 'name' ]}-subgroup-{$subGroup[ 'name' ]}"
				] ) );
				foreach ( $subGroup[ 'modules' ] as $moduleConfig ) {
					$module = $modules[ $moduleConfig[ 'name' ] ] ?? null;
					if ( $module && $module->supports( $platform ) ) {
						$this->emitModuleFrame( $platform, $moduleConfig[ 'name' ], $module );
					}
				}
				$out->addHTML( Html::closeElement( 'div' ) );
			}
			$out->addHTML( Html::closeElement( 'div' ) );
		}
		$out->addHTML( Html::closeElement( 'div' ) );
		$this->emitNoJsNotice();
	}

	/**
	 * Render a single module as the whole page: the real page an expandable
	 * card's anchor falls through to when JS is off.
	 *
	 * @param string $platform One of the IModule::PLATFORM_* constants
	 * @param string $name Module name
	 * @param IModule $module Resolved module
	 */
	private function renderFocusedFrame( string $platform, string $name, IModule $module ): void {
		$out = $this->getContext()->getOutput();
		$out->addBodyClasses( [ 'personal-dashboard-' . $platform, 'personal-dashboard-focused' ] );
		$out->addHTML( Html::openElement( 'div', [ 'class' => 'personal-dashboard-container' ] ) );
		// A progressively enhanced module renders its deep no-JS content only when
		// it is the whole focused page, not in its dashboard card.
		if ( $module instanceof BaseModule ) {
			$module->setFocused( true );
		}
		$this->emitModuleFrame( $platform, $name, $module );
		$out->addHTML( Html::closeElement( 'div' ) );
		$this->emitNoJsNotice();
	}

	/**
	 * A page-level noscript notice explaining the empty card bodies. Island
	 * bodies only fill in once the client mounts, so a no-JS visitor otherwise
	 * sees bordered cards with nothing in them and no reason why.
	 */
	private function emitNoJsNotice(): void {
		$this->getOutput()->addHTML( Html::rawElement( 'noscript', [],
			Html::element( 'p', [ 'class' => 'personal-dashboard-no-js-notice' ],
				$this->msg( 'personal-dashboard-module-no-js-fallback' )->text()
			)
		) );
	}

	/**
	 * Render one module's frame into the page and record its timing.
	 *
	 * @param string $platform One of the IModule::PLATFORM_* constants
	 * @param string $name Module name
	 * @param IModule $module Resolved module
	 */
	private function emitModuleFrame( string $platform, string $name, IModule $module ): void {
		$startTime = microtime( true );
		$module->setPageURL( $this->getPageTitle()->getLinkURL() );
		$this->getOutput()->addHTML( $this->getModuleRenderHtmlSafe( $module, $platform ) );
		$this->recordModuleRenderingTime( $name, $platform, microtime( true ) - $startTime );
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

	/**
	 * Get the module render HTML for a platform, catching exceptions by default.
	 *
	 * If PersonalDashboardDeveloperSetup is on, then throw the exceptions.
	 * @param IModule $module
	 * @param string $platform One of the IModule::PLATFORM_* constants
	 * @throws Throwable
	 * @return string
	 */
	private function getModuleRenderHtmlSafe( IModule $module, string $platform ): string {
		$html = '';
		try {
			$html = $module->render( $platform );
		} catch ( Throwable $throwable ) {
			if ( $this->getConfig()->get( 'PersonalDashboardDeveloperSetup' ) ) {
				throw $throwable;
			}
			Util::logException( $throwable, [ 'origin' => __METHOD__ ] );
		}
		return $html;
	}

	/**
	 * Get the module's getJsData() result, catching exceptions by default.
	 *
	 * If PersonalDashboardDeveloperSetup is on, then throw the exceptions.
	 * @param IModule $module
	 * @param string $platform One of the IModule::PLATFORM_* constants
	 * @throws Throwable
	 * @return array
	 */
	private function getModuleJsDataSafe( IModule $module, string $platform ): array {
		try {
			return $module->getJsData( $platform );
		} catch ( Throwable $throwable ) {
			if ( $this->getConfig()->get( 'PersonalDashboardDeveloperSetup' ) ) {
				throw $throwable;
			}
			Util::logException( $throwable, [ 'origin' => __METHOD__ ] );
			return [];
		}
	}
}
