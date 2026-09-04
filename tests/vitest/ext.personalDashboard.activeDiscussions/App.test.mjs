import { vi, beforeEach, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

vi.mock( '/resources/ext.personalDashboard.activeDiscussions/composables/useActiveDiscussionsFeed.js', () => {
	const mock = {
		feedState: reactive( { items: [], isLoading: true, error: null } ),
		load: vi.fn()
	};
	return { useActiveDiscussionsFeed: () => mock };
} );

import { useActiveDiscussionsFeed } from '/resources/ext.personalDashboard.activeDiscussions/composables/useActiveDiscussionsFeed.js';
const { feedState, load } = useActiveDiscussionsFeed();

import ActiveDiscussions from '/resources/ext.personalDashboard.activeDiscussions/App.vue';

function makeItem( index ) {
	return {
		id: `Wikipedia:Village pump#Discussion ${ index }`,
		discussionPage: 'Wikipedia:Village pump',
		discussionTitle: `Discussion ${ index }`,
		commentCount: 4,
		authorCount: 3,
		latestReply: new Date( 2024, 11, 2 ).toISOString(),
		latestReplyId: `c-Username-${ index }`
	};
}

beforeEach( () => {
	feedState.items = [];
	feedState.isLoading = true;
	feedState.error = null;
	load.mockReset();
} );

test( 'mount component', () => {
	const wrapper = mount( ActiveDiscussions );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'shows progress bar when loading', () => {
	const wrapper = mount( ActiveDiscussions );
	expect( wrapper.find( '.cdx-progress-bar' ).exists() ).toStrictEqual( true );
} );

test( 'shows error message when there is one', () => {
	feedState.isLoading = false;
	feedState.error = new Error( 'An Error' );

	// Asserted through the $i18n call rather than the rendered text: the
	// message mock drops parameters, so the failure is only visible there.
	const i18n = vi.fn( ( key ) => key );
	const wrapper = mount( ActiveDiscussions, { global: { mocks: { $i18n: i18n } } } );

	expect( i18n ).toHaveBeenCalledWith( 'personal-dashboard-feed-error', 'An Error' );
	expect( wrapper.text() ).toContain( 'personal-dashboard-feed-error' );
} );

test( 'shows discussions with their counts and latest reply', () => {
	feedState.isLoading = false;
	feedState.items = [ makeItem( 0 ) ];

	const wrapper = mount( ActiveDiscussions );

	expect( wrapper.text() ).toContain( 'Discussion 0' );
	expect( wrapper.text() ).toContain( 'Wikipedia:Village pump' );
	expect( wrapper.text() ).toContain( '1 year ago' );
} );

test( 'does not leak the synthetic feed id onto the rendered card', () => {
	feedState.isLoading = false;
	feedState.items = [ makeItem( 0 ) ];

	const wrapper = mount( ActiveDiscussions );

	// makeItem()'s id contains a "#", so a leaked id would be a malformed
	// (and non-unique) DOM attribute; FeedPanel keys the list on it instead.
	expect( wrapper.find( '.personal-dashboard-active-discussions__card' ).attributes( 'id' ) )
		.toBeUndefined();
} );

test( 'fetches the full 10-item limit regardless of detail', () => {
	feedState.isLoading = false;

	mount( ActiveDiscussions );
	expect( load ).toHaveBeenCalledWith( 10 );
} );

test( 'the compact summary shows only the first 3 items of a larger fetched feed', () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeItem( i ) );

	const wrapper = mount( ActiveDiscussions, { props: { detail: 'compact' } } );

	expect( wrapper.findAllComponents( { name: 'ListCard' } ) ).toHaveLength( 3 );
	expect( wrapper.text() ).toContain( 'Discussion 0' );
	expect( wrapper.text() ).not.toContain( 'Discussion 3' );
} );

// This module follows the viewport rule rather than Review Changes' card rule,
// so a wide viewport shows the whole feed in the card itself.
test( 'a full detail card shows every fetched item', () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeItem( i ) );

	const wrapper = mount( ActiveDiscussions, { props: { detail: 'full' } } );

	expect( wrapper.findAllComponents( { name: 'ListCard' } ) ).toHaveLength( 5 );
	expect( wrapper.find( '.personal-dashboard-feed__show-more' ).exists() ).toStrictEqual( false );
} );

test( 'show more opens the module dialog via the router', async () => {
	feedState.isLoading = false;
	feedState.items = Array.from( { length: 5 }, ( _, i ) => makeItem( i ) );

	const push = vi.fn();
	const wrapper = mount( ActiveDiscussions, {
		props: { detail: 'compact' },
		global: {
			mocks: {
				$router: { push }
			}
		}
	} );

	await wrapper.find( '#personal-dashboard-go-to-active-discussions' ).trigger( 'click' );

	expect( push ).toHaveBeenCalledWith( '/ext.personalDashboard.activeDiscussions' );
} );
