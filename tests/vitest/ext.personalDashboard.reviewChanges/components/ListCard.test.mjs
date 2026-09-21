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
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date( 2026, 1, 1, 3, 0 ).toISOString(),
			tags: [],
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
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
			timestamp: date.toISOString(),
			description: 'a description',
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
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
			timestamp: date.toISOString(),
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
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
			timestamp: date.toISOString(),
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
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'Plain text <h1>heading</h1>, <b>bold</b>, and <a href="#">link</a>.',
			tags: [ 'test' ],
			timestamp: new Date( 2024, 11, 2, 4, 29 ).toISOString(),
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
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date().toISOString(),
			tags: [],
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
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date().toISOString(),
			tags: [],
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
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date().toISOString(),
			tags: [],
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
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date().toISOString(),
			tags: [],
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
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date().toISOString(),
			tags: [],
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

test( 'shows the page description the feed item carries', () => {
	const wrapper = mountWithLengths( 0, 0 );
	expect( wrapper.find( '.personal-dashboard-review-changes__card__description' ).exists() )
		.toStrictEqual( false );

	const described = mount( ListCard, {
		props: {
			title: 'TestTitle',
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date().toISOString(),
			tags: [],
			description: 'Fifth planet from the Sun',
			feedorigin: 'recentchanges'
		},
		global: {
			stubs: {
				UserInfoButton: true
			}
		}
	} );

	expect( described.find( '.personal-dashboard-review-changes__card__description' ).text() )
		.toStrictEqual( 'Fifth planet from the Sun' );
} );

test( 'leaves oldid out of the diff link for an item with no parent revision', () => {
	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			newlen: 0,
			oldlen: 0,
			revid: 4711,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date().toISOString(),
			tags: [],
			feedorigin: 'recentchanges'
		},
		global: {
			stubs: {
				UserInfoButton: true
			}
		}
	} );

	// The endpoint declares old_revid nullable, so the prop has to accept it
	// without turning the link into oldid=null.
	const href = wrapper.find( '.personal-dashboard-feed__card__link' ).attributes( 'href' );
	expect( href ).toContain( 'diff=4711' );
	expect( href ).not.toContain( 'oldid' );
} );

test( 'marks the diff link with the feed the item came from', () => {
	// ext.wikimediaEvents.diff reads this parameter on the diff page, to record
	// that the reader came from the dashboard and from which feed (T421397).
	for ( const [ feedorigin, expected ] of [
		[ 'recentchanges', 'origin=personaldashboard-recentchanges' ],
		[ 'watchlist', 'origin=personaldashboard-watchlist' ],
		[ 'recentlyedited', 'origin=personaldashboard-recentlyedited' ]
	] ) {
		const wrapper = mount( ListCard, {
			props: {
				title: 'TestTitle',
				newlen: 0,
				oldlen: 0,
				// eslint-disable-next-line camelcase
				old_revid: 4710,
				revid: 4711,
				user: 'TestUser',
				parsedcomment: 'TestComment',
				timestamp: new Date().toISOString(),
				tags: [],
				feedorigin
			},
			global: {
				stubs: {
					UserInfoButton: true
				}
			}
		} );

		const link = wrapper.find( '.personal-dashboard-feed__card__link' );
		expect( link.attributes( 'href' ) ).toContain( expected );
	}
} );

/**
 * @param {Object} props Overrides on top of a plain, unflagged edit
 * @return {Object} A mounted card
 */
function mountCard( props ) {
	return mount( ListCard, {
		props: Object.assign( {
			title: 'TestTitle',
			newlen: 0,
			oldlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			revid: 4711,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			timestamp: new Date( 2026, 1, 1, 3, 0 ).toISOString(),
			feedorigin: 'recentchanges'
		}, props ),
		global: { stubs: { UserInfoButton: true } }
	} );
}

const THRESHOLD = { model: 'revertrisklanguageagnostic', class: 'true', min: 0.95 };

test( 'flags an edit whose score reaches the wiki threshold', () => {
	mw.config.set( 'wgPersonalDashboardHighRiskThreshold', THRESHOLD );

	const wrapper = mountCard( {
		oresscores: { revertrisklanguageagnostic: { true: 0.97, false: 0.03 } }
	} );

	expect( wrapper.find( '.cdx-info-chip' ).text() )
		.toContain( 'personal-dashboard-review-changes-high-revert-risk-label' );
	expect( wrapper.find( '.cdx-info-chip' ).classes() )
		.toContain( 'cdx-info-chip--warning' );
} );

test( 'flags an edit that sits exactly on the threshold', () => {
	mw.config.set( 'wgPersonalDashboardHighRiskThreshold', THRESHOLD );

	const wrapper = mountCard( {
		oresscores: { revertrisklanguageagnostic: { true: THRESHOLD.min, false: 0.05 } }
	} );

	expect( wrapper.find( '.cdx-info-chip' ).text() )
		.toContain( 'personal-dashboard-review-changes-high-revert-risk-label' );
} );

test( 'leaves an edit below the threshold unflagged', () => {
	mw.config.set( 'wgPersonalDashboardHighRiskThreshold', THRESHOLD );

	const wrapper = mountCard( {
		oresscores: { revertrisklanguageagnostic: { true: 0.5, false: 0.5 } }
	} );

	expect( wrapper.find( '.cdx-info-chip' ).exists() ).toStrictEqual( false );
} );

test( 'makes no check where the wiki configured no threshold', () => {
	mw.config.set( 'wgPersonalDashboardHighRiskThreshold', null );

	const wrapper = mountCard( {
		oresscores: { revertrisklanguageagnostic: { true: 0.99, false: 0.01 } }
	} );

	expect( wrapper.find( '.cdx-info-chip' ).exists() ).toStrictEqual( false );
} );

test( 'makes no check for an edit ORES never scored', () => {
	mw.config.set( 'wgPersonalDashboardHighRiskThreshold', THRESHOLD );

	expect( mountCard( {} ).find( '.cdx-info-chip' ).exists() ).toStrictEqual( false );
} );

test( 'shows both flags on an edit that earns both', () => {
	mw.config.set( 'wgPersonalDashboardHighRiskThreshold', THRESHOLD );

	const wrapper = mountCard( {
		newlen: 5000,
		oldlen: 0,
		oresscores: { revertrisklanguageagnostic: { true: 0.99, false: 0.01 } }
	} );

	const chips = wrapper.findAll( '.cdx-info-chip' );
	expect( chips ).toHaveLength( 2 );

	// The chips sit directly in the card's own row, which is what carries the
	// flex-wrap and the gap. jsdom applies no stylesheet, so the structure is
	// what a test can hold on to.
	const row = wrapper.find( '.personal-dashboard-review-changes__card__flags' );
	expect( Array.from( row.element.children ) ).toHaveLength( 2 );

	// Risk first: it is the reason to look at the edit at all.
	expect( chips[ 0 ].classes() ).toContain( 'cdx-info-chip--warning' );
	expect( chips[ 1 ].text() )
		.toContain( 'personal-dashboard-review-changes-major-changes-label' );
} );
