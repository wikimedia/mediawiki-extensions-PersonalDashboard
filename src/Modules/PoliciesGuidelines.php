<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;

/**
 * Class for the Moderation module.
 */
class PoliciesGuidelines extends BaseModule {
	public function __construct( IContextSource $context ) {
		parent::__construct( $context, true );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'personal-dashboard-policies-guidelines-title' )->text();
	}

	/** @inheritDoc */
	protected function getSubheaderText() {
		return $this->msg( $this->getPlatform() === self::PLATFORM_DESKTOP ?
			'personal-dashboard-policies-guidelines-body' :
			'personal-dashboard-policies-guidelines-mobile-summary' )->text();
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.personalDashboard.policiesGuidelines' ];
	}
}
