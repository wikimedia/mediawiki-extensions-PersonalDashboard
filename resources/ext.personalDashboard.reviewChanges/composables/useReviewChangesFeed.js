/**
 * @file composables/useReviewChangesFeed.js
 *
 * The Review Changes feed: the personalized sources when the viewer has opted
 * in, recent changes otherwise.
 */

const { fetchFeed, useFeedState } = require( 'ext.personalDashboard.common' );
const { isPersonalized } = require( '../utils.js' );

const PERSONALIZED_SOURCES = [ 'watchlist', 'recentlyedited', 'mostedited' ];

// Human edits to articles that are not reverted, the same for every user.
const UNPERSONALIZED_SOURCES = [ 'recentchanges' ];

function fetchReviewChanges( limit, continuation ) {
	const sources = isPersonalized() ? PERSONALIZED_SOURCES : UNPERSONALIZED_SOURCES;
	return fetchFeed( sources, limit, continuation );
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
