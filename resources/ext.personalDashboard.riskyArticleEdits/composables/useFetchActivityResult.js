const { ref } = require( 'vue' );

const recentActivityResult = ref( null );
const loading = ref( false );
const error = ref( null );

const fetchRecentActivity = async () => {
	loading.value = true;
	error.value = null;
	try {
		const api = new mw.Api();
		const params = {
			action: 'query',
			list: 'recentchanges',
			rcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|unpatrolled|oresscores',
			rclimit: '100',
			rcnamespace: '0',
			rctype: 'categorize|edit|external|log',
			format: 'json'
		};
		recentActivityResult.value = await api.get( params );
		if (
			recentActivityResult.value &&
			recentActivityResult.value.query &&
			Array.isArray( recentActivityResult.value.query.recentchanges )
		) {
			// exclude reverted edits
			const filteredResults = recentActivityResult.value.query.recentchanges.filter(
				( change ) => !change.tags.includes( 'mw-reverted' )
			);
			const filteredByScore = filteredResults.filter(
				( change ) => change !== null &&
					change.oresscores !== undefined &&
					change.oresscores.revertrisklanguageagnostic !== undefined &&
					change.oresscores.revertrisklanguageagnostic >= 0.5
			);
			if ( filteredByScore ) {
				if ( filteredByScore.length > 10 ) {
					const randomIndexes = [];
					while ( randomIndexes.length < 10 ) {
						const randomIndex = Math.floor(
							Math.random() * filteredByScore.length
						);
						if ( !randomIndexes.includes( randomIndex ) ) {
							randomIndexes.push( randomIndex );
						}
					}
					recentActivityResult.value.query.recentchanges = filteredByScore.filter(
						( change, index ) => randomIndexes.includes( index )
					);
				} else {
					recentActivityResult.value.query.recentchanges = filteredByScore;
				}
			}
			if ( recentActivityResult.value.warnings ) {
				// @TODO: remove this ahead of going live
				// eslint-disable-next-line no-console
				console.warn( JSON.parse( JSON.stringify( recentActivityResult.value.warnings.recentchanges[ '*' ] ) ) );
			}
		}

	} catch ( err ) {
		error.value = err;
	} finally {
		loading.value = false;
	}
};

module.exports = {
	recentActivityResult,
	loading,
	error,
	fetchRecentActivity
};
