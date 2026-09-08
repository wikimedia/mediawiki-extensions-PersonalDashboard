<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * One page of a merged feed, and what the endpoint needs to ask for the next.
 *
 * The cursors are the reason this is not a bare array of items. A source hands
 * back more items than the merge uses, so the next page has to resume from the
 * last item the merge consumed, not the last one the source offered.
 */
readonly class FeedMergeResult {

	/**
	 * @param IFeedItem[] $items The selection, newest first.
	 * @param array<string,string|null> $cursors Source name to the cursor of the
	 *   last item the merge consumed from it — selected, or skipped because
	 *   another source had already contributed the same thing. Null when the
	 *   merge never reached that source at all, and then the endpoint keeps
	 *   whatever cursor it already had for it.
	 * @param array<string,bool> $hasMore Source name to whether asking it again
	 *   may return something. A source is only reported exhausted when the merge
	 *   used everything it offered and the source itself reported no more.
	 */
	public function __construct(
		public array $items,
		public array $cursors,
		public array $hasMore,
	) {
	}
}
