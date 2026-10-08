/**
 * @file composables/useActiveDiscussionsFeed.js
 *
 * The Active Discussions feed: threads from the configured discussion pages,
 * newest reply first.
 */

const { fetchFeed, useFeedState } = require( 'ext.personalDashboard.common' );

function fetchActiveDiscussions( limit, continuation ) {
	return fetchFeed( [ 'activediscussions' ], limit, continuation );
}

// One state for the whole module: the card and the dialog share a single
// teleported component instance, and any further mount should see the same feed
// rather than issue its own request.
const { feedState, load, loadMore } = useFeedState( fetchActiveDiscussions );

/**
 * @return {{feedState: Object, load: function(number): Promise<void>,
 *   loadMore: function(): Promise<void>}} The shared feed contract for this
 *   module (see useFeedState.js), its loader and its pager.
 */
function useActiveDiscussionsFeed() {
	return { feedState, load, loadMore };
}

module.exports = { useActiveDiscussionsFeed };
