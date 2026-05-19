/**
 * @file useWatchlistFeed.js
 *
 * Composable for fetching and processing watchlist changes.
 * Owns param-building, pagination, and normalization for the watchlist
 * source so the store action stays thin.
 */

// can't currently import with nested destruct due to our commonjs/es conversion for vitest
const { utils } = require( 'ext.personalDashboard.common' );
const { getRandomItems, handleApiErrors, parseApiStatus } = utils;
const { handleApiData, normalizeFeedItem } = require( '../utils/feedHelpers.js' );

/**
 * @typedef {import('../utils/feedHelpers.js').FeedItem} FeedItem
 */

/**
 * Build the MediaWiki API params for a watchlist query.
 *
 * @param {string} [offset] wlcontinue token for pagination
 * @return {Promise<Object>}
 */
async function getWLParams( offset ) {
	const params = {
		action: 'query',
		format: 'json',
		list: 'watchlist',
		formatversion: '2',
		wlnamespace: '0',
		wllimit: 100,
		wlexcludeuser: mw.user.getName(),
		wlprop: 'ids|title|flags|oresscores|parsedcomment|user|sizes|timestamp|tags',
		wlshow: '!bot',
		wltype: 'edit'
	};

	if ( offset ) {
		params.wlcontinue = offset;
	}

	const userRights = await mw.user.getRights();
	if ( userRights && userRights.includes( 'patrol' ) ) {
		params.wlshow += '|!patrolled';
	}

	return params;
}

/**
 * Merge two raw watchlist API responses into one.
 *
 * @param {Object} watchlistData
 * @param {Object} moreWatchlistData
 * @return {Object}
 */
function mergeWatchlistData( watchlistData, moreWatchlistData ) {
	return {
		...moreWatchlistData,
		query: {
			...moreWatchlistData.query,
			watchlist: [
				...watchlistData.query.watchlist,
				...moreWatchlistData.query.watchlist
			]
		}
	};
}

/**
 * Paginate through the watchlist API until the limit is reached or
 * the maximum number of requests is exhausted.
 *
 * @param {Object} api
 * @param {number} limit
 * @param {number} maxApiRequests
 * @param {string} offset
 * @param {Object} wlData  Initial API response to continue from
 * @return {Promise<Object>}
 */
async function getWatchListData( api, limit, maxApiRequests, offset, wlData ) {
	let apiRequestCount = 0;
	let data = wlData;
	while (
		offset &&
		apiRequestCount < maxApiRequests &&
		data.query.watchlist.length < limit
	) {
		const wlParams = await getWLParams( offset );
		apiRequestCount++;
		const more = await api.get( wlParams ).then(
			( d ) => handleApiData( d, limit, 'watchlist', parseApiStatus ),
			( code, d ) => handleApiErrors( code, d )
		);
		data = mergeWatchlistData( data, more );
		offset = data.continue ? data.continue.wlcontinue : undefined;
	}
	return data;
}

/**
 * Fetch watchlist items, paginating if needed, then randomly sample
 * down to the per-feed limit and normalize to FeedItems.
 *
 * @param {number} limit          Per-feed item limit
 * @param {number} maxApiRequests Maximum pagination requests
 * @return {Promise<FeedItem[]>}
 */
async function fetchWatchlistItems( limit, maxApiRequests ) {
	const api = new mw.Api();
	const wlParams = await getWLParams();
	let data = await api.get( wlParams ).then(
		( d ) => handleApiData( d, limit, 'watchlist', parseApiStatus ),
		( code, d ) => handleApiErrors( code, d )
	);

	const shouldFetchMore = data.query.watchlist.length > 0 &&
		data.query.watchlist.length < limit;
	if ( shouldFetchMore ) {
		const offset = data.continue ? data.continue.wlcontinue : undefined;
		data = await getWatchListData( api, limit, maxApiRequests, offset, data );
	}

	const sampled = getRandomItems( data.query.watchlist, limit );
	return sampled.map( ( item ) => normalizeFeedItem( item, 'watchlist' ) );
}

function useWatchlistFeed() {
	return { fetchWatchlistItems };
}

module.exports = { useWatchlistFeed };
