import { test, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '@resources/ext.personalDashboard.activeDiscussions/App.vue';
import { ref } from 'vue';

const mockFetchActiveDiscussions = vi.fn();
const loading = ref( true );
const error = ref( null );
const activeDiscussionsResult = ref( null );

vi.mock( 'ext.personalDashboard.common', () => ( { useFetchActiveDiscussionsResult: () => ( {
	activeDiscussionsResult,
	loading,
	error,
	fetchActiveDiscussions: mockFetchActiveDiscussions
} ) } ) );

test( 'mount component', () => {
	const card = document.createElement( 'div' );
	card.className = 'personal-dashboard-module-activeDiscussions';
	document.body.appendChild( card );

	const header = document.createElement( 'div' );
	header.className = 'personal-dashboard-module-header';
	card.appendChild( header );

	const wrapper = mount( App );
	expect( wrapper.element ).toMatchSnapshot();
} );
