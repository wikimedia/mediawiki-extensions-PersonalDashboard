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

import { useRecentChangesFeed } from '/resources/ext.personalDashboard.riskyArticleEdits/composables/useRecentChangesFeed.js';

function makeRawRCItem( overrides ) {
	return Object.assign( {
		revid: 2001,
		// eslint-disable-next-line camelcase
		old_revid: 2000,
		title: 'Article Title',
		user: 'Bob',
		timestamp: '2024-03-10T11:00:00Z',
		newlen: 3000,
		oldlen: 2900,
		parsedcomment: 'A comment',
		minor: false,
		bot: false,
		new: false,
		tags: []
	}, overrides );
}

function makeApiResponse( items, pages, continueToken ) {
	const response = { query: { recentchanges: items, pages: pages || [] } };
	if ( continueToken ) {
		response.continue = { rccontinue: continueToken };
	}
	return response;
}

beforeEach( () => {
	mw.user.getRights = () => [];
	mw.config.set( 'wgPersonalDashboardRiskyArticleEditsMlEnabled', false );
	mw.config.set( 'wgPersonalDashboardRiskyArticleEditsMlModel', null );
} );

afterEach( () => {
	mw.Api.reset();
} );

// ---------------------------------------------------------------------------
// Basic fetching
// ---------------------------------------------------------------------------

describe( 'fetchRecentChangesItems', () => {
	test( 'returns normalized feed items and pages from the API response', async () => {
		const raw = makeRawRCItem();
		const pages = [ { ns: 0, pageid: 1, title: 'Article Title', description: 'A description' } ];
		mw.Api.mock( () => makeApiResponse( [ raw ], pages ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toHaveLength( 1 );
		expect( result.items[ 0 ] ).toMatchObject( {
			id: 'recentchanges-2001',
			feedorigin: 'recentchanges',
			title: 'Article Title',
			revid: 2001,
			user: 'Bob',
			timestamp: '2024-03-10T11:00:00Z',
			newlen: 3000,
			oldlen: 2900,
			parsedcomment: 'A comment',
			minor: false,
			bot: false,
			tags: []
		} );
		expect( result.pages ).toEqual( pages );
	} );

	test( 'returns empty items and pages when the API returns no items', async () => {
		mw.Api.mock( () => makeApiResponse( [] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toEqual( [] );
		expect( result.pages ).toEqual( [] );
	} );

	test( 'returns empty items and pages when the API returns null', async () => {
		mw.Api.mock( () => null );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toEqual( [] );
		expect( result.pages ).toEqual( [] );
	} );

	test( 'returns empty items and pages when the API response has no query', async () => {
		mw.Api.mock( () => ( {} ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toEqual( [] );
		expect( result.pages ).toEqual( [] );
	} );

	test( 'respects the limit when sampling items', async () => {
		const rawItems = Array.from( { length: 10 }, ( _, i ) => makeRawRCItem( { revid: i + 1, title: 'Article ' + ( i + 1 ) } )
		);
		mw.Api.mock( () => makeApiResponse( rawItems ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 3, 10, [] );

		expect( result.items ).toHaveLength( 3 );
	} );

	test( 'excludes items tagged with mw-reverted', async () => {
		const raw = makeRawRCItem( { tags: [ 'mw-reverted' ] } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toHaveLength( 0 );
	} );

	test( 'excludes items tagged with mw-rollback', async () => {
		const raw = makeRawRCItem( { tags: [ 'mw-rollback' ] } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toHaveLength( 0 );
	} );

	test( 'excludes items tagged with mw-undo', async () => {
		const raw = makeRawRCItem( { tags: [ 'mw-undo' ] } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toHaveLength( 0 );
	} );

	test( 'keeps items with unrelated tags', async () => {
		const raw = makeRawRCItem( { tags: [ 'mobile edit' ] } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toHaveLength( 1 );
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

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		await fetchRecentChangesItems( 5, 10, [] );

		expect( capturedParams ).toMatchObject( {
			action: 'query',
			list: 'recentchanges',
			prop: 'description',
			generator: 'recentchanges',
			format: 'json',
			formatversion: '2',
			rcnamespace: '0',
			rctype: 'edit',
			rcshow: '!bot',
			rclimit: '500'
		} );
	} );

	test( 'excludes the current user from results', async () => {
		mw.user.getName = () => 'TestUser';
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		await fetchRecentChangesItems( 5, 10, [] );

		expect( capturedParams.rcexcludeuser ).toBe( 'TestUser' );
		expect( capturedParams.grcexcludeuser ).toBe( 'TestUser' );
	} );

	test( 'adds unpatrolled to rcshow and grcshow when user has patrol rights', async () => {
		mw.user.getRights = () => [ 'patrol' ];
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		await fetchRecentChangesItems( 5, 10, [] );

		expect( capturedParams.rcshow ).toContain( 'unpatrolled' );
		expect( capturedParams.grcshow ).toContain( 'unpatrolled' );
	} );

	test( 'does not add unpatrolled when user lacks patrol rights', async () => {
		mw.user.getRights = () => [];
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		await fetchRecentChangesItems( 5, 10, [] );

		expect( capturedParams.rcshow ).not.toContain( 'unpatrolled' );
		expect( capturedParams.grcshow ).not.toContain( 'unpatrolled' );
	} );

	test( 'adds revertrisklanguageagnostic filter when ML model is set', async () => {
		mw.config.set( 'wgPersonalDashboardRiskyArticleEditsMlEnabled', true );
		mw.config.set( 'wgPersonalDashboardRiskyArticleEditsMlModel', 'revertrisklanguageagnostic' );
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		await fetchRecentChangesItems( 5, 10, [] );

		expect( capturedParams.rcshow ).toContain( 'revertrisklanguageagnostic' );
		expect( capturedParams.grcshow ).toContain( 'revertrisklanguageagnostic' );
	} );

	test( 'adds oresreview filter when ML model is damaging', async () => {
		mw.config.set( 'wgPersonalDashboardRiskyArticleEditsMlEnabled', true );
		mw.config.set( 'wgPersonalDashboardRiskyArticleEditsMlModel', 'damaging' );
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		await fetchRecentChangesItems( 5, 10, [] );

		expect( capturedParams.rcshow ).toContain( 'oresreview' );
		expect( capturedParams.grcshow ).toContain( 'oresreview' );
	} );

	test( 'does not add ML filters when ML is disabled', async () => {
		mw.config.set( 'wgPersonalDashboardRiskyArticleEditsMlEnabled', false );
		let capturedParams = null;
		mw.Api.mock( ( params ) => {
			capturedParams = params;
			return makeApiResponse( [] );
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		await fetchRecentChangesItems( 5, 10, [] );

		expect( capturedParams.rcshow ).not.toContain( 'oresreview' );
		expect( capturedParams.rcshow ).not.toContain( 'revertrisklanguageagnostic' );
	} );
} );

// ---------------------------------------------------------------------------
// Deduplication
// ---------------------------------------------------------------------------

describe( 'deduplication', () => {
	test( 'excludes items whose titles are in wlTitles', async () => {
		const raw = makeRawRCItem( { title: 'Watchlisted Article' } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [ 'Watchlisted Article' ] );

		expect( result.items ).toHaveLength( 0 );
	} );

	test( 'keeps items whose titles are not in wlTitles', async () => {
		const raw = makeRawRCItem( { title: 'Other Article' } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [ 'Watchlisted Article' ] );

		expect( result.items ).toHaveLength( 1 );
	} );

	test( 'deduplicates RC items with the same title', async () => {
		const rawItems = [
			makeRawRCItem( { revid: 1, title: 'Duplicate Article' } ),
			makeRawRCItem( { revid: 2, title: 'Duplicate Article' } ),
			makeRawRCItem( { revid: 3, title: 'Other Article' } )
		];
		mw.Api.mock( () => makeApiResponse( rawItems ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toHaveLength( 2 );
		expect( result.items.map( ( i ) => i.title ) ).toEqual(
			[ 'Duplicate Article', 'Other Article' ]
		);
	} );

	test( 'keeps all items when wlTitles is empty', async () => {
		const rawItems = [
			makeRawRCItem( { revid: 1, title: 'Article A' } ),
			makeRawRCItem( { revid: 2, title: 'Article B' } )
		];
		mw.Api.mock( () => makeApiResponse( rawItems ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items ).toHaveLength( 2 );
	} );
} );

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

describe( 'pagination', () => {
	test( 'does not paginate when the first response meets the limit', async () => {
		const rawItems = Array.from( { length: 5 }, ( _, i ) => makeRawRCItem( { revid: i + 1, title: 'Article ' + ( i + 1 ) } )
		);
		let callCount = 0;
		mw.Api.mock( () => {
			callCount++;
			// Return a continue token to prove pagination was not triggered
			return makeApiResponse( rawItems, [], 'token-page-2' );
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		await fetchRecentChangesItems( 5, 10, [] );

		expect( callCount ).toBe( 1 );
	} );

	test( 'paginates when results are below the limit', async () => {
		const page1Items = [
			makeRawRCItem( { revid: 1, title: 'Article 1' } ),
			makeRawRCItem( { revid: 2, title: 'Article 2' } )
		];
		const page2Items = [
			makeRawRCItem( { revid: 3, title: 'Article 3' } ),
			makeRawRCItem( { revid: 4, title: 'Article 4' } ),
			makeRawRCItem( { revid: 5, title: 'Article 5' } )
		];

		let callCount = 0;
		mw.Api.mock( ( params ) => {
			callCount++;
			if ( params.rccontinue === 'token-page-2' ) {
				return makeApiResponse( page2Items );
			}
			return makeApiResponse( page1Items, [], 'token-page-2' );
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( callCount ).toBe( 2 );
		expect( result.items ).toHaveLength( 5 );
	} );

	test( 'sends rccontinue and grccontinue offset on paginated requests', async () => {
		const page1Items = [ makeRawRCItem( { revid: 1, title: 'Article 1' } ) ];
		const capturedParams = [];

		mw.Api.mock( ( params ) => {
			capturedParams.push( { ...params } );
			if ( params.rccontinue === 'token-page-2' ) {
				return makeApiResponse( [ makeRawRCItem( { revid: 2, title: 'Article 2' } ) ] );
			}
			return makeApiResponse( page1Items, [], 'token-page-2' );
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		await fetchRecentChangesItems( 5, 10, [] );

		expect( capturedParams[ 1 ].rccontinue ).toBe( 'token-page-2' );
		expect( capturedParams[ 1 ].grccontinue ).toBe( 'token-page-2' );
	} );

	test( 'respects maxApiRequests and stops paginating', async () => {
		let callCount = 0;
		mw.Api.mock( () => {
			callCount++;
			return makeApiResponse(
				[ makeRawRCItem( { revid: callCount, title: 'Article ' + callCount } ) ],
				[],
				'token-next'
			);
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		// limit=100 ensures we'd always want more, maxApiRequests=3 stops us
		await fetchRecentChangesItems( 100, 3, [] );

		// 1 initial request + 3 paginated = 4 total
		expect( callCount ).toBe( 4 );
	} );

	test( 'merges pages from paginated responses', async () => {
		const page1Pages = [ { pageid: 1, title: 'Article 1' } ];
		const page2Pages = [ { pageid: 2, title: 'Article 2' } ];

		mw.Api.mock( ( params ) => {
			if ( params.rccontinue === 'token-page-2' ) {
				return makeApiResponse(
					[ makeRawRCItem( { revid: 2, title: 'Article 2' } ) ],
					page2Pages
				);
			}
			return makeApiResponse(
				[ makeRawRCItem( { revid: 1, title: 'Article 1' } ) ],
				page1Pages,
				'token-page-2'
			);
		} );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.pages ).toHaveLength( 2 );
		expect( result.pages.map( ( p ) => p.pageid ) ).toEqual( [ 1, 2 ] );
	} );
} );

// ---------------------------------------------------------------------------
// Normalization
// ---------------------------------------------------------------------------

describe( 'normalization', () => {
	test( 'defaults user to empty string when absent', async () => {
		const raw = makeRawRCItem( { user: undefined } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items[ 0 ].user ).toBe( '' );
	} );

	test( 'defaults tags to empty array when absent', async () => {
		const raw = makeRawRCItem( { tags: undefined } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items[ 0 ].tags ).toEqual( [] );
	} );

	test( 'casts minor to boolean', async () => {
		const raw = makeRawRCItem( { minor: '' } );
		mw.Api.mock( () => makeApiResponse( [ raw ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( typeof result.items[ 0 ].minor ).toBe( 'boolean' );
	} );

	test( 'sets feedorigin to recentchanges', async () => {
		mw.Api.mock( () => makeApiResponse( [ makeRawRCItem() ] ) );

		const { fetchRecentChangesItems } = useRecentChangesFeed();
		const result = await fetchRecentChangesItems( 5, 10, [] );

		expect( result.items[ 0 ].feedorigin ).toBe( 'recentchanges' );
	} );
} );
