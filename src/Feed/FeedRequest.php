<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\Permissions\Authority;

/**
 * What the feed endpoint asks one source for.
 *
 * The endpoint divides its own limit between the sources it was asked for, so
 * $limit is this source's share and not the size of the response.
 */
readonly class FeedRequest {

	/**
	 * @param Authority $authority The viewer. A source filters its results for
	 *   this authority; it never assumes the global user.
	 * @param int $limit Maximum number of items to return.
	 * @param ?string $cursor The cursor of the last item the endpoint took
	 *   from this source, or null to start at the newest item. A source made
	 *   this value itself, in IFeedItem::getCursor().
	 */
	public function __construct(
		public Authority $authority,
		public int $limit,
		public ?string $cursor = null,
	) {
	}
}
