import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FeedCard from '/resources/ext.personalDashboard.common/components/FeedCard.vue';

const VISITED_CLASS = 'personal-dashboard-feed__card--visited';
const LINK_SELECTOR = '.personal-dashboard-feed__card__link';

function mountCard( props = {}, slots = {} ) {
	return mount( FeedCard, {
		props,
		slots: Object.assign( { header: '<div class="test-header">Title</div>' }, slots )
	} );
}

test( 'renders the header slot', () => {
	const wrapper = mountCard();

	expect( wrapper.find( '.personal-dashboard-feed__card__header .test-header' ).exists() )
		.toStrictEqual( true );
} );

test( 'omits the meta and description rows when nothing fills them', () => {
	const wrapper = mountCard();

	expect( wrapper.find( '.personal-dashboard-feed__card__meta' ).exists() ).toStrictEqual( false );
	expect( wrapper.find( '.personal-dashboard-feed__card__description' ).exists() ).toStrictEqual( false );
} );

test( 'renders the meta and description rows when filled', () => {
	const wrapper = mountCard( {}, {
		meta: '<span>A user</span>',
		description: 'The latest comment'
	} );

	expect( wrapper.find( '.personal-dashboard-feed__card__meta' ).text() ).toStrictEqual( 'A user' );
	expect( wrapper.find( '.personal-dashboard-feed__card__description' ).text() ).toStrictEqual( 'The latest comment' );
} );

test( 'passes supporting text through to the card', () => {
	const wrapper = mountCard( {}, { 'supporting-text': '<span class="test-chip">Major</span>' } );

	expect( wrapper.find( '.test-chip' ).exists() ).toStrictEqual( true );
} );

test( 'covers the card with a labelled link to its destination', () => {
	const wrapper = mountCard( { url: '/wiki/Foo', ariaLabel: 'Review the edit to Foo' } );

	const link = wrapper.find( LINK_SELECTOR );
	expect( link.attributes( 'href' ) ).toStrictEqual( '/wiki/Foo' );
	expect( link.attributes( 'aria-label' ) ).toStrictEqual( 'Review the edit to Foo' );
	expect( link.attributes( 'target' ) ).toStrictEqual( '_blank' );
} );

test( 'has no overlay link without a destination', () => {
	const wrapper = mountCard();

	expect( wrapper.find( LINK_SELECTOR ).exists() ).toStrictEqual( false );
} );

test( 'marks itself visited once the overlay link is clicked', async () => {
	const wrapper = mountCard( { url: '/wiki/Foo' } );

	expect( wrapper.classes() ).not.toContain( VISITED_CLASS );

	// Remove href for testing only, otherwise happy-dom will fetch it.
	const link = wrapper.find( LINK_SELECTOR );
	link.element.removeAttribute( 'href' );
	await link.trigger( 'click' );

	expect( wrapper.classes() ).toContain( VISITED_CLASS );
} );

test( 'a click on a slotted link does not mark the card visited', async () => {
	const wrapper = mountCard( { url: '/wiki/Foo' }, {
		meta: '<a class="test-inner-link">A user</a>'
	} );

	await wrapper.find( '.test-inner-link' ).trigger( 'click' );

	expect( wrapper.classes() ).not.toContain( VISITED_CLASS );
} );

test( 'keeps a module class and data attribute on the rendered card', () => {
	const wrapper = mount( FeedCard, {
		attrs: {
			class: 'personal-dashboard-review-changes__card',
			'data-feedorigin': 'watchlist'
		},
		slots: { header: '<div>Title</div>' }
	} );

	expect( wrapper.classes() ).toContain( 'personal-dashboard-review-changes__card' );
	expect( wrapper.attributes( 'data-feedorigin' ) ).toStrictEqual( 'watchlist' );
} );
