import { vi, beforeEach, test, expect, describe } from 'vitest';

// Mock ext.personalDashboard.common so getRandomItems is predictable in tests
const { mockGetRandomItems } = vi.hoisted( () => ( {
	// Return items as-is up to limit so tests are deterministic
	mockGetRandomItems: vi.fn( ( items, limit ) => items.slice( 0, limit ) )
} ) );

vi.mock( 'ext.personalDashboard.common', () => ( {
	utils: {
		getRandomItems: mockGetRandomItems
	}
} ) );

import { useRecentlyEditedFeed } from '/resources/ext.personalDashboard.reviewChanges/composables/useRecentlyEditedFeed.js';

/**
 * Build a raw item in the shape ReviewChanges::getRecentlyEditedItems() emits.
 * Numeric fields are strings because they come straight out of the DB via
 * mw.config, which is what the Number() casts in normalizeFeedItem exist for.
 *
 * @param {Object} [overrides]
 * @return {Object}
 */
function makeRawItem( overrides ) {
	return Object.assign( {
		revid: '3001',
		pageid: '77',
		// eslint-disable-next-line camelcase
		old_revid: '3000',
		title: 'Article Title',
		user: 'Carol',
		timestamp: '2024-03-10T12:00:00Z',
		newlen: '4000',
		oldlen: '3900',
		parsedcomment: 'A <b>parsed</b> comment',
		minor: false,
		bot: false,
		new: false,
		tags: []
	}, overrides );
}

beforeEach( () => {
	mockGetRandomItems.mockClear();
	// Remove rather than null out: the composable relies on mw.config.get()'s
	// fallback, which only applies when the key is genuinely absent.
	mw.config.delete( 'wgPersonalDashboardRecentlyEditedItems' );
} );

describe( 'fetchRecentlyEditedItems', () => {
	test( 'returns normalized feed items from the config var', () => {
		mw.config.set( 'wgPersonalDashboardRecentlyEditedItems', [ makeRawItem() ] );

		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();
		const items = fetchRecentlyEditedItems( 5 );

		expect( items ).toHaveLength( 1 );
		expect( items[ 0 ] ).toEqual( {
			id: 'recentlyedited-3001',
			feedorigin: 'recentlyedited',
			title: 'Article Title',
			revid: 3001,
			pageid: 77,
			// eslint-disable-next-line camelcase
			old_revid: 3000,
			user: 'Carol',
			timestamp: '2024-03-10T12:00:00Z',
			newlen: 4000,
			oldlen: 3900,
			parsedcomment: 'A <b>parsed</b> comment',
			minor: false,
			bot: false,
			new: false,
			tags: []
		} );
	} );

	test( 'returns an empty array when the config var is unset', () => {
		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();

		expect( fetchRecentlyEditedItems( 5 ) ).toEqual( [] );
	} );

	test( 'returns an empty array when the config var holds no items', () => {
		mw.config.set( 'wgPersonalDashboardRecentlyEditedItems', [] );

		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();

		expect( fetchRecentlyEditedItems( 5 ) ).toEqual( [] );
	} );

	test( 'stamps every item with the recentlyedited feedorigin', () => {
		mw.config.set( 'wgPersonalDashboardRecentlyEditedItems', [
			makeRawItem( { revid: '1' } ),
			makeRawItem( { revid: '2' } )
		] );

		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();
		const items = fetchRecentlyEditedItems( 5 );

		expect( items.map( ( i ) => i.feedorigin ) ).toEqual( [ 'recentlyedited', 'recentlyedited' ] );
		expect( items.map( ( i ) => i.id ) ).toEqual( [ 'recentlyedited-1', 'recentlyedited-2' ] );
	} );

	test( 'samples the prefetched items down to the requested limit', () => {
		const raw = Array.from( { length: 10 }, ( _, i ) => makeRawItem( {
			revid: String( i + 1 ),
			title: 'Article ' + ( i + 1 )
		} ) );
		mw.config.set( 'wgPersonalDashboardRecentlyEditedItems', raw );

		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();
		const items = fetchRecentlyEditedItems( 3 );

		expect( mockGetRandomItems ).toHaveBeenCalledWith( raw, 3 );
		expect( items ).toHaveLength( 3 );
	} );

	test( 'returns everything when there are fewer items than the limit', () => {
		mw.config.set( 'wgPersonalDashboardRecentlyEditedItems', [ makeRawItem() ] );

		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();

		expect( fetchRecentlyEditedItems( 10 ) ).toHaveLength( 1 );
	} );
} );

// ---------------------------------------------------------------------------
// Normalization of server-prefetched values
//
// Unlike the API-backed feeds, these rows come from the DB via PHP, so numeric
// columns arrive as strings and must not leak into the FeedItem untyped.
// ---------------------------------------------------------------------------

describe( 'normalization', () => {
	test( 'casts string numeric columns to numbers', () => {
		mw.config.set( 'wgPersonalDashboardRecentlyEditedItems', [ makeRawItem() ] );

		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();
		const item = fetchRecentlyEditedItems( 5 )[ 0 ];

		expect( item.revid ).toBe( 3001 );
		expect( item.pageid ).toBe( 77 );
		expect( item.old_revid ).toBe( 3000 );
		expect( item.newlen ).toBe( 4000 );
		expect( item.oldlen ).toBe( 3900 );
	} );

	test( 'keeps boolean flags boolean', () => {
		mw.config.set( 'wgPersonalDashboardRecentlyEditedItems', [
			makeRawItem( { minor: true, bot: false } )
		] );

		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();
		const item = fetchRecentlyEditedItems( 5 )[ 0 ];

		expect( item.minor ).toBe( true );
		expect( item.bot ).toBe( false );
	} );

	test( 'carries change tags through', () => {
		mw.config.set( 'wgPersonalDashboardRecentlyEditedItems', [
			makeRawItem( { tags: [ 'mobile edit', 'visualeditor' ] } )
		] );

		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();

		expect( fetchRecentlyEditedItems( 5 )[ 0 ].tags ).toEqual( [ 'mobile edit', 'visualeditor' ] );
	} );

	test( 'defaults old_revid to null for a page creation', () => {
		// eslint-disable-next-line camelcase
		mw.config.set( 'wgPersonalDashboardRecentlyEditedItems', [ makeRawItem( { old_revid: '0' } ) ] );

		const { fetchRecentlyEditedItems } = useRecentlyEditedFeed();

		expect( fetchRecentlyEditedItems( 5 )[ 0 ].old_revid ).toBeNull();
	} );
} );
