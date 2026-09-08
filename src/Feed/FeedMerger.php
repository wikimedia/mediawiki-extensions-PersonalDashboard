<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * Combines what several sources returned into one page of feed items.
 *
 * This is the PHP side of selectEvenlyAcrossFeeds() in
 * resources/ext.personalDashboard.reviewChanges/utils/feedHelpers.js, which the
 * client used to run over three separate API responses. The unit tests port that
 * function's cases one for one, so the two stay equivalent while both exist.
 */
class FeedMerger {

	/**
	 * Select up to $limit items, spread as evenly as possible across the sources.
	 *
	 * Sources are drawn round-robin, newest first within each, so every source
	 * gets an equal share rather than the busiest one crowding the others out. A
	 * source that runs dry drops out of the rotation and the others take up its
	 * share, so a quiet watchlist does not leave the feed short. When the limit
	 * does not divide evenly the earlier sources get the extra slots, so pass
	 * them in priority order.
	 *
	 * Items are deduplicated on IFeedItem::getDedupKey(). A source that loses an
	 * item that way draws its next one instead, so its share stays intact.
	 *
	 * @param array<string,FeedSourceResult> $bySource Results by source name, in
	 *   priority order.
	 * @param int $limit Maximum number of items to return.
	 * @return FeedMergeResult
	 */
	public function merge( array $bySource, int $limit ): FeedMergeResult {
		$names = array_keys( $bySource );

		// Sort defensively. A source promises newest first, but ordering is this
		// class's whole job, so it should not rest on that promise. Copying the
		// items first also keeps the caller's arrays untouched.
		$queues = [];
		foreach ( $names as $name ) {
			$items = $bySource[$name]->items;
			usort( $items, self::byTimestampDesc( ... ) );
			$queues[$name] = $items;
		}

		$positions = array_fill_keys( $names, 0 );
		// The last item the merge moved past, whether it went into the page or
		// was skipped as a duplicate. Not the last item selected: a source that
		// only ever skipped still has to resume after what it skipped.
		$lastConsumed = array_fill_keys( $names, null );
		$seenKeys = [];
		$selected = [];

		$drewAnItem = true;
		while ( count( $selected ) < $limit && $drewAnItem ) {
			$drewAnItem = false;

			foreach ( $names as $name ) {
				if ( count( $selected ) >= $limit ) {
					break;
				}

				// Skip past anything another source already contributed, and
				// count it as consumed. A key only enters $seenKeys when an item
				// is selected, so anything skipped here duplicates something
				// already in this page and is genuinely represented. Leaving the
				// cursor behind it would offer it again on the next page, where
				// $seenKeys is empty and nothing would stop it.
				while (
					$positions[$name] < count( $queues[$name] ) &&
					self::isDuplicate( $queues[$name][$positions[$name]], $seenKeys )
				) {
					$lastConsumed[$name] = $queues[$name][$positions[$name]];
					$positions[$name]++;
				}

				if ( $positions[$name] >= count( $queues[$name] ) ) {
					continue;
				}

				$item = $queues[$name][$positions[$name]++];
				$key = $item->getDedupKey();
				if ( $key !== null ) {
					$seenKeys[$key] = true;
				}

				$selected[] = $item;
				$lastConsumed[$name] = $item;
				$drewAnItem = true;
			}
		}

		usort( $selected, self::byTimestampDesc( ... ) );

		$cursors = [];
		$hasMore = [];
		foreach ( $names as $name ) {
			$cursors[$name] = $lastConsumed[$name]?->getCursor();
			// Anything left in the queue comes back on the next request, because
			// the cursor resumes before it. Only when nothing is left does the
			// source's own answer decide.
			$hasMore[$name] = $positions[$name] < count( $queues[$name] )
				|| $bySource[$name]->mayHaveMore;
		}

		return new FeedMergeResult( $selected, $cursors, $hasMore );
	}

	/**
	 * @param IFeedItem $item
	 * @param array<string,true> $seenKeys
	 * @return bool
	 */
	private static function isDuplicate( IFeedItem $item, array $seenKeys ): bool {
		$key = $item->getDedupKey();
		return $key !== null && isset( $seenKeys[$key] );
	}

	/**
	 * Order two items newest first.
	 *
	 * Timestamps are ISO-8601, so comparing them as strings orders them
	 * correctly. PHP sorts are stable, so items sharing a timestamp keep the
	 * order the round-robin put them in.
	 *
	 * @param IFeedItem $a
	 * @param IFeedItem $b
	 * @return int
	 */
	private static function byTimestampDesc( IFeedItem $a, IFeedItem $b ): int {
		return strcmp( $b->getTimestamp(), $a->getTimestamp() );
	}
}
