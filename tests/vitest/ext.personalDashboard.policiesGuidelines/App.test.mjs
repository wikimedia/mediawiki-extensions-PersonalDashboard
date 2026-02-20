import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '@resources/ext.personalDashboard.policiesGuidelines/App.vue';
import ListCard from '@resources/ext.personalDashboard.policiesGuidelines/components/ListCard.vue';

test( 'list components match cards ', () => {
	const wrapper = mount( App );
	const entries = Object.entries( wrapper.vm.cards );
	const components = wrapper.findAllComponents( ListCard );

	for ( let i = 0; i < entries.length; i++ ) {
		const [ name, steps ] = entries[ i ];
		const card = components[ i ].vm;
		expect( card.name ).toBe( name );
		expect( card.steps ).toBe( steps );
	}
} );
