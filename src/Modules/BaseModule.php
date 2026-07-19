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

	/**
	 * Platforms that are supported by this module. Subclasses that don't support a
	 * platform should override this to list only the platforms they support. For more
	 * granular control, override supports() instead.
	 * @var string[]
	 */
	protected static $supportedPlatforms = [
		self::PLATFORM_DESKTOP,
		self::PLATFORM_MOBILE
	];

	/** @var string Name of the module */
	protected string $name;

	protected IContextSource $context;

	/** @var string Rendering platform (one of PLATFORM_* constants) */
	private string $platform;

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

	/**
	 * @return string Rendering platform (one of PLATFORM_* constants)
	 */
	final protected function getPlatform(): string {
		return $this->platform;
	}

	/** @inheritDoc */
	final protected function getName(): string {
		return $this->name;
	}

	/**
	 * @param string $platform Rendering platform (one of PLATFORM_* constants)
	 */
	protected function setPlatform( string $platform ) {
		$this->platform = $platform;
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
	public function supports( string $platform ): bool {
		return in_array( $platform, static::$supportedPlatforms );
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
	 * @param string $platform One of the PLATFORM_* constants
	 * @return array
	 */
	public function getJsData( string $platform ): array {
		if ( !$this->supports( $platform ) ) {
			return [];
		}

		$this->setPlatform( $platform );

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
	 * Whether this module renders its whole body server-side.
	 *
	 * An island module (the default) emits a frame with an empty slot that its Vue
	 * component fills in client-side. A server-rendered module emits its full body
	 * from render() and is left untouched by the client. Override to return true for
	 * static, JS-free modules.
	 *
	 * @return bool
	 */
	protected function serverRendered(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function render( string $platform ): string {
		if ( !$this->supports( $platform ) ) {
			return '';
		}
		$this->setPlatform( $platform );
		if ( !$this->shouldRender() ) {
			return '';
		}

		$this->outputDependencies();
		return $this->getHtml();
	}

	/**
	 * Get the module frame HTML for the current platform.
	 *
	 * @return string
	 */
	protected function getHtml() {
		return $this->getPlatform() === self::PLATFORM_MOBILE ?
			$this->renderMobileFrame() :
			$this->renderDesktopFrame();
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
		$moduleContent = Html::rawElement(
			'div',
			[
				'id' => $this->getName(),
				'class' => array_merge( [
					self::BASE_CSS_CLASS,
					self::BASE_CSS_CLASS . '-' . $this->name,
					self::BASE_CSS_CLASS . '-' . $this->getPlatform(),
				], $this->getCssClasses() ),
				'data-module-name' => $this->name,
				'data-platform' => $this->getPlatform(),
			],
			implode( "\n", $sections )
		);

		if (
			$this->getPlatform() === self::PLATFORM_MOBILE &&
			$this->shouldWrapModuleWithLink()
		) {
			return Html::rawElement( 'a', [
				'href' => $this->getPageURL() . '/' . $this->getName(),
			], $moduleContent );
		}

		return $moduleContent;
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
					static::BASE_CSS_CLASS . '-section-' . $name,
					static::BASE_CSS_CLASS . '-' . $name
				]
			],
			$content
		) : '';
	}

	/**
	 * @return string The desktop card frame: header, subheader, body slot, footer.
	 */
	protected function renderDesktopFrame() {
		return $this->buildModuleWrapper(
			$this->buildSection( 'header', $this->getHeader(), $this->getHeaderTag() ),
			$this->buildSection( 'subheader', $this->getSubheader(), $this->getSubheaderTag() ),
			$this->buildSection( 'body', $this->getBodyContent() ),
			$this->buildSection( 'footer', $this->getFooter() )
		);
	}

	/**
	 * @return string The compact mobile card frame: header and body slot.
	 */
	protected function renderMobileFrame() {
		return $this->buildModuleWrapper(
			$this->buildSection( 'header', $this->getMobileSummaryHeader(), $this->getHeaderTag() ),
			$this->buildSection( 'body', $this->getBodyContent() )
		);
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
	 * @return string HTML string to be used as header of the mobile summary.
	 */
	protected function getMobileSummaryHeader() {
		return $this->getHeaderTextElement() . $this->getNavIcon();
	}

	/**
	 * @return string HTML string wrapper for the navigation icon.
	 */
	protected function getNavIcon() {
		return Html::element(
			'span',
			[
				'class' => [ static::BASE_CSS_CLASS . '-header-nav-icon' ],
			],
		);
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
