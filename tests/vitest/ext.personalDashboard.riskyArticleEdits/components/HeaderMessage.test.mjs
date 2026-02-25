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
