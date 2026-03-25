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
		private readonly IContextSource $ctx,
		private readonly Config $wikiConfig,
		private readonly UserEditTracker $userEditTracker
	) {
		parent::__construct( 'riskyArticleEdits', $ctx, $wikiConfig );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'personal-dashboard-risky-article-edits-header' )->text();
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
			$this->msg( 'personal-dashboard-risky-article-edits-footer-preamble' )->parse()
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
		// TODO: Remove this feature flag once watchlist changes are integrated to the feed completely
		$watchListFeedEnabled = $this->ctx->getRequest()->getText( 'personaldashboard_riskyarticleedits_wlenabled' );
		// fallback to ml disabled if ores isn't loaded and configured as expected
		$config = $this->getConfig();
		$mlDisabledConf = [
				'wgPersonalDashboardRiskyArticleEditsMlEnabled' => false,
				'wgPersonalDashboardRiskyArticleEditsWlEnabled' => (bool)$watchListFeedEnabled
		];
		if (
			!ExtensionRegistry::getInstance()->isLoaded( 'ORES' ) ||
			!$config->has( 'OresUiEnabled' ) || !$config->get( 'OresUiEnabled' ) ||
			!$config->has( 'OresFiltersThresholds' ) ||
			!$config->has( 'OresModels' )
		) {
			return $mlDisabledConf;
		}

		// Provide ML model threshold configuration from ORES extension if avaiable
		$thresholds = $config->get( 'OresFiltersThresholds' );
		$oresModels = $config->get( 'OresModels' );

		// use a predefined filter for models we allow
		$filters = [
			'revertrisklanguageagnostic' => 'revertrisk',
			'damaging' => 'likelybad',
		];
		// get model from url param or config
		$models = [];
		$requestedModel = $this->ctx->getRequest()->getText( 'personaldashboard_riskyarticleedits_mlmodel' );
		if ( $requestedModel !== '' ) {
			$models[] = $requestedModel;
		}
		$models[] = $config->get( 'PersonalDashboardRiskyArticleEditsMlModel' );

		// try models in decending order
		// make the model avaiable if it is enabled and the expected filter is configured
		foreach ( $models as $model ) {
			if (
				// model conf: model key exists
				!array_key_exists( $model, $oresModels ) ||
				// model conf: model enablement key exists
				!array_key_exists( 'enabled', $oresModels[ $model ] ) ||
				// model conf: model enabled
				$oresModels[ $model ][ 'enabled' ] !== true ||
				// allowed filters: model key exists
				!array_key_exists( $model, $filters ) ||
				// thresholds conf: model key exists
				!array_key_exists( $model, $thresholds ) ||
				// thresholds conf: filter key exists
				!array_key_exists( $filters[ $model ], $thresholds[ $model ] )
			) {
				continue;
			}
			return [
				'wgPersonalDashboardRiskyArticleEditsMlModel' => $model,
				'wgPersonalDashboardRiskyArticleEditsMlEnabled' => true,
				'wgPersonalDashboardRiskyArticleEditsWlEnabled' => (bool)$watchListFeedEnabled
			];
		}
		// fallback to ml disabled if no model is available
		return $mlDisabledConf;
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.personalDashboard.riskyArticleEdits' ];
	}
}
