<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * Combines what several sources returned into one page of feed items.
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
	 * @param string[] $alreadyServed Dedup hashes carried by the continuation
	 *   token, naming what the page before this one served. A source that still
	 *   holds a copy of one of those is moved past it rather than offering it
	 *   again, because another source already showed it.
	 * @return FeedMergeResult
	 */
	public function merge(
		array $bySource,
		int $limit,
		array $alreadyServed = []
	): FeedMergeResult {
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
		// What the page before this one served comes in hashed. Nothing reads a
		// key back out, so the merge only ever compares hashes.
		$seenKeys = array_fill_keys( $alreadyServed, true );
		$servedKeys = [];
		$selected = [];

		$drewAnItem = true;
		while ( count( $selected ) < $limit && $drewAnItem ) {
			$drewAnItem = false;

			foreach ( $names as $name ) {
				if ( count( $selected ) >= $limit ) {
					break;
				}

				// Skip past anything already served, and count it as consumed.
				// $seenKeys holds what this page selected, plus what the token
				// says the page before it served, so anything skipped here is
				// already with the client. Leaving the cursor behind it would
				// offer it again on a later page.
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
					$hash = self::hashKey( $key );
					$seenKeys[$hash] = true;
					$servedKeys[$hash] = true;
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

		return new FeedMergeResult(
			$selected,
			$cursors,
			$hasMore,
			// strval() because PHP turns an array key that looks like a number
			// into one, and about one hash in forty is all digits. The token
			// packs these as hex, which wants a string.
			array_map( 'strval', array_keys( $servedKeys ) )
		);
	}

	/**
	 * @param IFeedItem $item
	 * @param array<string,true> $seenKeys
	 * @return bool
	 */
	private static function isDuplicate( IFeedItem $item, array $seenKeys ): bool {
		$key = $item->getDedupKey();
		return $key !== null && isset( $seenKeys[ self::hashKey( $key ) ] );
	}

	/**
	 * Shorten a dedup key so a page of them fits in a continuation token.
	 *
	 * The token rides in a query string and has a size limit, so it names what
	 * was served by hash rather than by key. Eight hex characters is enough:
	 * nothing reads a key back, and the cost of a collision is one item left out
	 * of one page. At the maximum of 50 keys the chance of that is about 1 in 4
	 * million.
	 *
	 * @param string $key
	 * @return string
	 */
	private static function hashKey( string $key ): string {
		return substr( hash( 'sha256', $key ), 0, 8 );
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
