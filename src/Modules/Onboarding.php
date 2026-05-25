<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Html\Html;
use MediaWiki\User\Options\UserOptionsManager;

/**
 * Provides an oboarding facility for new users.
 */
class Onboarding implements IModule {
	private IContextSource $context;
	private UserOptionsManager $userOptionsManager;

	/**
	 * @param IContextSource $context
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		IContextSource $context,
		UserOptionsManager $userOptionsManager,
	) {
		$this->context = $context;
		$this->userOptionsManager = $userOptionsManager;
	}

	/** @inheritDoc */
	public function setName( $name ) {
	}

	/** @inheritDoc */
	public function render( $mode ) {
		$user = $this->context->getUser();
		$userOptionsManager = $this->userOptionsManager;
		if ( !$userOptionsManager->getBoolOption( $user, 'personaldashboard-visited' ) ) {
			$out = $this->context->getOutput();
			$out->addModules( 'ext.personalDashboard.onboarding' );
			return Html::element( 'div', [ 'id' => 'personal-dashboard-onboarding' ] );
		}
		return '';
	}

	/** @inheritDoc */
	public function getJsData( $mode ) {
		return [];
	}

	/** @inheritDoc */
	public function supports( $mode ) {
		return true;
	}

	/** @inheritDoc */
	public function setPageURL( string $url ) {
	}
}
