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

const getRandomIndexes = ( filteredByScore, limit ) => {
	const randomIndexes = [];
	while ( randomIndexes.length < limit ) {
		const randomIndex = Math.floor(
			Math.random() * filteredByScore.length
		);
		if ( !randomIndexes.includes( randomIndex ) ) {
			randomIndexes.push( randomIndex );
		}
	}
	return randomIndexes;
};

const getRandomChanges = ( data, changes, limit ) => {
	data.query.recentchanges = changes.length > limit ?
		changes.filter( ( _, index ) => getRandomIndexes( changes, limit ).includes( index ) ) :
		changes;
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
		getRandomChanges( data, filteredResults, limit );
		return data;
	}

	const thresholds = mw.config.get( 'wgOresFiltersThresholds' );
	const wiki = mw.config.get( 'wgUserLanguage' );
	const {
		revertrisklanguageagnostic: {
			revertrisk: { min: threshold } = {}
		} = {}
	} = thresholds[ wiki ] || {};

	if ( !threshold ) {
		data.query.recentchanges = [];
		return data;
	}

	const filteredByScore = filteredResults.filter(
		( change ) => change !== null &&
		change.oresscores !== undefined &&
		change.oresscores.revertrisklanguageagnostic !== undefined &&
		change.oresscores.revertrisklanguageagnostic.true >= threshold
	);

	getRandomChanges( data, filteredByScore, limit );
	return data;
};

const getParams = async () => {
	let params = {
		format: 'json',
		action: 'query',
		errorlang: mw.util.getParamValue( 'uselang' ) || mw.config.get( 'wgUserLanguage' ),
		errorsuselocal: true,
		errorformat: 'plaintext',
		prop: 'description',
		list: 'recentchanges',
		generator: 'recentchanges',
		formatversion: '2',
		rcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|oresscores|timestamp',
		rclimit: '100',
		rcnamespace: '0',
		rctype: 'categorize|edit|external|log',
		rcexcludeuser: mw.user.getName()
	};
	const userRights = await mw.user.getRights();
	if ( userRights && userRights.includes( 'patrol' ) ) {
		params = {
			rcshow: 'unpatrolled',
			...params
		};
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
