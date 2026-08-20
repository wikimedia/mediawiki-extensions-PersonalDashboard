import { vi, beforeEach, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

vi.mock( '/resources/ext.personalDashboard.reviewChanges/store/reviewChangesStore.js', () => {
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

import { useReviewChangesStore } from '/resources/ext.personalDashboard.reviewChanges/store/reviewChangesStore.js';
const store = useReviewChangesStore();

import RecentActivity from '/resources/ext.personalDashboard.reviewChanges/App.vue';

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

test( 'shows recent changes with information', () => {
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

	expect( wrapper.text() ).toContain( 'Article Title' );
	expect( wrapper.text() ).toContain( 'A comment' );
	expect( wrapper.text() ).toContain( 'A description' );
	expect( wrapper.text() ).toContain( '1 year ago' );
} );

test( 'fetches the full 10-item limit regardless of summary/focused/active', () => {
	store.isLoading = false;
	store.hasFeed = true;

	mount( RecentActivity );
	expect( store.fetchRecentActivity ).toHaveBeenCalledWith( 10 );
} );

function makeFeedItem( index ) {
	return {
		title: `Article ${ index }`,
		type: '',
		ns: 0,
		pageid: 15864 + index,
		revid: 2430984 + index,
		// eslint-disable-next-line camelcase
		old_revid: 2394508293 + index,
		rcid: 2348 + index,
		user: 'User',
		bot: false,
		newlen: 250,
		oldlen: 20,
		temp: '',
		parsedcomment: 'A comment',
		tags: [],
		timestamp: new Date( 2024, 11, 2 ).toISOString(),
		feedorigin: 'recentchanges'
	};
}

test( 'the summary card shows only the first 3 items of a larger fetched feed', () => {
	store.isLoading = false;
	store.hasFeed = true;
	store.feed = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const wrapper = mount( RecentActivity );

	expect( wrapper.findAllComponents( { name: 'ListCard' } ) ).toHaveLength( 3 );
	expect( wrapper.text() ).toContain( 'Article 0' );
	expect( wrapper.text() ).not.toContain( 'Article 3' );
} );

test( 'a dialog reusing the same teleported instance shows every fetched item, not the summary subset', () => {
	store.isLoading = false;
	store.hasFeed = true;
	store.feed = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	// The dialog and the card teleport one component instance (see IslandMount.vue),
	// so this simulates the transition by mounting with active already true rather
	// than toggling props post-mount: what matters here is that the fetched feed
	// isn't re-sliced to the summary count once summary/full styling drops away.
	const wrapper = mount( RecentActivity, { props: { active: true } } );

	expect( wrapper.findAllComponents( { name: 'ListCard' } ) ).toHaveLength( 5 );
	expect( wrapper.text() ).toContain( 'Article 4' );
} );

test( 'the grid card shows the summary affordances on every viewport', () => {
	store.isLoading = false;
	store.hasFeed = true;

	const wrapper = mount( RecentActivity );
	expect( wrapper.find( '.personal-dashboard-review-changes__show-more' ).exists() ).toStrictEqual( true );
	expect( wrapper.find( '.personal-dashboard-review-changes__list--summary' ).exists() ).toStrictEqual( true );
} );

test( 'focused or active drops the summary affordances', () => {
	store.isLoading = false;
	store.hasFeed = true;

	const focused = mount( RecentActivity, { props: { focused: true } } );
	expect( focused.find( '.personal-dashboard-review-changes__show-more' ).exists() ).toStrictEqual( false );

	const active = mount( RecentActivity, { props: { active: true } } );
	expect( active.find( '.personal-dashboard-review-changes__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'show more opens the module dialog via the router', async () => {
	store.isLoading = false;
	store.hasFeed = true;

	const push = vi.fn();
	const wrapper = mount( RecentActivity, {
		global: {
			mocks: {
				$router: { push }
			}
		}
	} );

	await wrapper.find( '.personal-dashboard-review-changes__show-more' ).trigger( 'click' );

	expect( push ).toHaveBeenCalledWith( '/ext.personalDashboard.reviewChanges' );
} );
