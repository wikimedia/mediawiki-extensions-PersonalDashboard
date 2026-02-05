<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\User\UserEditTracker;

/**
 * Class for the RiskyArticleEdits module.
 */
class RiskyArticleEdits extends BaseModule {

	private \StdClass $moduleConfig;
	private UserEditTracker $userEditTracker;

	/**
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 * @param UserEditTracker $userEditTracker
	 */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig,
		UserEditTracker $userEditTracker
	) {
		parent::__construct( 'riskyArticleEdits', $ctx, $wikiConfig );
		$this->moduleConfig = $wikiConfig->get( 'PersonalDashboardRiskyArticleEdits' );
		$this->userEditTracker = $userEditTracker;
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'personal-dashboard-risky-article-edits-mobile-summary-header' );
	}

	/** @inheritDoc */
	protected function getSubheaderText() {
		return $this->msg( 'personal-dashboard-risky-article-edits-subheader' );
	}

	/** @inheritDoc */
	protected function shouldHeaderIncludeIcon(): bool {
		return false;
	}

	/** @inheritDoc */
	protected function getHeader() {
		$html = $this->getHeaderTextElement();
		if ( $this->shouldHeaderIncludeIcon() ) {
			$html .= $this->getHeaderIcon();
		}
		return $html;
	}

	/** @inheritDoc */
	protected function getMobileDetailsHeader() {
		$icon = $this->getBackIcon();
		$text = $this->getHeaderTextElement();
		return $icon . $text;
	}

	/** @inheritDoc */
	protected function getFooter() {
		return Html::rawElement(
			'div',
			[ 'id' => 'personal-dashboard-go-to-recentchanges' ],
			$this->msg( 'personal-dashboard-risky-article-edits-footer-preamble' )
		);
	}

	/** @inheritDoc */
	protected function getBody() {
		return implode( "\n", [
			Html::rawElement( 'div',
				[
					'id' => 'risky-article-edits-vue-root',
					'class' => [ 'ext-personal-dashboard-app-root' ],
				],
			),
			Html::element( 'p',
				[ 'class' => 'personal-dashboard-module-no-js-fallback' ],
				$this->msg( 'personal-dashboard-module-no-js-fallback' )->text()
			)
		] );
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return Html::rawElement( 'div',
				[
					'id' => 'risky-article-edits-vue-root--mobile',
				],
			) .
			Html::element( 'p',
				[ 'class' => 'personal-dashboard-module-no-js-fallback' ],
				$this->msg( 'personal-dashboard-impact-no-js-fallback' )->text()
			);
	}

	/** @inheritDoc */
	protected function getModules() {
		if ( $this->getMode() == self::RENDER_MOBILE_SUMMARY ) {
			return [ 'ext.personalDashboard.riskyArticleEdits.mobile', 'ext.personalDashboard.common' ];
		}
		return [ 'ext.personalDashboard.riskyArticleEdits', 'ext.personalDashboard.common' ];
	}

	/** @inheritDoc */
	protected function canRender() {
		// Disabled for this wiki?
		if ( $this->moduleConfig->enabled === false ) {
			return false;
		}

		// Not enough edits?
		$userIdentity = $this->getContext()->getUser();
		$editCount = $this->userEditTracker->getUserEditCount( $userIdentity );
		if ( $this->moduleConfig->minimumUserEdits > $editCount ) {
			return false;
		}

		// Otherwise, render
		return true;
	}
}
