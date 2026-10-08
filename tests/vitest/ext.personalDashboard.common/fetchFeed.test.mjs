import { beforeEach, test, expect, vi } from 'vitest';
import { fetchFeed } from '/resources/ext.personalDashboard.common/fetchFeed.js';

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

beforeEach( () => {
	get = vi.fn();
	// A real function, not an arrow: fetchFeed calls `new mw.Rest()`, and
	// arrows can't be constructors.
	// eslint-disable-next-line prefer-arrow-callback
	mw.Rest = vi.fn().mockImplementation( function () {
		return { get };
	} );
} );

test( 'asks for the sources in the order given', async () => {
	get.mockResolvedValue( { items: [] } );

	await fetchFeed( [ 'watchlist', 'recentchanges' ], 10, null );

	expect( get ).toHaveBeenCalledWith( '/personaldashboard/v0/feed', {
		sources: 'watchlist|recentchanges',
		limit: 10
	} );
} );

test( 'hands the token back', async () => {
	get.mockResolvedValue( { items: [] } );

	await fetchFeed( [ 'recentchanges' ], 10, 'token-1' );

	expect( get ).toHaveBeenCalledWith( '/personaldashboard/v0/feed', {
		sources: 'recentchanges',
		limit: 10,
		continue: 'token-1'
	} );
} );

test( 'returns the items and the token for the next page', async () => {
	const items = [ { id: 'watchlist-1' }, { id: 'recentchanges-2' } ];
	get.mockResolvedValue( { items, continue: 'token-1' } );

	expect( await fetchFeed( [ 'recentchanges' ], 10, null ) )
		.toStrictEqual( { items, continuation: 'token-1' } );
} );

test( 'returns no token once every source is spent', async () => {
	get.mockResolvedValue( {} );

	expect( await fetchFeed( [ 'recentchanges' ], 10, null ) )
		.toStrictEqual( { items: [], continuation: null } );
} );

test( 'rejects with the wiki message for the failure', async () => {
	mw.config.set( 'wgUserLanguage', 'de' );
	get.mockReturnValue( fails( 'http', errorBody( {
		messageTranslations: { en: 'Not logged in', de: 'Nicht angemeldet' }
	} ) ) );

	await expect( fetchFeed( [ 'recentchanges' ], 10, null ) )
		.rejects.toThrow( 'Nicht angemeldet' );
} );

test( 'falls back to English when the viewer language is untranslated', async () => {
	mw.config.set( 'wgUserLanguage', 'fr' );
	get.mockReturnValue( fails( 'http', errorBody( {
		messageTranslations: { en: 'Not logged in' }
	} ) ) );

	await expect( fetchFeed( [ 'recentchanges' ], 10, null ) )
		.rejects.toThrow( 'Not logged in' );
} );

test( 'falls back to the error code when the response carries no message', async () => {
	get.mockReturnValue( fails( 'http', { textStatus: 'error' } ) );

	await expect( fetchFeed( [ 'recentchanges' ], 10, null ) )
		.rejects.toThrow( 'http' );
} );
