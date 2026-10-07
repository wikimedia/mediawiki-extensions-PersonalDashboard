<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\Page\ProperPageIdentity;

/**
 * Discussion threads on a page, for the Active Discussions feed.
 *
 * This interface exists so the rest of the extension never names a
 * DiscussionTools class. PersonalDashboard does not require DiscussionTools, so
 * the implementation behind it is either the real one or one that finds
 * nothing, and a caller cannot tell.
 *
 * A thread is any heading with a reply below it, counted the way the talk page
 * counts it. So the list is not a partition of the page: a sub-heading is a
 * thread of its own and is also part of the one above it, whose counts include
 * its. Totalling the list double-counts. $topic is what tells a sub-thread
 * apart from a thread.
 */
interface IDiscussionThreadLookup {

	/**
	 * Get the discussion threads on a page.
	 *
	 * @param ProperPageIdentity $page
	 * @return DiscussionThreadResult
	 */
	public function getThreadsForPage( ProperPageIdentity $page ): DiscussionThreadResult;
}
