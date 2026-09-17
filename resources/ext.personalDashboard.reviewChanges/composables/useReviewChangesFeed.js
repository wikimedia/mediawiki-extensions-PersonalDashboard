/**
 * @file composables/useReviewChangesFeed.js
 *
 * The Review Changes feed source: one request to the shared feed endpoint,
 * which merges the three sources, drops the duplicates between them and hands
 * back a token that resumes where the page stopped.
 */

const { useFeedState } = require( 'ext.personalDashboard.common' );

const FEED_PATH = '/personaldashboard/v0/feed';

// Priority order: the endpoint gives the earlier sources the extra slots when
// the limit does not divide evenly.
const SOURCES = [ 'watchlist', 'recentchanges', 'recentlyedited' ];

/**
 * Turn a mw.Rest rejection into an error the scaffold can show.
 *
 * @param {string} code
 * @param {Object} [details] Second rejection argument: { xhr, textStatus, exception }
 * @return {Error}
 */
function feedError( code, details ) {
	const body = details && details.xhr ? details.xhr.responseJSON : null;
	if ( !body ) {
		return new Error( code );
	}

	const translations = body.messageTranslations || {};

	return new Error(
		translations[ mw.config.get( 'wgUserLanguage' ) ] ||
		translations.en ||
		body.message ||
		code
	);
}

/**
 * Fetch one page of the merged feed.
 *
 * @param {number} limit Items per page
 * @param {?string} continuation Token from the page before, or null to start
 *   from the newest items
 * @return {Promise<{items: Object[], continuation: ?string}>}
 * @throws {Error} If the endpoint refuses the request
 */
async function fetchReviewChanges( limit, continuation ) {
	const query = { sources: SOURCES.join( '|' ), limit };
	if ( continuation ) {
		query.continue = continuation;
	}

	// catch(), because mw.Rest rejects with two arguments and await keeps only
	// the first. The second is where the wiki's own message for the failure is.
	const data = await new mw.Rest().get( FEED_PATH, query ).catch(
		( code, details ) => {
			throw feedError( code, details );
		}
	);

	return {
		items: data.items || [],
		// Absent once every source is spent.
		continuation: data.continue || null
	};
}

// One state for the whole module: the card and the dialog are the same
// teleported instance, so both read the same feed.
const { feedState, load, loadMore } = useFeedState( fetchReviewChanges );

/**
 * @return {{feedState: Object, load: function(number): Promise<void>,
 *   loadMore: function(): Promise<void>}} The shared feed contract for this
 *   module (see useFeedState.js), its loader and its pager.
 */
function useReviewChangesFeed() {
	return { feedState, load, loadMore };
}

module.exports = { useReviewChangesFeed };
