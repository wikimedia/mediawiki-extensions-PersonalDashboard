<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
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
	 * @param string $name Name of the module
	 * @param IContextSource $context
	 * @param bool $shouldWrapModuleWithLink
	 */
	public function __construct(
		string $name,
		IContextSource $context,
		bool $shouldWrapModuleWithLink = false,
	) {
		$this->name = $name;
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
	 * Modes that are supported by this module.Subclasses that don't support certain modes should
	 * override this to list only the modes they support. For more granular control, override
	 * supports() instead.
	 * @var string[]
	 */
	protected static $supportedModes = [
		self::RENDER_DESKTOP,
		self::RENDER_MOBILE_SUMMARY,
		self::RENDER_MOBILE_DETAILS
	];

	/** @var string Name of the module */
	protected string $name;

	protected IContextSource $context;

	/** @var string Rendering mode (one of RENDER_* constants) */
	private string $mode;

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
	 * @return string Rendering mode (one of RENDER_* constants)
	 */
	final protected function getMode(): string {
		return $this->mode;
	}

	final protected function getName(): string {
		return $this->name;
	}

	/**
	 * @param string $mode Rendering mode (one of RENDER_* constants)
	 */
	protected function setMode( string $mode ) {
		$this->mode = $mode;
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
	public function supports( $mode ) {
		return in_array( $mode, static::$supportedModes );
	}

	/**
	 * Get an array of data needed by the Javascript code related to this module.
	 * The data will be available in the 'personaldashboardmodules' JS configuration field, keyed by module name.
	 * Keys currently in use:
	 * - html: module HTML
	 * - rlModules: ResourceLoader modules this module depends on
	 * - heading: module header text
	 * 'html' is only present when the module supports dynamic loading, 'heading'
	 * in mobile summary mode, and 'rlModules' in both cases.
	 *
	 * @param string $mode One of RENDER_DESKTOP, RENDER_MOBILE_SUMMARY, RENDER_MOBILE_DETAILS
	 * @return array
	 */
	public function getJsData( $mode ) {
		if ( !$this->supports( $mode ) ) {
			return [];
		}

		$data = [];
		if ( $this->canRender()
			&& $mode == self::RENDER_MOBILE_SUMMARY
		) {
			$data = [
				'rlModules' => $this->getModules(),
				'heading' => $this->getHeaderText(),
			];
		}
		$this->setMode( $mode );
		$data[ 'renderMode' ] = $mode;
		return $data;
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
	protected function getJsConfigVars() {
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
	 * @inheritDoc
	 */
	public function render( $mode ) {
		if ( !$this->supports( $mode ) ) {
			return '';
		}
		$this->setMode( $mode );
		if ( !$this->shouldRender() ) {
			return '';
		}

		$this->outputDependencies();
		return $this->getHtml();
	}

	/**
	 * Get the module HTML for current mode
	 *
	 * @return string
	 */
	protected function getHtml() {
		if ( $this->mode === self::RENDER_DESKTOP ) {
			$html = $this->renderDesktop();
		} elseif ( $this->mode === self::RENDER_MOBILE_SUMMARY ) {
			$html = $this->renderMobileSummary();
		} elseif ( $this->mode === self::RENDER_MOBILE_DETAILS ) {
			$html = $this->renderMobileDetails();
		} else {
			throw new InvalidArgumentException( 'Invalid rendering mode: ' . $this->mode );
		}
		return $html;
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
					self::BASE_CSS_CLASS . '-' . $this->getMode(),
				], $this->getCssClasses() ),
				'data-module-name' => $this->name,
				'data-mode' => $this->getMode(),
			],
			implode( "\n", $sections )
		);

		if (
			$this->getMode() === self::RENDER_MOBILE_SUMMARY &&
			$this->supports( self::RENDER_MOBILE_DETAILS ) &&
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
	 * @return string HTML rendering for desktop.
	 */
	protected function renderDesktop() {
		return $this->buildModuleWrapper(
			$this->buildSection( 'header', $this->getHeader(), $this->getHeaderTag() ),
			$this->buildSection( 'subheader', $this->getSubheader(), $this->getSubheaderTag() ),
			$this->buildSection( 'body', $this->getBody() ),
			$this->buildSection( 'footer', $this->getFooter() )
		);
	}

	/**
	 * @return string HTML rendering for mobile summary.
	 */
	protected function renderMobileSummary() {
		return $this->buildModuleWrapper(
			$this->buildSection( 'header', $this->getMobileSummaryHeader(), $this->getHeaderTag() ),
			$this->buildSection( 'body', $this->getMobileSummaryBody() )
		);
	}

	/**
	 * @return string HTML rendering for mobile details.
	 */
	protected function renderMobileDetails() {
		return $this->buildModuleWrapper(
			$this->buildSection( 'header', $this->getMobileDetailsHeader(), $this->getHeaderTag() ),
			$this->buildSection( 'header-separator',
				Html::element( 'div', [ 'class' => static::BASE_CSS_CLASS . '-header-separator' ] ) ),
			$this->buildSection( 'subheader', $this->getSubheader(), $this->getSubheaderTag() ),
			$this->buildSection( 'body', $this->getBody() ),
			$this->buildSection( 'footer', $this->getFooter() )
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
	 * Implement this function to provide the module body.
	 *
	 * @return string HTML content of the body
	 */
	abstract protected function getBody();

	/**
	 * @return string HTML string to be used as header of the mobile summary.
	 */
	protected function getMobileSummaryHeader() {
		return $this->getHeaderTextElement() . $this->getNavIcon();
	}

	/**
	 * @return string HTML string to be used as header of the mobile details.
	 */
	protected function getMobileDetailsHeader() {
		$icon = $this->getBackIcon();
		$text = $this->getHeaderTextElement();
		return $icon . $text;
	}

	/**
	 * @return string HTML string wrapper for the back icon.
	 */
	protected function getBackIcon(): string {
		return Html::rawElement(
			'a',
			[
				'href' => SpecialPage::getTitleFor( 'PersonalDashboard' )->getLinkURL(),
				'class' => [ static::BASE_CSS_CLASS . '-header-back-icon' ],
			],
		);
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
	 * Implement this function to provide the module body
	 * when rendered as a mobile summary.
	 *
	 * @return string HTML content of the body
	 */
	protected function getMobileSummaryBody() {
		return $this->getBody();
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
