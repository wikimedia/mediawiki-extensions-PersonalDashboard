( function () {
	// 'use strict';
	const Vue = require( 'vue' );

	/**
	 * Setup common configs and helpers for all  apps.
	 *
	 * @param {string} mountPoint The XPath selector to mount the application.
	 * @return {Object} A Vue app instance
	 */
	const createApp = ( { mountPoint } ) => {
		const wrapper = require( './App.vue' );
		const app = Vue.createMwApp( wrapper );
		// $filters property is added to all vue component instances
		app.mount( mountPoint );
		return app;
	};
	createApp( {
		mountPoint: '#risky-article-edits-vue-root'
	} );
}() );
