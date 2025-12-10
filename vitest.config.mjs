import { defineConfig } from 'vitest/config';
import mediawiki from 'vitest-plugin-mediawiki';

export default defineConfig( {
	plugins: [ mediawiki() ],
	resolve: {
		alias: {
			'@resources': '/resources',
			'mediawiki.DateFormatter': '/tests/vitest/mocks/mediawiki.DateFormatter.mjs',
			'ext.personalDashboard.common': '/tests/vitest/mocks/ext.personalDashboard.common.mjs'
		}
	}
} );
