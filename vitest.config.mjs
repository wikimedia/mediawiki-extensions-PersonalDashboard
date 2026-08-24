import { defineConfig } from 'vitest/config';
import mediawiki from 'vitest-plugin-mediawiki';

export default defineConfig( {
	plugins: [ mediawiki() ],
	resolve: {
		alias: {
			'mediawiki.DateFormatter': '/tests/vitest/mocks/mediawiki.DateFormatter.mjs',
			'ext.personalDashboard.common': '/tests/vitest/mocks/ext.personalDashboard.common.mjs'
		}
	},
	test: {
		// Collect only our own tests. The default walk covers the whole tree,
		// hidden directories included, and those hold anything but our source.
		include: [ 'tests/vitest/**/*.test.mjs' ],
		// Merged with (not replacing) the plugin's own setup file.
		setupFiles: [ './tests/vitest/setup.mjs' ]
	}
} );
