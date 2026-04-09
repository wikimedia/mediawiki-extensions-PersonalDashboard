const { ref } = require( 'vue' );

const recentActivityResult = ref( null );
const loading = ref( false );
const error = ref( null );
// can't currently import with nested destruct due to our commonjs/es conversion for vitest
const { utils } = require( 'ext.personalDashboard.common' );
const { getRandomItems, handleApiErrors, parseApiStatus } = utils;

const handleApiData = ( data, limit, feed ) => {
	if ( !data ) {
		return;
	}

	if ( data.warnings ) {
		const warnings = parseApiStatus( data.warnings );
		if ( warnings.length > 0 ) {
			mw.log.warn( warnings.join( '\n' ) );
		}
	}

	if ( !data.query || !Array.isArray( data.query[ feed ] ) ) {
		return data;
	}

	// Exclude specific tags
	const excludeTags = [ 'mw-reverted', 'mw-rollback', 'mw-undo' ];
	const filteredResults = data.query[ feed ].filter(
		( change ) => !excludeTags.some( ( tag ) => change.tags.includes( tag ) )
	);

	data.query[ feed ] = getRandomItems( filteredResults, limit );

	return data;
};

const getRCParams = async () => {
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

const getWLParams = async () => {
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

	const userRights = await mw.user.getRights();

	if ( userRights && userRights.includes( 'patrol' ) ) {
		params.wlshow += '|!patrolled';
	}

	return params;
};

const fetchRecentActivity = async ( limit ) => {
	loading.value = true;
	error.value = null;
	const api = new mw.Api();
	const rcParams = await getRCParams();
	const wlParams = await getWLParams();
	const wlFeedEnabled = mw.config.get( 'wgPersonalDashboardRiskyArticleEditsWlEnabled' ) || false;
	let fullFeeds = {};
	try {
		const rcData = await api.get( rcParams ).then(
			( data ) => handleApiData( data, limit, 'recentchanges' ),
			( code, data ) => handleApiErrors( code, data )
		);

		if ( wlFeedEnabled ) {
			const wlData = await api.get( wlParams ).then(
				( data ) => handleApiData( data, limit, 'watchlist' ),
				( code, data ) => handleApiErrors( code, data )
			);
			fullFeeds = { ...rcData.query, ...wlData.query };
		} else {
			fullFeeds = { ...rcData.query };
		}

		for ( const feed in fullFeeds ) {
			// The pages object is not part of the feed, so no need to add feedorigin attribute
			if ( feed === 'pages' ) {
				continue;
			}
			for ( const f in fullFeeds[ feed ] ) {
				// Add the feedorigin attribute to distinguish between feeds
				fullFeeds[ feed ][ f ].feedorigin = feed;
			}
		}
		if ( wlFeedEnabled ) {
			const halfLimit = Math.ceil( limit / 2 );
			const wlList = fullFeeds.watchlist || [];
			const rcList = fullFeeds.recentchanges || [];
			const wlLength = wlList.length;
			const rcLength = rcList.length;
			const wlTitles = Array.from( wlList, ( item ) => item.title );

			// Remove edits from pages that already exist in the watchlist
			fullFeeds.recentchanges = fullFeeds.recentchanges.filter(
				( item ) => !wlTitles.includes( item.title ) );

			if ( wlLength < ( limit - halfLimit ) ) {
				fullFeeds.recentchanges = fullFeeds.recentchanges.slice( 0,
					halfLimit + ( halfLimit - wlLength ) );
				fullFeeds.watchlist = fullFeeds.watchlist.slice( 0, wlLength );

			} else if ( rcLength < ( limit - halfLimit ) ) {
				fullFeeds.recentchanges = fullFeeds.recentchanges.slice( 0, rcLength );
				fullFeeds.watchlist = fullFeeds.watchlist.slice( 0,
					halfLimit + ( halfLimit - rcLength ) );
			} else {
				fullFeeds.recentchanges = fullFeeds.recentchanges.slice( 0, halfLimit );
				fullFeeds.watchlist = fullFeeds.watchlist.slice( 0, limit - halfLimit );
			}
			fullFeeds.feed = fullFeeds.recentchanges.concat( fullFeeds.watchlist );
		} else {
			fullFeeds.recentchanges = fullFeeds.recentchanges.slice( 0, limit );
			fullFeeds.feed = fullFeeds.recentchanges;
		}

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
