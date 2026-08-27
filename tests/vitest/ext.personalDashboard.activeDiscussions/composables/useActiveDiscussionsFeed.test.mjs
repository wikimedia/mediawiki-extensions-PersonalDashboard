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

	await load();
	expect( feedState.error.message ).toStrictEqual( 'No valid active discussions found' );
	expect( logError ).toHaveBeenCalledExactlyOnceWith( 'No valid active discussions found' );
} );

test( 'fetchActiveDiscussions with all keys missing', async () => {
	mw.Api.mock( {} );
	const logError = vi.spyOn( mw.log, 'error' ).mockImplementationOnce( () => {} );

	await load();
	expect( feedState.error.message ).toStrictEqual( 'No valid active discussions found' );
	expect( logError ).toHaveBeenCalledExactlyOnceWith( 'No valid active discussions found' );
} );

test( 'fetchActiveDiscussions with error', async () => {
	mw.Api.mock( () => {
		throw new Error( 'An error' );
	} );
	const logError = vi.spyOn( mw.log, 'error' ).mockImplementationOnce( () => {} );

	await load();
	expect( feedState.error.message ).toStrictEqual( 'An error' );
	expect( logError ).toHaveBeenCalledExactlyOnceWith( 'An error' );
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
