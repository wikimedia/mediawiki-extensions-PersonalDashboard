<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

/**
 * Class for the Moderation module.
 */
class PoliciesGuidelines extends BaseModule {
	/**
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig
	) {
		parent::__construct( 'policiesGuidelines', $ctx, $wikiConfig );
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
		return Html::rawElement( 'p', [], $this->msg(
			'personal-dashboard-policies-guidelines-mobile-summary'
		)->text() );
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.personalDashboard.policiesGuidelines', 'ext.personalDashboard.policiesGuidelines' ];
	}
}
