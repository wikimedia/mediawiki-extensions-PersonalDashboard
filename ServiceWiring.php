<?php

use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\Feed\IRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\NullRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\OresRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\OresScoreFormatter;
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

	'PersonalDashboardRevisionScoreLookup' => static function (
		MediaWikiServices $services
	): IRevisionScoreLookup {
		// PersonalDashboard does not require ORES, so scoring is absent rather
		// than broken where it is missing. Everything downstream takes the
		// interface and never learns which of the two it got.
		if ( !$services->getExtensionRegistry()->isLoaded( 'ORES' ) ) {
			return new NullRevisionScoreLookup();
		}

		return new OresRevisionScoreLookup(
			$services->get( 'ORESScoreLookup' ),
			new OresScoreFormatter(),
			$services->getMainConfig()
		);
	},

];
