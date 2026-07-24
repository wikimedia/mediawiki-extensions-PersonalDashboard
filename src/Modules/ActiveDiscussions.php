<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;

/**
 * Class for the Active Discussions module.
 */
class ActiveDiscussions extends BaseModule {
	public function __construct( IContextSource $context ) {
		parent::__construct( $context, true );
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		return $this->msg( 'personal-dashboard-active-discussions-title' )->text();
	}

	/** @inheritDoc */
	protected function getModules(): array {
		return [ 'ext.personalDashboard.activeDiscussions' ];
	}

	/** @inheritDoc */
	public function getJsConfigVars(): array {
		return [
			'wgPersonalDashboardActiveDiscussionsPages' =>
				$this->getConfig()->get( 'PersonalDashboardActiveDiscussionsPages' ),
		];
	}
}
