const { ref } = require( 'vue' );

const activeDiscussionsResult = ref( null );
const loading = ref( false );
const error = ref( null );

const fetchActiveDiscussions = async ( limit ) => {
	loading.value = true;
	error.value = null;
	try {
		const api = new mw.Api();
		const activeDiscussionsPages = mw.config.get( 'wgPersonalDashboardActiveDiscussionsPages' ) || [];
		let activeDiscussionsApiResult = [];
		const activeDiscussions = [];
		for ( const discussionPage of activeDiscussionsPages ) {
			const params = {
				action: 'discussiontoolspageinfo',
				format: 'json',
				page: discussionPage,
				prop: 'threaditemshtml',
				threaditemsflags: 'noreplies|excludesignatures|activity',
				formatversion: '2'
			};
			activeDiscussionsApiResult = await api.get( params );

			if ( activeDiscussionsApiResult.discussiontoolspageinfo === undefined ||
				activeDiscussionsApiResult.discussiontoolspageinfo.threaditemshtml === undefined ) {
				const errorMessage = new Error( 'No valid active discussions found' );
				mw.log.error( errorMessage.message );
				error.value = errorMessage;
				activeDiscussionsResult.value = [];
				return;
			}

			const items = activeDiscussionsApiResult.discussiontoolspageinfo.threaditemshtml;

			for ( const apiResult of items ) {
				// We want discussions that have more than one author
				if ( apiResult.authorCount > 1 ) {
					activeDiscussions.push( {
						discussionPage: discussionPage,
						discussionTitle: apiResult.html,
						authorCount: apiResult.authorCount,
						commentCount: apiResult.commentCount,
						latestReply: apiResult.latestReplyTimestamp,
						latestReplyId: apiResult.latestReply.id
					} );
				}
			}
		}
		activeDiscussions.sort( ( a, b ) => b.latestReply.localeCompare( a.latestReply ) );
		activeDiscussionsResult.value = activeDiscussions.slice( 0, limit );
	} catch ( err ) {
		mw.log.error( err.message );
		error.value = err;
	} finally {
		loading.value = false;
	}
};

function useFetchActiveDiscussionsResult() {
	return {
		activeDiscussionsResult,
		loading,
		error,
		fetchActiveDiscussions
	};
}

module.exports = useFetchActiveDiscussionsResult;
