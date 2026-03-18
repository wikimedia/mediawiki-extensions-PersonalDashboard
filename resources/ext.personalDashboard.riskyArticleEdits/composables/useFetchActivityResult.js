const { ref } = require( 'vue' );

const recentActivityResult = ref( null );
const loading = ref( false );
const error = ref( null );
// can't currently import with nested destruct due to our commonjs/es conversion for vitest
const { utils } = require( 'ext.personalDashboard.common' );
const { getRandomItems, handleApiErrors, parseApiStatus } = utils;

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
	data.query.recentchanges = getRandomItems( filteredResults, limit );
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
