<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Html\Html;

/**
 * Class for the Moderation module.
 */
class PoliciesGuidelines extends BaseModule {
	public function __construct() {
		parent::__construct( 'policiesGuidelines' );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'personal-dashboard-policies-guidelines-title' )->text();
	}

	/** @inheritDoc */
	protected function getSubheaderText() {
		return $this->msg( 'personal-dashboard-policies-guidelines-body' )->text();
	}

	/** @inheritDoc */
	protected function getBody() {
		return implode( "\n", [
			Html::rawElement( 'div',
				[
					'id' => 'policies-guidelines-vue-root',
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
		return implode( "\n", [
			Html::rawElement( 'p', [],
			$this->msg(
				'personal-dashboard-policies-guidelines-mobile-summary'
			)->text() ),
			Html::element( 'p',
				[ 'class' => 'personal-dashboard-module-no-js-fallback' ],
				$this->msg( 'personal-dashboard-module-no-js-fallback' )->text()
			)
		] );
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.personalDashboard.policiesGuidelines', 'ext.personalDashboard.policiesGuidelines' ];
	}
}
