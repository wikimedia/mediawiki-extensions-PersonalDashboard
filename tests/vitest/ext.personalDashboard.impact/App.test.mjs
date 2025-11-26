import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '@resources/ext.personalDashboard.impact/App.vue';

test( 'mount component', () => {
	mw.config.set( {
		wgPersonalDashboardImpactThanksCount: 1234,
		wgPersonalDashboardImpactReviewCount: 4321
	} );

	const wrapper = mount( App );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'counters fallback to 0 if no config values', () => {
	mw.config.delete(
		'wgPersonalDashboardImpactThanksCount',
		'wgPersonalDashboardImpactReviewCount'
	);

	const wrapper = mount( App );
	expect( wrapper.element ).toMatchSnapshot();
} );
