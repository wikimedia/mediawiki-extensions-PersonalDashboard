( function () {
	const container = document.querySelector( '.personal-dashboard-container' );

	if ( !container ) {
		return;
	}

	// Responsive breakpoint for dashboard layout on non-Minerva skins
	if ( mw.config.get( 'skin' ) !== 'minerva' ) {
		new ResizeObserver( ( entries ) => {
			// Toggle between 2-column and 1-column mode based on container width
			if ( entries[ 0 ].contentBoxSize[ 0 ].inlineSize < 800 ) {
				container.classList.add( 'personal-dashboard-container__compact' );
			} else {
				container.classList.remove( 'personal-dashboard-container__compact' );
			}
		} ).observe( container );
	}

	const modules = container.querySelectorAll( '.personal-dashboard-module[ data-module-name ]:not( .personal-dashboard-module-banner )' );

	if ( !modules || modules.length === 0 ) {
		return;
	}

	const { createMwApp, defineComponent, h, ref, render } = require( 'vue' );
	const ModuleRoute = require( './ModuleRoute.vue' );
	const contentRoot = document.getElementById( 'content' );
	const bodySelector = '.personal-dashboard-module-body';
	const navSelector = '.personal-dashboard-module-header-nav-icon';
	const rootSelector = '.ext-personal-dashboard-app-root';
	const navModuleList = [];
	const app = createMwApp( {} );

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
		const rendermode = mw.config.get( 'dashboardmodules' )[ jsModule ].renderMode;
		const title = moduleEl.querySelector( '.personal-dashboard-module-header-text' ).innerText;

		mw.loader.using( jsModule ).then( ( require ) => {
			// eslint-disable-next-line security/detect-non-literal-require
			const Module = require( jsModule );
			const nav = moduleEl.querySelector( navSelector );

			if ( nav ) {
				navModuleList.push( module );

				const navAnchor = document.createElement( 'a' );
				navAnchor.className = 'personal-dashboard-module-anchor';
				navAnchor.href = hash;
				navAnchor.role = 'button';
				// only update the hash on click; no scrolling
				navAnchor.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					document.location.hash = hash;
				} );
				moduleEl.parentNode.insertBefore( navAnchor, moduleEl );
				navAnchor.appendChild( moduleEl );

				const open = ref( false );

				mw.hook( `personaldashboard.special.personalDashboard.${ module }.open` ).add( () => {
					contentRoot.classList.add( 'personal-dashboard-hide-all' );
					window.scrollTo( { top: 0 } );
					open.value = true;
					this.location.hash = hash;
				} );

				mw.hook( `personaldashboard.special.personalDashboard.${ module }.close` ).add( () => {
					contentRoot.classList.remove( 'personal-dashboard-hide-all' );
					open.value = false;
				} );

				const NavComponent = defineComponent( {
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
							},
							rendermode: 'mobile-details'
						}, () => h( Module, { rendermode: 'mobile-details' } ) );
					}
				} );

				// render the component to a vnode
				const vnode = h( NavComponent );
				// attach the existing app context
				// eslint-disable-next-line no-underscore-dangle
				vnode.appContext = app._context;
				// borrow the root render function normally used for an app
				render( vnode, nav );
			}

			if ( root ) {
				const vnode = h( Module, { rendermode, open } );
				// eslint-disable-next-line no-underscore-dangle
				vnode.appContext = app._context;
				render( vnode, root );
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
