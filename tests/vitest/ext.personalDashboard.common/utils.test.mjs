import { test, expect } from 'vitest';
import {
	formatTimestamp,
	handleApiErrors,
	stripMarkup
} from '/resources/ext.personalDashboard.common/utils.js';

test( 'formats a timestamp relative to now', () => {
	// The DateFormatter mock pins "now" to 2026-02-01 01:00 so this cannot go
	// flaky; see tests/vitest/mocks/mediawiki.DateFormatter.mjs.
	const earlier = new Date( 2026, 0, 31, 22, 0 ).toISOString();

	expect( formatTimestamp( earlier ) ).toStrictEqual( '3 hours ago' );
} );

test( 'strips markup down to its text', () => {
	expect( stripMarkup( 'fixed a <a href="/wiki/Typo">typo</a>' ) )
		.toStrictEqual( 'fixed a typo' );
	expect( stripMarkup( '' ) ).toStrictEqual( '' );
	expect( stripMarkup( undefined ) ).toStrictEqual( '' );
} );

test( 'throws the API errors as one error', () => {
	expect( () => handleApiErrors( 'badvalue', {
		errors: [ { text: 'First problem' }, { text: 'Second problem' } ]
	} ) ).toThrow( 'First problem\nSecond problem' );
} );

test( 'falls back to the error code when the response carries no errors', () => {
	expect( () => handleApiErrors( 'http', undefined ) ).toThrow( 'http' );
	expect( () => handleApiErrors( 'http', {} ) ).toThrow( 'http' );
} );
