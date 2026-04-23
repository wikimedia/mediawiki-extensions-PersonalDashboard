<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

/**
 * Class for the Active Discussions module.
 */
class ActiveDiscussions extends BaseModule {
	public function __construct( IContextSource $context ) {
		parent::__construct( 'activeDiscussions', $context );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'personal-dashboard-active-discussions-title' )->text();
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
