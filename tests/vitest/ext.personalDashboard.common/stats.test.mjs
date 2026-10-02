import { afterEach, test, expect, vi } from 'vitest';
import { recordTiming } from '/resources/ext.personalDashboard.common/stats.js';

afterEach( () => {
	vi.restoreAllMocks();
	mw.config.set( 'wgPersonalDashboardStats', null );
} );

test( 'records nothing when the page sets no stats config', () => {
	const track = mw.track = vi.fn();

	recordTiming( 'ext.personalDashboard.reviewChanges', 'feed_fetch', 12 );

	expect( track ).not.toHaveBeenCalled();
} );

test( 'records under the component, with the server labels, normalised', () => {
	const track = mw.track = vi.fn();
	mw.config.set( 'wgPersonalDashboardStats', {
		component: 'PersonalDashboard',
		labels: {
			wiki: 'enwiki',
			// eslint-disable-next-line camelcase
			special_page: 'PersonalDashboard',
			// eslint-disable-next-line camelcase
			module_group: 'default',
			platform: 'desktop'
		}
	} );

	recordTiming( 'ext.personalDashboard.reviewChanges', 'feed_fetch', 12 );

	expect( track ).toHaveBeenCalledWith(
		'stats.mediawiki_PersonalDashboard_dashboard_client_seconds',
		12,
		{
			module: 'ext_personalDashboard_reviewChanges',
			operation: 'feed_fetch',
			wiki: 'enwiki',
			// eslint-disable-next-line camelcase
			special_page: 'PersonalDashboard',
			// eslint-disable-next-line camelcase
			module_group: 'default',
			platform: 'desktop'
		}
	);
} );

test.each( [
	[ 'foo__bar', 'foo_bar' ],
	[ '-x', 'x' ],
	[ 'a.b-c', 'a_b_c' ]
] )( 'normalises %s to %s, as StatsUtils::normalizeString() does', ( value, expected ) => {
	const track = mw.track = vi.fn();
	mw.config.set( 'wgPersonalDashboardStats', {
		component: 'PersonalDashboard',
		labels: { wiki: value }
	} );

	recordTiming( 'm', 'feed_fetch', 1 );

	expect( track.mock.calls[ 0 ][ 2 ].wiki ).toBe( expected );
} );
