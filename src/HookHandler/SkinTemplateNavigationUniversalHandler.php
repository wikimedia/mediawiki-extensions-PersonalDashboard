<?php

namespace MediaWiki\Extension\PersonalDashboard\HookHandler;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;

class SkinTemplateNavigationUniversalHandler implements SkinTemplateNavigation__UniversalHook {
	public function __construct(
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly UserEditTracker $userEditTracker
	) {
	}

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$user = $sktemplate->getUser();

		if ( !$this->isMenuLinkVisible( $sktemplate, $user ) ) {
			return;
		}

		$this->addToUserMenu( $sktemplate, $links );
		$output = $sktemplate->getOutput();

		if ( $this->isBlueDotVisible( $sktemplate, $user ) ) {
			$output->addModules( 'ext.personalDashboard.blueDot' );
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikimediaEvents' ) ) {
			$output->addModules( [
				'ext.wikimediaEvents.personalDashboard'
			] );
		}
	}

	/**
	 * Check if the user menu link should be visible on the current page.
	 */
	public function isMenuLinkVisible( SkinTemplate $sktemplate, User $user ): bool {
		// Never show if the user is anonymous/temporary or the config value is disabled
		if ( !$user->isNamed() || !$sktemplate->getConfig()->get( 'PersonalDashboardUserMenu' ) ) {
			return false;
		}

		// Always show if the user was previously eligible to see the user menu link
		if ( $this->userOptionsManager->getBoolOption( $user, 'personaldashboard-eligible' ) ) {
			return true;
		}

		$minimumEdits = max( $sktemplate->getConfig()->get( 'PersonalDashboardMinimumEdits' ), 0 );
		$maximumEdits = max( $sktemplate->getConfig()->get( 'PersonalDashboardMaximumEdits' ), 0 );

		$hasMinimum = $minimumEdits > 0;
		$hasMaximum = $maximumEdits > 0;

		// Always show if both the minimum and maximum thresholds are set to 0 (default value)
		if ( !$hasMinimum && !$hasMaximum ) {
			return true;
		}

		$editCount = $this->userEditTracker->getUserEditCount( $user );

		// Never show if the user's edit count is not within the minimum and maximum thresholds
		if ( ( $hasMinimum && $editCount < $minimumEdits ) ||
			( $hasMaximum && $editCount > $maximumEdits ) ) {
			return false;
		}

		// Persist the user's eligiblity state to skip redundant checks and ensure permanent access
		$this->userOptionsManager->setOption( $user, 'personaldashboard-eligible', true );
		$this->saveUserOptionsDeferred( $this->userOptionsManager, $user );
		return true;
	}

	/**
	 * Add a link to Special:PersonalDashboard in the user menu.
	 */
	public function addToUserMenu( SkinTemplate $sktemplate, array &$links ): void {
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

	/**
	 * Check if the blue dot indicator should be visible on the current page.
	 */
	public function isBlueDotVisible( SkinTemplate $sktemplate, UserIdentity $user ): bool {
		return $sktemplate->getConfig()->get( 'PersonalDashboardBlueDot' ) &&
			!$sktemplate->getTitle()->isSpecial( 'PersonalDashboard' ) &&
			!$this->userOptionsManager->getBoolOption( $user, 'personaldashboard-visited' );
	}

	/**
	 * @param UserOptionsManager $userOptionsManager
	 * @param User $user
	 * @return void
	 */
	private function saveUserOptionsDeferred( UserOptionsManager $userOptionsManager, User $user ): void {
		DeferredUpdates::addCallableUpdate(
			static function () use ( $userOptionsManager, $user ) {
				$userOptionsManager->saveOptions( $user );
			}
		);
	}
}
