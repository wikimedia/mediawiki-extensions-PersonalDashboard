/**
 * @file composables/useFeedState.js
 *
 * The normalized feed-data contract the shared feed scaffold consumes, and the
 * client-fetch implementation of it.
 *
 * A feed module supplies its items and its state; FeedPanel.vue owns everything
 * else. Anything producing the shape below satisfies the contract: this
 * composable, or a plain object built from data a module was handed through
 * getJsData(), which needs no fetch layer at all.
 */

const { computed, reactive, ref } = require( 'vue' );

/**
 * @typedef {Object} FeedState
 * @property {Object[]} items Feed items to render. Each item needs a unique
 *   `id`; the scaffold keys the list on it.
 * @property {boolean} isLoading Whether a first load is in flight.
 * @property {boolean} isLoadingMore Whether a further page is in flight.
 * @property {boolean} hasMore Whether a further page can be asked for.
 * @property {Error|null} error The last load failure, or null.
 */

/**
 * @typedef {Object} FeedPage
 * @property {Object[]} items The items in this page.
 * @property {?string} continuation Token that resumes after this page, or null
 *   when nothing follows it.
 */

/**
 * Build a FeedState around a loader, owning the state transitions every client
 * fetch repeats: flags up, flags down, log and surface the failure.
 *
 * The state is created per call, so a module that wants one feed shared across
 * every mount of its component calls this once at module scope and hands the
 * same object back from its composable.
 *
 * @param {function(...*): Promise<Object[]|FeedPage>} loader Resolves to the
 *   feed items, or to a FeedPage when the feed can be paged. Arguments passed
 *   to load() are forwarded to it; a loadMore() adds the token the last page
 *   returned after them, so a paged loader declares one parameter more than
 *   load() passes.
 * @return {{feedState: FeedState, load: function(...*): Promise<void>,
 *   loadMore: function(): Promise<void>}}
 */
function useFeedState( loader ) {
	const items = ref( [] );
	const isLoading = ref( false );
	const isLoadingMore = ref( false );
	const error = ref( null );

	const continuation = ref( null );
	const hasMore = computed( () => continuation.value !== null );

	let loadArgs = [];

	/**
	 * Run the loader and commit its result.
	 *
	 * One runs at a time: a call made while another is in flight does nothing,
	 * so two results can never interleave.
	 *
	 * Never rejects: a failure lands in `error` for the scaffold to render.
	 *
	 * @param {boolean} append Whether to add to the items already shown.
	 * @param {*[]} [args] Loader arguments for a first load, which starts the
	 *   feed over. A further page reuses the arguments the first load passed.
	 * @return {Promise<void>}
	 */
	async function run( append, args ) {
		if ( isLoading.value || isLoadingMore.value ) {
			return;
		}

		const inFlight = append ? isLoadingMore : isLoading;
		inFlight.value = true;
		error.value = null;

		if ( !append ) {
			loadArgs = args;
			continuation.value = null;
		}

		try {
			// Never hand a first load the token: a loader that cannot page
			// would find it in a parameter it declared for something else.
			const result = append ?
				await loader( ...loadArgs, continuation.value ) :
				await loader( ...loadArgs );

			// A loader with nothing to page through resolves to a plain array.
			const page = Array.isArray( result ) ? { items: result } : result;
			const pageItems = page.items || [];

			items.value = append ? items.value.concat( pageItems ) : pageItems;
			continuation.value = page.continuation || null;
		} catch ( err ) {
			mw.log.error( err.message );
			error.value = err;
			if ( !append ) {
				items.value = [];
			}
			// A failed further page keeps its token and its control, so the
			// reader can ask for the same page again.
		} finally {
			inFlight.value = false;
		}
	}

	/**
	 * Load the first page, replacing whatever is shown. Call it again once a
	 * load has finished to start the feed over.
	 *
	 * @param {...*} args Forwarded to the loader.
	 * @return {Promise<void>}
	 */
	async function load( ...args ) {
		return run( false, args );
	}

	/**
	 * Load the next page and add it below the items already shown.
	 *
	 * Does nothing when there is no next page.
	 *
	 * @return {Promise<void>}
	 */
	async function loadMore() {
		if ( hasMore.value ) {
			return run( true );
		}
	}

	return {
		// reactive(), so a consumer can v-bind the contract however it was
		// produced: a getJsData() payload hands over plain values.
		feedState: reactive( { items, isLoading, isLoadingMore, hasMore, error } ),
		load,
		loadMore
	};
}

module.exports = { useFeedState };
