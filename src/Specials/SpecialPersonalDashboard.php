<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Specials;

use MediaWiki\Extension\PersonalDashboard\Experiments;
use MediaWiki\Message\Message;

/**
 * The Moderator Tools dashboard, at Special:PersonalDashboard.
 *
 * Personal Dashboard's own dashboard, and the first subclass of
 * AbstractSpecialDashboard. It renders through the same code as any dashboard
 * another extension serves.
 */
class SpecialPersonalDashboard extends AbstractSpecialDashboard {

	/** @inheritDoc */
	protected function getPageName(): string {
		return 'PersonalDashboard';
	}

	/** @inheritDoc */
	protected function getBaselineModuleGroup(): string {
		return 'default';
	}

	/**
	 * The manifest in Experiments is this dashboard's, because it names this
	 * dashboard's module groups.
	 *
	 * @inheritDoc
	 */
	protected function getExperimentManifest(): array {
		return Experiments::all();
	}

	/**
	 * The page is still in beta, so it stays out of Special:SpecialPages. This
	 * is a product decision about this dashboard, not a rule for every one.
	 *
	 * @inheritDoc
	 */
	public function isListed(): bool {
		return false;
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}

	/**
	 * Overridden in order to inject the current user's name as message parameter
	 *
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return $this->msg( 'personal-dashboard-specialpage-title' )
			->params( $this->getUser()->getName() );
	}
}
