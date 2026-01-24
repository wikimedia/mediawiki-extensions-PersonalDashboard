import { test, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ListCard from '@resources/ext.personalDashboard.riskyArticleEdits/components/ListCard.vue';
import { formatDate } from 'mediawiki.DateFormatter';

test( 'mount component', () => {
	const date = new Date();
	date.setHours( 8, 25 );
	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			type: 'TestType',
			ns: 0,
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			pageid: 0,
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
			timestamp: date.toISOString(),
			pages: {
				0: {
					description: 'a description'
				}
			}
		}
	} );

	expect( wrapper.element ).toMatchSnapshot();
} );

test( 'renders appropriate message when edit is made today', () => {
	const date = new Date();
	date.setHours( 3, 25 );
	const expectedDate = '⧼personal-dashboard-risky-article-edits-info-time-text-today⧽';
	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			type: 'TestType',
			ns: 0,
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			pageid: 0,
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
			timestamp: date.toISOString(),
			pages: {}
		}
	} );
	expect( expectedDate ).toBe( wrapper.vm.timestampFormatted );
} );

test( 'renders timestamp without hours when edit is not made today', async () => {
	const date = new Date( 2024, 11, 2, 4, 29 );
	const expectedDate = formatDate( date );
	const wrapper = mount( ListCard, {
		props: {
			title: 'TestTitle',
			type: 'TestType',
			ns: 0,
			newlen: 0,
			// eslint-disable-next-line camelcase
			old_revid: 0,
			oldlen: 0,
			pageid: 0,
			rcid: 0,
			revid: 0,
			user: 'TestUser',
			parsedcomment: 'TestComment',
			tags: [ 'test' ],
			timestamp: date.toISOString(),
			pages: {}
		}
	} );

	expect( expectedDate ).toBe( wrapper.vm.timestampFormatted );
} );
