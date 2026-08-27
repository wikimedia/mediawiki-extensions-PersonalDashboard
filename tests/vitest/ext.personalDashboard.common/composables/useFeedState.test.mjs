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
