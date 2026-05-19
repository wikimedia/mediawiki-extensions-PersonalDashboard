import { vi, beforeEach, afterEach, test, expect, describe } from 'vitest';

// Mock ext.personalDashboard.common so getRandomItems is predictable in tests
vi.mock( 'ext.personalDashboard.common', () => ( {
	utils: {
		// Return items as-is up to limit so tests are deterministic
		getRandomItems: ( items, limit ) => items.slice( 0, limit ),
		handleApiErrors: vi.fn( ( code ) => {
			throw new Error( code );
		} ),
		parseApiStatus: vi.fn( () => [] )
	}
} ) );

import { useWatchlistFeed } from '/resources/ext.personalDashboard.riskyArticleEdits/composables/useWatchlistFeed.js';

function makeRawWatchlistItem( overrides ) {
	return Object.assign( {
		revid: 1001,
		// eslint-disable-next-line camelcase
		old_revid: 1000,
		title: 'Article Title',
		user: 'Alice',
		timestamp: '2024-03-10T10:00:00Z',
		newlen: 5000,
		oldlen: 4800,
		parsedcomment: 'A comment',
		minor: false,
		bot: false,
		new: false,
		tags: []
	}, overrides );
}

function makeApiResponse( items, continueToken ) {
	const response = { query: { watchlist: items } };
	if ( continueToken ) {
		response.continue = { wlcontinue: continueToken };
	}
	return response;
}

beforeEach( () => {
	mw.user.getRights = () => [];
} );

afterEach( () => {
	mw.Api.reset();
} );

// ---------------------------------------------------------------------------
// Basic fetching
// ---------------------------------------------------------------------------

describe( 'fetchWatchlistItems', () => {
	test( 'returns normalized feed items from the API response', async () => {
		const raw = makeRawWatchlistItem();
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items ).toHaveLength( 1 );
		expect( items[ 0 ] ).toMatchObject( {
			id: 'watchlist-1001',
			feedorigin: 'watchlist',
			title: 'Article Title',
			revid: 1001,
			// eslint-disable-next-line camelcase
			old_revid: 1000,
			user: 'Alice',
			timestamp: '2024-03-10T10:00:00Z',
			newlen: 5000,
			oldlen: 4800,
			parsedcomment: 'A comment',
			minor: false,
			bot: false,
			tags: []
		} );
	} );

	test( 'returns an empty array when the API returns no items', async () => {
		mw.Api.mock( () => makeApiResponse( [] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items ).toEqual( [] );
	} );

	test( 'returns an empty array when the API returns null', async () => {
		mw.Api.mock( () => null );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items ).toEqual( [] );
	} );

	test( 'returns an empty array when the API response has no query', async () => {
		mw.Api.mock( () => ( {} ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items ).toEqual( [] );
	} );

	test( 'respects the limit when sampling items', async () => {
		const rawItems = Array.from( { length: 10 }, ( _, i ) => makeRawWatchlistItem( { revid: i + 1, title: 'Article ' + ( i + 1 ) } )
		);
		mw.Api.mock( () => makeApiResponse( rawItems ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 3, 10 );

		expect( items ).toHaveLength( 3 );
	} );

	test( 'excludes items tagged with mw-reverted', async () => {
		const raw = makeRawWatchlistItem( { tags: [ 'mw-reverted' ] } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items ).toHaveLength( 0 );
	} );

	test( 'excludes items tagged with mw-rollback', async () => {
		const raw = makeRawWatchlistItem( { tags: [ 'mw-rollback' ] } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items ).toHaveLength( 0 );
	} );

	test( 'excludes items tagged with mw-undo', async () => {
		const raw = makeRawWatchlistItem( { tags: [ 'mw-undo' ] } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items ).toHaveLength( 0 );
	} );

	test( 'keeps items with unrelated tags', async () => {
		const raw = makeRawWatchlistItem( { tags: [ 'mobile edit' ] } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items ).toHaveLength( 1 );
	} );
} );

// ---------------------------------------------------------------------------
// API params
// ---------------------------------------------------------------------------

describe( 'API params', () => {
	test( 'sends the correct base params', async () => {
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchWatchlistItems } = useWatchlistFeed();
		await fetchWatchlistItems( 5, 10 );

		expect( capturedParams ).toMatchObject( {
			action: 'query',
			list: 'watchlist',
			format: 'json',
			formatversion: '2',
			wlnamespace: '0',
			wltype: 'edit',
			wlshow: '!bot'
		} );
	} );

	test( 'excludes the current user from results', async () => {
		mw.user.getName = () => 'TestUser';
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchWatchlistItems } = useWatchlistFeed();
		await fetchWatchlistItems( 5, 10 );

		expect( capturedParams.wlexcludeuser ).toBe( 'TestUser' );
	} );

	test( 'adds !patrolled to wlshow when user has patrol rights', async () => {
		mw.user.getRights = () => [ 'patrol' ];
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchWatchlistItems } = useWatchlistFeed();
		await fetchWatchlistItems( 5, 10 );

		expect( capturedParams.wlshow ).toContain( '!patrolled' );
	} );

	test( 'does not add !patrolled to wlshow when user lacks patrol rights', async () => {
		mw.user.getRights = () => [];
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchWatchlistItems } = useWatchlistFeed();
		await fetchWatchlistItems( 5, 10 );

		expect( capturedParams.wlshow ).not.toContain( '!patrolled' );
	} );
} );

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

describe( 'pagination', () => {
	test( 'does not paginate when the first response meets the limit', async () => {
		const rawItems = Array.from( { length: 5 }, ( _, i ) => makeRawWatchlistItem( { revid: i + 1 } )
		);
		let callCount = 0;
		mw.Api.mock( () => {
			callCount++;
			// Return a continue token to prove pagination was not triggered
			return makeApiResponse( rawItems, 'token-page-2' );
		} );

		const { fetchWatchlistItems } = useWatchlistFeed();
		await fetchWatchlistItems( 5, 10 );

		expect( callCount ).toBe( 1 );
	} );

	test( 'paginates when results are below the limit', async () => {
		const page1Items = [
			makeRawWatchlistItem( { revid: 1 } ),
			makeRawWatchlistItem( { revid: 2 } )
		];
		const page2Items = [
			makeRawWatchlistItem( { revid: 3 } ),
			makeRawWatchlistItem( { revid: 4 } ),
			makeRawWatchlistItem( { revid: 5 } )
		];

		let callCount = 0;
		mw.Api.mock( ( params ) => {
			callCount++;
			if ( params.wlcontinue === 'token-page-2' ) {
				return makeApiResponse( page2Items );
			}
			return makeApiResponse( page1Items, 'token-page-2' );
		} );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( callCount ).toBe( 2 );
		expect( items ).toHaveLength( 5 );
	} );

	test( 'sends wlcontinue offset on paginated requests', async () => {
		const page1Items = [ makeRawWatchlistItem( { revid: 1 } ) ];
		const capturedParams = [];

		mw.Api.mock( ( params ) => {
			capturedParams.push( { ...params } );
			if ( params.wlcontinue === 'token-page-2' ) {
				return makeApiResponse( [ makeRawWatchlistItem( { revid: 2 } ) ] );
			}
			return makeApiResponse( page1Items, 'token-page-2' );
		} );

		const { fetchWatchlistItems } = useWatchlistFeed();
		await fetchWatchlistItems( 5, 10 );

		expect( capturedParams[ 1 ].wlcontinue ).toBe( 'token-page-2' );
	} );

	test( 'respects maxApiRequests and stops paginating', async () => {
		let callCount = 0;
		mw.Api.mock( () => {
			callCount++;
			return makeApiResponse(
				[ makeRawWatchlistItem( { revid: callCount } ) ],
				'token-next'
			);
		} );

		const { fetchWatchlistItems } = useWatchlistFeed();
		// limit=100 ensures we'd always want more, maxApiRequests=3 stops us
		await fetchWatchlistItems( 100, 3 );

		// 1 initial request + 3 paginated = 4 total
		expect( callCount ).toBe( 4 );
	} );

	test( 'does not paginate when the first response returns zero items', async () => {
		let callCount = 0;
		mw.Api.mock( () => {
			callCount++;
			return makeApiResponse( [], 'token-page-2' );
		} );

		const { fetchWatchlistItems } = useWatchlistFeed();
		await fetchWatchlistItems( 5, 10 );

		expect( callCount ).toBe( 1 );
	} );
} );

// ---------------------------------------------------------------------------
// Normalization
// ---------------------------------------------------------------------------

describe( 'normalization', () => {
	test( 'defaults old_revid to null when absent', async () => {
		// eslint-disable-next-line camelcase
		const raw = makeRawWatchlistItem( { old_revid: undefined } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items[ 0 ].old_revid ).toBeNull();
	} );

	test( 'defaults user to empty string when absent', async () => {
		const raw = makeRawWatchlistItem( { user: undefined } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items[ 0 ].user ).toBe( '' );
	} );

	test( 'defaults tags to empty array when absent', async () => {
		const raw = makeRawWatchlistItem( { tags: undefined } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items[ 0 ].tags ).toEqual( [] );
	} );

	test( 'casts minor to boolean', async () => {
		const raw = makeRawWatchlistItem( { minor: '' } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( typeof items[ 0 ].minor ).toBe( 'boolean' );
	} );

	test( 'sets feedorigin to watchlist', async () => {
		mw.Api.mock( () => makeApiResponse( [ makeRawWatchlistItem() ] ) );

		const { fetchWatchlistItems } = useWatchlistFeed();
		const items = await fetchWatchlistItems( 5, 10 );

		expect( items[ 0 ].feedorigin ).toBe( 'watchlist' );
	} );
} );
