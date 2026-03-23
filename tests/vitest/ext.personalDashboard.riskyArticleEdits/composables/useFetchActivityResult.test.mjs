import { test, expect, vi } from 'vitest';
import { default as useFetchActivityResult } from '/resources/ext.personalDashboard.riskyArticleEdits/composables/useFetchActivityResult.js';

const logWarn = mw.log.warn;

vi.spyOn( mw.log, 'warn' ).mockImplementation( ( ...args ) => {
	const message = args[ 0 ];

	if ( typeof message === 'string' && message.startsWith( 'unable to randomly sample array' ) ) {
		return;
	}

	logWarn( ...args );
} );

const {
	recentActivityResult,
	error,
	fetchRecentActivity
} = useFetchActivityResult();

test( 'fetchRecentActivity with response', async () => {
	const mockResponse = {
		query: {
			recentchanges: [
				{
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			pages: {},
			feed: []
		}
	};

	mw.Api.mock( mockResponse );

	await fetchRecentActivity();
	expect( recentActivityResult.value ).toEqual( mockResponse.query );
} );

test( 'fetchRecentActivity with limit', async () => {
	mw.Api.mock( {
		query: {
			recentchanges: [
				{
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			pages: {}
		}
	} );

	await fetchRecentActivity( 1 );
	expect( recentActivityResult.value.feed.length ).toEqual( 1 );
} );

test( 'fetchRecentActivity with no changes', async () => {
	const mockResponse = {
		query: {
			recentchanges: [],
			pages: {},
			feed: []
		}
	};

	mw.Api.mock( mockResponse );

	await fetchRecentActivity();
	expect( recentActivityResult.value ).toEqual( mockResponse.query );
} );

test( 'fetchRecentActivity with error', async () => {
	mw.Api.mock( () => {
		throw new Error( 'An error' );
	} );
	const logError = vi.spyOn( mw.log, 'error' ).mockImplementationOnce( () => {} );

	await fetchRecentActivity();
	expect( error.value.message ).toStrictEqual( 'Error: An error' );
	expect( logError ).toHaveBeenCalledExactlyOnceWith( 'Error: An error' );
} );

test( 'fetchRecentActivity with revertrisklanguageagnostic model enabled', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: true,
		wgPersonalDashboardRiskyArticleEditsMlModel: 'revertrisklanguageagnostic'
	} );

	const expectedParams = {
		action: 'query',
		errorformat: 'plaintext',
		errorlang: 'en',
		errorsuselocal: true,
		format: 'json',
		formatversion: '2',
		generator: 'recentchanges',
		grcexcludeuser: 'Test User',
		grclimit: '500',
		grcnamespace: '0',
		grcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|timestamp',
		grcshow: '!bot|unpatrolled|revertrisklanguageagnostic',
		grctype: 'edit',
		list: 'recentchanges',
		prop: 'description',
		rcexcludeuser: 'Test User',
		rclimit: '500',
		rcnamespace: '0',
		rcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|timestamp',
		rcshow: '!bot|unpatrolled|revertrisklanguageagnostic',
		rctype: 'edit'
	};

	const mockResponse = {
		query: {
			recentchanges: [
				{
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			pages: {}
		}
	};

	mw.Api.mock( ( params ) => {
		expect( params ).toStrictEqual( expectedParams );
		return mockResponse;
	} );

	await fetchRecentActivity( 10 );
	expect( recentActivityResult.value.feed.length ).toEqual( 2 );
} );

test( 'fetchRecentActivity with damaging model enabled', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: true,
		wgPersonalDashboardRiskyArticleEditsMlModel: 'damaging'
	} );

	const expectedParams = {
		action: 'query',
		errorformat: 'plaintext',
		errorlang: 'en',
		errorsuselocal: true,
		format: 'json',
		formatversion: '2',
		generator: 'recentchanges',
		grcexcludeuser: 'Test User',
		grclimit: '500',
		grcnamespace: '0',
		grcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|timestamp',
		grcshow: '!bot|unpatrolled|oresreview',
		grctype: 'edit',
		list: 'recentchanges',
		prop: 'description',
		rcexcludeuser: 'Test User',
		rclimit: '500',
		rcnamespace: '0',
		rcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|timestamp',
		rcshow: '!bot|unpatrolled|oresreview',
		rctype: 'edit'
	};

	const mockResponse = {
		query: {
			recentchanges: [
				{
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			pages: {}
		}
	};

	mw.Api.mock( ( params ) => {
		expect( params ).toStrictEqual( expectedParams );
		return mockResponse;
	} );

	await fetchRecentActivity( 10 );
	expect( recentActivityResult.value.feed.length ).toEqual( 2 );
} );

test( 'fetchRecentActivity with no model enabled', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null
	} );

	const expectedParams = {
		action: 'query',
		errorformat: 'plaintext',
		errorlang: 'en',
		errorsuselocal: true,
		format: 'json',
		formatversion: '2',
		generator: 'recentchanges',
		grcexcludeuser: 'Test User',
		grclimit: '500',
		grcnamespace: '0',
		grcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|timestamp',
		grcshow: '!bot|unpatrolled',
		grctype: 'edit',
		list: 'recentchanges',
		prop: 'description',
		rcexcludeuser: 'Test User',
		rclimit: '500',
		rcnamespace: '0',
		rcprop: 'title|ids|sizes|flags|user|parsedcomment|tags|timestamp',
		rcshow: '!bot|unpatrolled',
		rctype: 'edit'
	};

	const mockResponse = {
		query: {
			recentchanges: [
				{
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			pages: {}
		}
	};

	mw.Api.mock( ( params ) => {
		expect( params ).toStrictEqual( expectedParams );
		return mockResponse;
	} );

	await fetchRecentActivity( 10 );
	expect( recentActivityResult.value.feed.length ).toEqual( 2 );
} );

test( 'fetchRecentActivity with watchlist enabled', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null,
		wgPersonalDashboardRiskyArticleEditsWlEnabled: true
	} );

	mw.Api.mock( {
		query: {
			recentchanges: [
				{
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			watchlist: [
				{
					title: 'Watched article',
					type: 'type',
					ns: 0,
					newlen: 22,
					// eslint-disable-next-line camelcase
					old_revid: 123213,
					oldlen: 321,
					pageid: 494,
					revid: 313132,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			pages: {}
		}
	} );

	await fetchRecentActivity( 10 );
	expect( recentActivityResult.value.feed.length ).toEqual( 3 );
} );

test( 'fetchRecentActivity with watchlist enabled and two edits from same page', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null,
		wgPersonalDashboardRiskyArticleEditsWlEnabled: true
	} );

	mw.Api.mock( {
		query: {
			recentchanges: [
				{
					title: 'A title 123',
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			watchlist: [
				{
					title: 'Watched article',
					type: 'type',
					ns: 0,
					newlen: 22,
					// eslint-disable-next-line camelcase
					old_revid: 123213,
					oldlen: 321,
					pageid: 494,
					revid: 313132,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			pages: {}
		}
	} );

	await fetchRecentActivity( 10 );
	expect( recentActivityResult.value.feed.length ).toEqual( 3 );
} );

test( 'fetchRecentActivity with watchlist enabled and more than limit', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null,
		wgPersonalDashboardRiskyArticleEditsWlEnabled: true
	} );

	mw.Api.mock( {
		query: {
			recentchanges: [
				{
					title: 'A title 123',
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title 783',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			watchlist: [
				{
					title: 'Watched article',
					type: 'type',
					ns: 0,
					newlen: 22,
					// eslint-disable-next-line camelcase
					old_revid: 123213,
					oldlen: 321,
					pageid: 494,
					revid: 313132,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
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
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title 8712',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				},
				{
					title: 'A title 3489721',
					type: 'type',
					ns: 0,
					newlen: 325,
					// eslint-disable-next-line camelcase
					old_revid: 58859,
					oldlen: 22,
					pageid: 494,
					rcid: 8947986,
					revid: 58860,
					temp: '',
					user: 'user',
					parsedcomment: 'comment',
					tags: [],
					timestamp: ''
				}
			],
			pages: {}
		}
	} );

	await fetchRecentActivity( 6 );
	expect( recentActivityResult.value.feed.length ).toEqual( 5 );
} );
