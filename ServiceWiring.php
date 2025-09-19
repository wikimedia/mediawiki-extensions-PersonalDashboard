<?php

use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardModuleRegistry;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [

	'PersonalDashboardCommunityConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->get( 'CommunityConfiguration.MediaWikiConfigRouter' );
	},

	'PersonalDashboardConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'PersonalDashboard' );
	},

	'PersonalDashboardModuleRegistry' => static function (
		MediaWikiServices $services
	): PersonalDashboardModuleRegistry {
		return new PersonalDashboardModuleRegistry( $services );
	},

];
