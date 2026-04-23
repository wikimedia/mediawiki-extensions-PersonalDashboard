import { vi, beforeEach, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';

vi.mock( '/resources/ext.personalDashboard.riskyArticleEdits/composables/useFetchActivityResult.js', () => {
	const mock = {
		recentActivityResult: ref( null ),
		loading: ref( true ),
		error: ref( null ),
		fetchRecentActivity: vi.fn()
	};
	return { default: () => mock };
} );

import useFetchActivityResult from '/resources/ext.personalDashboard.riskyArticleEdits/composables/useFetchActivityResult.js';
const {
	recentActivityResult,
	loading,
	error,
	fetchRecentActivity
} = useFetchActivityResult();

import RecentActivity from '/resources/ext.personalDashboard.riskyArticleEdits/App.vue';

mw.user.options.set( 'personaldashboard-risky-articles-info', 0 );

// Safely ignore error: Cannot find package 'ext.checkUser.userInfoCard'
mw.loader.using = () => {};

beforeEach( () => {
	recentActivityResult.value = null;
	loading.value = true;
	error.value = null;
	fetchRecentActivity.mockReset();
} );

test( 'mount component', () => {
	mw.Api.mock( ( params, options ) => {
		if ( options.type === 'POST' &&
			params.action === 'options' &&
			( params.optionname === 'personaldashboard-visited' ||
			params.optionname === 'personaldashboard-eligible' ) &&
			params.optionvalue === '1' ) {
			return {};
		}
	} );
	const wrapper = mount( RecentActivity );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'shows progress bar when loading', () => {
	mw.Api.mock( ( params, options ) => {
		if ( options.type === 'POST' &&
			params.action === 'options' &&
			( params.optionname === 'personaldashboard-visited' ||
			params.optionname === 'personaldashboard-eligible' ) &&
			params.optionvalue === '1' ) {
			return {};
		}
	} );
	const wrapper = mount( RecentActivity );
	expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( true );
} );

test( 'shows error message when there is one', () => {
	mw.Api.mock( ( params, options ) => {
		if ( options.type === 'POST' &&
			params.action === 'options' &&
			( params.optionname === 'personaldashboard-visited' ||
			params.optionname === 'personaldashboard-eligible' ) &&
			params.optionvalue === '1' ) {
			return {};
		}
	} );
	loading.value = false;
	error.value = new Error( 'An Error' );

	const wrapper = mount( RecentActivity );
	expect( wrapper.text() ).toContain( 'An Error' );
} );

test( 'shows up to 5 recent changes with information', () => {
	mw.config.set( 'wgMFMode', null );
	loading.value = false;
	recentActivityResult.value = {
		feed: [
			{
				title: 'Article Title',
				type: '',
				ns: 0,
				pageid: 15864,
				revid: 2430984,
				// eslint-disable-next-line camelcase
				old_revid: 2394508293,
				rcid: 2348,
				user: 'User',
				bot: false,
				newlen: 250,
				oldlen: 20,
				temp: '',
				parsedcomment: 'A comment',
				tags: [],
				timestamp: new Date( 2024, 11, 2 ).toISOString(),
				feedorigin: 'recentchanges'
			}
		],
		pages: [
			{
				ns: 0,
				pageid: 15864,
				title: 'Article Title',
				description: 'A description'
			}
		]
	};

	const wrapper = mount( RecentActivity );

	expect( fetchRecentActivity ).toHaveBeenCalledWith( 5 );
	expect( wrapper.text() ).toContain( 'Article Title' );
	expect( wrapper.text() ).toContain( 'A comment' );
	expect( wrapper.text() ).toContain( 'A description' );
	expect( wrapper.text() ).toContain( '1 year ago' );
} );

test( 'shows up to 10 recent changes with information when on mobile view', () => {
	mw.config.set( 'wgMFMode', true );
	loading.value = false;
	recentActivityResult.value = {
		feed: [
			{
				title: 'Article Title',
				type: '',
				ns: 0,
				pageid: 15864,
				revid: 2430984,
				// eslint-disable-next-line camelcase
				old_revid: 2394508293,
				rcid: 2348,
				user: 'User',
				bot: false,
				newlen: 250,
				oldlen: 20,
				temp: '',
				parsedcomment: 'A comment',
				tags: [],
				timestamp: new Date( 2024, 11, 2 ).toISOString(),
				feedorigin: 'recentchanges'
			}
		],
		pages: [
			{
				ns: 0,
				pageid: 15864,
				title: 'Article Title',
				description: 'A description'
			}
		]
	};

	const wrapper = mount( RecentActivity );

	expect( fetchRecentActivity ).toHaveBeenCalledWith( 10 );
	expect( wrapper.text() ).toContain( 'Article Title' );
	expect( wrapper.text() ).toContain( 'A comment' );
	expect( wrapper.text() ).toContain( 'A description' );
	expect( wrapper.text() ).toContain( '1 year ago' );
} );

test( 'shows header message by default on mobile and updates preference when closed', async () => {
	mw.user.options.set( 'personaldashboard-risky-articles-info', 1 );
	mw.config.set( 'wgMFMode', true );
	loading.value = false;
	let closed = false;

	mw.Api.mock( ( params, options ) => {
		if ( options.type === 'POST' &&
			params.action === 'options' &&
			params.optionname === 'personaldashboard-risky-articles-info' &&
			params.optionvalue === 0 ) {
			closed = true;
		}
		if ( options.type === 'POST' &&
			params.action === 'options' &&
			( params.optionname === 'personaldashboard-visited' ||
			params.optionname === 'personaldashboard-eligible' ) &&
			params.optionvalue === '1' ) {
			return {};
		}
	} );

	const wrapper = mount( RecentActivity );

	const messageHeader = wrapper.find( '.personal-dashboard-review-changes__message' );
	expect( messageHeader.exists() ).toStrictEqual( true );

	const dismissButton = wrapper.find( '.cdx-message__dismiss-button' );
	await dismissButton.trigger( 'click' );

	expect( closed ).toStrictEqual( true );
} );

test( 'shows header message by default on non-mobile', () => {
	mw.user.options.set( 'personaldashboard-risky-articles-info', 1 );
	mw.config.set( 'wgMFMode', null );
	loading.value = false;

	const wrapper = mount( RecentActivity );
	expect( wrapper.findComponent( '.personal-dashboard-review-changes__message' ).exists() ).toStrictEqual( true );
} );

test( 'does not show header message by default on mobile summary rendermode', () => {
	mw.user.options.set( 'personaldashboard-risky-articles-info', 1 );
	mw.config.set( 'wgMFMode', true );
	loading.value = false;

	const wrapper = mount( RecentActivity, {
		props: {
			rendermode: 'mobile-summary'
		}
	} );

	expect( wrapper.find( '.personal-dashboard-review-changes__message' ).exists() ).toStrictEqual( false );
} );

test( 'does not show header message if preference is off', async () => {
	mw.config.set( 'wgMFMode', true );
	mw.user.options.set( 'personaldashboard-risky-articles-info', 0 );
	loading.value = false;

	const wrapper = mount( RecentActivity );
	expect( wrapper.find( '.personal-dashboard-review-changes__message' ).exists() ).toStrictEqual( false );
} );
