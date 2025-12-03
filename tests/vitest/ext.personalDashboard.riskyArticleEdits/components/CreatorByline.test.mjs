import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CreatorByline from '@resources/ext.personalDashboard.riskyArticleEdits/components/CreatorByline.vue';

test( 'mount component', () => {
	const wrapper = mount( CreatorByline, {
		props: {
			creatorName: 'Name',
			creatorIsTempAccount: false
		}
	} );

	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'uses temp user link class if user is temp', () => {
	const wrapper = mount( CreatorByline, {
		props: {
			creatorName: 'Name',
			creatorIsTempAccount: true
		}
	} );

	expect( wrapper.vm.userPageClass ).toBe( 'mw-userlink mw-tempuserlink' );
} );

test( 'does not use temp user link class if user is not temp', () => {
	const wrapper = mount( CreatorByline, {
		props: {
			creatorName: 'Name',
			creatorIsTempAccount: false
		}
	} );

	expect( wrapper.vm.userPageClass ).toBe( 'mw-userlink' );
} );
