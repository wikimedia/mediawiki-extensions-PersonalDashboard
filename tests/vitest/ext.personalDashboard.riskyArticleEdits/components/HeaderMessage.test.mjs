import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HeaderMessage from '/resources/ext.personalDashboard.riskyArticleEdits/components/HeaderMessage.vue';
import { CdxPopover } from '@wikimedia/codex';

test( 'mount component', async () => {
	const wrapper = mount( HeaderMessage );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'removes message when clicking dismiss', async () => {
	const wrapper = mount( HeaderMessage );

	const dismissButton = wrapper.find( '.cdx-message__dismiss-button' );
	await dismissButton.trigger( 'click' );
	expect( wrapper.emitted().dismissed.length ).toStrictEqual( 1 );
} );

test( 'does not remove message if no click on the dismiss button', async () => {
	const wrapper = mount( HeaderMessage );
	expect( wrapper.emitted().dismissed ).toBeUndefined();
} );

test( 'closes popover when clicking on primary action button', async () => {
	const wrapper = mount( HeaderMessage );

	const infoLink = wrapper.find( '.personal-dashboard-review-changes__message__link' );
	await infoLink.trigger( 'click' );
	expect( wrapper.find( '.personal-dashboard-review-changes__message__popover' ).exists() ).toStrictEqual( true );

	const dismissPopoverButton = wrapper.find( '.cdx-popover__footer__primary-action' );
	await dismissPopoverButton.trigger( 'click' );
	expect( wrapper.find( '.personal-dashboard-review-changes__message__popover' ).exists() ).toStrictEqual( false );
} );

test( 'places popover right when it is mobile to prevent overflow', async () => {
	const wrapper = mount( HeaderMessage, {
		props: {
			isMobile: true
		}
	} );

	const infoLink = wrapper.find( '.personal-dashboard-review-changes__message__link' );
	await infoLink.trigger( 'click' );
	expect( wrapper.find( '.personal-dashboard-review-changes__message__popover' ).exists() ).toStrictEqual( true );

	const infoMessage = wrapper.findComponent( CdxPopover );
	expect( infoMessage.vm.placement ).toStrictEqual( 'right' );
} );

test( 'places popover on bottom when it is not mobile', async () => {
	const wrapper = mount( HeaderMessage );

	const infoLink = wrapper.find( '.personal-dashboard-review-changes__message__link' );
	await infoLink.trigger( 'click' );
	expect( wrapper.find( '.personal-dashboard-review-changes__message__popover' ).exists() ).toStrictEqual( true );

	const infoMessage = wrapper.findComponent( CdxPopover );
	expect( infoMessage.vm.placement ).toStrictEqual( 'bottom' );
} );
