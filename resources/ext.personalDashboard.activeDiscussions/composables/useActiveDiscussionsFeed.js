/**
 * @file composables/useActiveDiscussionsFeed.js
 *
 * The Active Discussions feed source: query DiscussionTools for the configured
 * discussion pages and normalize the threads into feed items. Loading and error
 * state come from the shared feed contract.
 */

const { useFeedState, utils } = require( 'ext.personalDashboard.common' );
const { handleApiErrors } = utils;

/**
 * Fetch the most recently active discussions across the configured pages.
 *
 * A thread counts as a discussion only once more than one person has spoken in
 * it, so a single unanswered post doesn't crowd out real conversation. A page
 * whose response is missing thread data is skipped rather than failing the
 * whole feed; only a feed with nothing usable from any page throws.
 *
 * @param {number} limit Maximum number of items to return
 * @return {Promise<Object[]>} Feed items, most recent reply first
 * @throws {Error} If no configured page returns usable thread data
 */
async function fetchActiveDiscussions( limit ) {
	const api = new mw.Api();
	const discussionPages = mw.config.get( 'wgPersonalDashboardActiveDiscussionsPages' ) || [];
	const activeDiscussions = [];
	let anyPageUsable = false;

	for ( const discussionPage of discussionPages ) {
		const result = await api.get( {
			action: 'discussiontoolspageinfo',
			format: 'json',
			page: discussionPage,
			prop: 'threaditemshtml',
			threaditemsflags: 'noreplies|excludesignatures|activity',
			formatversion: '2'
		} ).then(
			( data ) => data,
			( code, data ) => handleApiErrors( code, data )
		);

		if ( result.discussiontoolspageinfo === undefined ||
			result.discussiontoolspageinfo.threaditemshtml === undefined ) {
			continue;
		}

		anyPageUsable = true;

		for ( const item of result.discussiontoolspageinfo.threaditemshtml ) {
			if ( item.authorCount > 1 ) {
				// A minority of items are missing the latest-reply metadata below;
				// skip only that item rather than losing the rest of a valid page.
				if ( item.latestReplyTimestamp === undefined || item.latestReply === undefined ) {
					continue;
				}

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

	if ( !anyPageUsable && discussionPages.length > 0 ) {
		throw new Error( mw.msg( 'personal-dashboard-active-discussions-fetch-error' ) );
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
