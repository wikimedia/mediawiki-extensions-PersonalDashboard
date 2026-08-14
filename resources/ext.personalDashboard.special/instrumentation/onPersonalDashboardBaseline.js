const { withExperimentTagging } = require( './experiments.js' );

/*
 * ClickThroughRateInstrument comes from WikimediaEvents, which pulls in the
 * TestKitchen extension's own ext.testKitchen for mw.testKitchen. Both are
 * optional dependencies of PersonalDashboard, so we load them lazily, the same
 * soft-dependency pattern as ListCard.vue's ext.checkUser.userInfoCard load.
 */
mw.loader.using( 'ext.wikimediaEvents.testKitchen' ).then( ( require ) => {
	const { ClickThroughRateInstrument } = require( 'ext.wikimediaEvents.testKitchen' );

	const instrument = withExperimentTagging( mw.testKitchen.getInstrument(
		'personal-dashboard-health-metrics'
	) );
	instrument.send( 'pageview' );

	// One friendly name per feed source, so clicks are attributed to the
	// source the item actually came from. Anything unrecognised falls back
	// to the generic diff name rather than being lumped in with a specific
	// source and skewing that source's click-through rate.
	const friendlyNameDiff = 'Personal Dashboard diff link';
	const friendlyNamesByOrigin = {
		recentchanges: friendlyNameDiff,
		watchlist: 'Personal Dashboard watched diff link',
		recentlyedited: 'Personal Dashboard recently edited diff link'
	};

	function instrumentReviewChangesLinks( selector ) {
		const links = document.querySelectorAll( selector );
		for ( const [ i, link ] of links.entries() ) {
			const origin = link.dataset.feedorigin;
			const friendlyName = friendlyNamesByOrigin[ origin ] || friendlyNameDiff;
			ClickThroughRateInstrument.start(
				selector + ':nth-of-type(' + ( i + 1 ) + ')',
				friendlyName,
				instrument
			);
		}
	}

	mw.hook( 'personaldashboard.recentactivity.loaded' ).add( () => {
		// Not all views include this link
		if ( document.querySelector( '#personal-dashboard-go-to-recentchanges' ) ) {
			ClickThroughRateInstrument.start(
				'#personal-dashboard-go-to-recentchanges',
				'Go to Recent Changes link',
				instrument
			);
		}
	} );

	mw.hook( 'personaldashboard.recentactivity.listcard.loaded' ).add( () => {
		instrumentReviewChangesLinks(
			'.personal-dashboard-review-changes__card' );
	} );
// A rejection handler rather than a trailing .catch(), so we swallow only a
// failed load (expected without WikimediaEvents), not the instrumentation above.
}, () => {} );
