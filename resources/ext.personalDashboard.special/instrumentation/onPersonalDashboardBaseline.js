// TestKitchen (and the ext.testKitchen dependency that provides
// mw.testKitchen) lives in WikimediaEvents, which is an optional dependency of
// PersonalDashboard. Treat it as a soft dependency: only load it when the
// module is actually registered, and pull it in lazily so the dashboard keeps
// working when WikimediaEvents is not installed.
if (
	mw.loader.getState( 'ext.wikimediaEvents.testKitchen' ) !== null
) {
	mw.loader.using( 'ext.wikimediaEvents.testKitchen' ).then( ( require ) => {
		const { ClickThroughRateInstrument } = require( 'ext.wikimediaEvents.testKitchen' );

		const instrument = mw.testKitchen.getInstrument(
			'personal-dashboard-health-metrics'
		);
		instrument.submitInteraction( 'pageview' );

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
	} );
}
