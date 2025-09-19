<?php

namespace MediaWiki\Extension\PersonalDashboard;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

/**
 * A simple wrapper for MediaWikiServices, to support type safety when accessing
 * services defined by this extension.
 */
class PersonalDashboardServices {

	private MediaWikiServices $coreServices;

	public function __construct( MediaWikiServices $coreServices ) {
		$this->coreServices = $coreServices;
	}

	/**
	 * Static version of the constructor, for nicer syntax.
	 * @param MediaWikiServices $coreServices
	 * @return static
	 */
	public static function wrap( MediaWikiServices $coreServices ) {
		return new static( $coreServices );
	}

	// Service aliases
	// phpcs:disable MediaWiki.Commenting.FunctionComment

	public function getPersonalDashboardConfig(): Config {
		return $this->coreServices->get( 'PersonalDashboardConfig' );
	}

	public function getPersonalDashboardWikiConfig(): Config {
		return $this->coreServices->get( 'PersonalDashboardCommunityConfig' );
	}

	public function getPersonalDashboardModuleRegistry(): PersonalDashboardModuleRegistry {
		return $this->coreServices->get( 'PersonalDashboardModuleRegistry' );
	}

	public function getLogger(): LoggerInterface {
		return $this->coreServices->get( 'PersonalDashboardLogger' );
	}
}
