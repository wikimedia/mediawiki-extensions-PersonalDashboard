/**
 * @file fetchFeed.js
 *
 * One page of the shared feed endpoint, which merges the sources asked for,
 * drops the duplicates between them and hands back a token that resumes where
 * the page stopped.
 */

const FEED_PATH = '/personaldashboard/v0/feed';

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
 * @param {string[]} sources Source names, in priority order: the endpoint gives
 *   the earlier sources the extra slots when the limit does not divide evenly
 * @param {number} limit Items per page
 * @param {?string} continuation Token from the page before, or null to start
 *   from the newest items
 * @return {Promise<{items: Object[], continuation: ?string}>}
 * @throws {Error} If the endpoint refuses the request
 */
async function fetchFeed( sources, limit, continuation ) {
	const query = { sources: sources.join( '|' ), limit };
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

module.exports = { fetchFeed };
