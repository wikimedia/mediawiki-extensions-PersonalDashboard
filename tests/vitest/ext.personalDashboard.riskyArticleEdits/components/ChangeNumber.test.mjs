import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ChangeNumber from '@resources/ext.personalDashboard.riskyArticleEdits/components/ChangeNumber.vue';

test( 'mount component', () => {
	const wrapper = mount( ChangeNumber, {
		props: {
			newlen: 10,
			oldlen: 5,
		},
	} );

	expect( wrapper.element ).toMatchSnapshot();
} );
