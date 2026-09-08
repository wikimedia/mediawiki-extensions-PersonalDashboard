<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed\Source;

use MediaWiki\Extension\PersonalDashboard\Feed\ChangesListFeedSource;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQuery;

/**
 * Recent edits to pages on the viewer's watchlist.
 *
 * Entirely viewer-relative: an anonymous or watchlist-less viewer gets nothing
 * from this source, and the merge gives its share to the others.
 */
class WatchlistFeedSource extends ChangesListFeedSource {

	/** @inheritDoc */
	protected function applySourceFilters(
		ChangesListQuery $query,
		FeedRequest $request
	): bool {
		$user = $request->authority->getUser();
		if ( !$user->isRegistered() ) {
			return false;
		}

		$query->watchlistUser( $user )
			->requireWatched()
			// list=watchlist does this by default, through allrev=false. The
			// client relied on that default, so the port keeps it.
			->excludeOldRevisions();

		return true;
	}
}
