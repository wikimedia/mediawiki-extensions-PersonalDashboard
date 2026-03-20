import { test, expectTypeOf, expect } from 'vitest';
import { default as useFetchActiveDiscussionsResult } from '@resources/ext.personalDashboard.activeDiscussions/composables/useFetchActiveDiscussionsResult.js';
const {
	activeDiscussionsResult,
	error,
	fetchActiveDiscussions
} = useFetchActiveDiscussionsResult();

mw.config.set( {
	wgPersonalDashboardActiveDiscussionsPages: [ 'Wikipedia:Village_pump' ]
} );

test( 'fetchActiveDiscussions with response', async () => {
	const mockResponse = {
		discussiontoolspageinfo:
		{
			threaditemshtml: [ {
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
			} ]
		}
	};
	const expectedReturn = [ {
		discussionPage: 'Wikipedia:Village_pump',
		discussionTitle: 'This should be the first comment',
		commentCount: 4,
		authorCount: 3,
		latestReply: '2026-02-19T19:10:00Z',
		latestReplyId: 'c-Username1234-20260219191000-BrandNewUser-20260219013300'
	} ];
	mw.Api.callback = () => mockResponse;
	expectTypeOf( fetchActiveDiscussions ).toExtend( 'asyncFunction' );

	await fetchActiveDiscussions();
	const result = activeDiscussionsResult.value;

	expect( expectedReturn ).toEqual( result );
} );

test( 'fetchActiveDiscussions with no items', async () => {
	const mockResponse = {
		discussiontoolspageinfo: {
			threaditemshtml: []
		}
	};
	mw.Api.callback = () => mockResponse;
	expectTypeOf( fetchActiveDiscussions ).toExtend( 'asyncFunction' );

	await fetchActiveDiscussions();
	const result = activeDiscussionsResult.value;

	expect( mockResponse.discussiontoolspageinfo.threaditemshtml ).toEqual( result );
} );

test( 'fetchActiveDiscussions with key missing', async () => {
	const mockResponse = {
		discussiontoolspageinfo: {}
	};
	mw.Api.callback = () => mockResponse;
	expectTypeOf( fetchActiveDiscussions ).toExtend( 'asyncFunction' );

	await fetchActiveDiscussions();
	expect( error.value ).toBe( 'No valid active discussions found' );

} );

test( 'fetchActiveDiscussions with all keys missing', async () => {
	const mockResponse = {};
	mw.Api.callback = () => mockResponse;
	expectTypeOf( fetchActiveDiscussions ).toExtend( 'asyncFunction' );

	await fetchActiveDiscussions();
	expect( error.value ).toBe( 'No valid active discussions found' );

} );

test( 'fetchActiveDiscussions with error', async () => {
	mw.Api.callback = () => {
		throw new Error( 'An error' );
	};
	expectTypeOf( fetchActiveDiscussions ).toExtend( 'asyncFunction' );

	await fetchActiveDiscussions();

	expect( error ).toBeTruthy();
	expect( error.value.message ).toBe( 'An error' );
} );

test( 'fetchActiveDiscussions with low author count', async () => {
	const mockResponse = {
		discussiontoolspageinfo:
		{
			threaditemshtml: [ {
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
			} ]
		}
	};
	const expectedReturn = [];
	mw.Api.callback = () => mockResponse;
	expectTypeOf( fetchActiveDiscussions ).toExtend( 'asyncFunction' );

	await fetchActiveDiscussions();
	const result = activeDiscussionsResult.value;

	expect( expectedReturn ).toEqual( result );
} );
