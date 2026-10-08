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
	 * @param bool $complete False when the source could not read part of what it
	 *   covers, so the items are a short answer rather than the whole one.
	 */
	public function __construct(
		public array $items,
		public bool $mayHaveMore,
		public bool $complete = true,
	) {
	}

	/**
	 * Build the result for a source that has nothing to contribute. It is
	 * complete: there was nothing to read, not something it failed to read.
	 *
	 * @return self
	 */
	public static function newEmpty(): self {
		return new self( [], false );
	}
}
