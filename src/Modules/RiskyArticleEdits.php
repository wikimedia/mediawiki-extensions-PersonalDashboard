<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Registration\ExtensionRegistry;

/**
 * Class for the RiskyArticleEdits module.
 */
class RiskyArticleEdits extends BaseModule {

	public function __construct( IContextSource $context ) {
		parent::__construct( $context, true );
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		return $this->msg( 'personal-dashboard-risky-article-edits-header' )->text();
	}

	/** @inheritDoc */
	protected function getSubheaderText(): string {
		return $this->msg( 'personal-dashboard-risky-article-edits-subheader-info' )->text();
	}

	/** @inheritDoc */
	protected function shouldHeaderIncludeIcon(): bool {
		return false;
	}

	/** @inheritDoc */
	protected function getHeader(): string {
		$html = $this->getHeaderTextElement();
		if ( $this->shouldHeaderIncludeIcon() ) {
			$html .= $this->getHeaderIcon();
		}
		return $html;
	}

	/**
	 * The no-JS fallback footer: a plain link into recent changes, shown on every
	 * viewport when JS is off. With JS the client renders its own detail-branched
	 * footer inside the body slot, so this whole footer section is hidden under the
	 * .client-js no-js-fallback rule.
	 * @inheritDoc
	 */
	protected function getFooter(): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'personal-dashboard-module-no-js-fallback' ],
			$this->msg( 'personal-dashboard-risky-article-edits-footer-preamble' )->parse()
		);
	}

	/** @inheritDoc */
	public function getJsConfigVars(): array {
		// fallback to ml disabled if ores isn't loaded and configured as expected
		$config = $this->getConfig();
		$mlDisabledConf = [
				'wgPersonalDashboardRiskyArticleEditsMlEnabled' => false,
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
		$requestedModel = $this->getContext()->getRequest()->getText( 'personaldashboard_riskyarticleedits_mlmodel' );
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
			];
		}
		// fallback to ml disabled if no model is available
		return $mlDisabledConf;
	}

	/** @inheritDoc */
	protected function getModules(): array {
		return [ 'ext.personalDashboard.riskyArticleEdits' ];
	}
}
