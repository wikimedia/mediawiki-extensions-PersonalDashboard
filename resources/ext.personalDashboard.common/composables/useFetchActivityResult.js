const { ref } = require( 'vue' );

const recentActivityResult = ref( null );
const loading = ref( false );
const error = ref( null );

// gets error or warning messages from api response
const parseApiStatus = ( data ) => {
	const messages = [];
	for ( const index in data ) {
		const dataObj = data[ index ];
		// use the most specific message available
		const msg = dataObj.text || dataObj[ '*' ] || dataObj.code;
		messages.push( msg );
	}
	return messages;
};

const handleApiErrors = ( code, data ) => {
	if ( data === undefined ) {
		throw new Error( code );
	}
	if ( data.errors ) {
		const errors = parseApiStatus( data.errors );
		if ( errors.length > 0 ) {
			throw new Error( errors.join( '\n' ) );
		}
	}
};

const getRandomChanges = ( changes, limit ) => {
	if ( changes.length <= limit ) {
		mw.log.warn( `unable to randomly sample changes: only ${ changes.length } found` );
		return changes;
	}
	const randomChanges = [];
	while ( randomChanges.length < limit ) {
		const randomIndex = Math.floor(
			Math.random() * changes.length
		);
		const randomChange = changes[ randomIndex ];
		if ( !randomChanges.includes( randomChange ) ) {
			randomChanges.push( changes[ randomIndex ] );
		}
	}
	return randomChanges;
};

const handleApiData = ( data, limit ) => {
	if ( !data ) {
		return;
	}

	if ( data.warnings ) {
		const warnings = parseApiStatus( data.warnings );
		if ( warnings.length > 0 ) {
			mw.log.warn( warnings.join( '\n' ) );
		}
	}

	if ( !data.query || !Array.isArray( data.query.recentchanges ) ) {
		return data;
	}

	// Exclude specific tags
	const excludeTags = [ 'mw-reverted', 'mw-rollback', 'mw-undo' ];
	const filteredResults = data.query.recentchanges.filter(
		( change ) => !excludeTags.some( ( tag ) => change.tags.includes( tag ) )
	);

	if ( !mw.config.get( 'wgOresUiEnabled' ) ) {
		data.query.recentchanges = getRandomChanges( filteredResults, limit );
		return data;
	}

	const {
		revertrisklanguageagnostic: {
			revertrisk: { min: threshold } = {}
		} = {}
	} = mw.config.get( 'wgOresFiltersThresholds' ) || {};

	if ( !threshold ) {
		data.query.recentchanges = [];
		mw.log.error( 'no revertrisklanguageagnostic threshold found' );
		return data;
	}

	const filteredByScore = filteredResults.filter(
		( change ) => change !== null &&
		change.oresscores !== undefined &&
		change.oresscores.revertrisklanguageagnostic !== undefined &&
		change.oresscores.revertrisklanguageagnostic.true >= threshold
	);
	data.query.recentchanges = getRandomChanges( filteredByScore, limit );
	return data;
};

const getParams = async () => {
	const params = {
		action: 'query',
		prop: 'description',
		list: 'recentchanges',
		generator: 'recentchanges',
		rcnamespace: '0',
		rcexcludeuser: mw.user.getName(),
		rcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|oresscores|timestamp',
		rcshow: '!bot',
		rclimit: '100',
		rctype: 'categorize|edit|log',
		errorformat: 'plaintext',
		errorlang: mw.util.getParamValue( 'uselang' ) || mw.config.get( 'wgUserLanguage' ),
		errorsuselocal: true,
		format: 'json',
		formatversion: '2'
	};

	const userRights = await mw.user.getRights();

	if ( userRights && userRights.includes( 'patrol' ) ) {
		params.rcshow += '|unpatrolled';
	}

	return params;
};

const fetchRecentActivity = async ( limit ) => {
	loading.value = true;
	error.value = null;
	const api = new mw.Api();
	const params = await getParams();
	try {
		recentActivityResult.value = await api.get( params ).then(
			( data ) => handleApiData( data, limit ),
			( code, data ) => handleApiErrors( code, data )
		);
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
