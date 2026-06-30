import { vi, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Dashboard from '/resources/ext.personalDashboard.special/Dashboard.vue';

test( 'mount component', () => {
	const chars = 'ABC';

	for ( const a of chars ) {
		for ( const b of chars ) {
			for ( const c of chars ) {
				vi.doMock( a + b + c, () => ( {} ) );
			}
		}
	}

	const wrapper = mount( Dashboard, {
		global: {
			mocks: {
				$route: {
					path: '/'
				}
			}
		},
		props: {
			groups: [
				{
					name: 'A',
					enabled: true,
					hidden: false,
					subgroups: [
						{
							name: 'AA',
							enabled: true,
							hidden: false,
							modules: [
								{
									name: 'AAA',
									enabled: true,
									hidden: false
								},
								{
									name: 'AAB',
									enabled: true,
									hidden: true
								},
								{
									name: 'AAC',
									enabled: false,
									hidden: false
								}
							]
						},
						{
							name: 'AB',
							enabled: true,
							hidden: true,
							modules: [
								{
									name: 'ABA',
									enabled: true,
									hidden: false
								},
								{
									name: 'ABB',
									enabled: true,
									hidden: true
								},
								{
									name: 'ABC',
									enabled: false,
									hidden: false
								}
							]
						},
						{
							name: 'AC',
							enabled: false,
							hidden: false,
							modules: [
								{
									name: 'ACA',
									enabled: true,
									hidden: false
								},
								{
									name: 'ACB',
									enabled: true,
									hidden: true
								},
								{
									name: 'ACC',
									enabled: false,
									hidden: false
								}
							]
						}
					]
				},
				{
					name: 'B',
					enabled: true,
					hidden: true,
					subgroups: [
						{
							name: 'BA',
							enabled: true,
							hidden: false,
							modules: [
								{
									name: 'BAA',
									enabled: true,
									hidden: false
								},
								{
									name: 'BAB',
									enabled: true,
									hidden: true
								},
								{
									name: 'BAC',
									enabled: false,
									hidden: false
								}
							]
						},
						{
							name: 'BB',
							enabled: true,
							hidden: true,
							modules: [
								{
									name: 'BBA',
									enabled: true,
									hidden: false
								},
								{
									name: 'BBB',
									enabled: true,
									hidden: true
								},
								{
									name: 'BBC',
									enabled: false,
									hidden: false
								}
							]
						},
						{
							name: 'BC',
							enabled: false,
							hidden: false,
							modules: [
								{
									name: 'BCA',
									enabled: true,
									hidden: false
								},
								{
									name: 'BCB',
									enabled: true,
									hidden: true
								},
								{
									name: 'BCC',
									enabled: false,
									hidden: false
								}
							]
						}
					]
				},
				{
					name: 'C',
					enabled: false,
					hidden: false,
					subgroups: [
						{
							name: 'CA',
							enabled: true,
							hidden: false,
							modules: [
								{
									name: 'CAA',
									enabled: true,
									hidden: false
								},
								{
									name: 'CAB',
									enabled: true,
									hidden: true
								},
								{
									name: 'CAC',
									enabled: false,
									hidden: false
								}
							]
						},
						{
							name: 'CB',
							enabled: true,
							hidden: true,
							modules: [
								{
									name: 'CBA',
									enabled: true,
									hidden: false
								},
								{
									name: 'CBB',
									enabled: true,
									hidden: true
								},
								{
									name: 'CBC',
									enabled: false,
									hidden: false
								}
							]
						},
						{
							name: 'CC',
							enabled: false,
							hidden: false,
							modules: [
								{
									name: 'CCA',
									enabled: true,
									hidden: false
								},
								{
									name: 'CCB',
									enabled: true,
									hidden: true
								},
								{
									name: 'CCC',
									enabled: false,
									hidden: false
								}
							]
						}
					]
				}
			]
		}
	} );

	expect( wrapper.element ).toMatchSnapshot();
} );
