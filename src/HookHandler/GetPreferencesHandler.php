<?php

namespace MediaWiki\Extension\PersonalDashboard\HookHandler;

use MediaWiki\Preferences\Hook\GetPreferencesHook;

class GetPreferencesHandler implements GetPreferencesHook {
	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['personaldashboard-eligible'] = [ 'type' => 'api' ];
		$preferences['personaldashboard-visited'] = [ 'type' => 'api' ];
		$preferences['personaldashboard-risky-articles-info'] = [ 'type' => 'api' ];
	}
}
