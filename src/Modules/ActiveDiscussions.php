<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\Trait\FeedLoadingMarkup;

/**
 * Class for the Active Discussions module.
 */
class ActiveDiscussions extends BaseModule {

	use FeedLoadingMarkup;

	public function __construct( IContextSource $context ) {
		parent::__construct( $context, shouldWrapModuleWithLink: true );
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		return $this->msg( 'personal-dashboard-active-discussions-title' )->text();
	}

	/**
	 * The progress bar of the island while it loads its first page.
	 * @inheritDoc
	 */
	protected function getLoadingMarkup(): string {
		return $this->getFeedLoadingMarkup();
	}

	/** @inheritDoc */
	protected function getModules(): array {
		return [ 'ext.personalDashboard.activeDiscussions' ];
	}
}
