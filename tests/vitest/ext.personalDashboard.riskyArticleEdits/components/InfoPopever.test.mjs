import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import InfoPopover from '@resources/ext.personalDashboard.riskyArticleEdits/components/InfoPopover.vue';

test( 'mount component', () => {
	const wrapper = mount( InfoPopover );

	expect( wrapper.element ).toMatchSnapshot();
} );
