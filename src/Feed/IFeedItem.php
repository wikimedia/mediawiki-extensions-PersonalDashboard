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
	 * Get a value naming the thing this item is about, so the merge shows that
	 * thing once.
	 *
	 * Two sources can legitimately return the same subject: a page you edited
	 * that you also watch reaches the feed from both. The IDs differ, because
	 * each source prefixes its own, and the revisions can differ too, so only
	 * the source knows what makes two of its items the same. The merge keeps
	 * the first item with a given key and lets the losing source draw its next
	 * one instead, so that source keeps its share of the feed.
	 *
	 * Return null to never be merged away. That is the right answer for an item
	 * that is already unique, such as one discussion thread among many on one
	 * page.
	 *
	 * @return ?string
	 */
	public function getDedupKey(): ?string;

	/**
	 * Get this item in the shape the client renders.
	 *
	 * @return array
	 */
	public function toArray(): array;
}
