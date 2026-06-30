<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\IModule;
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
		return '';
	}

	/** @inheritDoc */
	public function getJsData( $mode ) {
		return [];
	}

	/** @inheritDoc */
	public function getJsConfigVars() {
		return [];
	}

	/** @inheritDoc */
	public function supports( $mode ) {
		return !$this->userOptionsManager->getBoolOption(
			$this->context->getUser(),
			'personaldashboard-visited' );
	}

	/** @inheritDoc */
	public function setPageURL( string $url ) {
	}
}
