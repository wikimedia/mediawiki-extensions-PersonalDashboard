const { ref } = require( 'vue' );

const recentActivityResult = ref( null );
const loading = ref( false );
const error = ref( null );

function getRandomIndexes( filteredByScore ) {
	const randomIndexes = [];
	while ( randomIndexes.length < 1 ) {
		const randomIndex = Math.floor(
			Math.random() * filteredByScore.length
		);
		if ( !randomIndexes.includes( randomIndex ) ) {
			randomIndexes.push( randomIndex );
		}
	}
	return randomIndexes;
}
// TODO: this is just completely duped from ext.personalDashboard.riskyArticleEdits, DRY IT UP
const fetchRecentActivity = async () => {
	loading.value = true;
	error.value = null;
	try {
		const api = new mw.Api();
		const params = {
			format: 'json',
			action: 'query',
			prop: 'description',
			list: 'recentchanges',
			generator: 'recentchanges',
			formatVersion: '2',
			rcprop: 'title|ids|sizes|flags|user|comment|tags|unpatrolled|oresscores|timestamp',
			rclimit: '100',
			rcnamespace: '0',
			rctype: 'categorize|edit|external|log',
			rcexcludeuser: mw.user.getName()
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
			if ( mw.config.get( 'wgOresUiEnabled' ) ) {
				const thresholds = mw.config.get( 'wgOresFiltersThresholds' );
				const wiki = mw.config.get( 'wgUserLanguage' );
				if ( thresholds[ wiki ] &&
					thresholds[ wiki ].revertrisklanguageagnostic &&
					thresholds[ wiki ].revertrisklanguageagnostic.revertrisk &&
					thresholds[ wiki ].revertrisklanguageagnostic.revertrisk.min
				) {
					const threshold = thresholds[ wiki ].revertrisklanguageagnostic.revertrisk.min;
					const filteredByScore = filteredResults.filter(
						( change ) => change !== null &&
							change.oresscores !== undefined &&
							change.oresscores.revertrisklanguageagnostic !== undefined &&
							change.oresscores.revertrisklanguageagnostic.true >= threshold
					);
					if ( filteredByScore ) {
						if ( filteredByScore.length > 1 ) {
							const randomIndexes = getRandomIndexes( filteredByScore );
							recentActivityResult.value.query.recentchanges = filteredByScore.filter(
								( change, index ) => randomIndexes.includes( index )
							);
						} else {
							recentActivityResult.value.query.recentchanges = filteredByScore;
						}
					}
				} else {
					recentActivityResult.value.query.recentchanges = [];
				}
			} else {
				if ( filteredResults.length > 1 ) {
					const randomIndexes = getRandomIndexes( filteredResults );
					recentActivityResult.value.query.recentchanges = filteredResults.filter(
						( change, index ) => randomIndexes.includes( index )
					);
				} else {
					recentActivityResult.value.query.recentchanges = filteredResults;
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
