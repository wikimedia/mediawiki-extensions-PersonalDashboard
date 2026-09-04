import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick, reactive } from 'vue';

vi.mock( '/resources/ext.personalDashboard.reviewChanges/store/reviewChangesStore.js', () => {
	const mockStore = reactive( {
		feed: [],
		pages: [],
		isLoading: true,
		error: null,
		// The shared feed-data contract the module hands to the scaffold; a
		// getter so a test can keep mutating the state fields above.
		get feedState() {
			return { items: this.feed, isLoading: this.isLoading, error: this.error };
		},
		fetchRecentActivity: vi.fn()
	} );
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

	// Asserted through the $i18n call rather than the rendered text: the
	// message mock drops parameters, so the failure is only visible there.
	const i18n = vi.fn( ( key ) => key );
	const wrapper = mount( RecentActivity, { global: { mocks: { $i18n: i18n } } } );

	expect( i18n ).toHaveBeenCalledWith( 'personal-dashboard-feed-error', 'An Error' );
	expect( wrapper.text() ).toContain( 'personal-dashboard-feed-error' );
} );

test( 'shows recent changes with information', () => {
	store.isLoading = false;
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

test( 'does not leak the synthetic feed id or other non-prop fields onto the rendered card', () => {
	store.isLoading = false;
	store.feed = [
		{
			title: 'Article Title',
			ns: 0,
			pageid: 15864,
			revid: 2430984,
			// eslint-disable-next-line camelcase
			old_revid: 2394508293,
			user: 'User',
			bot: false,
			minor: false,
			new: false,
			newlen: 250,
			oldlen: 20,
			parsedcomment: 'A comment',
			tags: [],
			timestamp: new Date( 2024, 11, 2 ).toISOString(),
			feedorigin: 'recentchanges',
			id: 'recentchanges-2430984'
		}
	];

	const wrapper = mount( RecentActivity );

	// normalizeFeedItem() stamps every item with these fields for FeedPanel's own
	// use; ListCard never declares them as props, so a leaked one would be a
	// malformed (or meaningless) DOM attribute.
	const card = wrapper.find( '.personal-dashboard-review-changes__card' );
	expect( card.attributes( 'id' ) ).toBeUndefined();
	expect( card.attributes( 'minor' ) ).toBeUndefined();
	expect( card.attributes( 'bot' ) ).toBeUndefined();
	expect( card.attributes( 'new' ) ).toBeUndefined();
	expect( card.attributes( 'tags' ) ).toBeUndefined();
} );

test( 'fetches the full 10-item limit regardless of summary/focused/active', () => {
	store.isLoading = false;

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
	store.feed = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const wrapper = mount( RecentActivity );

	expect( wrapper.findAllComponents( { name: 'ListCard' } ) ).toHaveLength( 3 );
	expect( wrapper.text() ).toContain( 'Article 0' );
	expect( wrapper.text() ).not.toContain( 'Article 3' );
} );

test( 'a dialog reusing the same teleported instance shows every fetched item, not the summary subset', () => {
	store.isLoading = false;
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
	store.feed = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const wrapper = mount( RecentActivity );
	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( true );
	expect( wrapper.find( '.personal-dashboard-feed__list--summary' ).exists() ).toStrictEqual( true );
} );

test( 'focused or active drops the summary affordances', () => {
	store.isLoading = false;
	store.feed = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const focused = mount( RecentActivity, { props: { focused: true } } );
	expect( focused.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );

	const active = mount( RecentActivity, { props: { active: true } } );
	expect( active.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'show more opens the module dialog via the router', async () => {
	store.isLoading = false;
	store.feed = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const push = vi.fn();
	const wrapper = mount( RecentActivity, {
		global: {
			mocks: {
				$router: { push }
			}
		}
	} );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );

	expect( push ).toHaveBeenCalledWith( '/ext.personalDashboard.reviewChanges' );
} );

// Every mock IntersectionObserver below must be a real function, not an arrow:
// App.vue calls `new IntersectionObserver()`, and arrows can't be constructors.
describe( 'IntersectionObserver lifecycle', () => {
	let originalIntersectionObserver;

	beforeEach( () => {
		originalIntersectionObserver = window.IntersectionObserver;
	} );

	afterEach( () => {
		window.IntersectionObserver = originalIntersectionObserver;
	} );

	test( 'disconnects the observer on unmount', () => {
		const disconnect = vi.fn();
		// eslint-disable-next-line prefer-arrow-callback
		window.IntersectionObserver = vi.fn().mockImplementation( function () {
			return {
				observe: vi.fn(),
				unobserve: vi.fn(),
				disconnect
			};
		} );

		const wrapper = mount( RecentActivity );
		wrapper.unmount();

		expect( disconnect ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'fires the loaded hook only once even if the observer intersects again', () => {
		let intersect;
		// eslint-disable-next-line prefer-arrow-callback
		window.IntersectionObserver = vi.fn().mockImplementation( function ( callback ) {
			intersect = callback;
			return { observe: vi.fn(), unobserve: vi.fn(), disconnect: vi.fn() };
		} );

		store.isLoading = false;
		const fired = vi.fn();
		mw.hook( 'personaldashboard.recentactivity.loaded' ).add( fired );

		mount( RecentActivity );
		intersect( [ { isIntersecting: true } ] );
		intersect( [ { isIntersecting: true } ] );

		mw.hook( 'personaldashboard.recentactivity.loaded' ).remove( fired );
		expect( fired ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'waits for the feed to finish loading before firing, even once visible', async () => {
		let intersect;
		// eslint-disable-next-line prefer-arrow-callback
		window.IntersectionObserver = vi.fn().mockImplementation( function ( callback ) {
			intersect = callback;
			return { observe: vi.fn(), unobserve: vi.fn(), disconnect: vi.fn() };
		} );

		store.isLoading = true;
		const fired = vi.fn();
		mw.hook( 'personaldashboard.recentactivity.loaded' ).add( fired );

		mount( RecentActivity );
		intersect( [ { isIntersecting: true } ] );

		expect( fired ).not.toHaveBeenCalled();

		store.isLoading = false;
		await nextTick();

		mw.hook( 'personaldashboard.recentactivity.loaded' ).remove( fired );
		expect( fired ).toHaveBeenCalledTimes( 1 );
	} );
} );
