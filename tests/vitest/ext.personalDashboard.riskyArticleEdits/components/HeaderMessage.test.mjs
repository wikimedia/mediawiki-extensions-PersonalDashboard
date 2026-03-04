import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HeaderMessage from '@resources/ext.personalDashboard.riskyArticleEdits/components/HeaderMessage.vue';

test( 'mount component', async () => {
	const wrapper = mount( HeaderMessage, {
		global: {
			directives: {
				'i18n-html': {
					mounted: () => {
					},
					updated: () => {
					}
				}
			}
		}
	} );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'removes message when clicking dismiss', async () => {
	const wrapper = mount( HeaderMessage, {
		global: {
			directives: {
				'i18n-html': {
					mounted: () => {
					},
					updated: () => {
					}
				}
			}
		}
	} );
	const dismissButton = wrapper.find( '.cdx-message__dismiss-button' );
	await dismissButton.trigger( 'click' );
	expect( wrapper.emitted().dismissed ).toBeTruthy();
	expect( wrapper.emitted().dismissed.length ).toBe( 1 );
} );

test( 'does not remove message if no click on the dismiss button', async () => {
	const wrapper = mount( HeaderMessage, {
		global: {
			directives: {
				'i18n-html': {
					mounted: () => {
					},
					updated: () => {
					}
				}
			}
		}
	} );
	expect( wrapper.emitted().dismissed ).toBeFalsy();
} );

test( 'closes popover when clicking on primary action button', async () => {
	const wrapper = mount( HeaderMessage, {
		global: {
			directives: {
				'i18n-html': {
					mounted: () => {
					},
					updated: () => {
					}
				}
			}
		}
	} );

	const infoLink = wrapper.find( '.ext-personal-dashboard-recent-activity-header-link' );
	await infoLink.trigger( 'click' );
	expect( wrapper.find( '.ext-personal-dashboard-recent-activity-header-popover' ).exists() ).toBeTruthy();
	const dismissPopoverButton = wrapper.find( '.cdx-popover__footer__primary-action' );
	await dismissPopoverButton.trigger( 'click' );
	expect( wrapper.find( '.ext-personal-dashboard-recent-activity-header-popover' ).exists() ).toBeFalsy();
} );
