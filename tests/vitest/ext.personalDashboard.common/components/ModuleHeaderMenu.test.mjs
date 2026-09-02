import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ModuleHeaderMenu from '/resources/ext.personalDashboard.common/components/ModuleHeaderMenu.vue';

const menuItems = [ { value: 'personalization', label: 'Personalization' } ];
const footerItem = { value: 'about', label: 'About Review changes' };

function mountMenu( slots = {}, global = {} ) {
	return mount( ModuleHeaderMenu, {
		props: { menuItems, footerItem, buttonLabel: 'More options for Review changes' },
		slots,
		global
	} );
}

test( 'names the icon-only button, which would otherwise announce nothing', () => {
	const wrapper = mountMenu();

	expect( wrapper.get( 'button' ).attributes( 'aria-label' ) )
		.toBe( 'More options for Review changes' );
} );

// A button with no type attribute submits. The dashboard header can sit inside
// a form, and CdxMenuButton sets no type of its own.
test( 'declares the button a button, so it never submits a form around it', () => {
	expect( mountMenu().get( 'button' ).attributes( 'type' ) ).toBe( 'button' );
} );

// The menu is z-index 50 and CdxDialog's backdrop is 400, and a teleported
// menu lands beside the dialog in the same stacking context. It would then open
// behind the dialog wherever the dialog stands in for a card, which is every
// narrow focused view. Ours must stay put whatever the app asks for.
test( 'keeps the menu in place even where the app teleports menus', async () => {
	const wrapper = mountMenu( {}, { provide: { CdxTeleportMenus: true } } );

	await wrapper.get( 'button' ).trigger( 'click' );

	expect( wrapper.get( 'teleport-stub' ).attributes( 'disabled' ) ).toBe( 'true' );
} );

test( 'renders every item the caller passes, the footer one last', async () => {
	const wrapper = mountMenu();

	await wrapper.get( 'button' ).trigger( 'click' );

	expect( wrapper.findAll( '.cdx-menu-item' ).map( ( item ) => item.text() ) )
		.toStrictEqual( [ 'Personalization', 'About Review changes' ] );
} );

// Codex's own footer prop reserves room for the item by measuring it before the
// menu has a width, which leaves an empty row on the first open (T433725). The
// item goes in as an ordinary one and a style rule draws the divider, so assert
// both halves of that: Codex must not be told there is a footer, and the class
// our rule hangs off must be there.
test( 'marks the footer with a class of its own, not with Codex\'s footer prop', async () => {
	const wrapper = mountMenu();

	await wrapper.get( 'button' ).trigger( 'click' );

	expect( wrapper.findComponent( { name: 'CdxMenuButton' } ).props( 'footer' ) )
		.toBeNull();
	expect( wrapper.find( '.cdx-menu' ).classes() )
		.not.toContain( 'cdx-menu--has-footer' );
	expect( wrapper.findComponent( { name: 'CdxMenuButton' } ).classes() )
		.toContain( 'personal-dashboard-module-menu__button--has-footer' );
} );

test( 'marks no footer where the caller passes none', () => {
	const wrapper = mount( ModuleHeaderMenu, {
		props: { menuItems, buttonLabel: 'More options' }
	} );

	expect( wrapper.findComponent( { name: 'CdxMenuButton' } ).classes() )
		.not.toContain( 'personal-dashboard-module-menu__button--has-footer' );
} );

test( 'reports the chosen item and keeps no selection', async () => {
	const wrapper = mountMenu();

	await wrapper.get( 'button' ).trigger( 'click' );
	// The footer item, which reports itself the same way an ordinary one does.
	await wrapper.findAll( '.cdx-menu-item' )[ 1 ].trigger( 'click' );

	expect( wrapper.emitted( 'select' ) ).toStrictEqual( [ [ 'about' ] ] );
	// A held selection makes the menu reopen with that item marked as chosen,
	// which reads as state the menu does not have: its items are actions.
	expect( wrapper.findComponent( { name: 'CdxMenuButton' } ).props( 'selected' ) )
		.toBeNull();
} );

test( 'ignores the null the menu reports when it clears itself', async () => {
	const wrapper = mountMenu();

	wrapper.findComponent( { name: 'CdxMenuButton' } ).vm.$emit( 'update:selected', null );
	await wrapper.vm.$nextTick();

	expect( wrapper.emitted( 'select' ) ).toBeUndefined();
} );

test( 'hands the button to the slot, since a popover cannot anchor without it', () => {
	let anchor = 'never called';
	mountMenu( {
		default: ( params ) => {
			anchor = params.anchor;
			return null;
		}
	} );

	expect( anchor ).not.toBe( 'never called' );
} );
