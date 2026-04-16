import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ListCardMobile from '/resources/ext.personalDashboard.activeDiscussions/components/ListCardMobile.vue';
import { formatRelativeTimeOrDate } from 'mediawiki.DateFormatter';

test( 'mount component', () => {
	const date = new Date( 2026, 0, 31, 8, 25 );
	date.setHours( 8, 25 );

	const wrapper = mount( ListCardMobile, {
		props: {
			discussionTitle: 'Test Title',
			discussionPage: 'Wikipedia:Village Pump',
			commentCount: 12,
			authorCount: 4,
			latestReply: date.toISOString(),
			latestReplyId: 'foo'
		}
	} );

	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'renders appropriate message when edit is made today', () => {
	const date = new Date( 2026, 0, 31, 22, 0 );
	const expectedDate = '3 hours ago';

	const wrapper = mount( ListCardMobile, {
		props: {
			discussionTitle: 'Test Title',
			discussionPage: 'Wikipedia:Village Pump',
			commentCount: 12,
			authorCount: 4,
			latestReply: date.toISOString(),
			latestReplyId: 'foo'
		}
	} );
	expect( expectedDate ).toStrictEqual( wrapper.vm.timestampFormatted );
} );

test( 'renders timestamp without hours when edit is not made today', async () => {
	const date = new Date( 2024, 11, 2, 4, 29 );
	const expectedDate = formatRelativeTimeOrDate( date );

	const wrapper = mount( ListCardMobile, {
		props: {
			discussionTitle: 'Test Title',
			discussionPage: 'Wikipedia:Village Pump',
			commentCount: 12,
			authorCount: 4,
			latestReply: date.toISOString(),
			latestReplyId: 'foo'
		}
	} );

	expect( expectedDate ).toStrictEqual( wrapper.vm.timestampFormatted );
} );
