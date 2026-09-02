import { afterEach, test, expect } from 'vitest';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import ModulePanel from '/resources/ext.personalDashboard.common/components/ModulePanel.vue';

// CdxDialog walks the DOM from its own backdrop when it opens, so it has to
// still be mounted when that runs. Open it after mount and let the tick pass.
async function mountPanel( props = {} ) {
	const wrapper = mount( ModulePanel, {
		props: Object.assign( { open: false, title: 'About Review changes' }, props ),
		slots: { default: '<p id="PANEL_BODY">Body copy</p>' }
	} );
	await wrapper.setProps( { open: true } );
	await wrapper.vm.$nextTick();
	return wrapper;
}

// A Codex dialog and popover both attach document-level listeners while open.
enableAutoUnmount( afterEach );

test( 'a wide viewport gets a dialog', async () => {
	const wrapper = await mountPanel();

	expect( wrapper.findComponent( { name: 'CdxDialog' } ).exists() ).toBe( true );
	expect( wrapper.findComponent( { name: 'CdxPopover' } ).exists() ).toBe( false );
} );

test( 'a narrow viewport gets a bottom sheet instead', async () => {
	const wrapper = await mountPanel( { isNarrow: true } );

	const popover = wrapper.findComponent( { name: 'CdxPopover' } );
	expect( popover.exists() ).toBe( true );
	// Without this the panel floats beside the anchor on a phone, which is what
	// the design replaced.
	expect( popover.props( 'useBottomSheet' ) ).toBe( true );
	expect( wrapper.findComponent( { name: 'CdxDialog' } ).exists() ).toBe( false );
} );

test.each( [ [ false ], [ true ] ] )( 'carries the title and the body when narrow is %s', async ( isNarrow ) => {
	const wrapper = await mountPanel( { isNarrow } );

	expect( wrapper.text() ).toContain( 'About Review changes' );
	expect( wrapper.find( '#PANEL_BODY' ).exists() ).toBe( true );
} );

test( 'crossing the breakpoint while open swaps the presentation, not the state', async () => {
	const wrapper = await mountPanel();

	await wrapper.setProps( { isNarrow: true } );

	expect( wrapper.findComponent( { name: 'CdxPopover' } ).props( 'open' ) ).toBe( true );
	expect( wrapper.find( '#PANEL_BODY' ).exists() ).toBe( true );
} );

test.each( [
	[ 'CdxDialog', false ],
	[ 'CdxPopover', true ]
] )( 'passes a close from %s up to the caller, which owns the state', async ( name, isNarrow ) => {
	const wrapper = await mountPanel( { isNarrow } );

	wrapper.findComponent( { name } ).vm.$emit( 'update:open', false );
	await wrapper.vm.$nextTick();

	expect( wrapper.emitted( 'update:open' ) ).toStrictEqual( [ [ false ] ] );
	// The caller has not answered yet, so the panel is still showing: it holds
	// no open state of its own.
	expect( wrapper.props( 'open' ) ).toBe( true );
} );
