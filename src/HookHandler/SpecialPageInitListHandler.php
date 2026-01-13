<?php

namespace MediaWiki\Extension\PersonalDashboard\HookHandler;

use MediaWiki\Extension\PersonalDashboard\Specials\SpecialPersonalDashboard;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

class SpecialPageInitListHandler implements SpecialPage_initListHook {

	/**
	 * @inheritDoc
	 */
	public function onSpecialPage_initList( &$list ) {
		$list['PersonalDashboard'] = [
			'class' => SpecialPersonalDashboard::class,
			'services' => [
				'UserOptionsManager',
				'PersonalDashboardModuleRegistry',
				'StatsFactory',
			]
		];
	}
}
