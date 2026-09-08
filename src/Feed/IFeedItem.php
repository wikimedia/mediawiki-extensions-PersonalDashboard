<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * One item in a feed.
 *
 * The contract is deliberately small. A recent change and a talk page thread
 * have no fields in common, so this interface holds only what the platform
 * itself needs: an identity to key the list on, a timestamp to merge on, a
 * cursor to resume from, and the payload the module's card renders. Everything
 * else belongs to the source that made the item.
 */
interface IFeedItem {

	/**
	 * Get a unique identifier for this item.
	 *
	 * The client keys its list on this value, so it must be unique across every
	 * source in one response. Prefix it with the source name to guarantee that.
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Get the time this item happened, as an ISO-8601 string.
	 *
	 * The merge orders items on this value, so every source must use the same
	 * format. String comparison must order two timestamps correctly.
	 *
	 * @return string
	 */
	public function getTimestamp(): string;

	/**
	 * Get the cursor that resumes this source directly after this item.
	 *
	 * The value is opaque to everything but the source that made it. The
	 * endpoint keeps the cursor of the last item it took from each source, and
	 * gives it back to that source on the next request.
	 *
	 * @return string
	 */
	public function getCursor(): string;

	/**
	 * Get this item in the shape the client renders.
	 *
	 * @return array
	 */
	public function toArray(): array;
}
