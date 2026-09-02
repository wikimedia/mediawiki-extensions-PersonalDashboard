import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FocusedHeader from '/resources/ext.personalDashboard.special/FocusedHeader.vue';

test( 'renders a real link when backHref is set', () => {
	const wrapper = mount( FocusedHeader, {
		props: { title: 'Some Module', backHref: '/wiki/Special:PersonalDashboard' }
	} );
	const link = wrapper.find( 'a' );
	expect( link.exists() ).toBe( true );
	expect( link.attributes( 'href' ) ).toBe( '/wiki/Special:PersonalDashboard' );
	expect( wrapper.findComponent( { name: 'CdxButton' } ).exists() ).toBe( false );
} );

test( 'renders a close button when backHref is empty', () => {
	const wrapper = mount( FocusedHeader, {
		props: { title: 'Some Module' }
	} );
	expect( wrapper.find( 'a' ).exists() ).toBe( false );
	expect( wrapper.findComponent( { name: 'CdxButton' } ).exists() ).toBe( true );
} );

test( 'the button branch emits back on click', async () => {
	const wrapper = mount( FocusedHeader, { props: { title: 'Some Module' } } );
	await wrapper.findComponent( { name: 'CdxButton' } ).trigger( 'click' );
	expect( wrapper.emitted( 'back' ) ).toBeTruthy();
} );

test( 'renders the title', () => {
	const wrapper = mount( FocusedHeader, { props: { title: 'Some Module' } } );
	expect( wrapper.find( '.personal-dashboard-focused-header__title' ).text() ).toBe( 'Some Module' );
} );

test( 'mints no actions slot without an id for it', () => {
	const wrapper = mount( FocusedHeader, { props: { title: 'Some Module' } } );
	expect( wrapper.find( '.personal-dashboard-focused-header__actions' ).exists() )
		.toBe( false );
} );

test( 'mints the actions slot the stand-in names, for the island to teleport into', () => {
	const wrapper = mount( FocusedHeader, {
		props: { title: 'Some Module', actionsTargetId: 'personal-dashboard-header-teleport' }
	} );
	const actions = wrapper.get( '.personal-dashboard-focused-header__actions' );
	expect( actions.attributes( 'id' ) ).toBe( 'personal-dashboard-header-teleport' );
	expect( actions.element.innerHTML ).toBe( '' );
} );

test( 'matches the link-branch snapshot', () => {
	const wrapper = mount( FocusedHeader, {
		props: { title: 'Some Module', backHref: '/wiki/Special:PersonalDashboard' }
	} );
	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'matches the button-branch snapshot', () => {
	const wrapper = mount( FocusedHeader, { props: { title: 'Some Module' } } );
	expect( wrapper.element ).toMatchSnapshot();
} );
