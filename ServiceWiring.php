<?php

declare( strict_types = 1 );

use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\Feed\IPageDescriptionLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\IRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\NullPageDescriptionLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\NullRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\OresRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\OresScoreFormatter;
use MediaWiki\Extension\PersonalDashboard\Feed\PersonalDashboardFeedSourceFactory;
use MediaWiki\Extension\PersonalDashboard\Feed\ShortDescriptionPageDescriptionLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\WikibasePageDescriptionLookup;
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
			$services->get( 'PersonalDashboardLogger' ),
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
			$services->getObjectFactory(),
		);
	},

	'PersonalDashboardPageDescriptionLookup' => static function (
		MediaWikiServices $services
	): IPageDescriptionLookup {
		/*
		 * The two extensions use different page properties: Wikibase reads its
		 * own wikibase-shortdesc plus the central description from the repo,
		 * ShortDescription reads shortdesc. T437491 treats them as alternatives,
		 * so we pick one rather than merge both, and production goes first.
		 */
		if ( $services->getExtensionRegistry()->isLoaded( 'WikibaseClient' ) ) {
			return new WikibasePageDescriptionLookup(
				$services->getService( 'WikibaseClient.DescriptionLookup' ),
				$services->getTitleFactory(),
				$services->getLinkBatchFactory(),
			);
		}

		if ( $services->getExtensionRegistry()->isLoaded( 'ShortDescription' ) ) {
			return new ShortDescriptionPageDescriptionLookup( $services->getPageProps() );
		}

		return new NullPageDescriptionLookup();
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
			$services->get( 'ORESThresholdLookup' ),
			new OresScoreFormatter(),
			$services->getMainConfig(),
		);
	},

];
