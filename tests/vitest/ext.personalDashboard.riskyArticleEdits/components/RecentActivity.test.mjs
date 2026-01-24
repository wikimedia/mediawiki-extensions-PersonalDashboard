import { test, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';

const recentActivityResult = ref( null );
const loading = ref( true );
const error = ref( null );
const mockFetchRecentActivity = vi.fn();

vi.mock( 'ext.personalDashboard.common', () => ( { useFetchActivityResult: () => ( {
	recentActivityResult,
	loading,
	error,
	fetchRecentActivity: mockFetchRecentActivity
} ) } ) );

import RecentActivity from '@resources/ext.personalDashboard.riskyArticleEdits/components/RecentActivity.vue';

beforeEach( () => {
	recentActivityResult.value = null;
	loading.value = true;
	error.value = null;
	mockFetchRecentActivity.mockReset();
} );

test( 'mount component', () => {
	const wrapper = mount( RecentActivity );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'shows loading message when loading', () => {
	const wrapper = mount( RecentActivity );

	expect( wrapper.text() ).toContain( 'Loading...' );
} );

test( 'shows error message when there is one', () => {
	loading.value = false;
	error.value = {
		message: 'An Error'
	};
	const wrapper = mount( RecentActivity );

	expect( wrapper.text() ).toContain( 'An Error' );
} );

test( 'shows recent changes information when available', () => {
	loading.value = false;
	recentActivityResult.value = {
		query: {
			recentchanges: [ {
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
				timestamp: new Date( 2024, 11, 2 ).toISOString()
			} ],
			pages: {
				15864: { ns: 0, title: 'Article Title', description: 'A description' }
			}
		}
	};
	const wrapper = mount( RecentActivity );

	expect( wrapper.text() ).toContain( 'Article Title' );
	expect( wrapper.text() ).toContain( 'A comment' );
	expect( wrapper.text() ).toContain( 'A description' );
	expect( wrapper.text() ).toContain( '+230' );
	expect( wrapper.text() ).toContain( 'December 2024' );
} );
