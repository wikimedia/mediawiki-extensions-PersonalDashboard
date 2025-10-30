import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import RecentActivity from '@resources/ext.personalDashboard.riskyArticleEdits/components/RecentActivity.vue';

test( 'mount component', () => {
	const wrapper = mount( RecentActivity );

	expect( wrapper.element ).toMatchSnapshot();
} );
