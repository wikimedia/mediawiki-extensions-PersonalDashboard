/**
 * @file useRecentlyEditedFeed.js
 *
 * Composable for processing changes to pages recently edited by the user.
 * Owns param-building, pagination, deduplication, and normalization for
 * the recentlyedited source so the store action stays thin.
 */

// can't currently import with nested destruct due to our commonjs/es conversion for vitest
const { utils } = require( 'ext.personalDashboard.common' );
const { getRandomItems } = utils;
const { normalizeFeedItem } = require( '../utils/feedHelpers.js' );

/**
 * @typedef {import('../utils/feedHelpers.js').FeedItem} FeedItem
 */

/**
 * Fetch a random sample of edits to pages recently edited by the user.
 * Data is prefetched serverside for performance.
 *
 * @param {number} limit Per-feed item limit.
 * @return {FeedItem[]}
 */
function fetchRecentlyEditedItems( limit ) {
	const recentEdits = mw.config.get( 'wgPersonalDashboardRecentlyEditedItems', [] );
	const sampled = getRandomItems( recentEdits, limit );
	return sampled.map( ( item ) => normalizeFeedItem( item, 'recentlyedited' ) );
}

function useRecentlyEditedFeed() {
	return { fetchRecentlyEditedItems };
}

module.exports = { useRecentlyEditedFeed };
