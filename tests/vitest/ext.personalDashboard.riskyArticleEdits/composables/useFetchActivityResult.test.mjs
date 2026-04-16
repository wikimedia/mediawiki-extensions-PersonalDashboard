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
					timestamp: '',
					feedorigin: 'recentchanges'
				}
			],
			pages: [],
			feed: [
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
					timestamp: '',
					feedorigin: 'recentchanges'
				}
			]
		}
	};

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( mockResponse );

	mw.Api.mock( ( params, options ) => {
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 10 );

	expect( mockApiRequests ).toHaveBeenCalledTimes( 1 );
	expect( recentActivityResult.value ).toEqual( mockResponse.query );
} );

test( 'fetchRecentActivity with limit', async () => {
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
			pages: []
		}
	};

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( mockResponse );

	mw.Api.mock( ( params, options ) => {
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 1 );
	expect( mockApiRequests ).toHaveBeenCalledTimes( 1 );
	expect( recentActivityResult.value.feed.length ).toEqual( 1 );
} );

test( 'fetchRecentActivity with no changes', async () => {
	const mockResponse = {
		query: {
			recentchanges: [],
			pages: [],
			feed: []
		}
	};

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( mockResponse );

	mw.Api.mock( ( params, options ) => {
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 1 );

	expect( mockApiRequests ).toHaveBeenCalledTimes( 1 );
	expect( recentActivityResult.value ).toEqual( mockResponse.query );
} );

test( 'fetchRecentActivity with error', async () => {
	mw.Api.mock( () => {
		throw new Error( 'An error' );
	} );
	const logError = vi.spyOn( mw.log, 'error' ).mockImplementationOnce( () => {} );

	await fetchRecentActivity( 1 );
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
					title: 'A title 1',
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
			pages: []
		}
	};

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( mockResponse );

	mw.Api.mock( ( params, options ) => {
		expect( params ).toStrictEqual( expectedParams );
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 10 );

	expect( mockApiRequests ).toHaveBeenCalledTimes( 1 );
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
					title: 'A title 1',
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
			pages: []
		}
	};

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( mockResponse );

	mw.Api.mock( ( params, options ) => {
		expect( params ).toStrictEqual( expectedParams );
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 10 );

	expect( mockApiRequests ).toHaveBeenCalledTimes( 1 );
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

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( {
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
						title: 'A title 1',
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
				pages: []
			}
		} );

	mw.Api.mock( ( params, options ) => {
		expect( params ).toStrictEqual( expectedParams );
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 10 );

	expect( mockApiRequests ).toHaveBeenCalledTimes( 1 );
	expect( recentActivityResult.value.feed.length ).toEqual( 2 );
} );

test( 'fetchRecentActivity with watchlist enabled', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null,
		wgPersonalDashboardRiskyArticleEditsWlEnabled: true
	} );

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( {
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
						title: 'A title 1',
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
				pages: []
			}
		} )
		.mockResolvedValueOnce( {
			query: {
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
				]
			}
		} );

	mw.Api.mock( ( params, options ) => {
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 10 );

	expect( mockApiRequests ).toHaveBeenCalledTimes( 2 );
	expect( recentActivityResult.value.feed.length ).toEqual( 3 );
} );

test( 'fetchRecentActivity with watchlist enabled and two edits from same page', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null,
		wgPersonalDashboardRiskyArticleEditsWlEnabled: true
	} );

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( {
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
				pages: []
			}
		} )
		.mockResolvedValueOnce( {
			query: {
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
				]
			}
		} );

	mw.Api.mock( ( params, options ) => {
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 10 );
	expect( mockApiRequests ).toHaveBeenCalledTimes( 2 );
	expect( recentActivityResult.value.feed.length ).toEqual( 3 );
} );

test( 'fetchRecentActivity with watchlist enabled and more than limit', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null,
		wgPersonalDashboardRiskyArticleEditsWlEnabled: true
	} );

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( {
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
				pages: []
			}
		} )
		.mockResolvedValueOnce( {
			query: {
				watchlist: [
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
				]
			}
		} );

	mw.Api.mock( ( params, options ) => {
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 6 );
	expect( mockApiRequests ).toHaveBeenCalledTimes( 2 );
	expect( recentActivityResult.value.feed.length ).toEqual( 5 );
} );

test( 'fetchRecentActivity fetches more if applicable with watchlist changes', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null,
		wgPersonalDashboardRiskyArticleEditsWlEnabled: true
	} );

	const mockChange = {
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
	};
	const mockWatchlistItem = {
		title: 'A title 1230',
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
	};
	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( {
			continue: { rccontinue: 'anything' },
			query: {
				recentchanges: [
					mockChange,
					{ ...mockChange, title: 'A title 38487' },
					{ ...mockChange, title: 'A title 783' }
				],
				pages: []
			}
		} )
		.mockResolvedValueOnce( {
			continue: { wlcontinue: 'anything' },
			query: {
				watchlist: [
					mockWatchlistItem,
					{ ...mockWatchlistItem, title: 'A title 39489038204' },
					{ ...mockWatchlistItem, title: 'A title 203058568' }
				]
			}
		} )
		.mockResolvedValueOnce( {
			continue: { wlcontinue: 'anything' },
			query: {
				watchlist: [
					mockWatchlistItem,
					{ ...mockWatchlistItem, title: 'A title 2395482039589' },
					{ ...mockWatchlistItem, title: 'A title 9089' }
				]
			}
		} )
		.mockResolvedValueOnce( {
			continue: { rccontinue: 'anything2' },
			query: {
				recentchanges: [
					{ ...mockChange, title: 'A title 2982389' },
					{ ...mockChange, title: 'A title 00902' },
					{ ...mockChange, title: 'A title 238298298' }
				],
				pages: []
			}
		} );

	mw.Api.mock( ( params, options ) => {
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 10 );
	expect( mockApiRequests ).toHaveBeenCalledTimes( 4 );
	expect( recentActivityResult.value.feed.length ).toEqual( 10 );
} );

test( 'fetchRecentActivity fetches more if applicable and no watchlist changes', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null,
		wgPersonalDashboardRiskyArticleEditsWlEnabled: true
	} );

	const mockChange = {
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
	};

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( {
			continue: { rccontinue: 'anything' },
			query: {
				recentchanges: [
					mockChange,
					{ ...mockChange, title: 'A title 38487' },
					{ ...mockChange, title: 'A title 783' }
				],
				pages: []
			}
		} )
		.mockResolvedValueOnce( {
			continue: { wlcontinue: 'anything' },
			query: {
				watchlist: []
			}
		} )
		.mockResolvedValueOnce( {
			continue: { rccontinue: 'anything2' },
			query: {
				recentchanges: [
					{ ...mockChange, title: 'A title 2982389' },
					{ ...mockChange, title: 'A title 00902' },
					{ ...mockChange, title: 'A title 238298298' }
				],
				pages: []
			}
		} )
		.mockResolvedValueOnce( {
			continue: { rccontinue: 'anything3' },
			query: {
				recentchanges: [
					{ ...mockChange, title: 'A title 1134' },
					{ ...mockChange, title: 'A title 23409' },
					{ ...mockChange, title: 'A title 229029' },
					{ ...mockChange, title: 'A title 2348789324' }
				],
				pages: []
			}
		} );

	mw.Api.mock( ( params, options ) => {
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 10 );
	expect( mockApiRequests ).toHaveBeenCalledTimes( 4 );
	expect( recentActivityResult.value.feed.length ).toEqual( 10 );
} );

test( 'fetchRecentActivity fetches more if applicable and watchlist disabled', async () => {
	mw.config.set( {
		wgPersonalDashboardRiskyArticleEditsMlEnabled: false,
		wgPersonalDashboardRiskyArticleEditsMlModel: null,
		wgPersonalDashboardRiskyArticleEditsWlEnabled: false
	} );

	const mockChange = {
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
	};

	const mockApiRequests = vi.fn()
		.mockResolvedValueOnce( {
			continue: { rccontinue: 'anything' },
			query: {
				recentchanges: [
					mockChange,
					{ ...mockChange, title: 'A title 38487' },
					{ ...mockChange, title: 'A title 783' }
				],
				pages: []
			}
		} )
		.mockResolvedValueOnce( {
			continue: { rccontinue: 'anything2' },
			query: {
				recentchanges: [
					{ ...mockChange, title: 'A title 2982389' },
					{ ...mockChange, title: 'A title 00902' },
					{ ...mockChange, title: 'A title 238298298' }
				],
				pages: []
			}
		} )
		.mockResolvedValueOnce( {
			continue: { rccontinue: 'anything3' },
			query: {
				recentchanges: [
					{ ...mockChange, title: 'A title 1134' },
					{ ...mockChange, title: 'A title 23409' },
					{ ...mockChange, title: 'A title 229029' },
					{ ...mockChange, title: 'A title 2348789324' }
				],
				pages: []
			}
		} );

	mw.Api.mock( ( params, options ) => {
		expect( params.action ).toBe( 'query' );
		return mockApiRequests( params, options );
	} );

	await fetchRecentActivity( 10 );
	expect( mockApiRequests ).toHaveBeenCalledTimes( 3 );
	expect( recentActivityResult.value.feed.length ).toEqual( 10 );
} );
