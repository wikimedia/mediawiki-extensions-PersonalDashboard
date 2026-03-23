import { defineConfig, globalIgnores } from 'eslint/config';
import { FlatCompat } from '@eslint/eslintrc';
import js from '@eslint/js';

const compat = new FlatCompat( {
	recommendedConfig: js.configs.recommended,
	allConfig: js.configs.all
} );

export default defineConfig( [
	globalIgnores( [ 'coverage/', 'vendor/' ] ),
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
		)
	}
] );
