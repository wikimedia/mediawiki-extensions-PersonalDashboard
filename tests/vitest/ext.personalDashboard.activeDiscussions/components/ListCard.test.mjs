import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ListCard from '/resources/ext.personalDashboard.activeDiscussions/components/ListCard.vue';
import { formatRelativeTimeOrDate } from 'mediawiki.DateFormatter';

test( 'mount component', () => {
	const date = new Date( 2026, 1, 1 );
	date.setHours( 8, 25 );
	const wrapper = mount( ListCard, {
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

	const wrapper = mount( ListCard, {
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

	const wrapper = mount( ListCard, {
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

test( 'renders discussion title without html markup', async () => {
	const date = new Date( 2024, 11, 2, 4, 29 );
	const wrapper = mount( ListCard, {
		props: {
			discussionTitle: '<a title="Markup" class="mw-redirect">Test Title</a>',
			discussionPage: 'Wikipedia:Village Pump',
			commentCount: 12,
			authorCount: 4,
			latestReply: date.toISOString(),
			latestReplyId: 'foo'
		}
	} );

	expect( 'Test Title' ).toStrictEqual( wrapper.vm.discussionTitleFormatted );
} );

test( 'renders discussion page without html markup', async () => {
	const date = new Date( 2024, 11, 2, 4, 29 );
	const wrapper = mount( ListCard, {
		props: {
			discussionTitle: 'Test Title',
			discussionPage: 'Wikipedia:Village_pump_(idea_lab)#<span_id="id"></span>DiscussionPage',
			commentCount: 12,
			authorCount: 4,
			latestReply: date.toISOString(),
			latestReplyId: 'foo'
		}
	} );

	expect( 'Wikipedia:Village_pump_(idea_lab)#DiscussionPage' ).toStrictEqual( wrapper.vm.discussionPageFormatted );
} );

test( 'renders discussion URL without html markup', async () => {
	const date = new Date( 2024, 11, 2, 4, 29 );
	const wrapper = mount( ListCard, {
		props: {
			discussionTitle: 'Test Title',
			discussionPage: 'Wikipedia:Village_pump_(idea_lab)#<span_id="id"></span>DiscussionPage',
			commentCount: 12,
			authorCount: 4,
			latestReply: date.toISOString(),
			latestReplyId: 'foo'
		}
	} );

	expect( '/wiki/Wikipedia:Village_pump_(idea_lab)#DiscussionPage#Test_Title' ).toStrictEqual( wrapper.vm.discussionUrl );
} );
