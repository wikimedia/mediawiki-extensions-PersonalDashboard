/**
 * @file feedHelpers.js
 *
 * Shared transformation utilities for the Review Changes feed.
 * All functions are pure and stateless so they can be reused by
 * any feed source composable and tested in isolation.
 */

/**
 * @typedef {'recentchanges'|'watchlist'|'recentlyedited'} FeedSource
 */

/**
 * @typedef {Object} FeedItem
 * @property {string}   id          Unique identifier (e.g. "watchlist-1001")
 * @property {FeedSource} feedorigin Feed source key
 * @property {string}   title       Page title
 * @property {number}   revid       Revision ID
 * @property {number}   pageid      Page ID
 * @property {number}   old_revid   Parent revision ID
 * @property {string}   user        Editor username or IP
 * @property {string}   timestamp   ISO-8601 timestamp string
 * @property {number}   newlen      Page size after edit, in bytes
 * @property {number}   oldlen      Page size before edit, in bytes
 * @property {string}   parsedcomment Edit summary (parsed HTML)
 * @property {boolean}  minor       Whether the edit is flagged as minor
 * @property {boolean}  bot         Whether the edit was made by a bot
 * @property {boolean}  new         Whether this edit created the page
 * @property {string[]} tags        Change tags applied to the edit
 */

/**
 * Build an empty feed response for a given source, used as a safe fallback
 * when the API returns nothing usable.
 *
 * @param {FeedSource} feed
 * @return {Object}
 */
function initializeEmptyFeed( feed ) {
	return feed === 'recentchanges' ?
		{ query: { recentchanges: [], pages: [] } } :
		{ query: { watchlist: [] } };
}

/**
 * Process a raw API response for a given feed source:
 * - Logs any API warnings
 * - Falls back to an empty feed if the response is unusable
 * - Excludes items with reviewed-edit tags
 * - Stamps each item with a feedorigin property
 *
 * @param {Object|null} data  Raw API response
 * @param {number}      limit Maximum number of items (used only for fallback context)
 * @param {FeedSource}  feed
 * @param {Function}    parseApiStatus From ext.personalDashboard.common utils
 * @return {Object}
 */
function handleApiData( data, limit, feed, parseApiStatus ) {
	/**
	 * Tags that indicate an edit has already been reviewed and should be excluded
	 * from the feed.
	 *
	 * @type {string[]}
	 */
	const EXCLUDED_TAGS = mw.config.get( 'wgPersonalDashboardReviewChangesExcludedTags' );

	if ( !data ) {
		return initializeEmptyFeed( feed );
	}

	if ( data.warnings ) {
		const warnings = parseApiStatus( data.warnings );
		if ( warnings.length > 0 ) {
			mw.log.warn( warnings.join( '\n' ) );
		}
	}

	if ( !data.query || !Array.isArray( data.query[ feed ] ) ) {
		return initializeEmptyFeed( feed );
	}

	const filteredResultsWithFeedOrigin = data.query[ feed ]
		.filter(
			( change ) => !EXCLUDED_TAGS.some(
				( tag ) => ( change.tags || [] ).includes( tag )
			)
		)
		.map( ( result ) => ( { ...result, feedorigin: feed } ) );

	return {
		...data,
		query: {
			...data.query,
			[ feed ]: filteredResultsWithFeedOrigin
		}
	};
}

/**
 * Normalize a raw MediaWiki API entry into a FeedItem.
 * Handles both watchlist and recentchanges responses since both APIs
 * return the same field names when equivalent props are requested.
 *
 * @param {Object} raw Raw API entry (already stamped with feedorigin)
 * @param {FeedSource} source
 * @return {FeedItem}
 */
function normalizeFeedItem( raw, source ) {
	return {
		id: source + '-' + raw.revid,
		feedorigin: source,
		title: raw.title,
		revid: Number( raw.revid ),
		pageid: Number( raw.pageid ),
		// eslint-disable-next-line camelcase
		old_revid: Number( raw.old_revid ) || null,
		user: raw.user || '',
		timestamp: raw.timestamp,
		newlen: Number( raw.newlen ) || 0,
		oldlen: Number( raw.oldlen ) || 0,
		parsedcomment: raw.parsedcomment || '',
		minor: Boolean( raw.minor ),
		bot: Boolean( raw.bot ),
		new: Boolean( raw.new ),
		tags: raw.tags || []
	};
}

/**
 * Order feed items newest first.
 *
 * @param {FeedItem} a
 * @param {FeedItem} b
 * @return {number}
 */
function byTimestampDesc( a, b ) {
	return b.timestamp.localeCompare( a.timestamp );
}

/**
 * Select up to `limit` items spread as evenly as possible across the feed sources.
 *
 * Sources are drawn from round-robin — newest first within each source — so every
 * source gets an equal share of the feed rather than whichever source happens to
 * be busiest crowding the others out. A source that runs dry drops out of the
 * rotation and the others take up its share, so a quiet watchlist doesn't leave
 * the feed short.
 *
 * Titles are deduplicated across sources: the same page can legitimately show up
 * in more than one source (a page you edited that is also on your watchlist), and
 * a source that loses an item this way draws its next one instead, so its share
 * stays intact.
 *
 * When the limit doesn't divide evenly the earlier sources get the extra slots,
 * so pass them in priority order.
 *
 * @param {FeedItem[][]} sources One array of items per feed source, in priority order.
 * @param {number} limit Maximum number of items to return.
 * @return {FeedItem[]} Selected items, newest first.
 */
function selectEvenlyAcrossFeeds( sources, limit ) {
	const queues = sources.map( ( items ) => items.slice().sort( byTimestampDesc ) );
	const cursors = queues.map( () => 0 );
	const seenTitles = new Set();
	const selected = [];

	let drewAnItem = true;
	while ( selected.length < limit && drewAnItem ) {
		drewAnItem = false;

		for ( let i = 0; i < queues.length && selected.length < limit; i++ ) {
			// Skip past anything another source already contributed.
			while (
				cursors[ i ] < queues[ i ].length &&
				seenTitles.has( queues[ i ][ cursors[ i ] ].title )
			) {
				cursors[ i ]++;
			}

			if ( cursors[ i ] >= queues[ i ].length ) {
				continue;
			}

			const item = queues[ i ][ cursors[ i ]++ ];
			seenTitles.add( item.title );
			selected.push( item );
			drewAnItem = true;
		}
	}

	return selected.sort( byTimestampDesc );
}

module.exports = {
	initializeEmptyFeed,
	handleApiData,
	normalizeFeedItem,
	selectEvenlyAcrossFeeds
};
