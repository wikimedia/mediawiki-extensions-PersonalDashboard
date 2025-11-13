<?php

namespace MediaWiki\Extension\PersonalDashboard\HookHandler;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\SpecialPage\SpecialPageFactory;

class SkinTemplateNavigationUniversalHandler implements SkinTemplateNavigation__UniversalHook {
	private SpecialPageFactory $specialPageFactory;

	public function __construct( SpecialPageFactory $specialPageFactory ) {
		$this->specialPageFactory = $specialPageFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$sktemplate->getUser()->isNamed() ) {
			return;
		}

		$menu = &$links['user-menu'];
		$link = [
			'text' => $sktemplate->msg( 'personal-dashboard-link-title' ),
			'href' => $this->specialPageFactory->getTitleForAlias( 'PersonalDashboard' )->getFullURL(),
			'icon' => 'viewCompact'
		];

		// Insert before the "Preferences" link, or fallback to end if it doesn't exist
		$offset = array_search( 'preferences', array_keys( $menu ) );

		if ( $offset === false ) {
			$menu['personaldashboard'] = $link;
		} else {
			$menu = array_slice( $menu, 0, $offset, true ) +
				[ 'personaldashboard' => $link ] +
				array_slice( $menu, $offset, null, true );
		}

		$sktemplate->getOutput()->addModuleStyles( 'ext.personalDashboard.menuIcon' );
	}
}
