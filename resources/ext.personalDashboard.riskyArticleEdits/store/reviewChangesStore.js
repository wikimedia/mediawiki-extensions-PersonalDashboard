/**
 * @file store/reviewChangeStore.js
 *
 * Pinia store for the PersonalDashboard Review Changes feed.
 *
 * Intentionally thin: all API logic, param-building, pagination, and
 * normalization lives in the composables and feedHelpers. The store's
 * only job is to coordinate the two sources, merge their output, and
 * expose reactive state to the UI.
 */

const { defineStore } = require( 'pinia' );
const { useWatchlistFeed } = require( '../composables/useWatchlistFeed.js' );
const { useRecentChangesFeed } = require( '../composables/useRecentChangesFeed.js' );

const NUM_FEEDS = 2;
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
			 * Passed as-is to <list-card> and <list-card-mobile> as a separate
			 * prop — does not fit the FeedItem shape.
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
		 * Whether the feed has loaded at least once and has items to show.
		 *
		 * @param {Object} state
		 * @return {boolean}
		 */
		hasFeed: ( state ) => state.feed.length > 0
	},

	actions: {
		/**
		 * Fetch and merge the RC and (optionally) watchlist feeds, then
		 * commit the result to state.
		 *
		 * When the watchlist feed is enabled, watchlist titles are passed to
		 * the RC composable so it can exclude duplicates before sampling.
		 *
		 * @param {number} limit Total number of feed items to display
		 * @return {Promise<void>}
		 */
		async fetchRecentActivity( limit ) {
			this.isLoading = true;
			this.error = null;

			const wlFeedEnabled =
				mw.config.get( 'wgPersonalDashboardRiskyArticleEditsWlEnabled' ) || false;
			const perFeedLimit = wlFeedEnabled ? Math.ceil( limit / NUM_FEEDS ) : limit;

			const { fetchWatchlistItems } = useWatchlistFeed();
			const { fetchRecentChangesItems } = useRecentChangesFeed();

			try {
				let wlItems = [];

				if ( wlFeedEnabled ) {
					wlItems = await fetchWatchlistItems( perFeedLimit, MAX_API_REQUESTS );
				}

				const wlTitles = wlItems.map( ( item ) => item.title );
				const amountToFill = wlFeedEnabled ?
					Math.max( perFeedLimit, limit - wlItems.length ) :
					limit;

				const { items: rcItems, pages } = await fetchRecentChangesItems(
					amountToFill, MAX_API_REQUESTS, wlTitles
				);

				const merged = rcItems.concat( wlItems );
				merged.sort( ( a, b ) => b.timestamp.localeCompare( a.timestamp ) );

				this.feed = merged.slice( 0, limit );
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
