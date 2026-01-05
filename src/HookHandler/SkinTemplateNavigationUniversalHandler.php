<?php

namespace MediaWiki\Extension\PersonalDashboard\HookHandler;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;

class SkinTemplateNavigationUniversalHandler implements SkinTemplateNavigation__UniversalHook {
	public function __construct(
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly UserEditTracker $userEditTracker
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$user = $sktemplate->getUser();

		if ( !$user->isNamed() ) {
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

		$output = $sktemplate->getOutput();
		$output->addModuleStyles( 'ext.personalDashboard.menuIcon' );

		if ( $this->isBlueDotVisible( $sktemplate, $user ) ) {
			$output->addModules( 'ext.personalDashboard.blueDot' );
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikimediaEvents' ) ) {
			$output->addModules( 'ext.wikimediaEvents.personalDashboard' );
		}
	}

	/**
	 * Check if the blue dot indicator should be visible on the current page.
	 */
	public function isBlueDotVisible( SkinTemplate $sktemplate, UserIdentity $user ): bool {
		$isEnabled = $sktemplate->getConfig()->get( 'PersonalDashboardBlueDot' );
		$isSelf = $sktemplate->getTitle()->isSpecial( 'PersonalDashboard' );
		$hasVisited = $this->userOptionsManager->getBoolOption( $user, 'personaldashboard-visited' );
		$editCount = $this->userEditTracker->getUserEditCount( $user );
		$minimumEdits = $sktemplate->getConfig()->get( 'PersonalDashboardBlueDotMinimumEdits' );

		return $isEnabled && !$isSelf && !$hasVisited && $editCount >= $minimumEdits;
	}
}
