<?php

namespace MediaWiki\Extension\PersonalDashboard;

use MediaWiki\Extension\PersonalDashboard\Modules\ActiveDiscussions;
use MediaWiki\Extension\PersonalDashboard\Modules\Banner;
use MediaWiki\Extension\PersonalDashboard\Modules\Impact;
use MediaWiki\Extension\PersonalDashboard\Modules\PoliciesGuidelines;
use MediaWiki\Extension\PersonalDashboard\Modules\RiskyArticleEdits;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'ext.personalDashboard.activeDiscussions' => static function (): ActiveDiscussions {
		return new ActiveDiscussions();
	},
	'ext.personalDashboard.banner' => static function (): Banner {
		return new Banner();
	},
	'ext.personalDashboard.impact' => static function (
		MediaWikiServices $services
	): Impact {
		return new Impact( $services->getConnectionProvider() );
	},
	'ext.personalDashboard.policiesGuidelines' => static function (): PoliciesGuidelines {
		return new PoliciesGuidelines();
	},
	'ext.personalDashboard.riskyArticleEdits' => static function (
		MediaWikiServices $services
	): RiskyArticleEdits {
		return new RiskyArticleEdits( $services->getUserEditTracker() );
	},
];
