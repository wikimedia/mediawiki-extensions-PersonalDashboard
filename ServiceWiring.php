<?php

use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\Feed\PersonalDashboardFeedSourceFactory;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardModuleFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

/** @phpcs-require-sorted-array */
return [

	'PersonalDashboardCommunityConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->get( 'CommunityConfiguration.MediaWikiConfigRouter' );
	},

	'PersonalDashboardConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'PersonalDashboard' );
	},

	'PersonalDashboardFeedSourceFactory' => static function (
		MediaWikiServices $services
	): PersonalDashboardFeedSourceFactory {
		return new PersonalDashboardFeedSourceFactory(
			$services->getExtensionRegistry(),
			$services->getObjectFactory(),
			$services->get( 'PersonalDashboardLogger' )
		);
	},

	'PersonalDashboardLogger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'PersonalDashboard' );
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
