import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '../../../resources/ext.personalDashboard.contentPolicies/App.vue';

test( 'mount component', () => {
	const wrapper = mount( App );
	expect( wrapper.element ).toMatchSnapshot();
} );
