import { vi, afterEach, test, expect, describe } from 'vitest';

import {
	initializeEmptyFeed,
	handleApiData,
	normalizeFeedItem
} from '/resources/ext.personalDashboard.riskyArticleEdits/utils/feedHelpers.js';

function makeRawItem( overrides ) {
	return Object.assign( {
		revid: 1001,
		pageid: 42,
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

const parseApiStatus = vi.fn( () => [] );

afterEach( () => {
	parseApiStatus.mockReset();
	parseApiStatus.mockReturnValue( [] );
} );

describe( 'initializeEmptyFeed', () => {
	test( 'returns empty recentchanges and pages for recentchanges feed', () => {
		const result = initializeEmptyFeed( 'recentchanges' );

		expect( result ).toEqual( { query: { recentchanges: [], pages: [] } } );
	} );

	test( 'returns empty watchlist for watchlist feed', () => {
		const result = initializeEmptyFeed( 'watchlist' );

		expect( result ).toEqual( { query: { watchlist: [] } } );
	} );
} );

describe( 'handleApiData', () => {
	test( 'returns empty feed when data is null', () => {
		const result = handleApiData( null, 5, 'watchlist', parseApiStatus );

		expect( result ).toEqual( { query: { watchlist: [] } } );
	} );

	test( 'returns empty feed when data is undefined', () => {
		const result = handleApiData( undefined, 5, 'watchlist', parseApiStatus );

		expect( result ).toEqual( { query: { watchlist: [] } } );
	} );

	test( 'returns empty feed when data has no query', () => {
		const result = handleApiData( {}, 5, 'watchlist', parseApiStatus );

		expect( result ).toEqual( { query: { watchlist: [] } } );
	} );

	test( 'returns empty feed when query feed list is not an array', () => {
		const data = { query: { watchlist: null } };
		const result = handleApiData( data, 5, 'watchlist', parseApiStatus );

		expect( result ).toEqual( { query: { watchlist: [] } } );
	} );

	test( 'stamps each item with feedorigin', () => {
		const data = { query: { watchlist: [ makeRawItem() ] } };
		const result = handleApiData( data, 5, 'watchlist', parseApiStatus );

		expect( result.query.watchlist[ 0 ].feedorigin ).toBe( 'watchlist' );
	} );

	test( 'stamps recentchanges items with correct feedorigin', () => {
		const data = { query: { recentchanges: [ makeRawItem() ], pages: [] } };
		const result = handleApiData( data, 5, 'recentchanges', parseApiStatus );

		expect( result.query.recentchanges[ 0 ].feedorigin ).toBe( 'recentchanges' );
	} );

	test( 'excludes items tagged with mw-reverted', () => {
		const data = { query: { watchlist: [ makeRawItem( { tags: [ 'mw-reverted' ] } ) ] } };
		const result = handleApiData( data, 5, 'watchlist', parseApiStatus );

		expect( result.query.watchlist ).toHaveLength( 0 );
	} );

	test( 'excludes items tagged with mw-rollback', () => {
		const data = { query: { watchlist: [ makeRawItem( { tags: [ 'mw-rollback' ] } ) ] } };
		const result = handleApiData( data, 5, 'watchlist', parseApiStatus );

		expect( result.query.watchlist ).toHaveLength( 0 );
	} );

	test( 'excludes items tagged with mw-undo', () => {
		const data = { query: { watchlist: [ makeRawItem( { tags: [ 'mw-undo' ] } ) ] } };
		const result = handleApiData( data, 5, 'watchlist', parseApiStatus );

		expect( result.query.watchlist ).toHaveLength( 0 );
	} );

	test( 'keeps items with unrelated tags', () => {
		const data = { query: { watchlist: [ makeRawItem( { tags: [ 'mobile edit' ] } ) ] } };
		const result = handleApiData( data, 5, 'watchlist', parseApiStatus );

		expect( result.query.watchlist ).toHaveLength( 1 );
	} );

	test( 'keeps items with no tags', () => {
		const data = { query: { watchlist: [ makeRawItem( { tags: [] } ) ] } };
		const result = handleApiData( data, 5, 'watchlist', parseApiStatus );

		expect( result.query.watchlist ).toHaveLength( 1 );
	} );

	test( 'keeps items with missing tags field', () => {
		const data = { query: { watchlist: [ makeRawItem( { tags: undefined } ) ] } };
		const result = handleApiData( data, 5, 'watchlist', parseApiStatus );

		expect( result.query.watchlist ).toHaveLength( 1 );
	} );

	test( 'preserves other query properties alongside filtered feed', () => {
		const pages = [ { pageid: 1, title: 'Article Title' } ];
		const data = { query: { recentchanges: [ makeRawItem() ], pages } };
		const result = handleApiData( data, 5, 'recentchanges', parseApiStatus );

		expect( result.query.pages ).toEqual( pages );
	} );
} );

describe( 'normalizeFeedItem', () => {
	test( 'maps all expected fields for watchlist source', () => {
		const raw = makeRawItem();
		const result = normalizeFeedItem( raw, 'watchlist' );

		expect( result ).toEqual( {
			id: 'watchlist-1001',
			feedorigin: 'watchlist',
			title: 'Article Title',
			revid: 1001,
			pageid: 42,
			// eslint-disable-next-line camelcase
			old_revid: 1000,
			user: 'Alice',
			timestamp: '2024-03-10T10:00:00Z',
			newlen: 5000,
			oldlen: 4800,
			parsedcomment: 'A comment',
			minor: false,
			bot: false,
			new: false,
			tags: []
		} );
	} );

	test( 'maps all expected fields for recentchanges source', () => {
		const raw = makeRawItem( { revid: 2001 } );
		const result = normalizeFeedItem( raw, 'recentchanges' );

		expect( result.id ).toBe( 'recentchanges-2001' );
		expect( result.feedorigin ).toBe( 'recentchanges' );
	} );

	test( 'builds id from source and revid', () => {
		const raw = makeRawItem( { revid: 42 } );

		expect( normalizeFeedItem( raw, 'watchlist' ).id ).toBe( 'watchlist-42' );
		expect( normalizeFeedItem( raw, 'recentchanges' ).id ).toBe( 'recentchanges-42' );
	} );

	test( 'defaults old_revid to null when absent', () => {
		// eslint-disable-next-line camelcase
		const raw = makeRawItem( { old_revid: undefined } );
		const result = normalizeFeedItem( raw, 'watchlist' );

		expect( result.old_revid ).toBeNull();
	} );

	test( 'defaults user to empty string when absent', () => {
		const raw = makeRawItem( { user: undefined } );
		const result = normalizeFeedItem( raw, 'watchlist' );

		expect( result.user ).toBe( '' );
	} );

	test( 'defaults newlen to 0 when absent', () => {
		const raw = makeRawItem( { newlen: undefined } );
		const result = normalizeFeedItem( raw, 'watchlist' );

		expect( result.newlen ).toBe( 0 );
	} );

	test( 'defaults oldlen to 0 when absent', () => {
		const raw = makeRawItem( { oldlen: undefined } );
		const result = normalizeFeedItem( raw, 'watchlist' );

		expect( result.oldlen ).toBe( 0 );
	} );

	test( 'defaults parsedcomment to empty string when absent', () => {
		const raw = makeRawItem( { parsedcomment: undefined } );
		const result = normalizeFeedItem( raw, 'watchlist' );

		expect( result.parsedcomment ).toBe( '' );
	} );

	test( 'defaults tags to empty array when absent', () => {
		const raw = makeRawItem( { tags: undefined } );
		const result = normalizeFeedItem( raw, 'watchlist' );

		expect( result.tags ).toEqual( [] );
	} );

	test( 'casts minor to boolean', () => {
		expect( normalizeFeedItem( makeRawItem( { minor: '' } ), 'watchlist' ).minor ).toBe( false );
		expect( normalizeFeedItem( makeRawItem( { minor: true } ), 'watchlist' ).minor ).toBe( true );
	} );

	test( 'casts bot to boolean', () => {
		expect( normalizeFeedItem( makeRawItem( { bot: '' } ), 'watchlist' ).bot ).toBe( false );
		expect( normalizeFeedItem( makeRawItem( { bot: true } ), 'watchlist' ).bot ).toBe( true );
	} );

	test( 'casts new to boolean', () => {
		expect( normalizeFeedItem( makeRawItem( { new: '' } ), 'watchlist' ).new ).toBe( false );
		expect( normalizeFeedItem( makeRawItem( { new: true } ), 'watchlist' ).new ).toBe( true );
	} );
} );
