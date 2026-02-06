import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ListCard from '@resources/ext.personalDashboard.activeDiscussions/components/ListCard.vue';
import { formatRelativeTimeOrDate } from 'mediawiki.DateFormatter';

test( 'mount component', () => {
	const date = new Date();
	date.setHours( 8, 25 );
	const wrapper = mount( ListCard, {
		props: {
			discussionTitle: 'Test Title',
			discussionPage: 'Wikipedia:Village Pump',
			commentCount: 12,
			authorCount: 4,
			latestReply: date.toISOString()
		}
	} );

	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'renders appropriate message when edit is made today', () => {
	const date = new Date();
	date.setHours( 3, 25 );
	const expectedDate = '3 hours ago';
	const wrapper = mount( ListCard, {
		props: {
			discussionTitle: 'Test Title',
			discussionPage: 'Wikipedia:Village Pump',
			commentCount: 12,
			authorCount: 4,
			latestReply: date.toISOString()
		}
	} );
	expect( expectedDate ).toBe( wrapper.vm.timestampFormatted );
} );

test( 'renders timestamp without hours when edit is not made today', async () => {
	const date = new Date( 2024, 11, 2, 4, 29 );
	const expectedDate = formatRelativeTimeOrDate( date );
	const wrapper = mount( ListCard, {
		props: {
			discussionTitle: 'Test Title',
			discussionPage: 'Wikipedia:Village Pump',
			commentCount: 12,
			authorCount: 4,
			latestReply: date.toISOString()
		}
	} );

	expect( expectedDate ).toBe( wrapper.vm.timestampFormatted );
} );
