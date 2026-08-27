import { test, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import FeedPanel from '/resources/ext.personalDashboard.common/components/FeedPanel.vue';

const ITEM_SLOT = '<div class="test-item">{{ params.item.id }}</div>';

function makeItems( count ) {
	return Array.from( { length: count }, ( _, i ) => ( { id: `item-${ i }` } ) );
}

function mountPanel( props = {}, options = {} ) {
	return mount( FeedPanel, {
		props: Object.assign( {
			items: [],
			moduleName: 'ext.test.feed',
			footerLabel: 'Show more'
		}, props ),
		slots: {
			item: `<template #item="params">${ ITEM_SLOT }</template>`
		},
		...options
	} );
}

test( 'shows the progress bar while loading', () => {
	const wrapper = mountPanel( { isLoading: true, progressBarAriaLabel: 'Loading' } );

	const progressBar = wrapper.find( '.cdx-progress-bar' );
	expect( progressBar.exists() ).toStrictEqual( true );
	expect( progressBar.attributes( 'aria-label' ) ).toStrictEqual( 'Loading' );
} );

test( 'shows the error message instead of the progress bar', () => {
	// Asserted through $i18n rather than the rendered text: the message mock
	// drops parameters, so what the failure reaches the reader as is only
	// visible in the call.
	const i18n = vi.fn( ( key ) => key );
	const wrapper = mountPanel(
		{ error: new Error( 'An Error' ) },
		{ global: { mocks: { $i18n: i18n } } }
	);

	expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( false );
	expect( i18n ).toHaveBeenCalledWith( 'personal-dashboard-feed-error', 'An Error' );
	expect( wrapper.text() ).toContain( 'personal-dashboard-feed-error' );
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
	const container = wrapper.find( '.personal-dashboard-feed__container' );
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
	expect( wrapper.find( '.personal-dashboard-feed__list--summary' ).exists() ).toStrictEqual( true );
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
			moduleName: 'ext.test.feed',
			footerLabel: 'Show more',
			isNarrow: true
		},
		slots: {
			item: '<template #item="{ item, isNarrow }"><div class="test-item">{{ item.id }}/{{ isNarrow }}</div></template>'
		}
	} );

	expect( wrapper.find( '.test-item' ).text() ).toStrictEqual( 'item-0/true' );
} );

test( 'labels the footer control and gives it the module id', () => {
	const wrapper = mountPanel( {
		detail: 'compact',
		footerLabel: 'Show more',
		footerAriaLabel: 'Show more edits',
		footerId: 'test-footer'
	} );

	const button = wrapper.find( '.personal-dashboard-feed__show-more' );
	expect( button.text() ).toStrictEqual( 'Show more' );
	expect( button.attributes( 'aria-label' ) ).toStrictEqual( 'Show more edits' );
	expect( button.attributes( 'id' ) ).toStrictEqual( 'test-footer' );
} );

test( 'omits the id attribute when no footer id is given', () => {
	const wrapper = mountPanel( { detail: 'compact' } );

	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).attributes( 'id' ) )
		.toBeUndefined();
} );

test( 'the footer control routes to the module', async () => {
	const push = vi.fn();
	const wrapper = mountPanel( { detail: 'compact' }, {
		global: {
			mocks: {
				$router: { push }
			}
		}
	} );

	await wrapper.find( '.personal-dashboard-feed__show-more' ).trigger( 'click' );

	expect( push ).toHaveBeenCalledWith( '/ext.test.feed' );
} );
