import { test, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import FeedCard from '/resources/ext.personalDashboard.common/components/FeedCard.vue';
import FeedPanel from '/resources/ext.personalDashboard.common/components/FeedPanel.vue';

const ITEM_SLOT = '<div class="test-item">{{ params.item.id }}</div>';

function makeItems( count ) {
	return Array.from( { length: count }, ( _, i ) => ( { id: `item-${ i }` } ) );
}

function mountPanel( props = {}, options = {} ) {
	return mount( FeedPanel, {
		props: Object.assign( {
			items: [],
			route: 'feed'
		}, props ),
		slots: {
			item: `<template #item="params">${ ITEM_SLOT }</template>`
		},
		...options
	} );
}

test( 'shows the progress bar while loading', () => {
	const wrapper = mountPanel( { isLoading: true } );

	expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( true );
} );

test( 'shows the error message instead of the progress bar', () => {
	// Asserted through the $i18n call rather than the rendered text: the
	// message mock drops parameters, so the failure is only visible there.
	const i18n = vi.fn( ( key ) => key );
	const wrapper = mountPanel(
		{ error: new Error( 'An Error' ) },
		{ global: { mocks: { $i18n: i18n } } }
	);

	expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( false );
	expect( i18n ).toHaveBeenCalledWith( 'personal-dashboard-feed-error', 'An Error' );
	expect( wrapper.text() ).toContain( 'personal-dashboard-feed-error' );
} );

test( 'says so when a load comes back empty', () => {
	const wrapper = mountPanel( { emptyLabel: 'Nothing here' } );

	expect( wrapper.find( '.personal-dashboard-feed__empty' ).text() ).toStrictEqual( 'Nothing here' );
} );

test.each( [
	[ 'still loading', { isLoading: true } ],
	[ 'failed', { error: new Error( 'An Error' ) } ],
	[ 'has items', { items: makeItems( 1 ) } ]
] )( 'keeps the empty label away while the feed %s', ( _, props ) => {
	const wrapper = mountPanel( { emptyLabel: 'Nothing here', ...props } );

	expect( wrapper.find( '.personal-dashboard-feed__empty' ).exists() ).toStrictEqual( false );
} );

test( 'says it could not load, not that there is nothing, when the feed is incomplete and empty', () => {
	const wrapper = mountPanel( { isIncomplete: true, emptyLabel: 'Nothing here' } );

	const notice = wrapper.find( '.personal-dashboard-feed__empty' );
	expect( notice.text() ).toContain( 'personal-dashboard-feed-incomplete' );
	expect( wrapper.text() ).not.toContain( 'Nothing here' );
} );

test( 'shows the items and no notice when an incomplete feed has items', () => {
	const wrapper = mountPanel( { isIncomplete: true, items: makeItems( 2 ), detail: 'full' } );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 2 );
	expect( wrapper.find( '.personal-dashboard-feed__empty' ).exists() ).toStrictEqual( false );
} );

test( 'renders one item slot per item', () => {
	const wrapper = mountPanel( { items: makeItems( 2 ), detail: 'full' } );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 2 );
	expect( wrapper.text() ).toContain( 'item-1' );
} );

test( 'keeps the list mounted but hidden when the feed is empty', () => {
	const wrapper = mountPanel( { items: [] } );

	// v-show, not v-if: the card and the dialog share one teleported instance,
	// so the list must survive the summary/full transition.
	const container = wrapper.find( '.personal-dashboard-feed__list' );
	expect( container.exists() ).toStrictEqual( true );
	expect( container.attributes( 'style' ) ).toContain( 'display: none' );
} );

test( 'follows the viewport rule unless told otherwise', () => {
	const wrapper = mountPanel( { items: makeItems( 5 ), detail: 'full', isNarrow: false } );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 5 );
	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'card mode summarizes a grid card on every viewport', () => {
	const wrapper = mountPanel( { items: makeItems( 5 ), summaryMode: 'card', isNarrow: false } );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 3 );
	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( true );
} );

test.each( [ 'focused', 'active' ] )( 'card mode drops the summary when %s', ( prop ) => {
	const wrapper = mountPanel( { items: makeItems( 5 ), summaryMode: 'card', [ prop ]: true } );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 5 );
	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'viewport mode summarizes only a compact card', () => {
	const compact = mountPanel( { items: makeItems( 5 ), summaryMode: 'viewport', detail: 'compact' } );
	expect( compact.findAll( '.test-item' ) ).toHaveLength( 3 );
	expect( compact.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( true );

	const full = mountPanel( { items: makeItems( 5 ), summaryMode: 'viewport', detail: 'full' } );
	expect( full.findAll( '.test-item' ) ).toHaveLength( 5 );
	expect( full.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'honours a module-supplied summary limit', () => {
	const wrapper = mountPanel( { items: makeItems( 5 ), detail: 'compact', summaryLimit: 1 } );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 1 );
} );

test( 'hands the item and the viewport to the slot', () => {
	const wrapper = mount( FeedPanel, {
		props: {
			items: makeItems( 1 ),
			route: 'feed',
			isNarrow: true
		},
		slots: {
			item: '<template #item="{ item, isNarrow }"><div class="test-item">{{ item.id }}/{{ isNarrow }}</div></template>'
		}
	} );

	expect( wrapper.find( '.test-item' ).text() ).toStrictEqual( 'item-0/true' );
} );

test( 'labels the show more button and gives it the module id', () => {
	const wrapper = mountPanel( {
		items: makeItems( 5 ),
		detail: 'compact',
		showMoreAriaLabel: 'Show more edits',
		footerId: 'test-footer'
	} );

	const button = wrapper.find( '.personal-dashboard-feed__show-more' );
	expect( button.text() ).toStrictEqual( '⧼personal-dashboard-feed-load-more⧽' );
	expect( button.attributes( 'aria-label' ) ).toStrictEqual( 'Show more edits' );
	expect( button.attributes( 'id' ) ).toStrictEqual( 'test-footer' );
} );

test( 'the show more button routes to the module', async () => {
	const push = vi.fn();
	const wrapper = mountPanel( { items: makeItems( 5 ), detail: 'compact' }, {
		global: {
			mocks: {
				$router: { push }
			}
		}
	} );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );

	expect( push ).toHaveBeenCalledWith( '/feed' );
} );

test( 'the show more button goes nowhere when no route reached the panel', async () => {
	// A module mounted outside the dashboard app is handed no route, so the
	// control must not push a path built out of nothing.
	const push = vi.fn();
	const wrapper = mountPanel( { items: makeItems( 5 ), detail: 'compact', route: '' }, {
		global: {
			mocks: {
				$router: { push }
			}
		}
	} );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );

	expect( push ).not.toHaveBeenCalled();
} );

test( 'hides the show more button while loading, even with enough items to summarize', () => {
	const wrapper = mountPanel( { items: makeItems( 5 ), detail: 'compact', isLoading: true } );

	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'hides the show more button when there are not enough items to need a summary', () => {
	const wrapper = mountPanel( { items: makeItems( 2 ), detail: 'compact' } );

	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'the full list offers to load more, the summary does not', () => {
	const full = mountPanel( { items: makeItems( 5 ), detail: 'full', hasMore: true } );
	expect( full.find( '.personal-dashboard-feed__load-more' ).exists() ).toStrictEqual( true );

	const summary = mountPanel( { items: makeItems( 5 ), detail: 'compact', hasMore: true } );
	expect( summary.find( '.personal-dashboard-feed__load-more' ).exists() ).toStrictEqual( false );
} );

test( 'offers to load more only when there is another page', () => {
	const wrapper = mountPanel( { items: makeItems( 5 ), detail: 'full' } );

	expect( wrapper.find( '.personal-dashboard-feed__load-more' ).exists() ).toStrictEqual( false );
} );

test( 'the load-more control asks the module for the next page', async () => {
	const wrapper = mountPanel( { items: makeItems( 5 ), detail: 'full', hasMore: true } );

	await wrapper.find( '.personal-dashboard-feed__load-more' ).trigger( 'click' );

	expect( wrapper.emitted( 'load-more' ) ).toHaveLength( 1 );
} );

test( 'swaps the load-more control for a progress bar while the next page loads', () => {
	const wrapper = mountPanel( {
		items: makeItems( 5 ),
		detail: 'full',
		hasMore: true,
		isLoadingMore: true
	} );

	// Gone, not disabled, so a second click cannot reach it.
	expect( wrapper.find( '.personal-dashboard-feed__load-more' ).exists() )
		.toStrictEqual( false );
	expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( true );
	// The items already loaded stay on screen below the first page.
	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 5 );
} );

test( 'hides the load-more control while the first page loads', () => {
	const wrapper = mountPanel( {
		items: makeItems( 5 ),
		detail: 'full',
		hasMore: true,
		isLoading: true
	} );

	expect( wrapper.find( '.personal-dashboard-feed__load-more' ).exists() )
		.toStrictEqual( false );
} );

test( 'without expandInPlace, the show more button opens the full list on a wide viewport too', async () => {
	const push = vi.fn();
	const wrapper = mountPanel( { items: makeItems( 10 ), summaryMode: 'card' }, {
		global: { mocks: { $router: { push } } }
	} );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );

	expect( push ).toHaveBeenCalledWith( '/feed' );
	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 3 );
} );

test( 'the show more button shows more items in place on a wide viewport', async () => {
	const push = vi.fn();
	const wrapper = mountPanel( { items: makeItems( 10 ), summaryMode: 'card', expandInPlace: true }, {
		global: { mocks: { $router: { push } } }
	} );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 6 );
	expect( push ).not.toHaveBeenCalled();
	expect( wrapper.emitted( 'load-more' ) ).toBeUndefined();
} );

test( 'asks for the next page once the wide summary runs out of loaded items', async () => {
	const wrapper = mountPanel( { items: makeItems( 5 ), summaryMode: 'card', expandInPlace: true, hasMore: true } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 5 );
	expect( wrapper.emitted( 'load-more' ) ).toHaveLength( 1 );
} );

test( 'offers more on a wide viewport when every loaded item shows but another page exists', () => {
	const wrapper = mountPanel( { items: makeItems( 3 ), summaryMode: 'card', expandInPlace: true, hasMore: true } );

	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( true );
} );

test( 'swaps the show more button for a progress bar while a wide summary loads more', async () => {
	const wrapper = mountPanel( { items: makeItems( 5 ), summaryMode: 'card', expandInPlace: true, hasMore: true } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.setProps( { isLoadingMore: true } );

	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
	expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( true );
} );

test( 'the show less button appears after showing more and returns to the summary', async () => {
	const wrapper = mountPanel( { items: makeItems( 10 ), summaryMode: 'card', expandInPlace: true } );
	expect( wrapper.find( '.personal-dashboard-feed__show-less' ).exists() ).toStrictEqual( false );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	const showLess = wrapper.find( '.personal-dashboard-feed__show-less' );
	expect( showLess.attributes( 'aria-label' ) ).toStrictEqual( '⧼personal-dashboard-feed-show-less⧽' );

	await showLess.trigger( 'click' );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 3 );
	expect( wrapper.find( '.personal-dashboard-feed__show-less' ).exists() ).toStrictEqual( false );
} );

test( 'keeps only the show less button once everything shows and there is no other page', async () => {
	const wrapper = mountPanel( { items: makeItems( 5 ), summaryMode: 'card', expandInPlace: true } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 5 );
	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
	expect( wrapper.find( '.personal-dashboard-feed__show-less' ).exists() ).toStrictEqual( true );
} );

test( 'a reload returns a wide summary to its first items', async () => {
	const wrapper = mountPanel( { items: makeItems( 10 ), summaryMode: 'card', expandInPlace: true } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.setProps( { isLoading: true } );
	await wrapper.setProps( { isLoading: false } );

	expect( wrapper.findAll( '.test-item' ) ).toHaveLength( 3 );
} );

test( 'uses module-supplied labels for the show more and show less buttons', async () => {
	const wrapper = mountPanel( {
		items: makeItems( 10 ),
		summaryMode: 'card',
		expandInPlace: true,
		showMoreLabel: 'More edits',
		showLessAriaLabel: 'Show fewer edits'
	} );

	const button = wrapper.find( '.personal-dashboard-feed__show-more' );
	expect( button.text() ).toStrictEqual( 'More edits' );

	await button.trigger( 'click' );

	expect( wrapper.find( '.personal-dashboard-feed__show-less' ).attributes( 'aria-label' ) )
		.toStrictEqual( 'Show fewer edits' );
} );

const LINK_ITEM_SLOT = '<template #item="params"><a class="test-item" :href="\'#\' + params.item.id">{{ params.item.id }}</a></template>';

function mountAttached( props ) {
	return mount( FeedPanel, {
		props: { route: 'feed', summaryMode: 'card', expandInPlace: true, ...props },
		slots: { item: LINK_ITEM_SLOT },
		attachTo: document.body
	} );
}

test( 'moves focus to the first new item after showing more', async () => {
	const wrapper = mountAttached( { items: makeItems( 10 ) } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.vm.$nextTick();

	expect( document.activeElement.textContent ).toStrictEqual( 'item-3' );
	wrapper.unmount();
} );

test( 'keeps focus on the busy list while the next page loads, then moves it to the first new item', async () => {
	const wrapper = mountAttached( { items: makeItems( 3 ), hasMore: true } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.setProps( { isLoadingMore: true } );
	await wrapper.vm.$nextTick();

	const list = wrapper.find( '.personal-dashboard-feed__list' );
	expect( document.activeElement ).toStrictEqual( list.element );
	expect( list.attributes( 'aria-busy' ) ).toStrictEqual( 'true' );
	expect( list.attributes( 'aria-label' ) ).toStrictEqual( '⧼personal-dashboard-feed-loading-more⧽' );

	await wrapper.setProps( { isLoadingMore: false, items: makeItems( 13 ) } );
	await wrapper.vm.$nextTick();

	expect( document.activeElement.textContent ).toStrictEqual( 'item-3' );
	wrapper.unmount();
} );

test( 'keeps focus on the show less button while the next page loads', async () => {
	const wrapper = mountAttached( { items: makeItems( 6 ), hasMore: true } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.setProps( { isLoadingMore: true } );
	await wrapper.vm.$nextTick();

	expect( document.activeElement.getAttribute( 'aria-label' ) )
		.toStrictEqual( '⧼personal-dashboard-feed-show-less⧽' );
	wrapper.unmount();
} );

test( 'moves focus back to the show more button after collapsing', async () => {
	const wrapper = mountAttached( { items: makeItems( 10 ) } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.find( '.personal-dashboard-feed__show-less' ).trigger( 'click' );
	await wrapper.vm.$nextTick();

	expect( document.activeElement.textContent.trim() ).toStrictEqual( '⧼personal-dashboard-feed-load-more⧽' );
	wrapper.unmount();
} );

test( 'keeps focus on the list after the show less button is used while a page loads', async () => {
	const wrapper = mountAttached( { items: makeItems( 6 ), hasMore: true } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.setProps( { isLoadingMore: true } );
	await wrapper.find( '.personal-dashboard-feed__show-less' ).trigger( 'click' );
	await wrapper.vm.$nextTick();

	const list = wrapper.find( '.personal-dashboard-feed__list' ).element;

	expect( document.activeElement ).toBe( list );

	await wrapper.setProps( { isLoadingMore: false, items: makeItems( 9 ) } );
	await wrapper.vm.$nextTick();

	expect( document.activeElement ).toBe( list );
	wrapper.unmount();
} );

test( 'after a failed page, asks for the same page again and focuses its first item', async () => {
	const wrapper = mountAttached( { items: makeItems( 3 ), hasMore: true } );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.setProps( { isLoadingMore: true } );
	await wrapper.setProps( { isLoadingMore: false, error: { message: 'Failed' } } );
	await wrapper.vm.$nextTick();

	expect( wrapper.find( '.personal-dashboard-feed__show-less' ).exists() ).toStrictEqual( false );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );
	await wrapper.setProps( { isLoadingMore: true, error: null } );
	await wrapper.setProps( { isLoadingMore: false, items: makeItems( 13 ) } );
	await wrapper.vm.$nextTick();

	expect( wrapper.emitted( 'load-more' ) ).toHaveLength( 2 );
	expect( document.activeElement.textContent ).toStrictEqual( 'item-3' );
	wrapper.unmount();
} );

test( 'shows loading cards and a disabled show more button while the summary loads', () => {
	const wrapper = mountPanel( { isLoading: true, detail: 'compact' }, {
		slots: { 'loading-card': '<div class="test-loading-card"></div>' }
	} );

	expect( wrapper.findAll( '.test-loading-card' ) ).toHaveLength( 3 );
	expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( false );
	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).attributes( 'disabled' ) ).toBeDefined();
} );

test( 'hides the earlier items behind the loading cards while the summary reloads', () => {
	const wrapper = mountPanel( { isLoading: true, detail: 'compact', items: makeItems( 5 ) }, {
		slots: {
			item: `<template #item="params">${ ITEM_SLOT }</template>`,
			'loading-card': '<div class="test-loading-card"></div>'
		},
		attachTo: document.body
	} );

	expect( wrapper.find( '.test-item' ).isVisible() ).toStrictEqual( false );
	wrapper.unmount();
} );

test( 'shows divider rows, not cards, in the full list on a narrow viewport', () => {
	const mountWithCards = ( props ) => mount( FeedPanel, {
		props: { items: makeItems( 2 ), route: 'feed', ...props },
		slots: {
			item: '<template #item="params"><feed-card>{{ params.item.id }}</feed-card></template>'
		},
		global: { components: { FeedCard } }
	} );

	expect( mountWithCards( { detail: 'full', isNarrow: true } )
		.findAll( '.cdx-card--separation-divider' ) ).toHaveLength( 2 );
	expect( mountWithCards( { detail: 'full', isNarrow: false } )
		.find( '.cdx-card--separation-divider' ).exists() ).toStrictEqual( false );
	expect( mountWithCards( { detail: 'compact', isNarrow: true } )
		.find( '.cdx-card--separation-divider' ).exists() ).toStrictEqual( false );
} );
