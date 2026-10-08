import { test, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ListCard from '/resources/ext.personalDashboard.activeDiscussions/components/ListCard.vue';
import { formatRelativeTimeOrDate } from 'mediawiki.DateFormatter';

function mountCard( props = {} ) {
	return mount( ListCard, {
		props: {
			title: 'Wikipedia:Village pump (idea lab)',
			section: 'Test Title',
			anchor: 'Test_Title',
			topic: null,
			commentCount: 12,
			authorCount: 4,
			timestamp: new Date( 2024, 11, 2, 4, 29 ).toISOString(),
			latestReplyId: 'foo',
			...props
		}
	} );
}

test( 'mount component', () => {
	const date = new Date( 2026, 1, 1 );
	date.setHours( 8, 25 );

	expect( mountCard( { timestamp: date.toISOString() } ).element ).toMatchSnapshot();
} );

test( 'renders appropriate message when edit is made today', () => {
	const date = new Date( 2026, 0, 31, 22, 0 );

	const wrapper = mountCard( { timestamp: date.toISOString() } );

	expect( wrapper.vm.timestampFormatted ).toStrictEqual( '3 hours ago' );
} );

test( 'renders timestamp without hours when edit is not made today', () => {
	const date = new Date( 2024, 11, 2, 4, 29 );

	const wrapper = mountCard( { timestamp: date.toISOString() } );

	expect( wrapper.vm.timestampFormatted ).toStrictEqual( formatRelativeTimeOrDate( date ) );
} );

// An empty topic comes from a top-level heading whose text strips to nothing.
test.each( [ null, '' ] )( 'meta row is the page title alone when the topic is %j', ( topic ) => {
	const msg = vi.spyOn( mw, 'msg' );

	const wrapper = mountCard( { topic } );

	expect( wrapper.find( '.personal-dashboard-active-discussions__card__subheader' ).text() )
		.toStrictEqual( 'Wikipedia:Village pump (idea lab)' );
	expect( msg ).not.toHaveBeenCalledWith(
		'personal-dashboard-active-discussions-in-topic',
		expect.anything(),
		expect.anything()
	);
	expect( wrapper.find( 'a' ).attributes( 'aria-label' ) )
		.toStrictEqual( 'Test Title⧼comma-separator⧽Wikipedia:Village pump (idea lab)' );

	msg.mockRestore();
} );

test( 'meta row names the topic when the thread is a sub-thread', () => {
	const msg = vi.spyOn( mw, 'msg' );

	const wrapper = mountCard( { section: 'Survey', topic: 'Requested move of Jupiter' } );

	// The message mock drops parameters, so the call is where they show.
	expect( msg ).toHaveBeenCalledWith(
		'personal-dashboard-active-discussions-in-topic',
		'Wikipedia:Village pump (idea lab)',
		'Requested move of Jupiter'
	);
	expect( wrapper.find( '.personal-dashboard-active-discussions__card__subheader' ).text() )
		.toStrictEqual( '⧼personal-dashboard-active-discussions-in-topic⧽' );
	expect( wrapper.find( 'a' ).attributes( 'aria-label' ) )
		.toStrictEqual( 'Survey⧼comma-separator⧽⧼personal-dashboard-active-discussions-in-topic⧽' );

	msg.mockRestore();
} );

test( 'links the card to the rendered anchor, with one fragment', () => {
	const wrapper = mountCard( { anchor: 'Test_Title' } );

	expect( wrapper.find( 'a' ).attributes( 'href' ) )
		.toStrictEqual( '/wiki/Wikipedia:Village_pump_(idea_lab)#Test_Title' );
} );

test( 'links the card to the page when the heading has no anchor', () => {
	const wrapper = mountCard( { anchor: '' } );

	expect( wrapper.find( 'a' ).attributes( 'href' ) )
		.toStrictEqual( '/wiki/Wikipedia:Village_pump_(idea_lab)' );
} );

test( 'links the timestamp to the latest comment on a wide viewport', () => {
	const wrapper = mountCard( { isNarrow: false } );

	const link = wrapper.find( '.personal-dashboard-feed__card__description a' );
	expect( link.attributes( 'href' ) )
		.toStrictEqual( '/wiki/Wikipedia:Village_pump_(idea_lab)#foo' );
} );

test( 'drops the timestamp link on a narrow viewport', () => {
	const wrapper = mountCard( {
		isNarrow: true,
		timestamp: new Date( 2024, 11, 2 ).toISOString()
	} );

	expect( wrapper.find( '.personal-dashboard-feed__card__description a' ).exists() )
		.toStrictEqual( false );
	expect( wrapper.find( '.personal-dashboard-feed__card__description' ).text() )
		.toContain( '1 year ago' );
} );
