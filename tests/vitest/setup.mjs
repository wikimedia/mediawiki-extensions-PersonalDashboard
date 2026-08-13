/**
 * @file setup.mjs
 *
 * Extension-wide Vitest setup, run in addition to the `mw` global that
 * vitest-plugin-mediawiki installs.
 *
 * vitest-plugin-mediawiki seeds `mw.config` with MediaWiki core's defaults only,
 * so any `wgPersonalDashboard*` variable a module exports via ResourceLoader has
 * to be seeded here, otherwise `mw.config.get()` returns null in tests that never
 * set it explicitly.
 *
 * These are registered as a `beforeEach` rather than set at import time so the
 * ordering against the plugin's own setup file doesn't matter, and so a test that
 * overwrites a value doesn't leak it into the next one.
 */

import { beforeEach } from 'vitest';

/**
 * Defaults mirroring what each module's getJsConfigVars() exports in production.
 * Keep in sync with the PHP side; see src/Modules/.
 *
 * @type {Object}
 */
const CONFIG_DEFAULTS = {
	// ReviewChanges::EXCLUDED_TAGS
	wgPersonalDashboardReviewChangesExcludedTags: [ 'mw-reverted', 'mw-undo', 'mw-rollback' ]
};

beforeEach( () => {
	mw.config.set( CONFIG_DEFAULTS );
} );
