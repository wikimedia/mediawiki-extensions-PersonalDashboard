/**
 * Record a client-side timing with the labels of the server-side timings.
 *
 * Does nothing when the page sets no wgPersonalDashboardStats, which is the
 * case off the dashboard and in a pdo session.
 *
 * @param {string} module Module name, e.g. 'ext.personalDashboard.reviewChanges'
 * @param {string} operation What was timed, e.g. 'feed_fetch'
 * @param {number} milliseconds
 */
function recordTiming( module, operation, milliseconds ) {
	const stats = mw.config.get( 'wgPersonalDashboardStats' );
	if ( !stats ) {
		return;
	}
	const labels = Object.assign( { module, operation }, stats.labels );
	for ( const key in labels ) {
		// The same normalisation that StatsFactory applies on the server.
		labels[ key ] = String( labels[ key ] )
			.replace( /[^A-Za-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' );
	}
	mw.track(
		'stats.mediawiki_' + stats.component + '_dashboard_client_seconds',
		milliseconds,
		labels
	);
}

module.exports = { recordTiming };
