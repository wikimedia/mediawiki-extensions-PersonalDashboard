import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '@resources/ext.personalDashboard.onboarding/App.vue';

test( 'mount component', () => {
	mw.user.options.set( 'personaldashboard-onboarding', 1 );

	const wrapper = mount( App );
	expect( wrapper.element ).toMatchSnapshot();
} );
