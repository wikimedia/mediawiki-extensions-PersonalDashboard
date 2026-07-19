<?php

namespace MediaWiki\Extension\PersonalDashboard;

interface IModule {
	/**
	 * Server-side platform the module frame is rendered for. This is the only
	 * rendering distinction the server and the client care about.
	 * The detail level within a platform (a compact summary versus the full
	 * view) is derived client-side and never reaches PHP.
	 */
	public const PLATFORM_DESKTOP = 'desktop';
	public const PLATFORM_MOBILE = 'mobile';

	/**
	 * Render the server-side card frame for the given platform.
	 *
	 * For an island module (the BaseModule default) this is the Codex-styled
	 * header, subheader, an empty mount slot, and footer; the body is filled in
	 * client-side once the module's Vue island loads. A server-rendered module
	 * emits its full body with no slot instead, either static and left untouched
	 * (a banner, a plain link) or progressively enhanced by a behavior module
	 * that adds interactivity to the server DOM.
	 *
	 * @param string $platform One of the PLATFORM_* constants
	 * @return string HTML for the card frame
	 */
	public function render( string $platform ): string;

	/**
	 * Client bootstrap data for this module, packed into wgPersonalDashboardGroups
	 * and keyed by module name.
	 *
	 * Carries only what the client needs to mount and coordinate: whether the
	 * module is enabled, whether it has an expandable full view, its header text,
	 * whether it is server-rendered, plus any module-specific keys. It does NOT
	 * carry body or footer HTML; those belong to render() now.
	 *
	 * @param string $platform One of the PLATFORM_* constants
	 * @return array
	 */
	public function getJsData( string $platform ): array;

	/**
	 * Override this function to provide JS config vars needed by this module.
	 *
	 * @return array
	 */
	public function getJsConfigVars();

	/**
	 * Whether this module supports the given platform. If this returns false,
	 * render() and getJsData() should not be called for that platform.
	 *
	 * @param string $platform One of the PLATFORM_* constants
	 * @return bool
	 */
	public function supports( string $platform ): bool;

	/**
	 * Sets the page base URL where the module is being rendered.
	 * Can be later used for generating links from inside the module.
	 * @param string $url base url
	 * @return void
	 */
	public function setPageURL( string $url );

	/**
	 * Sets the module name.
	 * @param string $name module name
	 * @return void
	 */
	public function setName( string $name );
}
