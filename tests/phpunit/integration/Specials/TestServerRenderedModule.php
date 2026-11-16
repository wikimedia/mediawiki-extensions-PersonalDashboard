<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Specials;

use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Html\Html;

/**
 * A server-rendered test module that reads a focused subpath. It renders an
 * open <details> element with the subpath as its id, so a test can see that
 * the subpath reached the module.
 */
class TestServerRenderedModule extends BaseModule {

	/** @inheritDoc */
	protected function serverRendered(): bool {
		return true;
	}

	/** @inheritDoc */
	public function acceptsFocusedSubPath(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		return 'Server-rendered test module';
	}

	/** @inheritDoc */
	protected function getBody(): string {
		$subPath = $this->getFocusedSubPath();
		if ( $subPath === '' ) {
			return '';
		}
		return Html::element( 'details', [ 'id' => $subPath, 'open' => true ] );
	}
}
