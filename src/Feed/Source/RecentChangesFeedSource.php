<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed\Source;

use MediaWiki\Extension\PersonalDashboard\Feed\ChangesListFeedSource;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQuery;

/**
 * Recent edits from across the wiki.
 *
 * The widest of the three sources, and the only one that adds nothing to the
 * shared query. It is what remains when the viewer has no watchlist and no edit
 * history to draw on, and it is the source a depersonalized Review Changes falls
 * back to.
 */
class RecentChangesFeedSource extends ChangesListFeedSource {

	/**
	 * One row per page, newest first.
	 *
	 * The client used to fetch every revision and then drop repeated titles in
	 * JavaScript, which spent items out of a limit it had already paid for. The
	 * database can answer the question directly.
	 *
	 * @inheritDoc
	 */
	protected function applySourceFilters(
		ChangesListQuery $query,
		FeedRequest $request
	): bool {
		$query->excludeOldRevisions();

		return true;
	}
}
