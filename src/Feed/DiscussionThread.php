<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * One discussion thread: a single heading and everything said below it.
 *
 * A sub-heading is a thread in its own right as well as part of the one above
 * it, so an RfC and its "Survey" sub-section both appear, and the RfC's counts
 * include the Survey's. Both read the same as the talk page does under those
 * headings. $topic is what tells the two apart.
 */
readonly class DiscussionThread {

	/**
	 * @param string $itemId DiscussionTools' own item id for the heading,
	 *   page-scoped.
	 * @param string $section Heading text, already stripped of markup.
	 * @param string $anchor Heading's id attribute, or an empty string where
	 *   the headline carries no id.
	 * @param string|null $topic Text of the outermost heading this thread sits
	 *   under, or null where it is already one. Gives a sub-thread the topic it
	 *   belongs to, which its own heading text often does not say.
	 * @param int $commentCount Number of comments below this heading,
	 *   sub-headings included.
	 * @param int $authorCount Number of distinct authors among those comments.
	 * @param string $latestReplyTimestamp ISO-8601 timestamp of the newest of
	 *   those comments.
	 * @param string $latestReplyId DiscussionTools' item id for that comment.
	 */
	public function __construct(
		public string $itemId,
		public string $section,
		public string $anchor,
		public ?string $topic,
		public int $commentCount,
		public int $authorCount,
		public string $latestReplyTimestamp,
		public string $latestReplyId,
	) {
	}
}
