import { vi, beforeEach, afterEach, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref, h } from 'vue';

const isNarrow = ref( false );
vi.mock( '/resources/ext.personalDashboard.special/useViewport.js', () => ( {
	useViewport: () => ( { isNarrow } )
} ) );

import IslandMount from '/resources/ext.personalDashboard.special/IslandMount.vue';

const stub = {
	name: 'IslandStub',
	props: [ 'detail', 'focused', 'isNarrow', 'active', 'headerTarget' ],
	template: '<div></div>'
};

async function mountIsland( props ) {
	const wrapper = mount( IslandMount, {
		props: Object.assign( {
			name: 'ext.example.one'
		}, props ),
		slots: {
			// A bare component in slots drops the scoped-slot params, so forward
			// them by hand to assert what IslandMount passes to the island body.
			default: ( params ) => h( stub, {
				detail: params.detail,
				focused: params.focused,
				isNarrow: params.isNarrow,
				active: params.active,
				headerTarget: params.headerTarget
			} )
		}
	} );
	// Let Suspense resolve the child.
	await wrapper.vm.$nextTick();
	return wrapper;
}

function childDetail( wrapper ) {
	return wrapper.findComponent( stub ).props( 'detail' );
}

function childFocused( wrapper ) {
	return wrapper.findComponent( stub ).props( 'focused' );
}

function childIsNarrow( wrapper ) {
	return wrapper.findComponent( stub ).props( 'isNarrow' );
}

function childActive( wrapper ) {
	return wrapper.findComponent( stub ).props( 'active' );
}

function childHeaderTarget( wrapper ) {
	return wrapper.findComponent( stub ).props( 'headerTarget' );
}

// The server emits this slot only for a module that declares hasHeaderMenu().
function addHeaderSlot( name ) {
	return addSlot( 'pd-header-slot-' + name );
}

function addSlot( id ) {
	const slot = document.createElement( 'div' );
	slot.id = id;
	document.body.append( slot );
	return slot;
}

// Mirrors the real composition a menu-bearing module renders: a body, plus a
// nested teleport that carries the header affordance to the header slot.
const nestedStub = {
	name: 'NestedTeleportStub',
	props: [ 'headerTarget' ],
	template: `
		<div id="ISLAND_BODY"></div>
		<teleport v-if="headerTarget" :to="headerTarget">
			<span id="ISLAND_MENU"></span>
		</teleport>
	`
};

function parentOf( id ) {
	const el = document.getElementById( id );
	return el ? el.parentElement : null;
}

beforeEach( () => {
	isNarrow.value = false;
} );

afterEach( () => {
	vi.restoreAllMocks();
} );

test( 'a narrow card and not active or focused renders a compact card', async () => {
	isNarrow.value = true;
	const wrapper = await mountIsland();
	expect( childDetail( wrapper ) ).toBe( 'compact' );
} );

test( 'a wide card and not active or focused renders a full card', async () => {
	isNarrow.value = false;
	const wrapper = await mountIsland();
	expect( childDetail( wrapper ) ).toBe( 'full' );
} );

test( 'a focused island is full even when narrow', async () => {
	isNarrow.value = true;
	const wrapper = await mountIsland( { focused: true } );
	expect( childDetail( wrapper ) ).toBe( 'full' );
} );

test( 'an active island is full even when narrow', async () => {
	isNarrow.value = true;
	const wrapper = await mountIsland( { activeTarget: '#personal-dashboard-teleport' } );
	expect( childDetail( wrapper ) ).toBe( 'full' );
} );

test( 'passes focused through to the island body', async () => {
	isNarrow.value = true;
	expect( childFocused( await mountIsland( { focused: true } ) ) ).toBe( true );
	expect( childFocused( await mountIsland() ) ).toBe( false );
} );

test( 'passes the raw isNarrow value through to the island body', async () => {
	isNarrow.value = true;
	expect( childIsNarrow( await mountIsland( { activeTarget: '#personal-dashboard-teleport' } ) ) ).toBe( true );

	isNarrow.value = false;
	expect( childIsNarrow( await mountIsland() ) ).toBe( false );
} );

test( 'passes active through to the island body', async () => {
	expect( childActive( await mountIsland( { activeTarget: '#personal-dashboard-teleport' } ) ) ).toBe( true );
	expect( childActive( await mountIsland() ) ).toBe( false );
} );

test( 'a different active target still reports active, not just a truthy one', async () => {
	expect( childActive( await mountIsland( { activeTarget: '#personal-dashboard-focused-frame-teleport' } ) ) )
		.toBe( true );
} );

test( 'no header target when the frame emits no header slot', async () => {
	expect( childHeaderTarget( await mountIsland() ) ).toBeNull();
} );

test( 'escapes the dotted module name in the header slot selector', async () => {
	const slot = addHeaderSlot( 'ext.example.one' );

	// An unescaped '#pd-header-slot-ext.example.one' reads the dots as class
	// selectors, and the teleport target is never found.
	expect( childHeaderTarget( await mountIsland() ) )
		.toBe( '#pd-header-slot-ext\\.example\\.one' );
	expect( document.querySelector( childHeaderTarget( await mountIsland() ) ) )
		.toBe( slot );

	slot.remove();
} );

test( 'a stand-in moves both halves of the card, each to its own slot', async () => {
	const bodySlot = addSlot( 'pd-slot-ext.example.one' );
	const headerSlot = addHeaderSlot( 'ext.example.one' );
	const dialogSlot = addSlot( 'personal-dashboard-teleport' );
	const dialogHeaderSlot = addSlot( 'personal-dashboard-header-teleport' );

	const wrapper = mount( IslandMount, {
		props: { name: 'ext.example.one' },
		// Teleport is stubbed extension-wide, but this is the one assertion that
		// needs the real thing: the whole header-slot design rests on a nested
		// teleport resolving its own target, not inheriting its parent's.
		global: { stubs: { teleport: false } },
		slots: {
			default: ( params ) => h( nestedStub, { headerTarget: params.headerTarget } )
		}
	} );
	await wrapper.vm.$nextTick();

	expect( parentOf( 'ISLAND_BODY' ) ).toBe( bodySlot );
	expect( parentOf( 'ISLAND_MENU' ) ).toBe( headerSlot );

	// The dialog stands in here; the in-page frame is the same mechanism with
	// the other pair of target ids.
	await wrapper.setProps( {
		activeTarget: '#personal-dashboard-teleport',
		activeHeaderTarget: '#personal-dashboard-header-teleport'
	} );
	// IslandMount defers each move a tick so the stand-in's targets exist.
	await wrapper.vm.$nextTick();
	await wrapper.vm.$nextTick();

	expect( parentOf( 'ISLAND_BODY' ) ).toBe( dialogSlot );
	expect( parentOf( 'ISLAND_MENU' ) ).toBe( dialogHeaderSlot );

	// Closing the stand-in returns both to the card.
	await wrapper.setProps( { activeTarget: '', activeHeaderTarget: '' } );
	await wrapper.vm.$nextTick();
	await wrapper.vm.$nextTick();

	expect( parentOf( 'ISLAND_BODY' ) ).toBe( bodySlot );
	expect( parentOf( 'ISLAND_MENU' ) ).toBe( headerSlot );

	wrapper.unmount();
	[ bodySlot, headerSlot, dialogSlot, dialogHeaderSlot ].forEach( ( el ) => el.remove() );
} );

test( 'a module that declared no header menu gets no target from a stand-in', async () => {
	// A stand-in mints its header slot for whichever module it shows, so only
	// the card's own slot says whether this module asked for a menu at all.
	const wrapper = await mountIsland( {
		activeHeaderTarget: '#personal-dashboard-header-teleport'
	} );

	expect( childHeaderTarget( wrapper ) ).toBeNull();
} );

test( 'an active header target wins over the card header slot', async () => {
	const slot = addHeaderSlot( 'ext.example.one' );

	const wrapper = await mountIsland();
	expect( childHeaderTarget( wrapper ) ).toBe( '#pd-header-slot-ext\\.example\\.one' );

	await wrapper.setProps( { activeHeaderTarget: '#personal-dashboard-header-teleport' } );
	// Deferred a tick: the stand-in's header slot does not exist until it has
	// rendered, and a teleport at a missing target warns and drops its content.
	expect( childHeaderTarget( wrapper ) ).toBe( '#pd-header-slot-ext\\.example\\.one' );
	await wrapper.vm.$nextTick();
	await wrapper.vm.$nextTick();
	expect( childHeaderTarget( wrapper ) ).toBe( '#personal-dashboard-header-teleport' );

	slot.remove();
} );
