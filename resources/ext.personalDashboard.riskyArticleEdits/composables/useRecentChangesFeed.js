/**
 * @file useRecentChangesFeed.js
 *
 * Composable for fetching and processing recent changes.
 * Owns param-building, pagination, deduplication, and normalization for
 * the recentchanges source so the store action stays thin.
 */

// can't currently import with nested destruct due to our commonjs/es conversion for vitest
const { utils } = require( 'ext.personalDashboard.common' );
const { getRandomItems, handleApiErrors, parseApiStatus } = utils;
const { handleApiData, normalizeFeedItem } = require( '../utils/feedHelpers.js' );

/**
 * @typedef {import('../utils/feedHelpers.js').FeedItem} FeedItem
 */

/**
 * Build the MediaWiki API params for a recentchanges query.
 * Uses a generator so that page description data (prop: 'description')
 * is fetched in the same request, producing the `pages` array consumed
 * by <list-card> and <list-card-mobile>.
 *
 * @param {string} [offset] rccontinue token for pagination
 * @return {Promise<Object>}
 */
async function getRCParams( offset ) {
	const params = {
		action: 'query',
		prop: 'description',
		list: 'recentchanges',
		generator: 'recentchanges',
		rcnamespace: '0',
		rcexcludeuser: mw.user.getName(),
		rcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|timestamp',
		rcshow: '!bot',
		rclimit: '500',
		rctype: 'edit',
		grcnamespace: '0',
		grcexcludeuser: mw.user.getName(),
		grcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|timestamp',
		grcshow: '!bot',
		grclimit: '500',
		grctype: 'edit',
		errorformat: 'plaintext',
		errorlang: mw.util.getParamValue( 'uselang' ) || mw.config.get( 'wgUserLanguage' ),
		errorsuselocal: true,
		format: 'json',
		formatversion: '2'
	};

	const userRights = await mw.user.getRights();
	if ( offset ) {
		params.rccontinue = offset;
		params.grccontinue = offset;
	}
	if ( userRights && userRights.includes( 'patrol' ) ) {
		params.rcshow += '|unpatrolled';
		params.grcshow += '|unpatrolled';
	}
	if ( mw.config.get( 'wgPersonalDashboardRiskyArticleEditsMlEnabled' ) === true ) {
		const model = mw.config.get( 'wgPersonalDashboardRiskyArticleEditsMlModel' );
		if ( model === 'revertrisklanguageagnostic' ) {
			params.rcshow += '|revertrisklanguageagnostic';
			params.grcshow += '|revertrisklanguageagnostic';
		}
		if ( model === 'damaging' ) {
			params.rcshow += '|oresreview';
			params.grcshow += '|oresreview';
		}
	}

	return params;
}

/**
 * Merge two raw recentchanges API responses into one, combining both
 * the recentchanges list and the pages metadata from the generator.
 *
 * @param {Object} rcData
 * @param {Object} moreRcData
 * @return {Object}
 */
function mergeRecentChangeData( rcData, moreRcData ) {
	return {
		...moreRcData,
		query: {
			...moreRcData.query,
			pages: [ ...rcData.query.pages, ...moreRcData.query.pages ],
			recentchanges: [
				...rcData.query.recentchanges,
				...moreRcData.query.recentchanges
			]
		}
	};
}

/**
 * Paginate through the recentchanges API until the limit is reached or
 * the maximum number of requests is exhausted.
 *
 * @param {Object} api
 * @param {number} limit
 * @param {number} maxApiRequests
 * @param {string} offset
 * @param {Object} rcData  Initial API response to continue from
 * @return {Promise<Object>}
 */
async function getRecentChangesData( api, limit, maxApiRequests, offset, rcData ) {
	let apiRequestCount = 0;
	let data = rcData;
	while (
		offset &&
		apiRequestCount < maxApiRequests &&
		data.query.recentchanges.length < limit
	) {
		const rcParams = await getRCParams( offset );
		apiRequestCount++;
		const more = await api.get( rcParams ).then(
			( d ) => handleApiData( d, limit, 'recentchanges', parseApiStatus ),
			( code, d ) => handleApiErrors( code, d )
		);
		data = mergeRecentChangeData( data, more );
		offset = data.continue ? data.continue.rccontinue : undefined;
	}
	return data;
}

/**
 * Remove RC items whose titles appear in the watchlist set, and deduplicate
 * by title within the RC results themselves.
 *
 * @param {Object}   rcData
 * @param {string[]} wlTitles  Titles already represented by the watchlist feed
 * @return {Object}
 */
function filterOutDuplicateTitles( rcData, wlTitles ) {
	const seenTitles = new Set();
	const wlTitlesSet = new Set( wlTitles );
	const filteredChanges = rcData.query.recentchanges
		.filter( ( rc ) => wlTitles.length === 0 || !wlTitlesSet.has( rc.title ) )
		.filter( ( rc ) => {
			if ( seenTitles.has( rc.title ) ) {
				return false;
			}
			seenTitles.add( rc.title );
			return true;
		} );
	return {
		...rcData,
		query: { ...rcData.query, recentchanges: filteredChanges }
	};
}

/**
 * Fetch recent changes items, paginating and deduplicating as needed,
 * then randomly sample down to the limit and normalize to FeedItems.
 *
 * Returns both the normalized feed items and the raw pages array from
 * the generator query, since pages is consumed separately by the card
 * components and does not fit the FeedItem shape.
 *
 * @param {number}   limit          Per-feed item limit
 * @param {number}   maxApiRequests Maximum pagination requests
 * @param {string[]} wlTitles       Titles to exclude (already in watchlist feed)
 * @return {Promise<{ items: FeedItem[], pages: Object[] }>}
 */
async function fetchRecentChangesItems( limit, maxApiRequests, wlTitles ) {
	const api = new mw.Api();
	const rcParams = await getRCParams();
	let data = await api.get( rcParams ).then(
		( d ) => handleApiData( d, limit, 'recentchanges', parseApiStatus ),
		( code, d ) => handleApiErrors( code, d )
	);

	data = filterOutDuplicateTitles( data, wlTitles );

	if ( data.query.recentchanges.length < limit ) {
		const offset = data.continue ? data.continue.rccontinue : undefined;
		data = await getRecentChangesData( api, limit, maxApiRequests, offset, data );
		data = filterOutDuplicateTitles( data, wlTitles );
	}

	const sampled = getRandomItems( data.query.recentchanges, limit );
	return {
		items: sampled.map( ( item ) => normalizeFeedItem( item, 'recentchanges' ) ),
		pages: data.query.pages || []
	};
}

function useRecentChangesFeed() {
	return { fetchRecentChangesItems };
}

module.exports = { useRecentChangesFeed };
