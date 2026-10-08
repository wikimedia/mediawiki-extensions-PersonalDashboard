import { afterEach, beforeEach, test, expect, vi } from 'vitest';
import { useReviewChangesFeed } from '/resources/ext.personalDashboard.reviewChanges/composables/useReviewChangesFeed.js';

const { feedState, load, loadMore } = useReviewChangesFeed();

const PERSONALIZE_OPTION_NAME = 'moderatortoolkit-reviewchanges-personalize';

let get;

beforeEach( async () => {
	mw.user.options.set( PERSONALIZE_OPTION_NAME, '1' );
	get = vi.fn();
	// A real function, not an arrow: fetchFeed calls `new mw.Rest()`, and
	// arrows can't be constructors.
	// eslint-disable-next-line prefer-arrow-callback
	mw.Rest = vi.fn().mockImplementation( function () {
		return { get };
	} );

	// The composable shares one state across every mount, so empty it here
	// rather than leaving each case to the one before it.
	get.mockResolvedValue( { items: [] } );
	await load( 10 );
	get.mockReset();
} );

afterEach( () => {
	vi.restoreAllMocks();
} );

test( 'asks for the personalized sources when personalization is on', async () => {
	get.mockResolvedValue( { items: [] } );

	await load( 10 );

	expect( get ).toHaveBeenCalledWith( '/personaldashboard/v0/feed', {
		sources: 'watchlist|recentlyedited|mostedited',
		limit: 10
	} );
} );

test( 'asks only for recent changes when personalization is off', async () => {
	mw.user.options.set( PERSONALIZE_OPTION_NAME, '0' );
	get.mockResolvedValue( { items: [] } );

	await load( 10 );

	expect( get ).toHaveBeenCalledWith( '/personaldashboard/v0/feed', {
		sources: 'recentchanges',
		limit: 10
	} );
} );

test( 'hands the token back and adds the next page below the first', async () => {
	get.mockResolvedValue( { items: [ { id: 'a' } ], continue: 'token-1' } );
	await load( 10 );
	expect( feedState.hasMore ).toBe( true );

	get.mockResolvedValue( { items: [ { id: 'b' } ] } );
	await loadMore();

	expect( get ).toHaveBeenLastCalledWith( '/personaldashboard/v0/feed', {
		sources: 'watchlist|recentlyedited|mostedited',
		limit: 10,
		continue: 'token-1'
	} );
	expect( feedState.items ).toStrictEqual( [ { id: 'a' }, { id: 'b' } ] );
	expect( feedState.hasMore ).toBe( false );
} );
