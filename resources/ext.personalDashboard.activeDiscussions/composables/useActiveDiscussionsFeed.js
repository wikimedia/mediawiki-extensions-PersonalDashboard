/**
 * @file composables/useActiveDiscussionsFeed.js
 *
 * The Active Discussions feed source: query DiscussionTools for the configured
 * discussion pages and normalize the threads into feed items. Loading and error
 * state come from the shared feed contract.
 */

const { useFeedState } = require( 'ext.personalDashboard.common' );

/**
 * Fetch the most recently active discussions across the configured pages.
 *
 * A thread counts as a discussion only once more than one person has spoken in
 * it, so a single unanswered post doesn't crowd out real conversation.
 *
 * @param {number} limit Maximum number of items to return
 * @return {Promise<Object[]>} Feed items, most recent reply first
 * @throws {Error} If a page returns no usable thread data
 */
async function fetchActiveDiscussions( limit ) {
	const api = new mw.Api();
	const discussionPages = mw.config.get( 'wgPersonalDashboardActiveDiscussionsPages' ) || [];
	const activeDiscussions = [];

	for ( const discussionPage of discussionPages ) {
		const result = await api.get( {
			action: 'discussiontoolspageinfo',
			format: 'json',
			page: discussionPage,
			prop: 'threaditemshtml',
			threaditemsflags: 'noreplies|excludesignatures|activity',
			formatversion: '2'
		} );

		if ( result.discussiontoolspageinfo === undefined ||
			result.discussiontoolspageinfo.threaditemshtml === undefined ) {
			throw new Error( 'No valid active discussions found' );
		}

		for ( const item of result.discussiontoolspageinfo.threaditemshtml ) {
			if ( item.authorCount > 1 ) {
				activeDiscussions.push( {
					id: discussionPage + '#' + item.id,
					discussionPage: discussionPage,
					discussionTitle: item.html,
					authorCount: item.authorCount,
					commentCount: item.commentCount,
					latestReply: item.latestReplyTimestamp,
					latestReplyId: item.latestReply.id
				} );
			}
		}
	}

	activeDiscussions.sort( ( a, b ) => b.latestReply.localeCompare( a.latestReply ) );

	return activeDiscussions.slice( 0, limit );
}

// One state for the whole module: the card and the dialog share a single
// teleported component instance, and any further mount should see the same feed
// rather than issue its own request.
const { feedState, load } = useFeedState( fetchActiveDiscussions );

/**
 * @return {{feedState: Object, load: function(number): Promise<void>}} The
 *   shared feed contract for this module (see useFeedState.js), and its loader.
 */
function useActiveDiscussionsFeed() {
	return { feedState, load };
}

module.exports = { useActiveDiscussionsFeed };
