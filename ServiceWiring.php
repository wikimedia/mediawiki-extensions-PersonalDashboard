<?php

use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardModuleFactory;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [

	'PersonalDashboardCommunityConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->get( 'CommunityConfiguration.MediaWikiConfigRouter' );
	},

	'PersonalDashboardConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'PersonalDashboard' );
	},

	'PersonalDashboardModuleFactory' => static function (
		MediaWikiServices $services
	): PersonalDashboardModuleFactory {
		return new PersonalDashboardModuleFactory(
			$services->getExtensionRegistry(),
			$services->getObjectFactory()
		);
	},

];
