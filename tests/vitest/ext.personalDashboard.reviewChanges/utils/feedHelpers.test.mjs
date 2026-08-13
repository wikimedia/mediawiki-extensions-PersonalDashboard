import { vi, afterEach, test, expect, describe } from 'vitest';

import {
	initializeEmptyFeed,
	handleApiData,
	normalizeFeedItem,
	selectEvenlyAcrossFeeds
} from '/resources/ext.personalDashboard.reviewChanges/utils/feedHelpers.js';

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

	// Server-prefetched items (the recentlyedited source) come from the DB, where
	// integer columns arrive as strings. They have to end up as numbers so the UI
	// can do arithmetic on them (byte diffs) rather than string concatenation.
	describe( 'numeric coercion', () => {
		test( 'casts string ids and lengths to numbers', () => {
			const raw = makeRawItem( {
				revid: '1001',
				pageid: '42',
				// eslint-disable-next-line camelcase
				old_revid: '1000',
				newlen: '5000',
				oldlen: '4800'
			} );
			const result = normalizeFeedItem( raw, 'recentlyedited' );

			expect( result.revid ).toBe( 1001 );
			expect( result.pageid ).toBe( 42 );
			expect( result.old_revid ).toBe( 1000 );
			expect( result.newlen ).toBe( 5000 );
			expect( result.oldlen ).toBe( 4800 );
		} );

		test( 'treats a string zero old_revid as null', () => {
			// eslint-disable-next-line camelcase
			const raw = makeRawItem( { old_revid: '0' } );

			expect( normalizeFeedItem( raw, 'recentlyedited' ).old_revid ).toBeNull();
		} );

		test( 'treats string zero lengths as 0 rather than NaN', () => {
			const raw = makeRawItem( { newlen: '0', oldlen: '0' } );
			const result = normalizeFeedItem( raw, 'recentlyedited' );

			expect( result.newlen ).toBe( 0 );
			expect( result.oldlen ).toBe( 0 );
		} );

		test( 'leaves already-numeric values untouched', () => {
			const result = normalizeFeedItem( makeRawItem(), 'watchlist' );

			expect( result.revid ).toBe( 1001 );
			expect( result.newlen ).toBe( 5000 );
		} );
	} );
} );

describe( 'selectEvenlyAcrossFeeds', () => {
	/**
	 * Build items for one source. Timestamps descend from `startDay` so each
	 * source occupies its own stretch of the calendar and ordering is obvious.
	 *
	 * @param {string} source
	 * @param {number} count
	 * @param {number} startDay
	 * @return {Object[]}
	 */
	function items( source, count, startDay ) {
		return Array.from( { length: count }, ( _, i ) => ( {
			id: `${ source }-${ i }`,
			feedorigin: source,
			title: `${ source } ${ i }`,
			timestamp: `2024-01-${ String( startDay - i ).padStart( 2, '0' ) }T00:00:00Z`
		} ) );
	}

	function countByOrigin( selected ) {
		return selected.reduce( ( counts, item ) => Object.assign( counts, {
			[ item.feedorigin ]: ( counts[ item.feedorigin ] || 0 ) + 1
		} ), {} );
	}

	test( 'splits the limit evenly when every source has enough', () => {
		const selected = selectEvenlyAcrossFeeds(
			[ items( 'a', 10, 20 ), items( 'b', 10, 20 ), items( 'c', 10, 20 ) ],
			9
		);

		expect( selected ).toHaveLength( 9 );
		expect( countByOrigin( selected ) ).toEqual( { a: 3, b: 3, c: 3 } );
	} );

	test( 'gives the leftover slots to the earlier sources', () => {
		const selected = selectEvenlyAcrossFeeds(
			[ items( 'a', 10, 20 ), items( 'b', 10, 20 ), items( 'c', 10, 20 ) ],
			10
		);

		expect( countByOrigin( selected ) ).toEqual( { a: 4, b: 3, c: 3 } );
	} );

	test( 'ignores how many items a source has when dividing the slots', () => {
		const selected = selectEvenlyAcrossFeeds(
			[ items( 'busy', 100, 28 ), items( 'quiet', 5, 10 ) ],
			6
		);

		expect( countByOrigin( selected ) ).toEqual( { busy: 3, quiet: 3 } );
	} );

	test( 'redistributes the share of an empty source', () => {
		const selected = selectEvenlyAcrossFeeds(
			[ items( 'a', 10, 20 ), [], items( 'c', 10, 20 ) ],
			9
		);

		expect( selected ).toHaveLength( 9 );
		expect( countByOrigin( selected ) ).toEqual( { a: 5, c: 4 } );
	} );

	test( 'takes up the share of a source that runs out partway', () => {
		const selected = selectEvenlyAcrossFeeds(
			[ items( 'a', 10, 20 ), items( 'short', 1, 20 ) ],
			6
		);

		expect( countByOrigin( selected ) ).toEqual( { a: 5, short: 1 } );
	} );

	test( 'returns everything when the sources cannot fill the limit', () => {
		const selected = selectEvenlyAcrossFeeds(
			[ items( 'a', 2, 20 ), items( 'b', 1, 10 ) ],
			50
		);

		expect( selected ).toHaveLength( 3 );
	} );

	test( 'takes the newest items from within each source', () => {
		const selected = selectEvenlyAcrossFeeds( [ items( 'a', 10, 20 ) ], 2 );

		expect( selected.map( ( i ) => i.title ) ).toEqual( [ 'a 0', 'a 1' ] );
	} );

	test( 'returns the selection newest first regardless of source order', () => {
		const selected = selectEvenlyAcrossFeeds(
			[ items( 'older', 3, 10 ), items( 'newer', 3, 28 ) ],
			6
		);

		const timestamps = selected.map( ( i ) => i.timestamp );
		expect( timestamps ).toEqual( [ ...timestamps ].sort().reverse() );
	} );

	test( 'keeps only the first copy of a title seen in more than one source', () => {
		const shared = { title: 'Shared', timestamp: '2024-01-15T00:00:00Z' };
		const selected = selectEvenlyAcrossFeeds( [
			[ Object.assign( { id: 'a-0', feedorigin: 'a' }, shared ) ],
			[ Object.assign( { id: 'b-0', feedorigin: 'b' }, shared ) ]
		], 9 );

		expect( selected ).toHaveLength( 1 );
		expect( selected[ 0 ].feedorigin ).toBe( 'a' );
	} );

	test( 'lets a source draw a replacement when its pick was a duplicate', () => {
		const shared = { title: 'Shared', timestamp: '2024-01-15T00:00:00Z' };
		const selected = selectEvenlyAcrossFeeds( [
			[ Object.assign( { id: 'a-0', feedorigin: 'a' }, shared ) ],
			[
				Object.assign( { id: 'b-0', feedorigin: 'b' }, shared ),
				{ id: 'b-1', feedorigin: 'b', title: 'B Only', timestamp: '2024-01-14T00:00:00Z' }
			]
		], 2 );

		expect( selected.map( ( i ) => i.title ) ).toEqual( [ 'Shared', 'B Only' ] );
	} );

	test( 'returns nothing for a zero limit', () => {
		expect( selectEvenlyAcrossFeeds( [ items( 'a', 10, 20 ) ], 0 ) ).toEqual( [] );
	} );

	test( 'returns nothing when there are no sources', () => {
		expect( selectEvenlyAcrossFeeds( [], 10 ) ).toEqual( [] );
	} );

	test( 'does not mutate the arrays it is given', () => {
		const source = items( 'a', 3, 10 );
		const before = source.map( ( i ) => i.id );

		selectEvenlyAcrossFeeds( [ source ], 1 );

		expect( source.map( ( i ) => i.id ) ).toEqual( before );
	} );
} );
