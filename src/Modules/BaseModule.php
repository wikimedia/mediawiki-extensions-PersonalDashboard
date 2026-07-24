<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\User\User;
use Wikimedia\Message\MessageSpecifier;

/**
 * BaseModule is a base class for personaldashboard modules.
 * It provides utilities and a default structure (header, subheader, body, footer).
 */
abstract class BaseModule implements IModule {

	protected const BASE_CSS_CLASS = 'personal-dashboard-module';
	protected const MODULE_STATE_COMPLETE = 'complete';
	protected const MODULE_STATE_INCOMPLETE = 'incomplete';
	protected const MODULE_STATE_ACTIVATED = 'activated';
	protected const MODULE_STATE_UNACTIVATED = 'unactivated';
	protected const MODULE_STATE_UNCONFIRMED = 'unconfirmed';
	protected const MODULE_STATE_NOTRENDERED = 'notrendered';

	/**
	 * @var bool
	 */
	private $shouldWrapModuleWithLink;

	/**
	 * @var string
	 */
	private $pageURL = null;

	/**
	 * @var bool Whether the module is rendering as the whole focused page.
	 */
	private bool $focused = false;

	/**
	 * @var string The focused page's deep route below the module name (a policy,
	 * an example), or empty. A progressively enhanced module reads it to open to
	 * the matching content server-side; the client router owns anything deeper.
	 */
	private string $focusedSubPath = '';

	/**
	 * @var string Page-provided link back to the dashboard, rendered in the header
	 * of a focused whole-page render. Empty on a grouped card.
	 */
	private string $backLink = '';

	/**
	 * @param IContextSource $context
	 * @param bool $shouldWrapModuleWithLink
	 */
	public function __construct(
		IContextSource $context,
		bool $shouldWrapModuleWithLink = false,
	) {
		$this->context = $context;
		$this->shouldWrapModuleWithLink = $shouldWrapModuleWithLink;
	}

	/**
	 * Sets the page base URL where the module is being rendered.
	 * Can be later used for generating links from inside the module.
	 */
	public function setPageURL( string $url ): void {
		$this->pageURL = $url;
	}

	/** @inheritDoc */
	public function setName( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Mark this module as rendering the focused page: the whole-page view a single
	 * module gets on its own subpath. A progressively enhanced module uses this to
	 * emit deep content inline for no-JS visitors that it otherwise leaves to a
	 * client-side dialog.
	 */
	public function setFocused( bool $focused ): void {
		$this->focused = $focused;
	}

	/**
	 * @return bool Whether the module is rendering as the whole focused page.
	 */
	protected function isFocused(): bool {
		return $this->focused;
	}

	/**
	 * Set the focused page's deep route below the module name. The special page
	 * parses it off the subpage and hands it in; the module opens to the matching
	 * content for a no-JS visitor.
	 */
	public function setFocusedSubPath( string $subPath ): void {
		$this->focusedSubPath = $subPath;
	}

	/**
	 * @return string The focused page's deep route below the module name, or
	 * empty. The no-JS content a progressively enhanced module opens to; the
	 * client router owns anything deeper.
	 */
	protected function getFocusedSubPath(): string {
		return $this->focusedSubPath;
	}

	/**
	 * @return bool Whether this module reads a focused subpath (a deep route below
	 * its name). Default false: behind any other module a trailing path is
	 * meaningless, so the special page lets such a URL fall through to the grouped
	 * dashboard. A module that renders deep server-side content overrides to true.
	 */
	public function acceptsFocusedSubPath(): bool {
		return false;
	}

	/**
	 * Set the link back to the dashboard for a focused render. The special page
	 * owns this control (it is page navigation, not module content) and hands it
	 * in here so it renders inside the module's header, even for a headerless
	 * module that would otherwise leave the visitor with no way back.
	 */
	public function setBackLink( string $html ): void {
		$this->backLink = $html;
	}

	/**
	 * @return string The header for a focused whole-page render: the page-provided
	 * back link, then the module's own header so the focused view matches its card
	 * (including any icon or enrichment getHeader() adds). A headerless module (its
	 * title is empty) still gets the back link with no stray empty header.
	 */
	protected function getFocusedHeader(): string {
		return $this->backLink . ( $this->getHeaderText() ? $this->getHeader() : '' );
	}

	/**
	 * Gets the base page URL where the module is being rendered.
	 * @return string|null
	 */
	public function getPageURL(): ?string {
		return $this->pageURL;
	}

	/**
	 * Gets whether the module will be wrapped in a link to its
	 * full screen view or not
	 */
	public function shouldWrapModuleWithLink(): bool {
		return $this->shouldWrapModuleWithLink;
	}

	/** @var string Name of the module */
	protected string $name;

	protected IContextSource $context;

	final protected function getContext(): IContextSource {
		$this->context ??= RequestContext::getMain();
		return $this->context;
	}

	/**
	 * Override default context
	 */
	final public function setContext( IContextSource $context ): void {
		$this->context = $context;
	}

	/**
	 * Get current user
	 *
	 * Short for $this->getContext()->getUser().
	 */
	final protected function getUser(): User {
		return $this->getContext()->getUser();
	}

	/**
	 * Shortcut to get main config object
	 *
	 * Short for $this->getContext()->getConfig().
	 */
	final protected function getConfig(): Config {
		return $this->getContext()->getConfig();
	}

	/** @inheritDoc */
	final protected function getName(): string {
		return $this->name;
	}

	/**
	 * Whether the module can be rendered or not.
	 * When this returns false, callers should never attempt to render the module.
	 * @return bool
	 */
	protected function canRender() {
		return true;
	}

	/**
	 * Whether the module is supposed to be present on the homepage.
	 * When canRender() is true but shouldRender() is false, the module should not be displayed,
	 * but callers can choose to pre-render the module to display it dynamically without delay
	 * when it becames enabled.
	 * @return bool
	 */
	protected function shouldRender() {
		return $this->canRender();
	}

	/**
	 * Override this function to add additional CSS classes to the top-level
	 * <div> of this module.
	 *
	 * @return string[] Additional CSS classes
	 */
	protected function getCssClasses() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function supports(): bool {
		return true;
	}

	/**
	 * Client bootstrap data for this module, packed into wgPersonalDashboardGroups
	 * keyed by module name.
	 *
	 * Carries only what the client needs to mount and coordinate the module; the
	 * body and footer HTML belong to render() now. A module that reports
	 * serverRendered() true is left alone by the client: its render() output stays
	 * in the server DOM rather than being mounted as an island.
	 *
	 * @return array
	 */
	public function getJsData(): array {
		return [
			'enabled' => $this->shouldRender(),
			'header' => $this->getHeaderText(),
			'expandable' => $this->shouldWrapModuleWithLink(),
			'serverRendered' => $this->serverRendered(),
		];
	}

	/**
	 * Override this function to provide modules that need to be
	 * loaded for this module.
	 *
	 * @return string[] Name of the module(s) to load
	 */
	protected function getModules() {
		return [];
	}

	/**
	 * Override this function to provide module styles that need to be
	 * loaded in the <head> for this module.
	 *
	 * @return string[] Name of the module(s) to load
	 */
	protected function getModuleStyles() {
		return [];
	}

	/**
	 * Override this function to provide JS config vars needed by this module.
	 *
	 * @return array
	 */
	public function getJsConfigVars() {
		return [];
	}

	/**
	 * Override this function to provide the state of this module. It will
	 * be included in 'state' for all PersonalDashboardModule events.
	 *
	 * @return string
	 */
	public function getState() {
		return '';
	}

	/**
	 * Override this function to provide the action data of this module. It will
	 * be included in 'action_data' for PersonalDashboardModule events.
	 *
	 * @return array
	 */
	protected function getActionData() {
		return [];
	}

	protected function outputDependencies() {
		$out = $this->getContext()->getOutput();
		$out->addModuleStyles( [
			'ext.personalDashboard.styles',
			'ext.personalDashboard.icons'
		] );
		$out->addModuleStyles( $this->getModuleStyles() );
		$out->addModules( $this->getModules() );
		$out->addJsConfigVars( [
			'wgPersonalDashboardModuleState-' . $this->getName() => $this->getState(),
			'wgPersonalDashboardModuleActionData-' . $this->getName() => $this->getActionData(),
		] );
		$out->addJsConfigVars( $this->getJsConfigVars() );
	}

	/**
	 * Whether this module renders its whole body server-side. Three kinds of
	 * module share this one flag:
	 *
	 *  - Island (the default, false): the frame carries an empty slot that the
	 *    module's Vue component fills in client-side.
	 *  - Server-static (true, no getModules()): render() emits the full body and
	 *    the client leaves it alone (Banner, ReturnToHomepage).
	 *  - Progressively enhanced (true, with getModules()): render() emits the full
	 *    body and the module ships a behavior module that runs against that server
	 *    DOM, adding interactivity without re-rendering it (PoliciesGuidelines).
	 *
	 * @return bool
	 */
	protected function serverRendered(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function render(): string {
		if ( !$this->shouldRender() ) {
			return '';
		}

		$this->outputDependencies();
		return $this->getHtml();
	}

	/**
	 * The module card frame: header, subheader, body, footer.
	 *
	 * @return string
	 */
	protected function getHtml() {
		$header = $this->isFocused() ? $this->getFocusedHeader() : $this->getHeader();
		return $this->buildModuleWrapper(
			$this->buildSection( 'header', $header, $this->getHeaderTag() ),
			$this->buildSection( 'subheader', $this->getSubheader(), $this->getSubheaderTag() ),
			$this->buildSection( 'body', $this->getBodyContent() ),
			$this->buildSection( 'footer', $this->getFooter() )
		);
	}

	/**
	 * The body content for the frame: an empty mount slot for island modules, or
	 * the full server-rendered body for modules that report serverRendered() true.
	 *
	 * @return string
	 */
	protected function getBodyContent(): string {
		return $this->serverRendered() ? $this->getBody() : $this->getSlot();
	}

	/**
	 * The empty mount slot an island module's Vue component teleports into.
	 *
	 * @return string
	 */
	protected function getSlot(): string {
		return Html::element( 'div', [
			'id' => 'pd-slot-' . $this->getName(),
			'class' => 'personal-dashboard-module-slot',
		] );
	}

	/**
	 * @param string ...$sections
	 * @return string
	 */
	protected function buildModuleWrapper( ...$sections ) {
		$className = $this->name;
		$lastDot = strrpos( $className, '.' );

		if ( $lastDot !== false ) {
			$className = substr( $className, $lastDot + 1 );
		}

		return Html::rawElement(
			'div',
			[
				'id' => $this->getName(),
				'class' => array_merge( [
					self::BASE_CSS_CLASS,
					self::BASE_CSS_CLASS . '-' . $className,
				], $this->getCssClasses() ),
				'data-module-name' => $this->name,
			],
			implode( "\n", $sections )
		);
	}

	/**
	 * Build a module section.
	 *
	 * $content is HTML, do not pass plain text. Use ->escaped() or ->parse() for messages.
	 *
	 * @param string $name Name of the section, used to generate a class
	 * @param string $content HTML content of the section
	 * @param string $tag HTML tag to use for the section
	 * @return string
	 */
	protected function buildSection( $name, $content, $tag = 'div' ) {
		return $content ? Html::rawElement(
			$tag,
			[
				'class' => [
					static::BASE_CSS_CLASS . '-section',
					static::BASE_CSS_CLASS . '-section-' . $name
				]
			],
			$content
		) : '';
	}

	/**
	 * @return string HTML element containing the header text.
	 */
	protected function getHeaderTextElement() {
		return Html::element(
			'div',
			[ 'class' => static::BASE_CSS_CLASS . '-header-text' ],
			$this->getHeaderText()
		);
	}

	/**
	 * Override this function to provide the header text
	 *
	 * @return string
	 */
	abstract protected function getHeaderText();

	/**
	 * Override this function to change the default header tag.
	 *
	 * @return string Tag to use with the header, eg. h2, h3, h4, ...
	 */
	protected function getHeaderTag() {
		return 'div';
	}

	/**
	 * Implement this function to provide the module header.
	 *
	 * @return string HTML content of the header. Will be wrapped in a section.
	 */
	protected function getHeader() {
		$html = '';
		if ( $this->shouldHeaderIncludeIcon() ) {
			$html .= $this->getHeaderIcon();
		}
		$html .= $this->getHeaderTextElement();
		return $html;
	}

	/**
	 * The module body. Island modules leave this empty (their body is the
	 * client-filled slot); server-rendered modules override it with their full
	 * content, and must also override serverRendered() to return true. Override
	 * one without the other and getBodyContent() hands back an empty slot, so the
	 * body silently never renders.
	 *
	 * @return string HTML content of the body
	 */
	protected function getBody() {
		return '';
	}

	/**
	 * Provide optional subheader for the module
	 *
	 * @return string HTML content of the subheader
	 */
	protected function getSubheader() {
		return $this->getSubheaderTextElement();
	}

	/**
	 * Override this function to provide an optional subheader for the module
	 *
	 * @return string Text content of the subheader
	 */
	protected function getSubheaderText() {
		return '';
	}

	/**
	 * @return string HTML element containing the header text.
	 */
	protected function getSubheaderTextElement() {
		$text = $this->getSubheaderText();
		return $text ? Html::element(
			'div',
			[ 'class' => static::BASE_CSS_CLASS . '-subheader-text' ],
			$text
		) : '';
	}

	/**
	 * Override this function to change the default subheader tag.
	 *
	 * @return string Tag to use with the subheader, e.g. h2, h3, h4
	 */
	protected function getSubheaderTag() {
		return 'div';
	}

	/**
	 * Override this function to provide an optional module footer.
	 *
	 * @return string HTML content of the footer
	 */
	protected function getFooter() {
		return '';
	}

	/**
	 * @return string HTML string wrapper for the header icon.
	 */
	protected function getHeaderIcon() {
		return Html::element(
			'span',
			[
				'class' => [ static::BASE_CSS_CLASS . '-header-icon' ],
			],
		);
	}

	/**
	 * Override this method if header should include the icon
	 *
	 * @return bool Should header include the icon?
	 */
	protected function shouldHeaderIncludeIcon(): bool {
		return false;
	}

	/**
	 * Alias for MessageLocalizer::msg
	 *
	 * @param string|string[]|MessageSpecifier $key
	 * @param mixed ...$params
	 * @return Message
	 * @see MessageLocalizer::msg()
	 */
	protected function msg( $key, ...$params ) {
		return $this->getContext()->msg( $key, ...$params );
	}
}
