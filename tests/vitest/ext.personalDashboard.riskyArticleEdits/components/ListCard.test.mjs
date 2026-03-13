import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ListCard from '/resources/ext.personalDashboard.riskyArticleEdits/components/ListCard.vue';

// Safely ignore error: Cannot find package 'ext.checkUser.userInfoCard'
mw.loader.using = () => {};

test( 'mount component', () => {
	const date = new Date( 2026, 1, 1, 3, 0 );

	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			type: 'TestType',
			ns: 0,
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			pageid: 8675309,
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
			timestamp: date.toISOString(),
			pages: [
				{
					pageid: 8675309,
					description: 'a description'
				}
			],
			feedorigin: 'recentchanges'
		}
	} );

	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'renders appropriate message when edit is made today', () => {
	const date = new Date( 2026, 0, 31, 22, 0 );
	const expectedDate = '3 hours ago';

	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			type: 'TestType',
			ns: 0,
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			pageid: 8675309,
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
			timestamp: date.toISOString(),
			pages: [],
			feedorigin: 'recentchanges'
		}
	} );

	expect( expectedDate ).toStrictEqual( wrapper.vm.timestampFormatted );
} );

test( 'renders timestamp without hours when edit is not made today', async () => {
	const date = new Date( 2024, 11, 2, 4, 29 );
	const expectedDate = '1 year ago';

	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			type: 'TestType',
			ns: 0,
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			pageid: 8675309,
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
			timestamp: date.toISOString(),
			pages: [],
			feedorigin: 'recentchanges'
		}
	} );

	expect( expectedDate ).toStrictEqual( wrapper.vm.timestampFormatted );
} );

test( 'strips all html formatting from parsedcomment', () => {
	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			type: 'TestType',
			ns: 0,
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			pageid: 8675309,
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'Plain text <h1>heading</h1>, <b>bold</b>, and <a href="#">link</a>.',
			tags: [ 'test' ],
			timestamp: new Date( 2024, 11, 2, 4, 29 ).toISOString(),
			pages: [],
			feedorigin: 'recentchanges'
		}
	} );

	expect( wrapper.vm.comment ).toStrictEqual( 'Plain text heading, bold, and link.' );
} );

test( 'user info card visible on desktop', () => {
	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			pageid: 8675309,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date().toISOString(),
			tags: [],
			pages: [],
			feedorigin: 'recentchanges',
			isMobile: false
		},
		global: {
			stubs: {
				UserInfoButton: true
			}
		}
	} );

	const button = wrapper.findComponent( { name: 'UserInfoButton' } );
	expect( button.exists() ).toStrictEqual( true );
} );

test( 'user info card hidden on mobile', () => {
	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			pageid: 8675309,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date().toISOString(),
			tags: [],
			pages: [],
			feedorigin: 'recentchanges',
			isMobile: true
		},
		global: {
			stubs: {
				UserInfoButton: true
			}
		}
	} );

	const button = wrapper.findComponent( { name: 'UserInfoButton' } );
	expect( button.exists() ).toStrictEqual( false );
} );
