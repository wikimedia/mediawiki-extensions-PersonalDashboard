<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * What one source returns for one request.
 *
 * The endpoint merges the items and decides how many of them it uses. It also
 * decides whether to ask this source again, so a source reports only whether
 * more items may exist.
 */
readonly class FeedSourceResult {

	/**
	 * @param IFeedItem[] $items Newest first. At most the requested limit.
	 * @param bool $mayHaveMore Whether more items may follow the last one here.
	 *   This is a hint, not a promise: a source that cannot tell cheaply must
	 *   report true, and the next request may then return nothing.
	 */
	public function __construct(
		public array $items,
		public bool $mayHaveMore,
	) {
	}

	/**
	 * Build the result for a source that has nothing to contribute.
	 *
	 * @return self
	 */
	public static function newEmpty(): self {
		return new self( [], false );
	}
}
