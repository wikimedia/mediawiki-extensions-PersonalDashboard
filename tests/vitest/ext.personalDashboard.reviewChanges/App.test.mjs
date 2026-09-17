import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';

vi.mock( '/resources/ext.personalDashboard.reviewChanges/composables/useReviewChangesFeed.js', () => {
	const feed = {
		feedState: reactive( {
			items: [],
			isLoading: true,
			isLoadingMore: false,
			hasMore: false,
			error: null
		} ),
		load: vi.fn(),
		loadMore: vi.fn()
	};
	return { useReviewChangesFeed: () => feed };
} );

import { useReviewChangesFeed } from '/resources/ext.personalDashboard.reviewChanges/composables/useReviewChangesFeed.js';
const { feedState, load, loadMore } = useReviewChangesFeed();

import RecentActivity from '/resources/ext.personalDashboard.reviewChanges/App.vue';

// Safely ignore error: Cannot find package 'ext.checkUser.userInfoCard'
mw.loader.using = () => {};

beforeEach( () => {
	feedState.items = [];
	feedState.isLoading = true;
	feedState.isLoadingMore = false;
	feedState.hasMore = false;
	feedState.error = null;
	load.mockReset();
	loadMore.mockReset();
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
	feedState.isLoading = false;
	feedState.error = new Error( 'An Error' );

	// Asserted through the $i18n call rather than the rendered text: the
	// message mock drops parameters, so the failure is only visible there.
	const i18n = vi.fn( ( key ) => key );
	const wrapper = mount( RecentActivity, { global: { mocks: { $i18n: i18n } } } );

	expect( i18n ).toHaveBeenCalledWith( 'personal-dashboard-feed-error', 'An Error' );
	expect( wrapper.text() ).toContain( 'personal-dashboard-feed-error' );
} );

test( 'shows recent changes with information', () => {
	feedState.isLoading = false;
	feedState.items = [
		{
			id: 'recentchanges-2430984',
			title: 'Article Title',
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
			description: 'A description',
			tags: [],
			oresscores: {},
			timestamp: new Date( 2024, 11, 2 ).toISOString(),
			feedorigin: 'recentchanges'
		}
	];

	const wrapper = mount( RecentActivity );

	expect( wrapper.text() ).toContain( 'Article Title' );
	expect( wrapper.text() ).toContain( 'A comment' );
	expect( wrapper.text() ).toContain( 'A description' );
	expect( wrapper.text() ).toContain( '1 year ago' );
} );

function makeFeedItem( index ) {
	return {
		id: `recentchanges-${ 2430984 + index }`,
		title: `Article ${ index }`,
		pageid: 15864 + index,
		revid: 2430984 + index,
		// eslint-disable-next-line camelcase
		old_revid: 2394508293 + index,
		user: 'User',
		bot: false,
		minor: false,
		new: false,
		newlen: 250,
		oldlen: 20,
		parsedcomment: 'A comment',
		description: '',
		tags: [],
		oresscores: {},
		timestamp: new Date( 2024, 11, 2 ).toISOString(),
		feedorigin: 'recentchanges'
	};
}

test( 'does not leak the feed id or other non-prop fields onto the rendered card', () => {
	feedState.isLoading = false;
	feedState.items = [ makeFeedItem( 0 ) ];

	const wrapper = mount( RecentActivity );

	// The endpoint stamps every item with these fields for FeedPanel's own use
	// and for a card that wants them; ListCard never declares them as props, so
	// a leaked one would be a malformed (or meaningless) DOM attribute.
	const card = wrapper.find( '.personal-dashboard-review-changes__card' );
	expect( card.attributes( 'id' ) ).toBeUndefined();
	expect( card.attributes( 'pageid' ) ).toBeUndefined();
	expect( card.attributes( 'minor' ) ).toBeUndefined();
	expect( card.attributes( 'bot' ) ).toBeUndefined();
	expect( card.attributes( 'new' ) ).toBeUndefined();
	expect( card.attributes( 'tags' ) ).toBeUndefined();
	expect( card.attributes( 'oresscores' ) ).toBeUndefined();
} );

test( 'fetches the full 10-item limit regardless of summary/focused/active', () => {
	feedState.isLoading = false;

	mount( RecentActivity );
	expect( load ).toHaveBeenCalledWith( 10 );
} );

test( 'the summary card shows only the first 3 items of a larger fetched feed', () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const wrapper = mount( RecentActivity );

	expect( wrapper.findAllComponents( { name: 'ListCard' } ) ).toHaveLength( 3 );
	expect( wrapper.text() ).toContain( 'Article 0' );
	expect( wrapper.text() ).not.toContain( 'Article 3' );
} );

test( 'a dialog reusing the same teleported instance shows every fetched item, not the summary subset', () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	// The dialog and the card teleport one component instance (see IslandMount.vue),
	// so this simulates the transition by mounting with active already true rather
	// than toggling props post-mount: what matters here is that the fetched feed
	// isn't re-sliced to the summary count once summary/full styling drops away.
	const wrapper = mount( RecentActivity, { props: { active: true } } );

	expect( wrapper.findAllComponents( { name: 'ListCard' } ) ).toHaveLength( 5 );
	expect( wrapper.text() ).toContain( 'Article 4' );
} );

test( 'the grid card shows the summary affordances on every viewport', () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const wrapper = mount( RecentActivity );
	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( true );
	expect( wrapper.find( '.personal-dashboard-feed__list--summary' ).exists() ).toStrictEqual( true );
} );

test( 'focused or active drops the summary affordances', () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const focused = mount( RecentActivity, { props: { focused: true } } );
	expect( focused.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );

	const active = mount( RecentActivity, { props: { active: true } } );
	expect( active.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'the summary footer opens the module dialog via the router', async () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

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

describe( 'loading more edits', () => {
	beforeEach( () => {
		feedState.isLoading = false;
		feedState.items = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );
		feedState.hasMore = true;
	} );

	test( 'the full list offers the control, the summary card does not', () => {
		const full = mount( RecentActivity, { props: { active: true } } );
		expect( full.find( '.personal-dashboard-feed__load-more' ).exists() )
			.toStrictEqual( true );

		const summary = mount( RecentActivity );
		expect( summary.find( '.personal-dashboard-feed__load-more' ).exists() )
			.toStrictEqual( false );
	} );

	test( 'asks the feed for the next page', async () => {
		const wrapper = mount( RecentActivity, { props: { active: true } } );

		await wrapper.find( '.personal-dashboard-feed__load-more' ).trigger( 'click' );

		expect( loadMore ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'spins a progress bar below the list while the next page loads', () => {
		feedState.isLoadingMore = true;

		const wrapper = mount( RecentActivity, { props: { active: true } } );

		expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( true );
		expect( wrapper.find( '.personal-dashboard-feed__load-more' ).exists() )
			.toStrictEqual( false );
		// The edits already loaded stay on screen.
		expect( wrapper.findAllComponents( { name: 'ListCard' } ) ).toHaveLength( 5 );
	} );
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

		feedState.isLoading = false;
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

		feedState.isLoading = true;
		const fired = vi.fn();
		mw.hook( 'personaldashboard.recentactivity.loaded' ).add( fired );

		mount( RecentActivity );
		intersect( [ { isIntersecting: true } ] );

		expect( fired ).not.toHaveBeenCalled();

		feedState.isLoading = false;
		await nextTick();

		mw.hook( 'personaldashboard.recentactivity.loaded' ).remove( fired );
		expect( fired ).toHaveBeenCalledTimes( 1 );
	} );
} );
