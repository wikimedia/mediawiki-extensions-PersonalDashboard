import { defineConfig, globalIgnores } from 'eslint/config';
import { FlatCompat } from '@eslint/eslintrc';
import js from '@eslint/js';

const compat = new FlatCompat( {
	recommendedConfig: js.configs.recommended,
	allConfig: js.configs.all
} );

export default defineConfig( [
	globalIgnores( [ 'coverage/', 'resources/lib/', 'vendor/' ] ),
	{
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
		// Parse TypeScript-style JSDoc types (e.g. import('./x.js').Foo), so
		// shared typedefs can live in one file and be referenced by import.
		// Must come after the wikimedia presets, which default to 'jsdoc' mode.
		settings: { jsdoc: { mode: 'typescript' } }
	}
] );
