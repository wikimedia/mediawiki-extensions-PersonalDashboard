import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ExamplesDialog from '@resources/ext.personalDashboard.policiesGuidelines/components/ExamplesDialog.vue';

test( 'examples dialog prefixes i18n keys', () => {
	const wrapper = mount( ExamplesDialog, {
		props: {
			prefix: 'foo',
			pages: []
		}
	} );

	expect( wrapper.vm.msgPrefix ).toBe( 'personal-dashboard-policies-guidelines-foo' );
} );
