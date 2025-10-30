import { test, expectTypeOf } from 'vitest';
import { fetchRecentActivity } from '@resources/ext.personalDashboard.riskyArticleEdits/composables/useFetchActivityResult.js';

test( 'fetchRecentActivity', () => {
	expectTypeOf( fetchRecentActivity ).toExtend( 'asyncFunction' );
	//@TODO: actually cover this function once we sort out fixtures for api calls.
} );
