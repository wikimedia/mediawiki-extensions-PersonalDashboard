import { vi, beforeEach, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

vi.mock( '/resources/ext.personalDashboard.riskyArticleEdits/store/reviewChangesStore.js', () => {
	const mockStore = {
		feed: [],
		pages: [],
		isLoading: true,
		error: null,
		hasFeed: false,
		fetchRecentActivity: vi.fn()
	};
	return { useReviewChangesStore: () => mockStore };
} );

import { useReviewChangesStore } from '/resources/ext.personalDashboard.riskyArticleEdits/store/reviewChangesStore.js';
const store = useReviewChangesStore();

import RecentActivity from '/resources/ext.personalDashboard.riskyArticleEdits/App.vue';

mw.user.options.set( 'personaldashboard-risky-articles-info', 0 );

// Safely ignore error: Cannot find package 'ext.checkUser.userInfoCard'
mw.loader.using = () => {};

beforeEach( () => {
	setActivePinia( createPinia() );
	store.feed = [];
	store.pages = [];
	store.isLoading = true;
	store.error = null;
	store.hasFeed = false;
	store.fetchRecentActivity.mockReset();
} );

test( 'mount component', () => {
	const wrapper = mount( RecentActivity );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'shows progress bar when loading', () => {
	const wrapper = mount( RecentActivity );
	expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( true );
} );

test( 'shows error message when there is one', () => {
	store.isLoading = false;
	store.error = new Error( 'An Error' );

	const wrapper = mount( RecentActivity );
	expect( wrapper.text() ).toContain( 'An Error' );
} );

test( 'shows up to 5 recent changes with information', () => {
	mw.config.set( 'wgMFMode', null );
	store.isLoading = false;
	store.hasFeed = true;
	store.feed = [
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
	];
	store.pages = [
		{
			ns: 0,
			pageid: 15864,
			title: 'Article Title',
			description: 'A description'
		}
	];

	const wrapper = mount( RecentActivity );

	expect( store.fetchRecentActivity ).toHaveBeenCalledWith( 5 );
	expect( wrapper.text() ).toContain( 'Article Title' );
	expect( wrapper.text() ).toContain( 'A comment' );
	expect( wrapper.text() ).toContain( 'A description' );
	expect( wrapper.text() ).toContain( '1 year ago' );
} );

test( 'shows up to 10 recent changes with information when on mobile view', () => {
	mw.config.set( 'wgMFMode', true );
	store.isLoading = false;
	store.hasFeed = true;
	store.feed = [
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
	];
	store.pages = [
		{
			ns: 0,
			pageid: 15864,
			title: 'Article Title',
			description: 'A description'
		}
	];

	const wrapper = mount( RecentActivity );

	expect( store.fetchRecentActivity ).toHaveBeenCalledWith( 10 );
	expect( wrapper.text() ).toContain( 'Article Title' );
	expect( wrapper.text() ).toContain( 'A comment' );
	expect( wrapper.text() ).toContain( 'A description' );
	expect( wrapper.text() ).toContain( '1 year ago' );
} );

test( 'does not show header message by default on mobile summary rendermode', () => {
	mw.user.options.set( 'personaldashboard-risky-articles-info', 1 );
	mw.config.set( 'wgMFMode', true );
	store.isLoading = false;

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
	store.isLoading = false;

	const wrapper = mount( RecentActivity );
	expect( wrapper.find( '.personal-dashboard-review-changes__message' ).exists() ).toStrictEqual( false );
} );
