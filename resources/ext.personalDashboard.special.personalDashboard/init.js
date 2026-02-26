( function () {
	// 'use strict';
	const modules = document.querySelectorAll( '.personal-dashboard-container .personal-dashboard-module[data-module-name]:not(.personal-dashboard-module-banner)' );
	if ( !modules || modules.length === 0 ) {
		return;
	}

	const { createMwApp, h, ref } = require( 'vue' );
	const ModuleRoute = require( './ModuleRoute.vue' );
	const bodySelector = '.personal-dashboard-module-body';
	const navSelector = '.personal-dashboard-module-header-nav-icon';
	const rootSelector = '.ext-personal-dashboard-app-root';
	const navModuleList = [];

	for ( const moduleEl of modules ) {
		// skip modules that are already wrapped in an anchor
		if ( moduleEl.parentElement.tagName === 'A' ) {
			return;
		}
		// skip modules with no body
		const body = moduleEl.querySelector( bodySelector );
		if ( !body ) {
			return;
		}
		const root = moduleEl.querySelector( rootSelector );
		const module = moduleEl.getAttribute( 'data-module-name' );
		const jsModule = `ext.personalDashboard.${ module }`;
		const hash = `#${ module }`;
		const allButModuleRouteSelector = `.mw-body > *:not(.personal-dashboard-module-route-${ module })`;
		const rendermode = mw.config.get( 'dashboardmodules' )[ module ].renderMode;
		const title = moduleEl.querySelector( '.personal-dashboard-module-header-text' ).innerText;
		mw.loader.using( jsModule ).then( ( require ) => {
			// eslint-disable-next-line security/detect-non-literal-require
			const Module = require( jsModule );
			const nav = moduleEl.querySelector( navSelector );
			if ( nav ) {
				navModuleList.push( module );
				const navAnchor = document.createElement( 'a' );
				navAnchor.href = hash;
				// only update the hash on click; no scrolling
				navAnchor.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					document.location.hash = hash;
				} );
				moduleEl.parentNode.insertBefore( navAnchor, moduleEl );
				navAnchor.appendChild( moduleEl );
				const open = ref( false );
				mw.hook( `personaldashboard.special.personalDashboard.${ module }.open` ).add( () => {
					window.scrollTo( { top: 0 } );
					open.value = true;
					for ( const el of document.querySelectorAll( allButModuleRouteSelector ) ) {
						el.style.display = 'none';
					}
					this.location.hash = hash;
				} );
				mw.hook( `personaldashboard.special.personalDashboard.${ module }.close` ).add( () => {
					open.value = false;
					for ( const el of document.querySelectorAll( allButModuleRouteSelector ) ) {
						el.style.display = '';
					}
				} );
				createMwApp( {
					props: [ 'open' ],
					emits: [ 'close' ],
					setup() {
						if ( open.value === false && document.location.hash === hash ) {
							mw.hook( `personaldashboard.special.personalDashboard.${ module }.open` ).fire();
						}

						return () => h( ModuleRoute, {
							module,
							title,
							open,
							onClose() {
								document.location.hash = '';
								mw.hook( `personaldashboard.special.personalDashboard.${ module }.close` ).fire();
							}
						}, () => h( Module, { rendermode: 'mobile-details' } ) );
					}
				} ).mount( nav );
			}
			if ( root ) {
				createMwApp( {
					render() {
						return h( Module, { rendermode } );
					}
				} ).mount( root );
			}
		} );
	}

	// sync state with hash
	window.addEventListener( 'hashchange', ( e ) => {
		const oldModule = new URL( e.oldURL ).hash.slice( 1 );
		const newModule = new URL( e.newURL ).hash.slice( 1 );
		mw.hook( `personaldashboard.special.personalDashboard.${ oldModule }.close` ).fire();
		if ( navModuleList.includes( newModule ) ) {
			mw.hook( `personaldashboard.special.personalDashboard.${ newModule }.open` ).fire();
		}
	} );
}() );
