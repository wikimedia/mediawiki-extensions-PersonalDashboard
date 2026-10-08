<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * A feed item that describes one discussion thread.
 *
 * The field names are DiscussionTools' own vocabulary, so a client that reads
 * `action=discussiontoolspageinfo` reads these too. `title` is the page, as in
 * every other source; the heading's own text is `section`.
 */
readonly class DiscussionThreadFeedItem implements IFeedItem {

	/**
	 * @param string $source Name of the source that made this item.
	 * @param string $title Prefixed title of the page the discussion is on.
	 * @param int $pageId Page id. The order and the cursor also use it to break
	 *   ties between pages.
	 * @param DiscussionThread $thread
	 */
	public function __construct(
		private string $source,
		private string $title,
		private int $pageId,
		private DiscussionThread $thread,
	) {
	}

	/**
	 * The page id joins the item id because DiscussionTools scopes an item id to
	 * its page, so two talk pages can share one.
	 *
	 * @inheritDoc
	 */
	public function getId(): string {
		return $this->source . '-' . $this->pageId . '-' . $this->thread->itemId;
	}

	/** @inheritDoc */
	public function getTimestamp(): string {
		return $this->thread->latestReplyTimestamp;
	}

	/**
	 * The ordering this source puts its items in: newest reply first, then page,
	 * then heading.
	 *
	 * A signature carries whole minutes, so two threads sharing a timestamp is
	 * the common case rather than the edge one, and an item id repeats across
	 * pages. Both tiebreakers are needed for an order the next request can
	 * resume from.
	 *
	 * @return array{0:string,1:int,2:string}
	 */
	public function getSortKey(): array {
		return [ $this->thread->latestReplyTimestamp, $this->pageId, $this->thread->itemId ];
	}

	/**
	 * The sort key, encoded. rawurlencode() is not optional: an item id is built
	 * from heading text and author names, so it can carry the separator itself.
	 *
	 * @inheritDoc
	 */
	public function getCursor(): string {
		return $this->thread->latestReplyTimestamp
			. '|' . $this->pageId
			. '|' . rawurlencode( $this->thread->itemId );
	}

	/**
	 * Nothing. One thread among many on a page is already unique, and no other
	 * source returns threads for the merge to confuse one with.
	 *
	 * @inheritDoc
	 */
	public function getDedupKey(): ?string {
		return null;
	}

	/** @inheritDoc */
	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'feedorigin' => $this->source,
			'title' => $this->title,
			'pageid' => $this->pageId,
			'timestamp' => $this->thread->latestReplyTimestamp,
			'section' => $this->thread->section,
			'anchor' => $this->thread->anchor,
			'topic' => $this->thread->topic,
			'commentCount' => $this->thread->commentCount,
			'authorCount' => $this->thread->authorCount,
			'latestReplyId' => $this->thread->latestReplyId,
		];
	}
}
