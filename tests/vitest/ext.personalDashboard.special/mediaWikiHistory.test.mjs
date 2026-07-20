import { test, expect, afterEach, vi } from 'vitest';
import {
	subpage,
	titleFromLocation,
	hrefFor
} from '/resources/ext.personalDashboard.special/mediaWikiHistory.js';

test( 'subpage returns everything after the special-page segment', () => {
	expect( subpage( 'Special:PersonalDashboard' ) ).toBe( '' );
	expect( subpage( 'Special:PersonalDashboard/ext.personalDashboard.impact' ) )
		.toBe( 'ext.personalDashboard.impact' );
	// A deeper tail is preserved verbatim; the history never interprets it.
	expect( subpage( 'Special:PersonalDashboard/ext.foo/neutral-point-of-view' ) )
		.toBe( 'ext.foo/neutral-point-of-view' );
} );

test( 'titleFromLocation reads the same title from either URL form', () => {
	const short = titleFromLocation(
		'/wiki/Special:PersonalDashboard/ext.personalDashboard.impact', '', '/wiki/$1'
	);
	const long = titleFromLocation(
		'/w/index.php', '?title=Special:PersonalDashboard/ext.personalDashboard.impact', '/wiki/$1'
	);
	// The whole design turns on these being equal: two URL forms, one route.
	expect( short ).toBe( 'Special:PersonalDashboard/ext.personalDashboard.impact' );
	expect( long ).toBe( short );
} );

test( 'titleFromLocation decodes a percent-encoded short path', () => {
	expect( titleFromLocation( '/wiki/Special:PersonalDashboard/a%20b', '', '/wiki/$1' ) )
		.toBe( 'Special:PersonalDashboard/a b' );
} );

afterEach( () => {
	vi.unstubAllGlobals();
} );

test( 'hrefFor preserves the long index.php form and appends the hash', () => {
	vi.stubGlobal( 'mw', { config: { get: ( k ) => ( k === 'wgScript' ? '/w/index.php' : null ) } } );
	expect( hrefFor( 'Special:PersonalDashboard/ext.foo', '#npov', true ) )
		.toBe( '/w/index.php?title=Special%3APersonalDashboard%2Fext.foo#npov' );
} );

test( 'hrefFor defers to mw.util.getUrl for the short form', () => {
	vi.stubGlobal( 'mw', { util: { getUrl: ( page ) => '/wiki/' + page } } );
	expect( hrefFor( 'Special:PersonalDashboard/ext.foo', '#npov', false ) )
		.toBe( '/wiki/Special:PersonalDashboard/ext.foo#npov' );
} );

test( 'hrefFor carries ambient query params forward in both forms', () => {
	vi.stubGlobal( 'mw', {
		config: { get: ( k ) => ( k === 'wgScript' ? '/w/index.php' : null ) },
		util: { getUrl: ( page, params ) => '/wiki/' + page + '?' + new URLSearchParams( params ) }
	} );
	const query = { uselang: 'fr' };
	expect( hrefFor( 'Special:PersonalDashboard/ext.foo', '#npov', true, query ) )
		.toBe( '/w/index.php?uselang=fr&title=Special%3APersonalDashboard%2Fext.foo#npov' );
	expect( hrefFor( 'Special:PersonalDashboard/ext.foo', '', false, query ) )
		.toBe( '/wiki/Special:PersonalDashboard/ext.foo?uselang=fr' );
} );
