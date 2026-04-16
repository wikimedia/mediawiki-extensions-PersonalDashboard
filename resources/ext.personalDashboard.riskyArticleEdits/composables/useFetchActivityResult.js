const { ref } = require( 'vue' );

const recentActivityResult = ref( null );
const loading = ref( false );
const error = ref( null );
// can't currently import with nested destruct due to our commonjs/es conversion for vitest
const { utils } = require( 'ext.personalDashboard.common' );
const { getRandomItems, handleApiErrors, parseApiStatus } = utils;
const NUM_FEEDS = 2;
const MAX_API_REQUESTS = 10;

function initializeEmptyFeed( feed ) {
	return feed === 'recentchanges' ?
		{ query: { recentchanges: [], pages: [] } } : { query: { watchlist: [] } };
}

const handleApiData = ( data, limit, feed ) => {
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

	// Exclude specific tags
	const excludeTags = [ 'mw-reverted', 'mw-rollback', 'mw-undo' ];
	const filteredResultsWithFeedOrigin = data.query[ feed ]
		.filter(
			( change ) => !excludeTags.some(
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
};

const getRCParams = async ( offset ) => {
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
};

const getWLParams = async ( offset ) => {
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
};

function mergeRecentChangeData( rcData, moreRcData ) {
	return {
		...moreRcData,
		query: {
			...moreRcData.query,
			pages: [
				...rcData.query.pages,
				...moreRcData.query.pages
			],
			recentchanges: [
				...rcData.query.recentchanges,
				...moreRcData.query.recentchanges
			]
		}
	};
}

async function getRecentChangesData( api, limit, maxApiRequests, offset, rcData ) {
	let apiRequestCount = 0;
	let recentChangeData = rcData;
	let rcChangesListLength = recentChangeData.query.recentchanges.length;
	while ( offset &&
	( apiRequestCount < maxApiRequests ) &&
	( rcChangesListLength < limit ) ) {
		const rcParams = await getRCParams( offset );
		apiRequestCount++;
		const moreRcData = await api.get( rcParams ).then(
			( data ) => handleApiData( data, limit, 'recentchanges' ),
			( code, data ) => handleApiErrors( code, data )
		);
		recentChangeData = mergeRecentChangeData( recentChangeData, moreRcData );
		rcChangesListLength = recentChangeData.query.recentchanges.length;
		offset = recentChangeData.continue ? recentChangeData.continue.rccontinue : undefined;
	}
	return recentChangeData;
}

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

async function getWatchListData( api, limit, maxApiRequests, offset, wlData ) {
	let apiRequestCount = 0;
	let watchlistData = wlData;
	let watchlistLength = watchlistData.query.watchlist.length;
	while ( offset && ( apiRequestCount < maxApiRequests ) && ( watchlistLength < limit ) ) {
		const wlParams = await getWLParams( offset );
		apiRequestCount++;
		const moreWatchlistData = await api.get( wlParams ).then(
			( data ) => handleApiData( data, limit, 'watchlist' ),
			( code, data ) => handleApiErrors( code, data )
		);
		watchlistData = mergeWatchlistData( watchlistData, moreWatchlistData );
		watchlistLength = watchlistData.query.watchlist.length;
		offset = watchlistData.continue ? watchlistData.continue.wlcontinue : undefined;
	}
	return watchlistData;
}

function filterOutDuplicateTitles( rcData, wlTitles ) {
	const seenTitles = new Set();
	const wlTitlesSet = new Set( wlTitles );
	const filteredChanges = rcData.query.recentchanges
		.filter( ( recentChange ) => wlTitles.length === 0 ||
			!wlTitlesSet.has( recentChange.title ) )
		.filter( ( recentChange ) => {
			if ( seenTitles.has( recentChange.title ) ) {
				return false;
			}
			seenTitles.add( recentChange.title );
			return true;
		} );
	return {
		...rcData,
		query: {
			...rcData.query,
			recentchanges: filteredChanges
		}
	};
}

async function processRecentChanges( rcData, limit, api, maxApiRequests, wlTitles ) {
	let data = filterOutDuplicateTitles( rcData, wlTitles );
	if ( data.query.recentchanges.length < limit ) {
		const offset =
			data.continue ? data.continue.rccontinue : undefined;
		data = await getRecentChangesData( api, limit, maxApiRequests, offset, data );
		data = filterOutDuplicateTitles( data, wlTitles );
	}
	data.query.recentchanges = getRandomItems( data.query.recentchanges, limit );
	return data;
}

async function processWatchlistChanges(
	perFeedLimit,
	wlData,
	api,
	maxApiRequests
) {
	let data = wlData;
	const shouldFetchMore = data.query.watchlist.length > 0 &&
		data.query.watchlist.length < perFeedLimit;
	if ( shouldFetchMore ) {
		const offset = data.continue ? data.continue.wlcontinue : undefined;
		data = await getWatchListData( api, perFeedLimit, maxApiRequests, offset, data );
	}
	data.query.watchlist = getRandomItems( data.query.watchlist, perFeedLimit );
	return data;
}

async function mergeFeeds( wlFeedEnabled, limit, api ) {
	const perFeedLimit = wlFeedEnabled ? Math.ceil( limit / NUM_FEEDS ) : limit;
	const rcParams = await getRCParams();
	const rcData = await api.get( rcParams ).then(
		( data ) => handleApiData( data, perFeedLimit, 'recentchanges' ),
		( code, data ) => handleApiErrors( code, data )
	);
	if ( !wlFeedEnabled ) {
		const processedRcData =
			await processRecentChanges( rcData, perFeedLimit, api, MAX_API_REQUESTS, [] );
		return { ...processedRcData.query, feed: processedRcData.query.recentchanges };
	} else {
		const wlParams = await getWLParams();
		const wlData = await api.get( wlParams ).then(
			( data ) => handleApiData( data, perFeedLimit, 'watchlist' ),
			( code, data ) => handleApiErrors( code, data )
		);
		// maybe get more changes if needed
		const processedWatchlistChanges = await processWatchlistChanges(
			perFeedLimit,
			wlData,
			api,
			MAX_API_REQUESTS );

		const wlTitles = Array.from( wlData.query.watchlist, ( item ) => item.title );
		// maybe get more changes
		const amountToFill = Math.max(
			perFeedLimit,
			limit - processedWatchlistChanges.query.watchlist.length
		);
		const processedRcData = await processRecentChanges( rcData,
			amountToFill,
			api,
			MAX_API_REQUESTS,
			wlTitles );

		return {
			...processedRcData.query,
			...processedWatchlistChanges.query,
			feed: processedRcData.query.recentchanges
				.concat( processedWatchlistChanges.query.watchlist )
		};
	}
}

const fetchRecentActivity = async ( limit ) => {
	loading.value = true;
	error.value = null;
	const api = new mw.Api();
	const wlFeedEnabled =
		mw.config.get( 'wgPersonalDashboardRiskyArticleEditsWlEnabled' ) || false;
	try {
		const fullFeeds = await mergeFeeds( wlFeedEnabled, limit, api );
		fullFeeds.feed = fullFeeds.feed.slice( 0, limit );
		fullFeeds.feed.sort( ( a, b ) => b.timestamp.localeCompare( a.timestamp ) );
		recentActivityResult.value = fullFeeds;
	} catch ( err ) {
		mw.log.error( err.message );
		error.value = err;
	} finally {
		loading.value = false;
	}
};

const useFetchActivityResult = () => ( {
	recentActivityResult,
	loading,
	error,
	fetchRecentActivity
} );

module.exports = useFetchActivityResult;
