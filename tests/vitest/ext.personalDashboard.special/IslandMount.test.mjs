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
	props: [ 'detail' ],
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
			default: ( params ) => h( stub, { detail: params.detail } )
		}
	} );
	// Let Suspense resolve the child.
	await wrapper.vm.$nextTick();
	return wrapper;
}

function childDetail( wrapper ) {
	return wrapper.findComponent( stub ).props( 'detail' );
}

beforeEach( () => {
	isNarrow.value = false;
} );

afterEach( () => {
	vi.restoreAllMocks();
} );

test( 'narrow and not active or focused renders a compact card', async () => {
	isNarrow.value = true;
	const wrapper = await mountIsland();
	expect( childDetail( wrapper ) ).toBe( 'compact' );
} );

test( 'wide and not active or focused renders a full card', async () => {
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
	const wrapper = await mountIsland( { active: true } );
	expect( childDetail( wrapper ) ).toBe( 'full' );
} );
