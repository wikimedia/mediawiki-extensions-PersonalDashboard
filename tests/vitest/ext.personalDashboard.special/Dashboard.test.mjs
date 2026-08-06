import { vi, afterEach, beforeEach, test, expect } from 'vitest';
import { mount, enableAutoUnmount } from '@vue/test-utils';
import { ref } from 'vue';

const isNarrow = ref( false );
vi.mock( '/resources/ext.personalDashboard.special/useViewport.js', () => ( {
	useViewport: () => ( { isNarrow } )
} ) );

import Dashboard from '/resources/ext.personalDashboard.special/Dashboard.vue';
import FocusedFrame from '/resources/ext.personalDashboard.special/FocusedFrame.vue';
import IslandMount from '/resources/ext.personalDashboard.special/IslandMount.vue';
import ModuleDialog from '/resources/ext.personalDashboard.special/ModuleDialog.vue';
import { DIALOG_TARGET_ID, FRAME_TARGET_ID } from '/resources/ext.personalDashboard.special/teleportTargets.js';

const islands = [
	{ name: 'ext.example.one', header: 'One', component: {} },
	{ name: 'ext.example.two', header: 'Two', component: {} }
];

function mountDashboard( {
	module = null, hash = '', focusedModule = null, push = () => {}, narrow = false
} = {} ) {
	isNarrow.value = narrow;
	return mount( Dashboard, {
		props: { islands, focusedModule },
		global: {
			mocks: {
				$route: { params: module ? { module } : {}, hash },
				$router: { push, resolve: () => ( { href: '/wiki/Special:PersonalDashboard' } ) }
			},
			stubs: {
				FocusedFrame: true,
				IslandMount: true,
				ModuleDialog: true
			}
		}
	} );
}

// A server-rendered card and its header link, inside the container the handler
// listens on. Returns the icon in the link, so a click starts below the anchor.
// Call it before mounting: the dashboard looks the container up as it mounts,
// just as it finds the server's own.
function renderCard( name ) {
	const container = document.createElement( 'div' );
	container.className = 'personal-dashboard-container';

	const card = document.createElement( 'div' );
	card.className = 'personal-dashboard-module';
	card.dataset.moduleName = name;

	const link = document.createElement( 'a' );
	link.className = 'personal-dashboard-module-header-container';
	link.href = '/wiki/Special:PersonalDashboard/' + name;

	const icon = document.createElement( 'div' );
	icon.className = 'personal-dashboard-module-header-forward-icon';

	link.append( icon );
	card.append( link );
	container.append( card );
	document.body.append( container );
	return icon;
}

function clickOn( element, init = {} ) {
	const event = new MouseEvent( 'click', Object.assign(
		{ bubbles: true, cancelable: true }, init
	) );
	element.dispatchEvent( event );
	return event;
}

// The click handler lives on the document, so drop each dashboard and its cards
// before the next test mounts one.
enableAutoUnmount( afterEach );
afterEach( () => {
	document.body.replaceChildren();
} );

beforeEach( () => {
	isNarrow.value = false;
} );

test( 'renders one mount per island at a narrow viewport', () => {
	const wrapper = mountDashboard( { narrow: true } );
	expect( wrapper.findAllComponents( IslandMount ) ).toHaveLength( islands.length );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'renders one mount per island at a wide viewport', () => {
	const wrapper = mountDashboard();
	expect( wrapper.findAllComponents( IslandMount ) ).toHaveLength( islands.length );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'the active module opens and titles the dialog', () => {
	const dialog = mountDashboard( { module: 'ext.example.two', narrow: true } ).findComponent( ModuleDialog );
	expect( dialog.props( 'open' ) ).toBe( true );
	expect( dialog.props( 'title' ) ).toBe( 'Two' );
} );

test( 'no active module leaves the dialog closed and untitled', () => {
	const dialog = mountDashboard( { narrow: true } ).findComponent( ModuleDialog );
	expect( dialog.props( 'open' ) ).toBe( false );
	expect( dialog.props( 'title' ) ).toBe( '' );
} );

test( 'a step hash leaves the module dialog shut for the module to own', () => {
	const dialog = mountDashboard( { module: 'ext.example.two', hash: '#a-step', narrow: true } )
		.findComponent( ModuleDialog );
	expect( dialog.props( 'open' ) ).toBe( false );
} );

test( 'a step hash suppresses both the dialog and the frame at a wide viewport', () => {
	const wrapper = mountDashboard( { module: 'ext.example.two', hash: '#a-step' } );
	expect( wrapper.findComponent( ModuleDialog ).props( 'open' ) ).toBe( false );
	expect( wrapper.findComponent( FocusedFrame ).exists() ).toBe( false );
} );

test( 'a focused island opens and titles the dialog on first render', () => {
	const dialog = mountDashboard( {
		module: 'ext.example.two',
		focusedModule: 'ext.example.two',
		narrow: true
	} ).findComponent( ModuleDialog );
	expect( dialog.props( 'open' ) ).toBe( true );
	expect( dialog.props( 'title' ) ).toBe( 'Two' );
} );

test( 'leaving a focused module closes the dialog behind it', () => {
	const dialog = mountDashboard( { focusedModule: 'ext.example.two', narrow: true } )
		.findComponent( ModuleDialog );
	expect( dialog.props( 'open' ) ).toBe( false );
} );

test( 'a focused render closes the dialog by link, a grouped one by button', () => {
	const focused = mountDashboard( {
		module: 'ext.example.two',
		focusedModule: 'ext.example.two',
		narrow: true
	} ).findComponent( ModuleDialog );
	expect( focused.props( 'backHref' ) ).toBe( '/wiki/Special:PersonalDashboard' );

	const grouped = mountDashboard( { module: 'ext.example.two', narrow: true } ).findComponent( ModuleDialog );
	expect( grouped.props( 'backHref' ) ).toBe( '' );
} );

test( 'a focused module with no matching island keeps its inline render', () => {
	const dialog = mountDashboard( {
		module: 'ext.example.serverRendered',
		focusedModule: 'ext.example.serverRendered',
		narrow: true
	} ).findComponent( ModuleDialog );
	expect( dialog.props( 'open' ) ).toBe( false );
} );

test( 'a wide soft nav opens the frame, not the dialog', () => {
	const wrapper = mountDashboard( { module: 'ext.example.two' } );
	// ModuleDialog stays mounted at every width (CdxDialog is designed to be,
	// closing via its own `open` prop rather than being torn out while open);
	// "not the dialog" means closed, not absent.
	expect( wrapper.findComponent( ModuleDialog ).props( 'open' ) ).toBe( false );
	const frame = wrapper.findComponent( FocusedFrame );
	expect( frame.exists() ).toBe( true );
	expect( frame.props( 'title' ) ).toBe( 'Two' );
} );

test( 'a narrow soft nav opens the dialog, not the frame', () => {
	const wrapper = mountDashboard( { module: 'ext.example.two', narrow: true } );
	expect( wrapper.findComponent( ModuleDialog ).props( 'open' ) ).toBe( true );
	expect( wrapper.findComponent( FocusedFrame ).exists() ).toBe( false );
} );

test( 'a wide cold load of the server-rendered focused page opens neither the dialog nor the frame', () => {
	// Pins the resolved design tension: the server's render of a focused subpage
	// is already the wide-viewport design, so nothing should open over it.
	const wrapper = mountDashboard( {
		module: 'ext.example.two',
		focusedModule: 'ext.example.two'
	} );
	expect( wrapper.findComponent( ModuleDialog ).props( 'open' ) ).toBe( false );
	expect( wrapper.findComponent( FocusedFrame ).exists() ).toBe( false );
} );

test( 'an island opened in the frame is focused and targets the frame teleport', () => {
	const wrapper = mountDashboard( { module: 'ext.example.two' } );
	const island = wrapper.findAllComponents( IslandMount )
		.find( ( component ) => component.props( 'name' ) === 'ext.example.two' );
	expect( island.props( 'focused' ) ).toBe( true );
	expect( island.props( 'activeTarget' ) ).toBe( '#' + FRAME_TARGET_ID );
} );

test( 'an island opened in the dialog is focused and targets the dialog teleport', () => {
	const wrapper = mountDashboard( { module: 'ext.example.two', narrow: true } );
	const island = wrapper.findAllComponents( IslandMount )
		.find( ( component ) => component.props( 'name' ) === 'ext.example.two' );
	expect( island.props( 'focused' ) ).toBe( true );
	expect( island.props( 'activeTarget' ) ).toBe( '#' + DIALOG_TARGET_ID );
} );

test( 'the active target differs between the dialog and the frame for the same module', () => {
	// Pins the teleport fix: a shared id here would leave <teleport> unable to
	// tell the dialog and the frame apart when the viewport crosses the
	// breakpoint with a module open, since its `to` prop would never change.
	const narrowTarget = mountDashboard( { module: 'ext.example.two', narrow: true } )
		.findAllComponents( IslandMount )
		.find( ( component ) => component.props( 'name' ) === 'ext.example.two' )
		.props( 'activeTarget' );
	const wideTarget = mountDashboard( { module: 'ext.example.two' } )
		.findAllComponents( IslandMount )
		.find( ( component ) => component.props( 'name' ) === 'ext.example.two' )
		.props( 'activeTarget' );

	expect( narrowTarget ).not.toBe( wideTarget );
} );

test( 'opening the frame marks the card container replaced, closing it unmarks it', () => {
	const container = document.createElement( 'div' );
	container.className = 'personal-dashboard-container';
	document.body.append( container );

	const wrapper = mountDashboard( { module: 'ext.example.two' } );
	expect( container.classList.contains( 'personal-dashboard-container__replaced' ) ).toBe( true );

	wrapper.unmount();
	expect( container.classList.contains( 'personal-dashboard-container__replaced' ) ).toBe( false );
} );

test( 'a mount with no soft nav active never marks the card container replaced', () => {
	// Covers the watcher's toggle-off branch (Dashboard.vue's frameName watcher
	// runs immediately on mount, including this off case) separately from the
	// unmount cleanup the previous test exercises -- $route is a static mock
	// here, so a live on-then-off transition within one mount isn't reachable.
	const container = document.createElement( 'div' );
	container.className = 'personal-dashboard-container';
	document.body.append( container );

	mountDashboard();
	expect( container.classList.contains( 'personal-dashboard-container__replaced' ) ).toBe( false );
} );

test( "the frame's back emit pushes the dashboard root", () => {
	const push = vi.fn();
	const wrapper = mountDashboard( { module: 'ext.example.two', push } );
	wrapper.findComponent( FocusedFrame ).vm.$emit( 'back' );
	expect( push ).toHaveBeenCalledWith( '/' );
} );

test( 'a card link opens its island in the dialog rather than loading a page', () => {
	const push = vi.fn();
	const icon = renderCard( 'ext.example.two' );
	mountDashboard( { push } );

	const event = clickOn( icon );

	expect( push ).toHaveBeenCalledWith( '/ext.example.two' );
	expect( event.defaultPrevented ).toBe( true );
} );

test( 'a card link for a module the client does not own is left to navigate', () => {
	const push = vi.fn();
	const icon = renderCard( 'ext.example.serverRendered' );
	mountDashboard( { push } );

	const event = clickOn( icon );

	expect( push ).not.toHaveBeenCalled();
	expect( event.defaultPrevented ).toBe( false );
} );

test( 'a modified click on a card link is left to navigate', () => {
	const push = vi.fn();
	const icon = renderCard( 'ext.example.two' );
	mountDashboard( { push } );

	const event = clickOn( icon, { metaKey: true } );

	expect( push ).not.toHaveBeenCalled();
	expect( event.defaultPrevented ).toBe( false );
} );
