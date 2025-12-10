<?php

namespace MediaWiki\Extension\PersonalDashboard\HookHandler;

use MediaWiki\Preferences\Hook\GetPreferencesHook;

class GetPreferencesHandler implements GetPreferencesHook {
	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['personaldashboard-onboarding'] = [ 'type' => 'api' ];
	}
}
