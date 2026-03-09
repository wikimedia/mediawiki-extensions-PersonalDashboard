import { test, expect, beforeEach, vi, afterEach } from 'vitest';
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

import RecentActivity from '@resources/ext.personalDashboard.riskyArticleEdits/App.vue';
mw.user.options.set( 'personaldashboard-risky-articles-info', 0 );
beforeEach( () => {
	recentActivityResult.value = null;
	loading.value = true;
	error.value = null;
	mockFetchRecentActivity.mockReset();
	// create teleport target for tests that require it
	const el = document.createElement( 'div' );
	el.classList = [ 'personal-dashboard-module-header' ];
	document.body.appendChild( el );
} );
afterEach( () => {
	// clean up teleport target
	document.body.innerHTML = '';
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

test( 'shows up to 5 recent changes with information', () => {
	mw.config.set( { wgMFMode: null } );
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
			pages: [
				{ ns: 0, pageid: 15864, title: 'Article Title', description: 'A description' }
			]
		}
	};
	const wrapper = mount( RecentActivity );

	expect( mockFetchRecentActivity ).toHaveBeenCalledWith( 5 );
	expect( wrapper.text() ).toContain( 'Article Title' );
	expect( wrapper.text() ).toContain( 'A comment' );
	expect( wrapper.text() ).toContain( 'A description' );
	expect( wrapper.text() ).toContain( '+230' );
	expect( wrapper.text() ).toContain( 'December 2024' );
} );

test( 'shows up to 10 recent changes with information when on mobile view', () => {
	mw.config.set( { wgMFMode: true } );
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
			pages: [
				{ ns: 0, pageid: 15864, title: 'Article Title', description: 'A description' }
			]
		}
	};
	const wrapper = mount( RecentActivity );

	expect( mockFetchRecentActivity ).toHaveBeenCalledWith( 10 );
	expect( wrapper.text() ).toContain( 'Article Title' );
	expect( wrapper.text() ).toContain( 'A comment' );
	expect( wrapper.text() ).toContain( 'A description' );
	expect( wrapper.text() ).toContain( '+230' );
	expect( wrapper.text() ).toContain( 'December 2024' );
} );

test( 'shows header message by default on mobile and updates preference when closed', async () => {
	mw.user.options.set( 'personaldashboard-risky-articles-info', 1 );
	mw.config.set( { wgMFMode: true } );
	loading.value = false;
	const wrapper = mount( RecentActivity, {
		global: {
			directives: {
				'i18n-html': {
					mounted: () => {
					},
					updated: () => {
					}
				}
			}
		}
	} );

	const messageHeader = wrapper.find( '.ext-personal-dashboard-recent-activity-header' );
	expect( messageHeader.exists() ).toBeTruthy();

	const dismissButton = wrapper.find( '.cdx-message__dismiss-button' );
	await dismissButton.trigger( 'click' );

	expect( mw.user.options.get( 'personaldashboard-risky-articles-info', 1 ), 0 );
} );

test( 'shows header message by default on non-mobile', () => {
	mw.user.options.set( 'personaldashboard-risky-articles-info', 1 );
	mw.config.set( { wgMFMode: null } );
	loading.value = false;

	mount( RecentActivity, {
		global: {
			directives: {
				'i18n-html': {
					mounted: () => {
					},
					updated: () => {
					}
				}
			}
		}
	} );

	const messageHeader = document.querySelector( '.ext-personal-dashboard-recent-activity-header' );
	expect( messageHeader ).not.toBeNull();
} );

test( 'does not show header message by default on mobile summary rendermode', () => {
	mw.user.options.set( 'personaldashboard-risky-articles-info', 1 );
	mw.config.set( { wgMFMode: true } );
	loading.value = false;

	const wrapper = mount( RecentActivity, {
		props: {
			rendermode: 'mobile-summary'
		},
		global: {
			directives: {
				'i18n-html': {
					mounted: () => {
					},
					updated: () => {
					}
				}
			}
		}
	} );
	expect( wrapper.find( '.ext-personal-dashboard-recent-activity-header' ).exists() ).toBeFalsy();
} );

test( 'does not show header message if preference is off', async () => {
	mw.config.set( { wgMFMode: true } );
	mw.user.options.set( 'personaldashboard-risky-articles-info', 0 );
	loading.value = false;
	const wrapper = mount( RecentActivity, {
		global: {
			directives: {
				'i18n-html': {
					mounted: () => {
					},
					updated: () => {
					}
				}
			}
		}
	} );
	expect( wrapper.find( '.ext-personal-dashboard-recent-activity-header' ).exists() ).toBeFalsy();
} );
