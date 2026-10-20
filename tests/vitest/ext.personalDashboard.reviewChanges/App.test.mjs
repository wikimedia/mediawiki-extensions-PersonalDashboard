import { vi, beforeEach, describe, test, expect } from 'vitest';
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
import PersonalizationPanel from '/resources/ext.personalDashboard.reviewChanges/components/PersonalizationPanel.vue';

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
} );

test( 'focused or active drops the summary affordances', () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const focused = mount( RecentActivity, { props: { focused: true } } );
	expect( focused.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );

	const active = mount( RecentActivity, { props: { active: true } } );
	expect( active.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'the show more button opens the module dialog via the router on a narrow viewport', async () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );

	const push = vi.fn();
	// route rides in $attrs: the dashboard app resolves this module's slug from
	// the group it is placed in and hands it down, so the module never names it.
	const wrapper = mount( RecentActivity, {
		props: { isNarrow: true, route: 'review' },
		global: {
			mocks: {
				$router: { push }
			}
		}
	} );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );

	expect( push ).toHaveBeenCalledWith( '/review' );
} );

test( 'the show more button shows more edits in the card on a wide viewport', async () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeFeedItem( i ) );
	feedState.hasMore = true;

	const push = vi.fn();
	const wrapper = mount( RecentActivity, { global: { mocks: { $router: { push } } } } );
	const showMore = wrapper.find( '.personal-dashboard-feed__show-more' );

	expect( showMore.attributes( 'aria-label' ) )
		.toStrictEqual( '⧼personal-dashboard-review-changes-show-more-aria-label⧽' );

	await showMore.trigger( 'click' );

	expect( wrapper.findAllComponents( { name: 'ListCard' } ) ).toHaveLength( 5 );
	expect( loadMore ).toHaveBeenCalledTimes( 1 );
	expect( push ).not.toHaveBeenCalled();
	expect( wrapper.find( '.personal-dashboard-feed__show-less' ).attributes( 'aria-label' ) )
		.toStrictEqual( '⧼personal-dashboard-review-changes-show-less-aria-label⧽' );
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

test( 'no header menu when the frame emits no header slot', () => {
	const wrapper = mount( RecentActivity );

	expect( wrapper.findComponent( { name: 'ModuleHeaderMenu' } ).exists() )
		.toStrictEqual( false );
} );

test( 'teleports the header menu into the header slot', () => {
	const wrapper = mount( RecentActivity, {
		props: { headerTarget: '#pd-header-slot-reviewChanges' }
	} );

	const menu = wrapper.findComponent( { name: 'ModuleHeaderMenu' } );

	expect( menu.exists() ).toStrictEqual( true );
	// Teleport is stubbed in tests, so assert the target rather than the move.
	expect( wrapper.find( '[to="#pd-header-slot-reviewChanges"]' ).exists() )
		.toStrictEqual( true );
	expect( menu.get( 'button' ).attributes( 'aria-label' ) )
		.toContain( 'personal-dashboard-review-changes-menu-button-label' );

	expect( menu.props( 'menuItems' ).map( ( item ) => item.value ) )
		.toStrictEqual( [ 'personalization' ] );
	// About sits in the footer, which is where the divider above it comes from.
	expect( menu.props( 'footerItem' ).value ).toStrictEqual( 'about' );
} );

test.each( [
	[ 'personalization', 'personal-dashboard-review-changes-personalization-title' ],
	[ 'about', 'personal-dashboard-review-changes-menu-about' ]
] )( 'picking %s opens that panel alone', async ( value, title ) => {
	const wrapper = mount( RecentActivity, {
		props: { headerTarget: '#pd-header-slot-reviewChanges' }
	} );

	const panels = wrapper.findAllComponents( { name: 'ModulePanel' } );
	expect( panels.map( ( panel ) => panel.props( 'open' ) ) )
		.toStrictEqual( [ false, false ] );

	wrapper.findComponent( { name: 'ModuleHeaderMenu' } ).vm.$emit( 'select', value );
	await nextTick();

	const open = panels.filter( ( panel ) => panel.props( 'open' ) );
	expect( open ).toHaveLength( 1 );
	expect( open[ 0 ].props( 'title' ) ).toContain( title );

	// A panel reports only its own close, and the menu owns which one is open.
	open[ 0 ].vm.$emit( 'update:open', false );
	await nextTick();
	expect( panels.filter( ( panel ) => panel.props( 'open' ) ) ).toHaveLength( 0 );
} );

// isNarrow is a declared prop here, unlike its fellow island props, so it no
// longer reaches the scaffold through $attrs. The template has to hand it over
// by name, and nothing else would notice if it stopped.
test( 'hands isNarrow to the scaffold as well as to the header panels', () => {
	const wrapper = mount( RecentActivity, {
		props: { headerTarget: '#pd-header-slot-reviewChanges', isNarrow: true }
	} );

	expect( wrapper.findComponent( { name: 'FeedPanel' } ).props( 'isNarrow' ) )
		.toStrictEqual( true );
	expect( wrapper.findAllComponents( { name: 'ModulePanel' } )
		.map( ( panel ) => panel.props( 'isNarrow' ) ) )
		.toStrictEqual( [ true, true ] );
} );

test( 'loads the feed again when personalization changes', () => {
	const wrapper = mount( RecentActivity, {
		props: { headerTarget: '#pd-header-slot-reviewChanges' }
	} );
	load.mockClear();

	wrapper.findComponent( PersonalizationPanel ).vm.$emit( 'change' );

	expect( load ).toHaveBeenCalledWith( 10 );
} );

// load() ignores a call while another is in flight, so a change then is lost.
test.each( [ 'isLoading', 'isLoadingMore' ] )( 'disables the personalization toggle while %s', ( field ) => {
	feedState.isLoading = false;
	feedState[ field ] = true;
	const wrapper = mount( RecentActivity, {
		props: { headerTarget: '#pd-header-slot-reviewChanges' }
	} );

	expect( wrapper.findComponent( PersonalizationPanel ).props( 'disabled' ) )
		.toBe( true );
} );
