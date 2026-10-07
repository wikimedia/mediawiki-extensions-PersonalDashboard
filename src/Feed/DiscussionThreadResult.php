<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * What the discussion thread lookup found for one page.
 *
 * $complete is false when the lookup could not be sure: a cold parser cache
 * (nothing to probe without triggering a synchronous parse) or a caught parse
 * failure. A caller sees an empty list either way; $complete says whether that
 * emptiness is a real answer or just a gap to try again later.
 */
readonly class DiscussionThreadResult {

	/**
	 * @param DiscussionThread[] $threads
	 * @param bool $complete False when the result could not be fully computed
	 *   and may be stale or incomplete.
	 */
	public function __construct(
		public array $threads,
		public bool $complete,
	) {
	}

	/** A real, final answer: this page has no threads to show. */
	public static function newEmpty(): self {
		return new self( [], true );
	}

	/** A placeholder for when the probe or the parse could not be trusted. */
	public static function newDegraded(): self {
		return new self( [], false );
	}
}
