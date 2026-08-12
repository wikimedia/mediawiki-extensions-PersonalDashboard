/**
 * @file feedHelpers.js
 *
 * Shared transformation utilities for the Review Changes feed.
 * All functions are pure and stateless so they can be reused by
 * any feed source composable and tested in isolation.
 */

/**
 * @typedef {Object} FeedItem
 * @property {string}   id          Unique identifier (e.g. "watchlist-1001")
 * @property {string}   feedorigin  Feed source key: 'watchlist' | 'recentchanges'
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
 * Tags that indicate an edit has already been reviewed and should be excluded
 * from the feed.
 *
 * @type {string[]}
 */
const EXCLUDE_TAGS = [ 'mw-reverted', 'mw-rollback', 'mw-undo' ];

/**
 * Build an empty feed response for a given source, used as a safe fallback
 * when the API returns nothing usable.
 *
 * @param {string} feed 'recentchanges' | 'watchlist'
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
 * @param {string}      feed  'recentchanges' | 'watchlist'
 * @param {Function}    parseApiStatus From ext.personalDashboard.common utils
 * @return {Object}
 */
function handleApiData( data, limit, feed, parseApiStatus ) {
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
			( change ) => !EXCLUDE_TAGS.some(
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
 * @param {Object} raw    Raw API entry (already stamped with feedorigin)
 * @param {string} source Feed source key, e.g. 'watchlist' | 'recentchanges'
 * @return {FeedItem}
 */
function normalizeFeedItem( raw, source ) {
	return {
		id: source + '-' + raw.revid,
		feedorigin: source,
		title: raw.title,
		revid: raw.revid,
		pageid: raw.pageid,
		// eslint-disable-next-line camelcase
		old_revid: raw.old_revid || null,
		user: raw.user || '',
		timestamp: raw.timestamp,
		newlen: raw.newlen || 0,
		oldlen: raw.oldlen || 0,
		parsedcomment: raw.parsedcomment || '',
		minor: Boolean( raw.minor ),
		bot: Boolean( raw.bot ),
		new: Boolean( raw.new ),
		tags: raw.tags || []
	};
}

module.exports = {
	initializeEmptyFeed,
	handleApiData,
	normalizeFeedItem
};
