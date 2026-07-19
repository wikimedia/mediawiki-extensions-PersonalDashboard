<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

/**
 * A module for displaying some text that can be specified on-wiki. This can be used as a low-key
 * sitenotice aimed at new users.
 *
 * The Banner message is generated from the contents of the MediaWiki:PersonalDashboard-banner wiki
 * page, if it exists. If the page does not exist or the page content is empty, the banner module
 * is not shown.
 */
class Banner extends BaseModule {

	public const MESSAGE_KEY = 'PersonalDashboard-banner';

	/**
	 * Check whether the module is enabled (ie. if there's a banner message set by the wiki admin).
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function isEnabled( IContextSource $context ) {
		return !$context->msg( self::MESSAGE_KEY )->isDisabled();
	}

	/** @inheritDoc */
	public function __construct( $context ) {
		parent::__construct( $context );
	}

	/** @inheritDoc */
	protected function canRender() {
		return self::isEnabled( $this->getContext() );
	}

	/**
	 * The banner is static server HTML; the client leaves it in place.
	 * @return bool
	 */
	protected function serverRendered(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return '';
	}

	/** @inheritDoc */
	protected function getHeader() {
		return '';
	}

	/** @inheritDoc */
	protected function getMobileSummaryHeader() {
		return '';
	}

	/** @inheritDoc */
	protected function getBody() {
		return Html::rawElement(
			'div',
			[
				'class' => 'mw-parser-output',
				'data-link-group-id' => 'banner',
			],
			$this->getContext()->msg( self::MESSAGE_KEY )->parse()
		);
	}

	/** @inheritDoc */
	public function getState() {
		return $this->canRender() ? self::MODULE_STATE_ACTIVATED : self::MODULE_STATE_UNACTIVATED;
	}

}
