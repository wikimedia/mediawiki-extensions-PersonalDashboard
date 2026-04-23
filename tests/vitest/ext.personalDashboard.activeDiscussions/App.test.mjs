import { vi, beforeEach, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';

vi.mock( '/resources/ext.personalDashboard.activeDiscussions/composables/useFetchActiveDiscussionsResult.js', () => {
	const mock = {
		activeDiscussionsResult: ref( null ),
		loading: ref( true ),
		error: ref( null ),
		fetchActiveDiscussions: vi.fn()
	};
	return { default: () => mock };
} );

import useFetchActiveDiscussionsResult from '/resources/ext.personalDashboard.activeDiscussions/composables/useFetchActiveDiscussionsResult.js';
const {
	activeDiscussionsResult,
	loading,
	error,
	fetchActiveDiscussions
} = useFetchActiveDiscussionsResult();

import ActiveDiscussions from '/resources/ext.personalDashboard.activeDiscussions/App.vue';

beforeEach( () => {
	activeDiscussionsResult.value = null;
	loading.value = true;
	error.value = null;
	fetchActiveDiscussions.mockReset();
} );

test( 'mount component', () => {
	const card = document.createElement( 'div' );
	card.className = 'personal-dashboard-module-activeDiscussions';
	document.body.appendChild( card );

	const header = document.createElement( 'div' );
	header.className = 'personal-dashboard-module-header';
	card.appendChild( header );

	const wrapper = mount( ActiveDiscussions );
	expect( wrapper.element ).toMatchSnapshot();
} );
