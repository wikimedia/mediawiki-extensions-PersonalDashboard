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
	protected function getHeaderIconName() {
		return 'chart';
	}

	/** @inheritDoc */
	protected function getBody() {
		return Html::rawElement( 'div', [ 'id' => 'policies-guidelines-vue-root' ] );
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
