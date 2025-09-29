import { defineConfig } from 'vitest/config';
import mediawiki from 'vitest-plugin-mediawiki';
import vue from '@vitejs/plugin-vue';

export default defineConfig( {
	plugins: [ mediawiki(), vue() ],
	test: {
		environment: 'happy-dom'
	}
} );
