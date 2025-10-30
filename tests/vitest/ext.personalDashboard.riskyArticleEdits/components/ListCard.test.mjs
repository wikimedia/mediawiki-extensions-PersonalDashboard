import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ListCard from '@resources/ext.personalDashboard.riskyArticleEdits/components/ListCard.vue';

test( 'mount component', () => {
	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			type: 'TestType',
			ns: 0,
			newlen: 0,
			old_revid: 0,
			oldlen: 0,
			pageid: 0,
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
		},
	} );

	expect( wrapper.element ).toMatchSnapshot();
} );
