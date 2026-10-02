<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Specials;

use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\PersonalDashboard\ExperimentResolver;
use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Extension\PersonalDashboard\Util;
use MediaWiki\Html\Html;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Utils\MWCryptRand;
use MediaWiki\WikiMap\WikiMap;
use Throwable;
use Wikimedia\Codex\Localization\MediaWikiLocalization;
use Wikimedia\Codex\Utility\Codex;

/**
 * A dashboard served at a special page.
 *
 * Subclass this to serve a dashboard at a page your own extension owns. Name
 * the single service `PersonalDashboardPageDependencies` in your
 * extension.json, and answer the configuration questions below.
 *
 * The extension points are these accessors, and only these:
 *
 *   - getPageName(): the special page this dashboard lives at.
 *   - getBaselineModuleGroup(): the registered module group to render.
 *   - getStatsComponent(): the StatsFactory component the timings go to.
 *   - getBackLinkTarget(): where a focused module's back link returns to.
 *   - getExperimentManifest(): the TestKitchen experiments that may reroute
 *     this dashboard. Return [] if it has none.
 *
 * An accessor must be pure configuration. getPageName() runs from the
 * constructor, so no accessor can read the context, the config, or subclass
 * state.
 *
 * execute() is final and every render helper is private. The render sequence
 * is not a contract, and Personal Dashboard changes it without notice. See
 * ../../docs/decisions.md and ../../docs/modules.md.
 *
 * @stable to extend
 */
abstract class AbstractSpecialDashboard extends SpecialPage {
	/** @var Codex Shared Codex-PHP instance used by beta chip and no-js message */
	private Codex $codex;

	/**
	 * @var string Unique identifier for this specific rendering of the dashboard.
	 * Used by various EventLogging schemas to correlate events.
	 */
	private string $pageviewToken;

	/** @var string Device label ('mobile'/'desktop') for the SSR timing metrics; analytics only. */
	private string $device;

	/** Per-request memo of getModuleGroups()'s result. */
	private ?array $resolvedModuleGroup = null;

	/** Per-request memo of getModuleGroups()'s resolved registry key (e.g. 'default', 'review-changes-home'). */
	private ?string $resolvedModuleGroupName = null;

	/** Per-request memo of experiment name => assigned variant, for every assignment that took effect. */
	private array $resolvedExperimentVariants = [];

	/** Per-request memo of whether the pdo ModuleGroup override resolved this request's module group. */
	private bool $pdoOverrideActive = false;

	public function __construct(
		private readonly DashboardPageDependencies $dependencies,
	) {
		// getPageName() runs before this constructor's body, and before a
		// subclass constructor's body too. That is safe because an accessor
		// returns configuration and never reads state; see the class docblock.
		parent::__construct( $this->getPageName() );
		$this->codex = new Codex( new MediaWikiLocalization( $this->getContext() ) );
		$this->pageviewToken = $this->generatePageviewToken();
	}

	/**
	 * The special page this dashboard is served at, as SpecialPage names it.
	 * Also give the page an alias in your extension's alias file, or
	 * SpecialPageFactory warns on every request.
	 */
	abstract protected function getPageName(): string;

	/**
	 * The module group this dashboard renders, as registered under the
	 * `PersonalDashboard.ModuleGroups` attribute. An experiment or the `pdo`
	 * dev override can still replace it for one request.
	 *
	 * This is deliberately abstract. A dashboard that silently fell through to
	 * Personal Dashboard's own group would serve another team's modules.
	 */
	abstract protected function getBaselineModuleGroup(): string;

	/**
	 * The StatsFactory component this dashboard's server-side timings go to.
	 * Give a dashboard its own component to tell its timings apart in Grafana.
	 *
	 * @stable to override
	 */
	protected function getStatsComponent(): string {
		return 'PersonalDashboard';
	}

	/**
	 * Where the back link of a focused module returns to. Defaults to this
	 * dashboard. Override it to send the user somewhere else, for example the
	 * page the dashboard is reached from.
	 *
	 * @stable to override
	 */
	protected function getBackLinkTarget(): Title {
		return $this->getPageTitle();
	}

	/**
	 * The TestKitchen experiments that can reroute this dashboard to a
	 * different module group, in the shape Experiments::all() returns.
	 *
	 * Defaults to no experiments. A manifest names module groups, and a module
	 * group belongs to one dashboard, so a dashboard only ever declares its
	 * own experiments; an inherited manifest would route users to another
	 * team's modules. Personal Dashboard's own experiments are declared by
	 * SpecialPersonalDashboard. See ../../docs/experiments.md.
	 *
	 * @return array<string, array<string, string>>
	 * @stable to override
	 */
	protected function getExperimentManifest(): array {
		return [];
	}

	/**
	 * Whether this dashboard requires a logged-in user. Defaults to true.
	 *
	 * @return bool
	 * @stable to override
	 */
	protected function requiresNamedUser(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 * @param string $par
	 * @throws ConfigException
	 * @throws ErrorPageError
	 * @throws UserNotLoggedIn
	 */
	final public function execute( $par = '' ) {
		$startTime = microtime( true );
		if ( $this->requiresNamedUser() ) {
			$this->requireNamedUser();
		}
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
			$this->emitNoJsNotice();

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
			'wgPersonalDashboardPdoActive' => $this->pdoOverrideActive,
		] );

		$overallSsrTimeInSeconds = microtime( true ) - $startTime;
		$this->dependencies->statsFactory->withComponent( $this->getStatsComponent() )
			->getTiming( 'special_dashboard_server_side_render_seconds' )
			->setLabel( 'platform', $this->device )
			->observeSeconds( $overallSsrTimeInSeconds );
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
		return $this->dependencies->moduleFactory->getModule( $moduleConfig[ 'name' ], [ $context ] );
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
	 * Resolve this request's module group: a TestKitchen enrollment first, then
	 * the `pdo` dev override, then this dashboard's baseline group registered
	 * in extension.json.
	 */
	private function getModuleGroups(): array {
		// Resolving once per request avoids redundant experiment resolution
		// (and a duplicate sendExposure() call for the experiment that wins).
		if ( $this->resolvedModuleGroup !== null ) {
			return $this->resolvedModuleGroup;
		}

		$registry = ExtensionRegistry::getInstance()->getAttribute( 'PersonalDashboardModuleGroups' );

		$resolver = new ExperimentResolver(
			$this->dependencies->experimentManager,
			$this->dependencies->statsFactory,
			$this->getExperimentManifest(),
			$registry
		);
		$resolution = $resolver->resolve();

		$experimentGroup = $resolution->getModuleGroup();
		if ( $experimentGroup !== null ) {
			$resolution->sendExposures();
			$this->resolvedExperimentVariants = $resolution->getVariants();
			$this->resolvedModuleGroupName = $experimentGroup;
			$this->resolvedModuleGroup = $registry[ $experimentGroup ];
			return $this->resolvedModuleGroup;
		}

		if ( $this->getConfig()->get( 'PersonalDashboardAllowOverride' ) ) {
			$pdoGroup = $this->resolvePdoOverride( $registry );
			if ( $pdoGroup !== null ) {
				/*
				 * pdo takes precedence over a non-overriding assignment (control,
				 * or a tag-only experiment): no exposure fires for those
				 * assignments, since pdo, not the experiment, decided what this
				 * user sees.
				 */
				$this->resolvedModuleGroupName = $pdoGroup;
				$this->resolvedModuleGroup = $registry[ $pdoGroup ];
				$this->pdoOverrideActive = true;
				return $this->resolvedModuleGroup;
			}
		}

		$resolution->sendExposures();
		$this->resolvedExperimentVariants = $resolution->getVariants();
		$baseline = $this->getBaselineModuleGroup();

		if ( !array_key_exists( $baseline, $registry ) ) {
			/*
			 * The dashboard names a group this wiki does not have: the
			 * extension that registers it is not enabled here, or a deploy
			 * dropped the group. Render an empty dashboard rather than fatal,
			 * the same way a group that names an unregistered module degrades
			 * to a placeholder card; see ../../docs/decisions.md.
			 */
			Util::logText(
				'Dashboard baseline module group is not registered',
				[
					'page' => $this->getName(),
					'moduleGroup' => $baseline,
					'origin' => __METHOD__,
				]
			);
			$this->resolvedModuleGroupName = $baseline;
			$this->resolvedModuleGroup = [ 'groups' => [] ];
			return $this->resolvedModuleGroup;
		}

		$this->resolvedModuleGroupName = $baseline;
		$this->resolvedModuleGroup = $registry[ $baseline ];
		return $this->resolvedModuleGroup;
	}

	/**
	 * Resolve the `pdo` ModuleGroup override: a URL param (`?pdo=`)
	 * or a fallback cookie, either naming a registered module group. The URL
	 * param also (re)sets the cookie for the rest of the browser session, so a
	 * single tagged link keeps routing a QA session on reload. Only called once
	 * real experiment enrollment (which always wins) has already returned null.
	 *
	 * The cookie is suffixed with the page name, to avoid collision with
	 * another dashboard.
	 *
	 * @param array $registry Registered module groups, keyed by ID
	 * @return ?string Module group ID, or null if no valid override applies
	 */
	private function resolvePdoOverride( array $registry ): ?string {
		$request = $this->getContext()->getRequest();
		$cookieName = 'pdo-' . $this->getName();

		$pdo = $request->getText( 'pdo' );
		$fromUrlParam = $pdo !== '';

		if ( !$fromUrlParam ) {
			$pdo = $request->getCookie( $cookieName ) ?? '';
		}

		if ( $pdo === '' || !array_key_exists( $pdo, $registry ) ) {
			return null;
		}

		if ( $fromUrlParam ) {
			/*
			 * A null expiry is what gets a session cookie; 0 would mean
			 * $wgCookieExpiration, pinning anyone who follows a shared `?pdo=` link
			 * to that module group (and out of the event stream) for a month.
			 * httpOnly => false is cheap insurance rather than a functional need:
			 * the cookie carries only a module-group ID, and nothing client-side
			 * reads it today (see wgPersonalDashboardPdoActive below).
			 */
			$request->response()->setCookie( $cookieName, $pdo, null, [ 'httpOnly' => false ] );
		}

		return $pdo;
	}

	/**
	 * Returns 32-character random string.
	 * The token is used for client-side logging and can be retrieved on the dashboard
	 * via the wgPersonalDashboardPageviewToken JS config variable.
	 *
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
		// The narrow-viewport overlay covers everything outside this wrapper, so the
		// notice has to render inside it here or a no-JS visitor never sees it.
		$this->emitNoJsNotice();
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
			'href' => $this->getBackLinkTarget()->getLinkURL(),
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
		// getPageTitle(), not getBackLinkTarget(): this is the route base each
		// module appends its own name to for its focused subpage, so it must
		// stay on this dashboard even where the back link points elsewhere.
		$module->setPageURL( $this->getPageTitle()->getLinkURL() );
		$this->getOutput()->addHTML( $this->getModuleRenderHtmlSafe( $module ) );
		$this->recordModuleRenderingTime( $name, microtime( true ) - $startTime );
	}

	private function recordModuleRenderingTime( string $moduleName, float $timeToRecordInSeconds ): void {
		$wiki = WikiMap::getCurrentWikiId();
		$this->dependencies->statsFactory->withComponent( $this->getStatsComponent() )
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
