/**
 * @file store/reviewChangeStore.js
 *
 * Pinia store for the PersonalDashboard Review Changes feed.
 *
 * Intentionally thin: all API logic, param-building, pagination, and
 * normalization lives in the composables and feedHelpers. The store's
 * only job is to coordinate the three sources, merge their output, and
 * expose the result as the shared feed-data contract (see the feedState
 * getter). It keeps its own state rather than using useFeedState because
 * a Pinia option store's state has to be plain, patchable data (this file's
 * tests rely on assigning straight to it, e.g. `store.feed = ...`); wiring in
 * useFeedState's own refs would mean rewriting the store as a Pinia setup
 * store instead. useFeedState's loader argument itself has no trouble with a
 * merge of three sources.
 */

const { defineStore } = require( 'pinia' );
const { useWatchlistFeed } = require( '../composables/useWatchlistFeed.js' );
const { useRecentChangesFeed } = require( '../composables/useRecentChangesFeed.js' );
const { useRecentlyEditedFeed } = require( '../composables/useRecentlyEditedFeed.js' );
const { selectEvenlyAcrossFeeds } = require( '../utils/feedHelpers.js' );

const NUM_FEEDS = 3;
const MAX_API_REQUESTS = 10;

const useReviewChangesStore = defineStore( 'reviewChanges', {
	state: function () {
		return {
			/**
			 * Merged, sorted feed items ready for the UI to render.
			 *
			 * @type {import('../utils/feedHelpers.js').FeedItem[]}
			 */
			feed: [],

			/**
			 * Pages metadata from the RC generator query (prop: 'description').
			 * Passed as-is to <list-card> as a separate prop; it does not fit the
			 * FeedItem shape.
			 *
			 * @type {Object[]}
			 */
			pages: [],

			/** @type {boolean} */
			isLoading: false,

			/** @type {Error|null} */
			error: null
		};
	},

	getters: {
		/**
		 * This store as the normalized feed-data contract the shared feed
		 * scaffold consumes, so the module can hand it over in one binding.
		 *
		 * @see ext.personalDashboard.common/composables/useFeedState.js
		 * @param {Object} state
		 * @return {Object}
		 */
		feedState: ( state ) => ( {
			items: state.feed,
			isLoading: state.isLoading,
			error: state.error
		} )
	},

	actions: {
		/**
		 * Fetch the watchlist, recent changes and recently-edited feeds, merge
		 * them into a roughly even split of the available slots, then commit the
		 * result to state.
		 *
		 * Watchlist titles are passed to the RC composable so it can exclude
		 * duplicates before sampling.
		 *
		 * Each source is asked for more than its share where that is cheap, so
		 * selectEvenlyAcrossFeeds has something to backfill with when another
		 * source comes up short.
		 *
		 * @param {number} limit Total number of feed items to display
		 * @return {Promise<void>}
		 */
		async fetchRecentActivity( limit ) {
			this.isLoading = true;
			this.error = null;

			const perFeedLimit = Math.ceil( limit / NUM_FEEDS );

			const { fetchWatchlistItems } = useWatchlistFeed();
			const { fetchRecentChangesItems } = useRecentChangesFeed();
			const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();

			try {
				const wlItems = await fetchWatchlistItems( perFeedLimit, MAX_API_REQUESTS );

				const wlTitles = wlItems.map( ( item ) => item.title );
				const amountToFill = Math.max( perFeedLimit, limit - wlItems.length );

				const { items: rcItems, pages } = await fetchRecentChangesItems(
					amountToFill, MAX_API_REQUESTS, wlTitles
				);

				// These are prefetched server-side, so sampling more than a third
				// costs nothing and leaves room to backfill the other sources.
				const reItems = fetchRecentlyEditedItems( limit );

				this.feed = selectEvenlyAcrossFeeds( [ rcItems, wlItems, reItems ], limit );
				this.pages = pages;
			} catch ( err ) {
				mw.log.error( err.message );
				this.error = err;
			} finally {
				this.isLoading = false;
			}
		}
	}
} );

module.exports = { useReviewChangesStore };
