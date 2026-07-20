const { createMwApp, defineAsyncComponent } = require( 'vue' );
const { createRouter } = require( 'vue-router' );
const { createPinia } = require( 'pinia' );
const { createMediaWikiHistory } = require( './mediaWikiHistory.js' );
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
const focusedModule = mw.config.get( 'wgPersonalDashboardFocusedModule', null );
for ( const group of groups ) {
	for ( const subgroup of group.subgroups ) {
		for ( const module of subgroup.modules ) {
			if ( !module.enabled || module.serverRendered ) {
				continue;
			}
			if ( focusedModule && module.name !== focusedModule && !module.behaviorOnly ) {
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
	history: createMediaWikiHistory(),
	routes: [
		{
			// The module name is the whole route: it is the page subpage ($par on
			// the server), the focused page a card falls through to with no JS, and
			// the island the dashboard app opens in a dialog. A step within a module
			// (the policies walkthrough's policy) rides in the URL hash, which the
			// module reads for itself; the dashboard app never reads the hash. The
			// greedy (.*) matches any subpage, even an unknown or multi-segment one,
			// so the app always mounts and falls through to the grouped dashboard
			// just as the server does for an unrecognized $par.
			path: '/:module(.*)',
			component: Dashboard,
			props: { islands, platform, focusedModule }
		}
	]
} );

createMwApp( App )
	.use( router )
	.use( createPinia() )
	.mount( '#personal-dashboard-root' );

/*
 * Hand the router to any behavior module that wants to drive its own dialog
 * through browser history (the policies examples walkthrough does). We wait for
 * isReady() so the initial navigation has resolved: a module reads currentRoute
 * the instant it receives the router, and a deep-linked step has to already be
 * the current route or it won't open on first paint. mw.hook replays, so a
 * module that loads later still receives it.
 */
router.isReady().then( () => {
	mw.hook( 'personalDashboard.router' ).fire( router );
} );

const container = document.querySelector( '.personal-dashboard-container' );
if ( container ) {
	// A mobile expandable card is a server <a> to a focused page: the real page
	// it falls through to with no JS. With JS, a plain click anywhere in that
	// card opens the module in the dialog instead. The whole summary card is one
	// tap target, same as the anchor it wraps; a desktop card has no anchor, so
	// its in-body links are never caught here.
	container.addEventListener( 'click', ( e ) => {
		// No dialog on a focused render: the module is already the whole page, so
		// routing a tap into a dialog would teleport the page's own body into a
		// modal duplicate of itself.
		if ( focusedModule ) {
			return;
		}
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

	// Desktop responsive breakpoint. Modern browsers collapse the columns from a
	// CSS container query at first paint; only browsers without @container support
	// need this JS fallback. It reads the breakpoint from a CSS variable so the
	// query and this fallback stay on one source, defaulting to 800 if that read
	// fails. We measure with contentRect rather than contentBoxSize
	// because this path is exactly the older browsers that predate contentBoxSize.
	if ( platform !== 'mobile' && !CSS.supports( 'container-type', 'inline-size' ) ) {
		const breakpoint = parseInt(
			getComputedStyle( container ).getPropertyValue( '--personal-dashboard-column-breakpoint' ), 10
		) || 800;
		new ResizeObserver( ( entries ) => {
			const entry = entries[ 0 ];
			entry.target.classList.toggle(
				'personal-dashboard-container__compact',
				entry.contentRect.width < breakpoint
			);
		} ).observe( container );
	}
}
