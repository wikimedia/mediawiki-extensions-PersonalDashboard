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
	props: [ 'detail', 'focused', 'isNarrow', 'active' ],
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
				active: params.active
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
