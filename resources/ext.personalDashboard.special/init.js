const { createMwApp, defineAsyncComponent } = require( 'vue' );
const { createRouter, createWebHashHistory } = require( 'vue-router' );
const { createPinia } = require( 'pinia' );
const App = require( './App.vue' );
const Dashboard = require( './Dashboard.vue' );

// Personal Dashboard health-metrics instrumentation. Self-contained: it wires
// up its own hooks and soft-loads TestKitchen, so we only need to require it
// here to run it.
require( './instrumentation/onPersonalDashboardBaseline.js' );

function lazyLoader( name ) {
	return () => new Promise( ( resolve ) => {
		mw.loader.using( name, ( require ) => {
			// eslint-disable-next-line security/detect-non-literal-require
			resolve( require( name ) );
		} );
	} );
}

// Flatten the server's group tree to the islands the client owns: enabled
// modules whose body the client fills in. A server-rendered module keeps its
// PHP-emitted body and is left untouched.
//
// A focused render emits only the focused module's frame as the whole page, so
// the other card islands have no slot to teleport into and would mount in place,
// stacked atop it. Drop them here. A behavior-only island (onboarding) owns no
// card anywhere and always mounts.
const islands = [];
const groups = mw.config.get( 'wgPersonalDashboardGroups', [] );
const focused = mw.config.get( 'wgPersonalDashboardFocusedModule', null );
for ( const group of groups ) {
	for ( const subgroup of group.subgroups ) {
		for ( const module of subgroup.modules ) {
			if ( !module.enabled || module.serverRendered ) {
				continue;
			}
			if ( focused && module.name !== focused && !module.behaviourOnly ) {
				continue;
			}
			islands.push( {
				name: module.name,
				header: module.header || '',
				component: defineAsyncComponent( lazyLoader( module.name ) )
			} );
		}
	}
}

const islandNames = new Set( islands.map( ( island ) => island.name ) );

// The rendering platform the server resolved from the skin; the client trusts
// it rather than re-sniffing, so both sides render for the same platform.
const platform = mw.config.get( 'wgPersonalDashboardPlatform', 'desktop' );

const router = createRouter( {
	history: createWebHashHistory(),
	routes: [
		{
			path: '/:module?',
			component: Dashboard,
			props: { islands, platform }
		}
	]
} );

createMwApp( App )
	.use( router )
	.use( createPinia() )
	.mount( '#personal-dashboard-root' );

const container = document.querySelector( '.personal-dashboard-container' );
if ( container ) {
	// A mobile expandable card is a server <a> to a focused page: the real page
	// it falls through to with no JS. With JS, a plain click anywhere in that
	// card opens the module in the dialog instead. The whole summary card is one
	// tap target, same as the anchor it wraps; a desktop card has no anchor, so
	// its in-body links are never caught here.
	container.addEventListener( 'click', ( e ) => {
		// Leave modified and non-primary clicks to the browser so the anchor's
		// real href still opens the focused page in a new tab.
		if ( e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey ) {
			return;
		}
		const card = e.target.closest( '[data-module-name]' );
		if ( !card || !card.closest( 'a' ) || !islandNames.has( card.dataset.moduleName ) ) {
			return;
		}
		e.preventDefault();
		router.push( '/' + card.dataset.moduleName );
	} );

	// Desktop responsive breakpoint. The server owns this container now, so we
	// observe it from here rather than a Vue component that no longer renders it.
	if ( platform !== 'mobile' ) {
		new ResizeObserver( ( entries ) => {
			const entry = entries[ 0 ];
			entry.target.classList.toggle(
				'personal-dashboard-container__compact',
				entry.contentBoxSize[ 0 ].inlineSize < 800
			);
		} ).observe( container );
	}
}
