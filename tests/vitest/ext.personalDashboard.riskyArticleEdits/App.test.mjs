import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '@resources/ext.personalDashboard.riskyArticleEdits/App.vue';

test( 'mount component', () => {
	const card = document.createElement( 'div' );
	card.className = 'personal-dashboard-module-riskyArticleEdits';
	document.body.appendChild( card );

	const header = document.createElement( 'div' );
	header.className = 'personal-dashboard-module-header';
	card.appendChild( header );

	const wrapper = mount( App );
	expect( wrapper.element ).toMatchSnapshot();
} );
