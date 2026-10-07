<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\Page\ProperPageIdentity;

/**
 * The discussion thread lookup used on a wiki without DiscussionTools.
 *
 * PersonalDashboard does not require DiscussionTools, so thread data has to be
 * absent rather than broken. Returning nothing here means a caller works
 * unchanged whether or not the extension is installed.
 */
class NullDiscussionThreadLookup implements IDiscussionThreadLookup {

	/** @inheritDoc */
	public function getThreadsForPage( ProperPageIdentity $page ): DiscussionThreadResult {
		return DiscussionThreadResult::newEmpty();
	}
}
