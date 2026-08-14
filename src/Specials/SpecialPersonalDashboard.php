<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Specials;

use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\PersonalDashboard\ExperimentResolver;
use MediaWiki\Extension\PersonalDashboard\Experiments;
use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardModuleFactory;
use MediaWiki\Extension\PersonalDashboard\Util;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\Html\Html;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Utils\MWCryptRand;
use MediaWiki\WikiMap\WikiMap;
use Throwable;
use Wikimedia\Codex\Localization\MediaWikiLocalization;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\Stats\StatsFactory;

class SpecialPersonalDashboard extends SpecialPage {
	/** @var Codex Shared Codex-PHP instance used by beta chip and no-js message */
	private Codex $codex;

	/**
	 * @var string Unique identifier for this specific rendering of Special:PersonalDashboard.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private string $pageviewToken;

	/** @var string Device label ('mobile'/'desktop') for the SSR timing metrics; analytics only. */
	private string $device;

	/** Per-request memo of getModuleGroups()'s result; both call sites use the same $name. */
	private ?array $resolvedModuleGroup = null;

	/** Per-request memo of getModuleGroups()'s resolved registry key (e.g. 'default', 'T426615'). */
	private ?string $resolvedModuleGroupName = null;

	/** Per-request memo of experiment name => assigned variant, for every assignment that took effect. */
	private array $resolvedExperimentVariants = [];

	public function __construct(
		private readonly PersonalDashboardModuleFactory $moduleFactory,
		private readonly StatsFactory $statsFactory,
		private readonly ?ExperimentManagerInterface $experimentManager = null,
	) {
		parent::__construct( 'PersonalDashboard' );
		$this->codex = new Codex( new MediaWikiLocalization( $this->getContext() ) );
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
		// Retained for analytics labels only: the rendered frame no longer varies by
		// device, but both SSR timing metrics keep their device dimension.
		$this->device = Util::isMobile() ? 'mobile' : 'desktop';

		$out->addModules( 'ext.personalDashboard.special' );
		$out->addModuleStyles( 'ext.personalDashboard.styles' );

		$surveyLink = $this->createSurveyLinkBetaChip();

		if ( $surveyLink ) {
			if ( $out->getSkin()->getSkinName() === 'minerva' ) {
				$out->addHTML( $surveyLink );
			} else {
				$out->setIndicators( [ 'mw-ext-personal-dashboard-survey' => $surveyLink ] );
			}
		}

		$groups = $this->getModuleGroups()['groups'];
		$modules = $this->getModules();

		// The Vue app mounts here and teleports each island into its server slot.
		$out->addHTML( Html::element( 'div', [ 'id' => 'personal-dashboard-root' ] ) );

		// Client bootstrap: per-module data the dashboard app mounts and routes from.
		foreach ( $groups as &$group ) {
			foreach ( $group['subgroups'] as &$subgroup ) {
				foreach ( $subgroup['modules'] as &$module ) {
					$resolved = $modules[ $module['name'] ] ?? null;

					if ( !$resolved ) {
						$module['enabled'] = false;
						continue;
					}

					$module['enabled'] = $enabled = $resolved->supports();

					if ( !$enabled ) {
						continue;
					}

					if ( $resolved instanceof BaseModule ) {
						$resolved->setStyles( $module['style'] ?? 'default',
							$module['styleMobile'] ?? 'default' );
					}

					foreach ( $this->getModuleJsDataSafe( $resolved ) as $key => $value ) {
						$module[ $key ] = $value;
					}

					$out->addJsConfigVars( $resolved->getJsConfigVars() );
				}
			}
		}

		$this->emitNoJsNotice();

		// The first subpage segment names a module. A bare module name is the
		// isolated focused page: the real page a card's in-body link falls through
		// to with no JS (a "see examples" link). A deeper subpath instead opens that
		// module in place within the full dashboard, so the URL composes the whole
		// page around the deep-linked state (the right policy, the right example)
		// rather than showing the module alone; the module renders what it owns
		// server-side and the client router owns anything deeper. An unknown module,
		// or a subpath behind one that reads none, falls through to the plain grouped
		// dashboard.
		[ $moduleName, $subPath ] = array_pad( explode( '/', $par ?? '', 2 ), 2, '' );
		$matched = ( $moduleName !== '' && isset( $modules[$moduleName] )
			&& $modules[$moduleName]->supports() ) ? $modules[$moduleName] : null;
		$isolated = $matched !== null && $subPath === '';

		if ( $isolated ) {
			$this->renderFocusedFrame( $moduleName, $matched );
		} else {
			if ( $matched instanceof BaseModule && $subPath !== ''
				&& $matched->acceptsFocusedSubPath()
			) {
				$matched->setFocusedSubPath( $subPath );
			}

			$this->renderGroupedFrames( $groups );
		}

		unset( $group, $subgroup, $module );

		$out->addJsConfigVars( [
			'wgPersonalDashboardGroups' => $groups,
			'wgPersonalDashboardPageviewToken' => $this->pageviewToken,
			// The module rendered as the isolated whole page, or null for a grouped
			// render (a deep subpath composes the full dashboard, so it is grouped
			// too). Only an isolated render has a single module's slot, so the app
			// drops the other card islands; a grouped render keeps them all.
			'wgPersonalDashboardFocusedModule' => $isolated ? $moduleName : null,
			'wgPersonalDashboardModuleGroup' => $this->resolvedModuleGroupName,
			// Cast to object: an empty PHP array JSON-encodes as [], but the
			// unenrolled case (most requests) needs {} on the wire.
			'wgPersonalDashboardExperimentVariants' => (object)$this->resolvedExperimentVariants,
		] );

		$overallSsrTimeInSeconds = microtime( true ) - $startTime;
		$this->statsFactory->withComponent( 'PersonalDashboard' )
			->getTiming( 'special_dashboard_server_side_render_seconds' )
			->setLabel( 'platform', $this->device )
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
	private function getRequestedModule( array $moduleConfig, IContextSource $context ): ?IModule {
		// $moduleConfig['enabled'] may be overriden by URL query param
		$moduleUrlParam = $this->getContext()->getRequest()->getText( $moduleConfig[ 'name' ] );
		if ( $moduleUrlParam !== '' ) {
			$moduleOverride = filter_var( $moduleUrlParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( $moduleOverride !== null ) {
				$moduleConfig['enabled'] = $moduleOverride;
			}
		}
		if ( !$moduleConfig || !array_key_exists( 'enabled', $moduleConfig ) || $moduleConfig['enabled'] !== true ) {
			return null;
		}
		return $this->moduleFactory->getModule( $moduleConfig[ 'name' ], [ $context ] );
	}

	/**
	 * @return IModule[]
	 */
	private function getModules(): array {
		$modules = [];
		$context = $this->getContext();
		foreach ( $this->getModuleGroups()[ 'groups' ] as $groupConfig ) {
			foreach ( (array)$groupConfig[ 'subgroups' ] as $subGroup ) {
				foreach ( $subGroup[ 'modules' ] as $moduleConfig ) {
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
	private function getModuleGroups( string $name = 'default' ): array {
		// Resolving once per request avoids redundant experiment resolution
		// (and a duplicate sendExposure() call for the experiment that wins).
		if ( $this->resolvedModuleGroup !== null ) {
			return $this->resolvedModuleGroup;
		}

		$registry = ExtensionRegistry::getInstance()->getAttribute( 'PersonalDashboardModuleGroups' );

		$resolver = new ExperimentResolver(
			$this->experimentManager,
			$this->statsFactory,
			Experiments::all(),
			$registry
		);
		$resolution = $resolver->resolve();
		$resolution->sendExposures();
		$this->resolvedExperimentVariants = $resolution->getVariants();

		$experimentGroup = $resolution->getModuleGroup();
		if ( $experimentGroup !== null ) {
			$this->resolvedModuleGroupName = $experimentGroup;
			$this->resolvedModuleGroup = $registry[ $experimentGroup ];
			return $this->resolvedModuleGroup;
		}

		// $moduleGroup may be overriden by URL query param
		$nameOverride = $this->getContext()->getRequest()->getText( 'moduleGroup' );
		if ( $nameOverride !== '' ) {
			if ( array_key_exists( $nameOverride, $registry ) ) {
				$this->resolvedModuleGroupName = $nameOverride;
				$this->resolvedModuleGroup = $registry[ $nameOverride ];
				return $this->resolvedModuleGroup;
			}
		}
		$this->resolvedModuleGroupName = $name;
		$this->resolvedModuleGroup = $registry[ $name ];
		return $this->resolvedModuleGroup;
	}

	/**
	 * Returns 32-character random string.
	 * The token is used for client-side logging and can be retrieved on Special:PersonalDashboard via the
	 * wgPersonalDashboardPageviewToken JS variable.
	 * @return string
	 */
	private function generatePageviewToken(): string {
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

		$betaChip = $this->codex
			->infoChip()
			->setStatus( 'notice' )
			->setIcon( 'personal-dashboard-survey-icon' )
			->setText( $this->msg( 'personal-dashboard-beta-info-chip-text' )->parse() )
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
	 * @param array $groups Module group tree
	 */
	private function renderGroupedFrames( array $groups ): void {
		$ctx = $this->getContext();
		$out = $ctx->getOutput();

		// The viewport wraps the container as its container-query context so modern
		// browsers stack the two columns to one from CSS at first paint, no flash.
		// The observer in init.js is the fallback where @container is unsupported.
		$out->addHTML( Html::openElement( 'div', [ 'class' => 'personal-dashboard-viewport' ] ) );
		$out->addHTML( Html::openElement( 'div', [ 'class' => 'personal-dashboard-container' ] ) );

		foreach ( $groups as $group ) {
			$out->addHTML( Html::openElement( 'div', [
				// The following CSS classes are used here:
				// * personal-dashboard-group-utils
				// * personal-dashboard-group-main
				// * personal-dashboard-group-sidebar
				'class' => "personal-dashboard-group-{$group[ 'name' ]}"
			] ) );

			foreach ( (array)$group[ 'subgroups' ] as $subGroup ) {
				$modules = array_filter( $subGroup['modules'], static fn ( $module ) => $module['enabled'] );

				$out->addHTML( Html::openElement( 'div', [
					// The following CSS classes are used here:
					// * personal-dashboard-group-utils-subgroup-startup
					// * personal-dashboard-group-main-subgroup-primary
					// * personal-dashboard-group-sidebar-subgroup-primary
					// * personal-dashboard-group-sidebar-subgroup-secondary
					'class' => "personal-dashboard-group-{$group[ 'name' ]}-subgroup-{$subGroup[ 'name' ]}",
					'style' => $modules ? null : 'display: none;'
				] ) );

				foreach ( $modules as $module ) {
					$resolved = $this->getRequestedModule( $module, $ctx );

					if ( $resolved ) {
						$this->emitModuleFrame( $module[ 'name' ], $resolved );
					}
				}

				$out->addHTML( Html::closeElement( 'div' ) );
			}

			$out->addHTML( Html::closeElement( 'div' ) );
		}

		$out->addHTML( Html::closeElement( 'div' ) . Html::closeElement( 'div' ) );
	}

	/**
	 * Render a single module as the whole page: the isolated focused-page view a
	 * module gets on its own subpath.
	 *
	 * @param string $name Module name
	 * @param IModule $module Resolved module
	 */
	private function renderFocusedFrame( string $name, IModule $module ): void {
		$out = $this->getContext()->getOutput();
		$out->addBodyClasses( 'personal-dashboard-focused' );
		// Same viewport wrapper as the grouped frames so the container query applies
		// here too, though a lone focused module never has a second column to drop.
		$out->addHTML( Html::openElement( 'div', [ 'class' => 'personal-dashboard-viewport' ] ) );
		$out->addHTML( Html::openElement( 'div', [ 'class' => 'personal-dashboard-container' ] ) );
		// A progressively enhanced module renders its deep no-JS content only when
		// it is the whole focused page, not in its dashboard card. The back link
		// is page navigation, so the page owns it and hands it to the module's
		// header rather than each module minting its own.
		if ( $module instanceof BaseModule ) {
			$module->setFocused( true );
			$module->setBackLink( $this->buildBackLink() );
		}
		$this->emitModuleFrame( $name, $module );
		$out->addHTML( Html::closeElement( 'div' ) . Html::closeElement( 'div' ) );
	}

	/**
	 * A link back to the grouped dashboard, rendered in the header of a focused
	 * whole-page render so the page is not a dead end. Owned by the page rather
	 * than minted per module, so a headerless module gets a way back too.
	 */
	private function buildBackLink(): string {
		return Html::element( 'a', [
			'href' => $this->getPageTitle()->getLinkURL(),
			'class' => 'personal-dashboard-module-header-back-icon',
			'aria-label' => $this->msg( 'personal-dashboard-back-to-dashboard' )->text(),
		] );
	}

	/**
	 * A page-level noscript notice explaining the empty card bodies. Island
	 * bodies only fill in once the client mounts, so a no-JS visitor otherwise
	 * sees bordered cards with nothing in them and no reason why.
	 */
	private function emitNoJsNotice(): void {
		$this->getOutput()->addHTML( $this->codex
			->message()
			->setType( 'warning' )
			->setContent( $this->msg( 'personal-dashboard-module-no-js-fallback' )->text() )
			->setAttributes( [ 'class' => 'personal-dashboard-js-warning' ] )
			->getHtml() );
	}

	/**
	 * Render one module's frame into the page and record its timing.
	 *
	 * @param string $name Module name
	 * @param IModule $module Resolved module
	 */
	private function emitModuleFrame( string $name, IModule $module ): void {
		$startTime = microtime( true );
		$module->setPageURL( $this->getPageTitle()->getLinkURL() );
		$this->getOutput()->addHTML( $this->getModuleRenderHtmlSafe( $module ) );
		$this->recordModuleRenderingTime( $name, microtime( true ) - $startTime );
	}

	private function recordModuleRenderingTime( string $moduleName, float $timeToRecordInSeconds ): void {
		$wiki = WikiMap::getCurrentWikiId();
		$this->statsFactory->withComponent( 'PersonalDashboard' )
			->getTiming( 'special_dashboard_ssr_per_module_seconds' )
			->setLabel( 'wiki', $wiki )
			->setLabel( 'module', $moduleName )
			->setLabel( 'mode', $this->device )
			->observeSeconds( $timeToRecordInSeconds );
	}

	/**
	 * Get the module render HTML, catching exceptions by default.
	 *
	 * If PersonalDashboardDeveloperSetup is on, then throw the exceptions.
	 * @param IModule $module
	 * @throws Throwable
	 * @return string
	 */
	private function getModuleRenderHtmlSafe( IModule $module ): string {
		try {
			return $module->render();
		} catch ( Throwable $throwable ) {
			if ( $this->getConfig()->get( 'PersonalDashboardDeveloperSetup' ) ) {
				throw $throwable;
			}

			Util::logException( $throwable, [ 'origin' => __METHOD__ ] );
		}

		return '';
	}

	/**
	 * Get the module's getJsData() result, catching exceptions by default.
	 *
	 * If PersonalDashboardDeveloperSetup is on, then throw the exceptions.
	 * @param IModule $module
	 * @throws Throwable
	 * @return array
	 */
	private function getModuleJsDataSafe( IModule $module ): array {
		try {
			return $module->getJsData();
		} catch ( Throwable $throwable ) {
			if ( $this->getConfig()->get( 'PersonalDashboardDeveloperSetup' ) ) {
				throw $throwable;
			}
			Util::logException( $throwable, [ 'origin' => __METHOD__ ] );
			return [];
		}
	}
}
