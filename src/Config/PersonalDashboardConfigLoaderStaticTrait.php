<?php

namespace MediaWiki\Extension\PersonalDashboard\Config;

use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\PersonalDashboardServices;
use MediaWiki\MediaWikiServices;

trait PersonalDashboardConfigLoaderStaticTrait {
	private static function getPersonalDashboardWikiConfig(): Config {
		return PersonalDashboardServices::wrap(
			MediaWikiServices::getInstance()
		)->getPersonalDashboardWikiConfig();
	}

	private static function getPersonalDashboardConfig(): Config {
		return PersonalDashboardServices::wrap(
			MediaWikiServices::getInstance()
		)->getPersonalDashboardConfig();
	}
}
