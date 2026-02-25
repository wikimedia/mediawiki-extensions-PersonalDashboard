<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserEditTracker;

/**
 * Class for the RiskyArticleEdits module.
 */
class RiskyArticleEdits extends BaseModule {

	/**
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 * @param UserEditTracker $userEditTracker
	 */
	public function __construct(
		IContextSource $ctx,
		private readonly Config $wikiConfig,
		private readonly UserEditTracker $userEditTracker
	) {
		parent::__construct( 'riskyArticleEdits', $ctx, $wikiConfig );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'personal-dashboard-risky-article-edits-header' );
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
		return Html::element( 'div',
				[
					'id' => 'risky-article-edits-vue-root',
					'class' => [ 'ext-personal-dashboard-app-root' ],
				],
			) .
			Html::element( 'p',
				[ 'class' => 'personal-dashboard-module-no-js-fallback' ],
				$this->msg( 'personal-dashboard-impact-no-js-fallback' )->text()
			);
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		$configVars = [];
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ORES' ) ) {
			$configVars[ 'wgOresUiEnabled' ] = $this->wikiConfig->get( 'OresUiEnabled' );
			$configVars[ 'wgOresFiltersThresholds' ] = $this->wikiConfig->get( 'OresFiltersThresholds' );
		}
		return $configVars;
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.personalDashboard.riskyArticleEdits' ];
	}

	/** @inheritDoc */
	protected function canRender() {
		// Disabled for this wiki?
		if ( $this->wikiConfig->get( "PersonalDashboardRiskyArticleEditsEnabled" ) === false ) {
			return false;
		}

		// Not enough edits?
		$userIdentity = $this->getContext()->getUser();
		$editCount = $this->userEditTracker->getUserEditCount( $userIdentity );
		if ( $this->wikiConfig->get( "PersonalDashboardRiskyArticleEditsMinimumEditCount" ) > $editCount ) {
			return false;
		}

		// Otherwise, render
		return true;
	}
}
