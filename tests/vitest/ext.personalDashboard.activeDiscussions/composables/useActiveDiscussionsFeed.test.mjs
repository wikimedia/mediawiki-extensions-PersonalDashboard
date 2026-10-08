import { afterEach, beforeEach, test, expect, vi } from 'vitest';
import { useActiveDiscussionsFeed } from '/resources/ext.personalDashboard.activeDiscussions/composables/useActiveDiscussionsFeed.js';

const { feedState, load, loadMore } = useActiveDiscussionsFeed();

let get;

beforeEach( async () => {
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

test( 'asks the feed endpoint for the active discussions source', async () => {
	get.mockResolvedValue( { items: [] } );

	await load( 10 );

	expect( get ).toHaveBeenCalledWith( '/personaldashboard/v0/feed', {
		sources: 'activediscussions',
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
		sources: 'activediscussions',
		limit: 10,
		continue: 'token-1'
	} );
	expect( feedState.items ).toStrictEqual( [ { id: 'a' }, { id: 'b' } ] );
	expect( feedState.hasMore ).toBe( false );
} );
