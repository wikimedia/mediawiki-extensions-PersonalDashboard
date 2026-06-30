const { createMwApp } = require( 'vue' );
const { createRouter, createWebHashHistory } = require( 'vue-router' );
const { createPinia } = require( 'pinia' );
const App = require( './App.vue' );
const Dashboard = require( './Dashboard.vue' );

function lazyLoader( name ) {
	return () => new Promise( ( resolve ) => {
		mw.loader.using( name, ( require ) => {
			// eslint-disable-next-line security/detect-non-literal-require
			resolve( require( name ) );
		} );
	} );
}

const groups = mw.config.get( 'wgPersonalDashboardGroups', [] )
	.filter( ( g ) => g.enabled );
const modules = [];

// Bind component refs in place. The same module objects appear in `groups`,
// so Dashboard's template picks up `.component` via the v-for tree.
for ( const group of groups ) {
	for ( const subgroup of group.subgroups.filter( ( s ) => s.enabled ) ) {
		for ( const module of subgroup.modules.filter( ( m ) => m.enabled ) ) {
			// Server-rendered modules have no JS to load; the viewport v-htmls
			// their content directly. The route still needs a component so
			// Vue Router can resolve navigation to /<name>.
			if ( module.html ) {
				module.component = () => ( { template: module.html } );
				module.style = 'none';
			} else {
				module.component = lazyLoader( module.name );
			}

			modules.push( module );
		}
	}
}

const router = createRouter( {
	history: createWebHashHistory(),
	routes: [
		{
			path: '/',
			component: Dashboard,
			props: { groups },
			children: modules.map( ( module ) => ( {
				path: module.name,
				name: module.name,
				component: module.component,
				meta: module
			} ) )
		}
	]
} );

createMwApp( App )
	.use( router )
	.use( createPinia() )
	.mount( '#personal-dashboard-root' );
