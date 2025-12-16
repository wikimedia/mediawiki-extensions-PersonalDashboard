import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import InfoPopover from '@resources/ext.personalDashboard.riskyArticleEdits/components/InfoPopover.vue';

test( 'mount component', () => {
	const wrapper = mount( InfoPopover );

	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'places popover correctly on mobile', () => {
	mw.config.set( {
		skin: 'minerva'
	} );
	const wrapper = mount( InfoPopover );

	expect( wrapper.vm.popoverPlacement ).toBe( 'left-end' );
} );

test( 'places popover correctly on non-mobile skin', () => {
	mw.config.set( {
		skin: 'vector'
	} );
	const wrapper = mount( InfoPopover );

	expect( wrapper.vm.popoverPlacement ).toBe( 'bottom' );
} );
