<?php

namespace MediaWiki\Extension\PersonalDashboard\HookHandler;

use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

class BeforePageDisplayHandler implements BeforePageDisplayHook {

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $skin instanceof SkinMinerva ) {
			$out->addModuleStyles( 'ext.personalDashboard.mobileMenu.icons' );
		}
	}
}
