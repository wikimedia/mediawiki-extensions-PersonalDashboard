import { afterEach, beforeEach, test, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import PersonalizationPanel from '/resources/ext.personalDashboard.reviewChanges/components/PersonalizationPanel.vue';

const PERSONALIZE_OPTION_NAME = 'moderatortoolkit-reviewchanges-personalize';

let saveOption;

// The real panel teleports its content out as a dialog. Render it in place.
function mountPanel( props = {} ) {
	return mount( PersonalizationPanel, {
		props,
		global: { stubs: { ModulePanel: { template: '<div><slot></slot></div>' } } }
	} );
}

function getToggle( wrapper ) {
	return wrapper.findComponent( { name: 'CdxToggleSwitch' } );
}

beforeEach( () => {
	mw.user.options.set( PERSONALIZE_OPTION_NAME, '1' );
	saveOption = vi.fn().mockResolvedValue( {} );
	mw.Api.prototype.saveOption = saveOption;
} );

afterEach( () => {
	delete mw.Api.prototype.saveOption;
	vi.restoreAllMocks();
} );

// The default comes from the server as a boolean, a saved value as a string.
test.each( [
	[ true, true ],
	[ '1', true ],
	[ '0', false ]
] )( 'starts from the saved option %s', ( value, expected ) => {
	mw.user.options.set( PERSONALIZE_OPTION_NAME, value );

	expect( getToggle( mountPanel() ).props( 'modelValue' ) ).toBe( expected );
} );

test( 'saves the option, then asks for a new feed', async () => {
	const wrapper = mountPanel();

	getToggle( wrapper ).vm.$emit( 'update:modelValue', false );
	await flushPromises();

	expect( saveOption ).toHaveBeenCalledWith( PERSONALIZE_OPTION_NAME, '0' );
	expect( mw.user.options.get( PERSONALIZE_OPTION_NAME ) ).toBe( '0' );
	expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
	expect( getToggle( wrapper ).props( 'modelValue' ) ).toBe( false );
} );

test( 'disables the toggle while the option saves', async () => {
	saveOption.mockReturnValue( new Promise( () => {} ) );
	const wrapper = mountPanel();

	getToggle( wrapper ).vm.$emit( 'update:modelValue', false );
	await flushPromises();

	expect( getToggle( wrapper ).props( 'disabled' ) ).toBe( true );
} );

test( 'disables the toggle when the parent asks', () => {
	expect( getToggle( mountPanel( { disabled: true } ) ).props( 'disabled' ) ).toBe( true );
} );

test( 'goes back to the saved setting when the save fails', async () => {
	vi.spyOn( mw.log, 'error' ).mockImplementation( () => {} );
	saveOption.mockRejectedValue( 'http' );
	const wrapper = mountPanel();

	getToggle( wrapper ).vm.$emit( 'update:modelValue', false );
	await flushPromises();

	expect( mw.user.options.get( PERSONALIZE_OPTION_NAME ) ).toBe( '1' );
	expect( wrapper.emitted( 'change' ) ).toBeUndefined();
	expect( getToggle( wrapper ).props( 'modelValue' ) ).toBe( true );
	expect( getToggle( wrapper ).props( 'disabled' ) ).toBe( false );
	expect( mw.log.error ).toHaveBeenCalledWith( 'http' );
} );
