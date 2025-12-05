import { test, expectTypeOf, vi, expect } from 'vitest';
import { useFetchActivityResult } from '@resources/ext.personalDashboard.common';
const {
	recentActivityResult,
	error,
	fetchRecentActivity
} = useFetchActivityResult();

test( 'fetchRecentActivity with response', async () => {
	const mockResponse = {
		query: {
			recentchanges: [ {
				title: 'A title',
				type: 'type',
				ns: 0,
				newlen: 22,
				// eslint-disable-next-line camelcase
				old_revid: 418549,
				oldlen: 545,
				pageid: 494,
				rcid: 8947984,
				revid: 58859,
				temp: '',
				user: 'user',
				comment: 'comment',
				tags: [],
				timestamp: ''
			} ],
			pages: {}
		}
	};
	window.mw = {
		...window.mw,
		user: {
			getName: vi.fn( () => 'TestUser' )
		},
		// eslint-disable-next-line prefer-arrow-callback
		Api: vi.fn().mockImplementation( function Api() {
			return {
				get: vi.fn().mockResolvedValue( mockResponse )
			};
		} )
	};
	expectTypeOf( fetchRecentActivity ).toExtend( 'asyncFunction' );

	await fetchRecentActivity();
	const result = recentActivityResult.value;

	expect( mockResponse.query.recentchanges ).toEqual( result.query.recentchanges );
	expect( mockResponse.query.pages ).toEqual( result.query.pages );
} );

test( 'fetchRecentActivity with no changes', async () => {
	const mockResponse = {
		query: {
			recentchanges: [],
			pages: {}
		}
	};
	window.mw = {
		...window.mw,
		user: {
			getName: vi.fn( () => 'TestUser' )
		},
		// eslint-disable-next-line prefer-arrow-callback
		Api: vi.fn().mockImplementation( function Api() {
			return {
				get: vi.fn().mockResolvedValue( mockResponse )
			};
		} )
	};
	expectTypeOf( fetchRecentActivity ).toExtend( 'asyncFunction' );

	await fetchRecentActivity();
	const result = recentActivityResult.value;

	expect( mockResponse.query.recentchanges ).toEqual( result.query.recentchanges );
	expect( mockResponse.query.pages ).toEqual( result.query.pages );
} );

test( 'fetchRecentActivity with error', async () => {
	const emptyResponse = {
		query: {
			recentchanges: [],
			pages: {}
		}
	};
	window.mw = {
		...window.mw,
		user: {
			getName: vi.fn( () => 'TestUser' )
		},
		// eslint-disable-next-line prefer-arrow-callback
		Api: vi.fn().mockImplementation( function Api() {
			return {
				get: vi.fn().mockRejectedValue( 'An error' )
			};
		} )
	};
	expectTypeOf( fetchRecentActivity ).toExtend( 'asyncFunction' );

	await fetchRecentActivity();

	expect( error ).toBeTruthy();
	expect( error.value ).toBe( 'An error' );
	expect( emptyResponse ).toEqual( recentActivityResult.value );
} );
