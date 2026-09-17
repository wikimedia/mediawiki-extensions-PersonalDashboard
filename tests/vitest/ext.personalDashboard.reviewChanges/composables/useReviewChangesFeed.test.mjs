import { afterEach, beforeEach, test, expect, vi } from 'vitest';
import { useReviewChangesFeed } from '/resources/ext.personalDashboard.reviewChanges/composables/useReviewChangesFeed.js';

const { feedState, load, loadMore } = useReviewChangesFeed();

let get;

/**
 * A failed mw.Rest request. mw.Rest rejects with two arguments, which a native
 * promise cannot carry, so this mimics the jQuery promise it really returns:
 * catch() hands the handler both, and what it throws rejects the chain.
 *
 * @param {string} code
 * @param {Object} [details]
 * @return {Object} A jQuery-shaped promise
 */
function fails( code, details ) {
	return {
		catch: ( onFail ) => {
			try {
				return Promise.resolve( onFail( code, details ) );
			} catch ( err ) {
				return Promise.reject( err );
			}
		}
	};
}

/**
 * The second rejection argument, carrying an error body in the shape the REST
 * layer returns.
 *
 * @param {Object} responseJSON
 * @return {Object}
 */
function errorBody( responseJSON ) {
	return { xhr: { responseJSON }, textStatus: 'error', exception: '' };
}

beforeEach( async () => {
	get = vi.fn();
	// A real function, not an arrow: the composable calls `new mw.Rest()`, and
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

test( 'asks the feed endpoint for every source', async () => {
	get.mockResolvedValue( { items: [] } );

	await load( 10 );

	expect( get ).toHaveBeenCalledWith( '/personaldashboard/v0/feed', {
		sources: 'watchlist|recentchanges|recentlyedited',
		limit: 10
	} );
} );

test( 'commits the items the endpoint returns', async () => {
	const items = [ { id: 'watchlist-1' }, { id: 'recentchanges-2' } ];
	get.mockResolvedValue( { items } );

	await load( 10 );

	expect( feedState.items ).toStrictEqual( items );
	expect( feedState.error ).toBeNull();
} );

test( 'offers another page only while the endpoint returns a token', async () => {
	get.mockResolvedValue( { items: [], continue: 'token-1' } );
	await load( 10 );
	expect( feedState.hasMore ).toBe( true );

	get.mockResolvedValue( { items: [] } );
	await load( 10 );
	expect( feedState.hasMore ).toBe( false );
} );

test( 'hands the token back and adds the next page below the first', async () => {
	get.mockResolvedValue( { items: [ { id: 'a' } ], continue: 'token-1' } );
	await load( 10 );

	get.mockResolvedValue( { items: [ { id: 'b' } ] } );
	await loadMore();

	expect( get ).toHaveBeenLastCalledWith( '/personaldashboard/v0/feed', {
		sources: 'watchlist|recentchanges|recentlyedited',
		limit: 10,
		continue: 'token-1'
	} );
	expect( feedState.items ).toStrictEqual( [ { id: 'a' }, { id: 'b' } ] );
	expect( feedState.hasMore ).toBe( false );
} );

test( 'surfaces the wiki message for the failure', async () => {
	vi.spyOn( mw.log, 'error' ).mockImplementation( () => {} );
	mw.config.set( 'wgUserLanguage', 'de' );
	get.mockReturnValue( fails( 'http', errorBody( {
		messageTranslations: { en: 'Not logged in', de: 'Nicht angemeldet' }
	} ) ) );

	await load( 10 );

	expect( feedState.error.message ).toBe( 'Nicht angemeldet' );
	expect( feedState.items ).toStrictEqual( [] );
} );

test( 'falls back to English when the viewer language is untranslated', async () => {
	vi.spyOn( mw.log, 'error' ).mockImplementation( () => {} );
	mw.config.set( 'wgUserLanguage', 'fr' );
	get.mockReturnValue( fails( 'http', errorBody( {
		messageTranslations: { en: 'Not logged in' }
	} ) ) );

	await load( 10 );

	expect( feedState.error.message ).toBe( 'Not logged in' );
} );

test( 'falls back to the error code when the response carries no message', async () => {
	vi.spyOn( mw.log, 'error' ).mockImplementation( () => {} );
	get.mockReturnValue( fails( 'http', { textStatus: 'error' } ) );

	await load( 10 );

	expect( feedState.error.message ).toBe( 'http' );
} );
