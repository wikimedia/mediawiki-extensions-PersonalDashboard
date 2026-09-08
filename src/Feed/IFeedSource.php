<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * One named source of feed items.
 *
 * A source answers a single question: for this viewer, which items come next?
 * It does not merge with other sources, sort across them, or build the
 * continuation token for a response. The feed endpoint owns all of that, so a
 * source stays a query and nothing more.
 *
 * A source is context-free by design. It receives an Authority, not an
 * IContextSource, because the REST endpoint that calls it has no context to
 * give. Keep message localisation and language handling out of a source.
 *
 * Register a source as an ObjectFactory spec under the
 * PersonalDashboard.FeedSources attribute. See ./docs/modules.md.
 */
interface IFeedSource {

	/**
	 * Get the next items from this source, newest first.
	 *
	 * Return at most $request->limit items. Return fewer to tell the caller that
	 * this source is exhausted.
	 *
	 * @param FeedRequest $request
	 * @return FeedSourceResult
	 */
	public function getItems( FeedRequest $request ): FeedSourceResult;

	/**
	 * Sets the source name.
	 *
	 * The factory calls this after it builds the source, so a source does not
	 * declare its own name. Items use the name to build their id.
	 *
	 * @param string $name source name
	 */
	public function setName( string $name ): void;
}
