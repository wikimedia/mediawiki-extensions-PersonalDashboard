import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '@resources/ext.personalDashboard.policiesGuidelines/App.vue';
import ListCard from '@resources/ext.personalDashboard.policiesGuidelines/components/ListCard.vue';

test( 'list components match cards ', () => {
	const wrapper = mount( App );
	const list = wrapper.findAllComponents( ListCard );
	let i = 0;

	for ( const [ prefix, pages ] of Object.entries( wrapper.vm.cards ) ) {
		const card = list[ i ].vm;
		expect( card.prefix ).toBe( prefix );
		expect( card.pages ).toBe( pages );
		i++;
	}
} );
