<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * A feed item that describes one edit.
 *
 * Every source backed by the recentchanges table returns these, so the watchlist
 * source, the recent changes source and the recently edited source all render
 * through one card. The field names match the FeedItem shape the client already
 * consumes, so the payload needs no translation in the browser.
 *
 * The one addition is `description`: the client used to recover it from a
 * separate `pages` array that the recentchanges generator returned alongside the
 * feed, and scan that array once per card. The field belongs on the item.
 */
readonly class RecentChangeFeedItem implements IFeedItem {

	/**
	 * @param string $source Name of the source that made this item.
	 * @param int $rcId recentchanges row id. The client never sees it; the
	 *   cursor uses it to break ties between two edits at the same second.
	 * @param string $title Prefixed page title.
	 * @param int $revid Revision id of this edit.
	 * @param int $pageid Page id.
	 * @param int|null $oldRevid Parent revision id, or null for a page creation.
	 * @param string $user Editor username or IP address.
	 * @param string $timestamp ISO-8601 timestamp.
	 * @param int $newlen Page size after the edit, in bytes.
	 * @param int $oldlen Page size before the edit, in bytes.
	 * @param string $parsedcomment Edit summary, as parsed HTML. Empty when the
	 *   summary is suppressed and the viewer cannot see it.
	 * @param string $description Short page description, or an empty string.
	 * @param bool $minor Whether the editor flagged the edit as minor.
	 * @param bool $bot Whether a bot made the edit.
	 * @param bool $new Whether the edit created the page.
	 * @param string[] $tags Change tags on the edit.
	 */
	public function __construct(
		private string $source,
		private int $rcId,
		private string $title,
		private int $revid,
		private int $pageid,
		private ?int $oldRevid,
		private string $user,
		private string $timestamp,
		private int $newlen,
		private int $oldlen,
		private string $parsedcomment,
		private string $description,
		private bool $minor,
		private bool $bot,
		private bool $new,
		private array $tags,
	) {
	}

	/** @inheritDoc */
	public function getId(): string {
		return $this->source . '-' . $this->revid;
	}

	/** @inheritDoc */
	public function getTimestamp(): string {
		return $this->timestamp;
	}

	/**
	 * The timestamp alone cannot resume a query: several edits can share one
	 * second, and the query would then repeat them or skip them. The row id
	 * breaks the tie.
	 *
	 * @inheritDoc
	 */
	public function getCursor(): string {
		return $this->timestamp . '|' . $this->rcId;
	}

	/** @inheritDoc */
	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'feedorigin' => $this->source,
			'title' => $this->title,
			'revid' => $this->revid,
			'pageid' => $this->pageid,
			'old_revid' => $this->oldRevid,
			'user' => $this->user,
			'timestamp' => $this->timestamp,
			'newlen' => $this->newlen,
			'oldlen' => $this->oldlen,
			'parsedcomment' => $this->parsedcomment,
			'description' => $this->description,
			'minor' => $this->minor,
			'bot' => $this->bot,
			'new' => $this->new,
			'tags' => $this->tags,
		];
	}
}
