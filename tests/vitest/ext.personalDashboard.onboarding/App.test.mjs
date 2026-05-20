import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '/resources/ext.personalDashboard.onboarding/App.vue';

test( 'mount component', () => {
	mw.Api.mock( ( params, options ) => {
		if ( options.type === 'POST' &&
			params.action === 'options' &&
			( params.optionname === 'personaldashboard-visited' ||
			params.optionname === 'personaldashboard-eligible' ) &&
			params.optionvalue === '1' ) {
			return {};
		}
	} );
	const wrapper = mount( App );
	expect( wrapper.element ).toMatchSnapshot();
} );
