<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

/**
 * Class for the Moderation module.
 */
class ContentPolicies extends BaseModule {
	/**
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig
	) {
		parent::__construct( 'ContentPolicies', $ctx, $wikiConfig, false );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'content-policies-title' )->text();
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'chart';
	}

	/** @inheritDoc */
	protected function getBody() {
		return Html::rawElement( 'div', [ 'id' => 'content-policies-vue-root' ] );
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return $this->getBody();
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.personalDashboard.contentPolicies' ];
	}
}
