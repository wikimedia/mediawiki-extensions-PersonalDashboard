import { vi, beforeEach, afterEach, test, expect, describe } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const { mockFetchWatchlistItems, mockFetchRecentChangesItems } = vi.hoisted( () => ( {
	mockFetchWatchlistItems: vi.fn(),
	mockFetchRecentChangesItems: vi.fn()
} ) );

vi.mock(
	'/resources/ext.personalDashboard.riskyArticleEdits/composables/useWatchlistFeed.js',
	() => ( { useWatchlistFeed: () => ( { fetchWatchlistItems: mockFetchWatchlistItems } ) } )
);

vi.mock(
	'/resources/ext.personalDashboard.riskyArticleEdits/composables/useRecentChangesFeed.js',
	() => ( { useRecentChangesFeed: () => ( { fetchRecentChangesItems: mockFetchRecentChangesItems } ) } )
);

import { useReviewChangesStore } from '/resources/ext.personalDashboard.riskyArticleEdits/store/reviewChangesStore.js';

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
