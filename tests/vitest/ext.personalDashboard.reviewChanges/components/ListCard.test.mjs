import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ListCard from '/resources/ext.personalDashboard.reviewChanges/components/ListCard.vue';

function hasVisited( wrapper ) {
	// The visited modifier belongs to the shared FeedCard chrome, and lands on
	// this same element alongside the module's own class.
	return wrapper.classes( 'personal-dashboard-feed__card--visited' );
}

function getOtherLinks( wrapper ) {
	return [
		wrapper.find( '.personal-dashboard-review-changes__card__title' ),
		wrapper.find( '.personal-dashboard-review-changes__card__username' )
	];
}

function mountWithLengths( oldlen, newlen ) {
	return mount( ListCard, {
		props: {
			title: 'TestTitle',
			newlen,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen,
			pageid: 8675309,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date( 2026, 1, 1, 3, 0 ).toISOString(),
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
}

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

test( 'sets visited on primary link click', async () => {
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
			isNarrow: false
		},
		global: {
			stubs: {
				UserInfoButton: true
			}
		}
	} );

	expect( hasVisited( wrapper ) ).toStrictEqual( false );

	// Remove href for testing only, otherwise happy-dom will fetch\
	const link = wrapper.find( '.personal-dashboard-feed__card__link' );
	link.element.removeAttribute( 'href' );

	await link.trigger( 'click' );
	expect( hasVisited( wrapper ) ).toStrictEqual( true );
} );

test( 'does not set visited on other link clicks', async () => {
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
			isNarrow: false
		},
		global: {
			stubs: {
				UserInfoButton: true
			}
		}
	} );

	expect( hasVisited( wrapper ) ).toStrictEqual( false );

	for ( const link of getOtherLinks( wrapper ) ) {
		// Remove href for testing only, otherwise happy-dom will fetch
		link.element.removeAttribute( 'href' );

		await link.trigger( 'click' );
		expect( hasVisited( wrapper ) ).toStrictEqual( false );
	}
} );

test( 'title and username are not links on mobile', async () => {
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
			isNarrow: true
		},
		global: {
			stubs: {
				UserInfoButton: true
			}
		}
	} );

	expect( hasVisited( wrapper ) ).toStrictEqual( false );

	for ( const link of getOtherLinks( wrapper ) ) {
		expect( link.element.tagName ).toStrictEqual( 'SPAN' );
	}
} );

test( 'user info card visible on desktop', () => {
	mw.user.options.set( 'checkuser-userinfocard-enable', true );

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
			isNarrow: false
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

test( 'shows the major changes chip when the size delta exceeds 1000 bytes', () => {
	const wrapper = mountWithLengths( 2000, 3001 );

	expect( wrapper.vm.isMajorChange ).toStrictEqual( true );

	const chip = wrapper.find( '.cdx-info-chip' );
	expect( chip.exists() ).toStrictEqual( true );
	expect( chip.text() ).toStrictEqual( '⧼personal-dashboard-review-changes-major-changes-label⧽' );
	expect( chip.find( '.cdx-icon' ).exists() ).toStrictEqual( true );
} );

test( 'shows the major changes chip for large removals', () => {
	const wrapper = mountWithLengths( 3001, 2000 );

	expect( wrapper.vm.isMajorChange ).toStrictEqual( true );
	expect( wrapper.find( '.cdx-info-chip' ).exists() ).toStrictEqual( true );
} );

test( 'hides the major changes chip for a small size delta', () => {
	const wrapper = mountWithLengths( 2000, 2100 );

	expect( wrapper.vm.isMajorChange ).toStrictEqual( false );
	expect( wrapper.find( '.cdx-info-chip' ).exists() ).toStrictEqual( false );
} );

test( 'hides the major changes chip when the size delta is exactly 1000 bytes', () => {
	const wrapper = mountWithLengths( 2000, 3000 );

	expect( wrapper.vm.isMajorChange ).toStrictEqual( false );
	expect( wrapper.find( '.cdx-info-chip' ).exists() ).toStrictEqual( false );
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
			isNarrow: true
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
