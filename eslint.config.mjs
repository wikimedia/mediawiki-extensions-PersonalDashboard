import { defineConfig, globalIgnores } from 'eslint/config';
import { FlatCompat } from '@eslint/eslintrc';
import js from '@eslint/js';

const compat = new FlatCompat( {
	recommendedConfig: js.configs.recommended,
	allConfig: js.configs.all
} );

export default defineConfig( [
	// Hidden directories hold tooling state, not our source, and flat config
	// no longer skips them. Directories only, so hidden config files still count.
	globalIgnores( [ '**/.*/', 'coverage/', 'resources/lib/', 'vendor/' ] ),
	{
		// Node CLI scripts get the server config below instead.
		ignores: [ 'tests/check-unused-messages.js' ],
		extends: compat.extends(
			'wikimedia/client/common',
			'wikimedia/language/es2018',
			'wikimedia/mediawiki/common'
		)
	},
	{
		files: [ '**/*.mjs' ],
		languageOptions: {
			sourceType: 'module'
		}
	},
	{
		files: [ '**/*.vue' ],
		extends: compat.extends(
			'wikimedia/vue3/common',
			'wikimedia/language/es2018',
			'wikimedia/mediawiki/common'
		),
		rules: {
			'vue/no-undef-components': [ 'error', {
				ignorePatterns: [ '^router-.+$' ]
			} ]
		}
	},
	{
		// Node.js scripts run from the command line, not shipped to browsers.
		files: [ 'tests/check-unused-messages.js' ],
		extends: compat.extends( 'wikimedia/server' ),
		rules: {
			// Walking the repo with computed paths is this script's job.
			'security/detect-non-literal-fs-filename': 'off'
		}
	},
	{
		// Parse TypeScript-style JSDoc types (e.g. import('./x.js').Foo), so
		// shared typedefs can live in one file and be referenced by import.
		// Must come after the wikimedia presets, which default to 'jsdoc' mode.
		settings: { jsdoc: { mode: 'typescript' } }
	}
] );
