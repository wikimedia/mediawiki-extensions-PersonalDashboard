import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Dashboard from '/resources/ext.personalDashboard.special/Dashboard.vue';
import IslandMount from '/resources/ext.personalDashboard.special/IslandMount.vue';
import ModuleDialog from '/resources/ext.personalDashboard.special/ModuleDialog.vue';

const islands = [
	{ name: 'ext.example.one', header: 'One', component: {} },
	{ name: 'ext.example.two', header: 'Two', component: {} }
];

function mountDashboard( activeModule ) {
	return mount( Dashboard, {
		props: { islands, platform: 'desktop' },
		global: {
			mocks: {
				$route: { params: activeModule ? { module: activeModule } : {} },
				$router: { push() {} }
			},
			stubs: {
				IslandMount: true,
				ModuleDialog: true
			}
		}
	} );
}

test( 'renders one mount per island', () => {
	const wrapper = mountDashboard();
	expect( wrapper.findAllComponents( IslandMount ) ).toHaveLength( islands.length );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'the active module opens and titles the dialog', () => {
	const dialog = mountDashboard( 'ext.example.two' ).findComponent( ModuleDialog );
	expect( dialog.props( 'open' ) ).toBe( true );
	expect( dialog.props( 'title' ) ).toBe( 'Two' );
} );

test( 'no active module leaves the dialog closed and untitled', () => {
	const dialog = mountDashboard().findComponent( ModuleDialog );
	expect( dialog.props( 'open' ) ).toBe( false );
	expect( dialog.props( 'title' ) ).toBe( '' );
} );
