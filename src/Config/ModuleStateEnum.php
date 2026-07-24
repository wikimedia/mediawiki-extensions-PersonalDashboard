<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Config;

use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;

/**
 * Enum representing the state of a PersonalDashboard moudle.
 *
 * @see BaseModule::getState()
 */
enum ModuleStateEnum: string {
	case UNKNOWN = '';
	case COMPLETE = 'complete';
	case INCOMPLETE = 'incomplete';
	case ACTIVATED = 'activated';
	case UNACTIVATED = 'unactivated';
	case UNCONFIRMED = 'unconfirmed';
	case NOT_RENDERED = 'notrendered';
}
