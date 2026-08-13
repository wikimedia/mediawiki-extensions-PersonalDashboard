import { vi, beforeEach, afterEach, test, expect, describe } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const {
	mockFetchWatchlistItems,
	mockFetchRecentChangesItems,
	mockFetchRecentlyEditedItems
} = vi.hoisted( () => ( {
	mockFetchWatchlistItems: vi.fn(),
	mockFetchRecentChangesItems: vi.fn(),
	mockFetchRecentlyEditedItems: vi.fn()
} ) );

vi.mock(
	'/resources/ext.personalDashboard.reviewChanges/composables/useWatchlistFeed.js',
	() => ( { useWatchlistFeed: () => ( { fetchWatchlistItems: mockFetchWatchlistItems } ) } )
);

vi.mock(
	'/resources/ext.personalDashboard.reviewChanges/composables/useRecentChangesFeed.js',
	() => ( {
		useRecentChangesFeed: () => ( { fetchRecentChangesItems: mockFetchRecentChangesItems } )
	} )
);

vi.mock(
	'/resources/ext.personalDashboard.reviewChanges/composables/useRecentlyEditedFeed.js',
	() => ( {
		useRecentlyEditedFeed: () => ( { fetchRecentlyEditedItems: mockFetchRecentlyEditedItems } )
	} )
);

import { useReviewChangesStore } from '/resources/ext.personalDashboard.reviewChanges/store/reviewChangesStore.js';

function makeFeedItem( overrides ) {
	return Object.assign( {
		id: 122,
		feedorigin: 'watchlist',
		title: 'Article Title',
		revid: 1,
		timestamp: '2024-03-10T10:00:00Z',
		tags: []
	}, overrides );
}

function makeRCResult( items, pages ) {
	return { items: items || [], pages: pages || [] };
}

beforeEach( () => {
	setActivePinia( createPinia() );
	mockFetchWatchlistItems.mockReset();
	mockFetchRecentChangesItems.mockReset();
	mockFetchRecentlyEditedItems.mockReset();
	// The recently-edited feed is synchronous and prefetched; default it to empty
	// so tests only opt in to it when that source is what's under test.
	mockFetchRecentlyEditedItems.mockReturnValue( [] );
} );

describe( 'initial state', () => {
	test( 'has empty feed array', () => {
		const store = useReviewChangesStore();
		expect( store.feed ).toEqual( [] );
	} );

	test( 'has empty pages array', () => {
		const store = useReviewChangesStore();
		expect( store.pages ).toEqual( [] );
	} );

	test( 'has isLoading set to false', () => {
		const store = useReviewChangesStore();
		expect( store.isLoading ).toBe( false );
	} );

	test( 'has no error', () => {
		const store = useReviewChangesStore();
		expect( store.error ).toBeNull();
	} );
} );

describe( 'hasFeed getter', () => {
	test( 'is false when feed is empty', () => {
		const store = useReviewChangesStore();
		expect( store.hasFeed ).toBe( false );
	} );

	test( 'is true when feed has items', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockResolvedValue(
			makeRCResult( [ makeFeedItem( { id: 98, feedorigin: 'recentchanges' } ) ] )
		);

		await store.fetchRecentActivity( 5 );

		expect( store.hasFeed ).toBe( true );
	} );
} );

describe( 'merge and sort behaviour', () => {
	test( 'merged feed is sorted by timestamp descending', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [
			makeFeedItem( { id: 312, feedorigin: 'watchlist', title: 'Old WL', timestamp: '2024-01-01T00:00:00Z' } )
		] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult( [
			makeFeedItem( { id: 12344, feedorigin: 'recentchanges', title: 'New RC', timestamp: '2024-03-01T00:00:00Z' } ),
			makeFeedItem( { id: 452, feedorigin: 'recentchanges', title: 'Mid RC', timestamp: '2024-02-01T00:00:00Z' } )
		] ) );

		await store.fetchRecentActivity( 10 );

		const titles = store.feed.map( ( i ) => i.title );
		expect( titles ).toEqual( [ 'New RC', 'Mid RC', 'Old WL' ] );
	} );

	test( 'feed.length never exceeds the requested limit', async () => {
		const store = useReviewChangesStore();
		const manyItems = Array.from( { length: 20 }, ( _, i ) => makeFeedItem( {
			id: `rc-${ i }`,
			feedorigin: 'recentchanges',
			title: `Article ${ i }`,
			timestamp: `2024-03-${ String( i + 1 ).padStart( 2, '0' ) }T00:00:00Z`
		} )
		);
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult( manyItems ) );

		await store.fetchRecentActivity( 5 );

		expect( store.feed.length ).toBe( 5 );
		expect( store.feed.map( ( i ) => i.title ) ).toEqual( [
			'Article 19', 'Article 18', 'Article 17', 'Article 16', 'Article 15'
		] );
	} );

	test( 'pages from RC result are stored on state', async () => {
		const store = useReviewChangesStore();
		const pages = [ { pageid: 1, title: 'Article Title', description: 'A description' } ];
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult( [], pages ) );

		await store.fetchRecentActivity( 5 );

		expect( store.pages ).toEqual( pages );
	} );
} );

describe( 'recently edited source', () => {
	test( 'includes recently-edited items in the merged feed', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult( [] ) );
		mockFetchRecentlyEditedItems.mockReturnValue( [
			makeFeedItem( { id: 'recentlyedited-1', feedorigin: 'recentlyedited', title: 'RE Article' } )
		] );

		await store.fetchRecentActivity( 10 );

		expect( store.feed ).toHaveLength( 1 );
		expect( store.feed[ 0 ].feedorigin ).toBe( 'recentlyedited' );
	} );

	test( 'merges items from all three sources', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [
			makeFeedItem( { id: 'watchlist-1', feedorigin: 'watchlist', title: 'WL Article', timestamp: '2024-03-01T00:00:00Z' } )
		] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult( [
			makeFeedItem( { id: 'recentchanges-1', feedorigin: 'recentchanges', title: 'RC Article', timestamp: '2024-03-02T00:00:00Z' } )
		] ) );
		mockFetchRecentlyEditedItems.mockReturnValue( [
			makeFeedItem( { id: 'recentlyedited-1', feedorigin: 'recentlyedited', title: 'RE Article', timestamp: '2024-03-03T00:00:00Z' } )
		] );

		await store.fetchRecentActivity( 10 );

		expect( store.feed.map( ( i ) => i.feedorigin ).sort() ).toEqual( [
			'recentchanges', 'recentlyedited', 'watchlist'
		] );
	} );

	test( 'sorts recently-edited items in with the other sources by timestamp', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [
			makeFeedItem( { id: 'watchlist-1', feedorigin: 'watchlist', title: 'Oldest', timestamp: '2024-01-01T00:00:00Z' } )
		] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult( [
			makeFeedItem( { id: 'recentchanges-1', feedorigin: 'recentchanges', title: 'Newest', timestamp: '2024-03-01T00:00:00Z' } )
		] ) );
		mockFetchRecentlyEditedItems.mockReturnValue( [
			makeFeedItem( { id: 'recentlyedited-1', feedorigin: 'recentlyedited', title: 'Middle', timestamp: '2024-02-01T00:00:00Z' } )
		] );

		await store.fetchRecentActivity( 10 );

		expect( store.feed.map( ( i ) => i.title ) ).toEqual( [ 'Newest', 'Middle', 'Oldest' ] );
	} );

	test( 'over-fetches the prefetched source so it can backfill the others', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult( [] ) );

		await store.fetchRecentActivity( 9 );

		// Sampling prefetched items costs no request, so ask for the whole limit.
		expect( mockFetchRecentlyEditedItems ).toHaveBeenCalledWith( 9 );
		// The watchlist costs an API request, so it is still only asked for a third.
		expect( mockFetchWatchlistItems ).toHaveBeenCalledWith( 3, expect.any( Number ) );
	} );

	test( 'still produces a feed when there are no recently-edited items', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult( [
			makeFeedItem( { id: 'recentchanges-1', feedorigin: 'recentchanges' } )
		] ) );
		mockFetchRecentlyEditedItems.mockReturnValue( [] );

		await store.fetchRecentActivity( 10 );

		expect( store.feed ).toHaveLength( 1 );
	} );
} );

describe( 'even split across sources', () => {
	/**
	 * Build `count` items for one source, newest first, on a timestamp track that
	 * doesn't overlap the other sources so ordering stays predictable.
	 *
	 * @param {string} source
	 * @param {number} count
	 * @param {string} month Two-digit month, to separate the sources in time.
	 * @return {Object[]}
	 */
	function makeSourceItems( source, count, month ) {
		return Array.from( { length: count }, ( _, i ) => makeFeedItem( {
			id: `${ source }-${ i }`,
			feedorigin: source,
			title: `${ source } ${ i }`,
			timestamp: `2024-${ month }-${ String( count - i ).padStart( 2, '0' ) }T00:00:00Z`
		} ) );
	}

	function countByOrigin( feed ) {
		return feed.reduce( ( counts, item ) => Object.assign( counts, {
			[ item.feedorigin ]: ( counts[ item.feedorigin ] || 0 ) + 1
		} ), {} );
	}

	test( 'gives each source an equal share when all have plenty', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( makeSourceItems( 'watchlist', 20, '01' ) );
		mockFetchRecentChangesItems.mockResolvedValue(
			makeRCResult( makeSourceItems( 'recentchanges', 20, '02' ) )
		);
		mockFetchRecentlyEditedItems.mockReturnValue( makeSourceItems( 'recentlyedited', 20, '03' ) );

		await store.fetchRecentActivity( 9 );

		expect( store.feed ).toHaveLength( 9 );
		expect( countByOrigin( store.feed ) ).toEqual( {
			recentchanges: 3,
			watchlist: 3,
			recentlyedited: 3
		} );
	} );

	test( 'does not let one busy source crowd out the others', async () => {
		const store = useReviewChangesStore();
		// Recent changes holds every one of the newest timestamps, so a plain
		// sort-and-slice would hand it all nine slots.
		mockFetchWatchlistItems.mockResolvedValue( makeSourceItems( 'watchlist', 5, '01' ) );
		mockFetchRecentChangesItems.mockResolvedValue(
			makeRCResult( makeSourceItems( 'recentchanges', 50, '12' ) )
		);
		mockFetchRecentlyEditedItems.mockReturnValue( makeSourceItems( 'recentlyedited', 5, '02' ) );

		await store.fetchRecentActivity( 9 );

		expect( countByOrigin( store.feed ) ).toEqual( {
			recentchanges: 3,
			watchlist: 3,
			recentlyedited: 3
		} );
	} );

	test( 'redistributes the share of a source that has nothing to offer', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockResolvedValue(
			makeRCResult( makeSourceItems( 'recentchanges', 20, '02' ) )
		);
		mockFetchRecentlyEditedItems.mockReturnValue( makeSourceItems( 'recentlyedited', 20, '03' ) );

		await store.fetchRecentActivity( 9 );

		// The feed still fills, split between the two sources that have items.
		expect( store.feed ).toHaveLength( 9 );
		expect( countByOrigin( store.feed ) ).toEqual( {
			recentchanges: 5,
			recentlyedited: 4
		} );
	} );

	test( 'returns everything available when no source can fill its share', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( makeSourceItems( 'watchlist', 1, '01' ) );
		mockFetchRecentChangesItems.mockResolvedValue(
			makeRCResult( makeSourceItems( 'recentchanges', 2, '02' ) )
		);
		mockFetchRecentlyEditedItems.mockReturnValue( [] );

		await store.fetchRecentActivity( 9 );

		expect( store.feed ).toHaveLength( 3 );
	} );

	test( 'shows a page once when two sources both surface it', async () => {
		const store = useReviewChangesStore();
		const shared = { title: 'Shared Page', timestamp: '2024-05-01T00:00:00Z' };
		mockFetchWatchlistItems.mockResolvedValue( [
			makeFeedItem( Object.assign( { id: 'watchlist-1', feedorigin: 'watchlist' }, shared ) )
		] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult( [] ) );
		mockFetchRecentlyEditedItems.mockReturnValue( [
			makeFeedItem( Object.assign( { id: 'recentlyedited-1', feedorigin: 'recentlyedited' }, shared ) )
		] );

		await store.fetchRecentActivity( 9 );

		expect( store.feed ).toHaveLength( 1 );
	} );

	test( 'still orders the selected items newest first', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( makeSourceItems( 'watchlist', 5, '01' ) );
		mockFetchRecentChangesItems.mockResolvedValue(
			makeRCResult( makeSourceItems( 'recentchanges', 5, '02' ) )
		);
		mockFetchRecentlyEditedItems.mockReturnValue( makeSourceItems( 'recentlyedited', 5, '03' ) );

		await store.fetchRecentActivity( 9 );

		const timestamps = store.feed.map( ( i ) => i.timestamp );
		expect( timestamps ).toEqual( [ ...timestamps ].sort().reverse() );
	} );
} );

describe( 'loading state', () => {
	test( 'sets isLoading to true while fetching then false after', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [] );
		let resolvePromise;
		mockFetchRecentChangesItems.mockReturnValue(
			new Promise( ( resolve ) => {
				resolvePromise = resolve;
			} )
		);

		const fetchPromise = store.fetchRecentActivity( 5 );
		expect( store.isLoading ).toBe( true );

		resolvePromise( makeRCResult() );
		await fetchPromise;
		expect( store.isLoading ).toBe( false );
	} );

	test( 'remains loading between watchlist and recentchanges fetches', async () => {
		const store = useReviewChangesStore();
		let resolveWL;
		let resolveRC;
		mockFetchWatchlistItems.mockReturnValue( new Promise( ( resolve ) => {
			resolveWL = resolve;
		} ) );
		mockFetchRecentChangesItems.mockReturnValue( new Promise( ( resolve ) => {
			resolveRC = resolve;
		} ) );

		const fetchPromise = store.fetchRecentActivity( 5 );
		expect( store.isLoading ).toBe( true );

		resolveWL( [] );
		await Promise.resolve();
		expect( store.isLoading ).toBe( true );

		resolveRC( makeRCResult() );
		await fetchPromise;
		expect( store.isLoading ).toBe( false );
	} );
} );

describe( 'error handling', () => {
	beforeEach( () => {
		vi.spyOn( mw.log, 'error' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	test( 'sets state.error when an exception is thrown', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [] );
		const error = new Error( 'fetch failed' );
		mockFetchRecentChangesItems.mockRejectedValue( error );

		await store.fetchRecentActivity( 5 );

		expect( store.error ).toBe( error );
		expect( mw.log.error ).toHaveBeenCalledWith( 'fetch failed' );
	} );

	test( 'resets isLoading to false after an error', async () => {
		const store = useReviewChangesStore();
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockRejectedValue( new Error( 'fail' ) );

		await store.fetchRecentActivity( 5 );

		expect( store.isLoading ).toBe( false );
		expect( mw.log.error ).toHaveBeenCalledWith( 'fail' );
	} );

	test( 'leaves feed unchanged when an error is thrown', async () => {
		const store = useReviewChangesStore();
		const existing = [ makeFeedItem( { id: 98, title: 'Existing' } ) ];
		store.feed = existing;
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockRejectedValue( new Error( 'fail' ) );

		await store.fetchRecentActivity( 5 );

		expect( store.feed ).toEqual( existing );
		expect( mw.log.error ).toHaveBeenCalledWith( 'fail' );
	} );

	test( 'clears previous error before fetching', async () => {
		const store = useReviewChangesStore();
		store.error = new Error( 'old error' );
		mockFetchWatchlistItems.mockResolvedValue( [] );
		mockFetchRecentChangesItems.mockResolvedValue( makeRCResult() );

		await store.fetchRecentActivity( 5 );

		expect( store.error ).toBeNull();
		expect( mw.log.error ).not.toHaveBeenCalled();
	} );

	test( 'sets state.error when watchlist throws', async () => {
		const store = useReviewChangesStore();
		const error = new Error( 'WL down' );
		mockFetchWatchlistItems.mockRejectedValue( error );

		await store.fetchRecentActivity( 5 );

		expect( store.error ).toBe( error );
		expect( store.isLoading ).toBe( false );
		expect( mw.log.error ).toHaveBeenCalledWith( 'WL down' );
	} );
} );
