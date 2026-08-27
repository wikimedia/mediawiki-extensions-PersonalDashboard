/**
 * @file composables/useFeedState.js
 *
 * The normalized feed-data contract the shared feed scaffold consumes, and the
 * client-fetch implementation of it.
 *
 * A feed module supplies its items and its state; FeedPanel.vue owns everything
 * else (loading, error, compact-versus-full, footer). Anything that produces the
 * shape below satisfies the contract: this composable, a Pinia store that
 * coordinates several sources (see reviewChangesStore's feedState getter), or a
 * plain object built from server-normalized data handed over via getJsData() —
 * a module fed that way needs no fetch layer at all, only
 * `{ items: data, isLoading: false, error: null }`.
 */

const { reactive, ref } = require( 'vue' );

/**
 * @typedef {Object} FeedState
 * @property {Object[]} items Feed items to render. Each item needs a unique
 *   `id`; the scaffold keys the list on it.
 * @property {boolean} isLoading Whether a load is in flight.
 * @property {Error|null} error The last load failure, or null.
 */

/**
 * Build a FeedState around a loader, owning the state transitions every client
 * fetch repeats: flags up, flags down, log and surface the failure.
 *
 * The state is created per call, so a module that wants one feed shared across
 * every mount of its component calls this once at module scope and hands the
 * same object back from its composable.
 *
 * @param {function(...*): Promise<Object[]>} loader Resolves to the feed items.
 *   Arguments passed to load() are forwarded to it.
 * @return {{feedState: FeedState, load: function(...*): Promise<void>}}
 */
function useFeedState( loader ) {
	const items = ref( [] );
	const isLoading = ref( false );
	const error = ref( null );

	/**
	 * Run the loader and commit its result.
	 *
	 * Never rejects: a failure lands in `error` for the scaffold to render.
	 *
	 * @param {...*} args Forwarded to the loader.
	 * @return {Promise<void>}
	 */
	async function load( ...args ) {
		isLoading.value = true;
		error.value = null;

		try {
			items.value = await loader( ...args );
		} catch ( err ) {
			mw.log.error( err.message );
			error.value = err;
			items.value = [];
		} finally {
			isLoading.value = false;
		}
	}

	return {
		// reactive() rather than the raw refs so the contract reads the same
		// however it was produced: a store getter and a getJsData() payload hand
		// over plain values, and a consumer can v-bind any of them unchanged.
		feedState: reactive( { items, isLoading, error } ),
		load
	};
}

module.exports = { useFeedState };
