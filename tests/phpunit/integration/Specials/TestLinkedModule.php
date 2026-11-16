<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Specials;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;

/**
 * A client-side test module whose card is a link to its own focused page.
 */
class TestLinkedModule extends BaseModule {

	public function __construct( IContextSource $context ) {
		parent::__construct( $context, shouldWrapModuleWithLink: true );
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		return 'Linked test module';
	}
}
