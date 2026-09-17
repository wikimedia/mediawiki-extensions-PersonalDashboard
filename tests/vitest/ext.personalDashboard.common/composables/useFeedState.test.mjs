import { beforeEach, test, expect, vi } from 'vitest';
import { useFeedState } from '/resources/ext.personalDashboard.common/composables/useFeedState.js';

beforeEach( () => {
	vi.restoreAllMocks();
} );

test( 'starts empty and idle', () => {
	const { feedState } = useFeedState( async () => [] );

	expect( feedState.items ).toStrictEqual( [] );
	expect( feedState.isLoading ).toBe( false );
	expect( feedState.error ).toBeNull();
} );

test( 'commits the loader result', async () => {
	const items = [ { id: 'a' }, { id: 'b' } ];
	const { feedState, load } = useFeedState( async () => items );

	await load();

	expect( feedState.items ).toStrictEqual( items );
	expect( feedState.isLoading ).toBe( false );
	expect( feedState.error ).toBeNull();
} );

test( 'forwards every load argument to the loader', async () => {
	const loader = vi.fn().mockResolvedValue( [] );
	const { load } = useFeedState( loader );

	await load( 10, 'extra' );

	expect( loader ).toHaveBeenCalledWith( 10, 'extra' );
} );

test( 'never hands a first load a continuation argument', async () => {
	// A loader that does not page declares its own last parameter, and a token
	// appended there would land in it.
	const loader = vi.fn().mockResolvedValue( [] );
	const { load } = useFeedState( loader );

	await load();

	expect( loader ).toHaveBeenCalledWith();
} );

test( 'is loading while the loader is in flight', async () => {
	let resolveLoader;
	const { feedState, load } = useFeedState(
		() => new Promise( ( resolve ) => {
			resolveLoader = resolve;
		} )
	);

	const pending = load();
	expect( feedState.isLoading ).toBe( true );

	resolveLoader( [] );
	await pending;

	expect( feedState.isLoading ).toBe( false );
} );

test( 'surfaces and logs a loader failure instead of rejecting', async () => {
	const logError = vi.spyOn( mw.log, 'error' ).mockImplementation( () => {} );
	const { feedState, load } = useFeedState( async () => {
		throw new Error( 'boom' );
	} );

	await expect( load() ).resolves.toBeUndefined();

	expect( feedState.error.message ).toBe( 'boom' );
	expect( feedState.items ).toStrictEqual( [] );
	expect( feedState.isLoading ).toBe( false );
	expect( logError ).toHaveBeenCalledWith( 'boom' );
} );

test( 'clears a previous failure and its items on the next load', async () => {
	vi.spyOn( mw.log, 'error' ).mockImplementation( () => {} );
	let shouldFail = true;
	const { feedState, load } = useFeedState( async () => {
		if ( shouldFail ) {
			throw new Error( 'boom' );
		}
		return [ { id: 'a' } ];
	} );

	await load();
	expect( feedState.error ).not.toBeNull();

	shouldFail = false;
	await load();

	expect( feedState.error ).toBeNull();
	expect( feedState.items ).toStrictEqual( [ { id: 'a' } ] );
} );

test( 'each call gets its own state', async () => {
	const first = useFeedState( async () => [ { id: 'a' } ] );
	const second = useFeedState( async () => [ { id: 'b' } ] );

	await first.load();

	expect( first.feedState.items ).toStrictEqual( [ { id: 'a' } ] );
	expect( second.feedState.items ).toStrictEqual( [] );
} );

test( 'a plain array result means there is nothing to page', async () => {
	const { feedState } = useFeedState( async () => [ { id: 'a' } ] );

	expect( feedState.hasMore ).toBe( false );
	expect( feedState.isLoadingMore ).toBe( false );
} );

test( 'offers a further page while the loader returns a continuation', async () => {
	let token = 'token-1';
	const { feedState, load } = useFeedState(
		async () => ( { items: [ { id: 'a' } ], continuation: token } )
	);

	await load();
	expect( feedState.hasMore ).toBe( true );

	token = null;
	await load();
	expect( feedState.hasMore ).toBe( false );
} );

test( 'loadMore hands the token back and adds the page below the first', async () => {
	const loader = vi.fn()
		.mockResolvedValueOnce( { items: [ { id: 'a' } ], continuation: 'token-1' } )
		.mockResolvedValueOnce( { items: [ { id: 'b' } ], continuation: null } );
	const { feedState, load, loadMore } = useFeedState( loader );

	await load( 10 );
	await loadMore();

	expect( loader ).toHaveBeenLastCalledWith( 10, 'token-1' );
	expect( feedState.items ).toStrictEqual( [ { id: 'a' }, { id: 'b' } ] );
	expect( feedState.hasMore ).toBe( false );
} );

test( 'loadMore does nothing without a further page', async () => {
	const loader = vi.fn().mockResolvedValue( [ { id: 'a' } ] );
	const { load, loadMore } = useFeedState( loader );

	await load();
	await loadMore();

	expect( loader ).toHaveBeenCalledTimes( 1 );
} );

test( 'is loading more, not loading, while a further page is in flight', async () => {
	let resolveLoader;
	const { feedState, load, loadMore } = useFeedState(
		( limit, continuation ) => continuation ?
			new Promise( ( resolve ) => {
				resolveLoader = resolve;
			} ) :
			Promise.resolve( { items: [ { id: 'a' } ], continuation: 'token-1' } )
	);

	await load( 10 );
	const pending = loadMore();

	expect( feedState.isLoadingMore ).toBe( true );
	expect( feedState.isLoading ).toBe( false );

	resolveLoader( { items: [], continuation: null } );
	await pending;

	expect( feedState.isLoadingMore ).toBe( false );
} );

test( 'a failed further page keeps the items and the offer to retry', async () => {
	vi.spyOn( mw.log, 'error' ).mockImplementation( () => {} );
	const { feedState, load, loadMore } = useFeedState(
		async ( limit, continuation ) => {
			if ( continuation ) {
				throw new Error( 'boom' );
			}
			return { items: [ { id: 'a' } ], continuation: 'token-1' };
		}
	);

	await load( 10 );
	await loadMore();

	expect( feedState.error.message ).toBe( 'boom' );
	expect( feedState.items ).toStrictEqual( [ { id: 'a' } ] );
	expect( feedState.hasMore ).toBe( true );
} );

test( 'ignores a load while the first one is still in flight', async () => {
	let releaseFirst;
	const loader = vi.fn().mockImplementationOnce( () => new Promise( ( resolve ) => {
		releaseFirst = resolve;
	} ) );
	const { feedState, load } = useFeedState( loader );

	const first = load();
	await load();

	releaseFirst( [ { id: 'first' } ] );
	await first;

	expect( loader ).toHaveBeenCalledTimes( 1 );
	expect( feedState.items ).toStrictEqual( [ { id: 'first' } ] );
	expect( feedState.isLoading ).toBe( false );
} );

test( 'ignores a load while a further page is in flight', async () => {
	let releaseMore;
	const loader = vi.fn()
		.mockResolvedValueOnce( { items: [ { id: 'a' } ], continuation: 'token-1' } )
		.mockImplementationOnce( () => new Promise( ( resolve ) => {
			releaseMore = resolve;
		} ) );
	const { feedState, load, loadMore } = useFeedState( loader );

	await load( 10 );
	const more = loadMore();
	await load( 10 );

	releaseMore( { items: [ { id: 'b' } ], continuation: null } );
	await more;

	expect( loader ).toHaveBeenCalledTimes( 2 );
	expect( feedState.items ).toStrictEqual( [ { id: 'a' }, { id: 'b' } ] );
	expect( feedState.hasMore ).toBe( false );
	expect( feedState.isLoadingMore ).toBe( false );
} );

test( 'treats a page with no items as an empty one', async () => {
	const { feedState, load } = useFeedState( async () => ( { continuation: null } ) );

	await load();

	expect( feedState.items ).toStrictEqual( [] );
} );

test( 'ignores a second loadMore while one is already in flight', async () => {
	let releaseMore;
	const loader = vi.fn()
		.mockResolvedValueOnce( { items: [ { id: 'a' } ], continuation: 'token-1' } )
		.mockImplementationOnce( () => new Promise( ( resolve ) => {
			releaseMore = resolve;
		} ) );
	const { load, loadMore } = useFeedState( loader );

	await load( 10 );
	const first = loadMore();
	await loadMore();
	await loadMore();

	// Two loads so far: the first page and the one page still in flight.
	expect( loader ).toHaveBeenCalledTimes( 2 );

	releaseMore( { items: [ { id: 'b' } ], continuation: null } );
	await first;
} );

test( 'ignores loadMore while the first page is still loading', async () => {
	let releaseFirst;
	const loader = vi.fn().mockImplementation( () => new Promise( ( resolve ) => {
		releaseFirst = resolve;
	} ) );
	const { load, loadMore } = useFeedState( loader );

	const first = load( 10 );
	await loadMore();

	expect( loader ).toHaveBeenCalledTimes( 1 );

	releaseFirst( { items: [], continuation: null } );
	await first;
} );
