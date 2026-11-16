const { withExperimentTagging } = require( 'ext.personalDashboard.common' );

/*
 * ext.wikimediaEvents.testKitchen comes from WikimediaEvents, which pulls in the
 * TestKitchen extension's own ext.testKitchen for mw.testKitchen. Both are
 * optional dependencies of PersonalDashboard, so we load them lazily.
 *
 * ModeratorToolkit's Review Changes sends its own click-through events to the
 * same instrument.
 */
mw.loader.using( 'ext.wikimediaEvents.testKitchen' ).then( () => {
	const instrument = withExperimentTagging( mw.testKitchen.getInstrument(
		'personal-dashboard-health-metrics'
	) );
	instrument.send( 'pageview' );
// A rejection handler rather than a trailing .catch(), so we swallow only a
// failed load (expected without WikimediaEvents), not the instrumentation above.
}, () => {} );
