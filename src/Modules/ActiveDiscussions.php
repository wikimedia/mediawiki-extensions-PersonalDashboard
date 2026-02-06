<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

/**
 * Class for the Active Discussions module.
 */
class ActiveDiscussions extends BaseModule {
	/**
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig
	) {
		parent::__construct( 'activeDiscussions', $ctx, $wikiConfig );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'personal-dashboard-active-discussions-title' )->text();
	}

	/** @inheritDoc */
	protected function getSubheaderText() {
		return $this->msg( 'personal-dashboard-active-discussions-description' )->text();
	}

	/** @inheritDoc */
	protected function getBody() {
		return implode( "\n", [
			Html::rawElement( 'div',
				[
					'id' => 'active-discussions-vue-root',
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
					'id' => 'active-discussions-vue-root',
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
		$showActiveDiscussions = $this->getContext()->getRequest()
			->getText( 'personaldashboard_activediscussions_show' );

		return [
			'wgPersonalDashboardShowActiveDiscussions' => $showActiveDiscussions
		];
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.personalDashboard.activeDiscussions' ];
	}
}
