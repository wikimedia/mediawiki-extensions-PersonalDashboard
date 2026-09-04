import { afterEach, test, expect, vi } from 'vitest';
import { useActiveDiscussionsFeed } from '/resources/ext.personalDashboard.activeDiscussions/composables/useActiveDiscussionsFeed.js';

const { feedState, load } = useActiveDiscussionsFeed();

mw.config.set( 'wgPersonalDashboardActiveDiscussionsPages', [ 'Wikipedia:Village_pump' ] );

afterEach( () => {
	vi.resetAllMocks();
} );

test( 'fetchActiveDiscussions with response', async () => {
	mw.Api.mock( {
		discussiontoolspageinfo: {
			threaditemshtml: [
				{
					headingLevel: 2,
					name: 'h-Username1234-20260219013200',
					type: 'heading',
					level: 0,
					id: 'h-This_should_be_the_first_comment-20260219013200',
					html: 'This should be the first comment',
					commentCount: 4,
					authorCount: 3,
					latestReplyTimestamp: '2026-02-19T19:10:00Z',
					latestReply: {
						timestamp: '2026-02-19T19:10:00Z',
						author: 'Username1234',
						type: 'comment',
						level: 4,
						id: 'c-Username1234-20260219191000-BrandNewUser-20260219013300'
					},
					oldestReply: {
						timestamp: '2026-02-19T01:32:00Z',
						author: 'Username1234',
						type: 'comment',
						level: 1,
						id: 'c-Username1234-20260219013200-This_should_be_the_first_comment'
					}
				}
			]
		}
	} );

	await load();
	expect( feedState.items ).toStrictEqual( [
		{
			id: 'Wikipedia:Village_pump#h-This_should_be_the_first_comment-20260219013200',
			discussionPage: 'Wikipedia:Village_pump',
			discussionTitle: 'This should be the first comment',
			commentCount: 4,
			authorCount: 3,
			latestReply: '2026-02-19T19:10:00Z',
			latestReplyId: 'c-Username1234-20260219191000-BrandNewUser-20260219013300'
		}
	] );
} );

test( 'fetchActiveDiscussions with no items', async () => {
	mw.Api.mock( {
		discussiontoolspageinfo: {
			threaditemshtml: []
		}
	} );

	await load();
	expect( feedState.items ).toStrictEqual( [] );
} );

test( 'fetchActiveDiscussions with key missing', async () => {
	mw.Api.mock( { discussiontoolspageinfo: {} } );
	const logError = vi.spyOn( mw.log, 'error' ).mockImplementationOnce( () => {} );
	const expectedMessage = '⧼personal-dashboard-active-discussions-fetch-error⧽';

	await load();
	expect( feedState.error.message ).toStrictEqual( expectedMessage );
	expect( logError ).toHaveBeenCalledExactlyOnceWith( expectedMessage );
} );

test( 'fetchActiveDiscussions with all keys missing', async () => {
	mw.Api.mock( {} );
	const logError = vi.spyOn( mw.log, 'error' ).mockImplementationOnce( () => {} );
	const expectedMessage = '⧼personal-dashboard-active-discussions-fetch-error⧽';

	await load();
	expect( feedState.error.message ).toStrictEqual( expectedMessage );
	expect( logError ).toHaveBeenCalledExactlyOnceWith( expectedMessage );
} );

test( 'fetchActiveDiscussions with error', async () => {
	// A real mw.Api rejection surfaces the failure code as a bare value, not an
	// Error; handleApiErrors is what normalizes it into one.
	mw.Api.mock( () => Promise.reject( 'http' ) );
	const logError = vi.spyOn( mw.log, 'error' ).mockImplementationOnce( () => {} );

	await load();
	expect( feedState.error ).toBeInstanceOf( Error );
	expect( feedState.error.message ).toStrictEqual( 'http' );
	expect( logError ).toHaveBeenCalledExactlyOnceWith( 'http' );
} );

test( 'one bad page does not empty an otherwise-successful feed', async () => {
	mw.config.set( 'wgPersonalDashboardActiveDiscussionsPages', [
		'Wikipedia:Village_pump', 'Wikipedia:Bad_page'
	] );
	let callCount = 0;
	mw.Api.mock( ( params ) => {
		callCount++;
		if ( params.page === 'Wikipedia:Bad_page' ) {
			// Missing threaditemshtml: the bad-page shape this fix skips.
			return { discussiontoolspageinfo: {} };
		}
		return {
			discussiontoolspageinfo: {
				threaditemshtml: [ {
					id: 'h-comment-1',
					html: 'Good page comment',
					commentCount: 2,
					authorCount: 2,
					latestReplyTimestamp: '2026-02-19T19:10:00Z',
					latestReply: { id: 'c-1' }
				} ]
			}
		};
	} );

	await load();

	expect( callCount ).toStrictEqual( 2 );
	expect( feedState.error ).toBeNull();
	expect( feedState.items ).toHaveLength( 1 );
	expect( feedState.items[ 0 ].discussionPage ).toStrictEqual( 'Wikipedia:Village_pump' );

	mw.config.set( 'wgPersonalDashboardActiveDiscussionsPages', [ 'Wikipedia:Village_pump' ] );
} );

test( 'one malformed item does not empty an otherwise-successful page', async () => {
	mw.Api.mock( {
		discussiontoolspageinfo: {
			threaditemshtml: [
				{
					id: 'h-malformed-comment',
					html: 'Malformed comment',
					commentCount: 2,
					authorCount: 2
					// Missing latestReplyTimestamp and latestReply: the malformed
					// shape this fix skips.
				},
				{
					id: 'h-good-comment',
					html: 'Good comment',
					commentCount: 2,
					authorCount: 2,
					latestReplyTimestamp: '2026-02-19T19:10:00Z',
					latestReply: { id: 'c-1' }
				}
			]
		}
	} );

	await load();

	expect( feedState.error ).toBeNull();
	expect( feedState.items ).toHaveLength( 1 );
	expect( feedState.items[ 0 ].discussionTitle ).toStrictEqual( 'Good comment' );
} );

test( 'fetchActiveDiscussions with low author count', async () => {
	mw.Api.mock( {
		discussiontoolspageinfo: {
			threaditemshtml: [
				{
					headingLevel: 2,
					name: 'h-Username1234-20260219013200',
					type: 'heading',
					level: 0,
					id: 'h-This_should_be_the_first_comment-20260219013200',
					html: 'This should be the first comment',
					commentCount: 4,
					authorCount: 1,
					latestReplyTimestamp: '2026-02-19T19:10:00Z',
					latestReply: {
						timestamp: '2026-02-19T19:10:00Z',
						author: 'Username1234',
						type: 'comment',
						level: 4,
						id: 'c-Username1234-20260219191000-BrandNewUser-20260219013300'
					},
					oldestReply: {
						timestamp: '2026-02-19T01:32:00Z',
						author: 'Username1234',
						type: 'comment',
						level: 1,
						id: 'c-Username1234-20260219013200-This_should_be_the_first_comment'
					}
				}
			]
		}
	} );

	await load();
	expect( feedState.items ).toStrictEqual( [] );
} );
