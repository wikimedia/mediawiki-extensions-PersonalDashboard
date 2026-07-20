/**
 * A Vue Router history for MediaWiki special pages.
 *
 * Vue Router's built-in histories map the route to and from
 * window.location.pathname. But a MediaWiki special page is addressable two
 * equally valid ways: the short /wiki/Special:Foo/bar and the long
 * /w/index.php?title=Special:Foo/bar. The subpage ("bar") is the route, and the
 * server already gets it as $par regardless of form. This is the client-side
 * mirror of that: it reads the route from the page title (form-invariant) and
 * writes URLs back preserving whichever form the visitor arrived on, so the
 * matcher, routes, and components never see the difference, exactly as execute()
 * never sees it.
 */

/**
 * The subpage of a special-page title: everything after the first segment.
 * 'Special:PersonalDashboard/ext.foo' -> 'ext.foo'; the bare page -> ''.
 *
 * @param {string} title
 * @return {string}
 */
function subpage( title ) {
	const slash = title.indexOf( '/' );
	return slash === -1 ? '' : title.slice( slash + 1 );
}

/**
 * The page title carried by a location, read the same way from either URL form:
 * the long form carries it in the title param, the short form in the path after
 * the article-path prefix. This is the form-invariant read the whole history
 * turns on.
 *
 * @param {string} pathname window.location.pathname
 * @param {string} search window.location.search
 * @param {string} articlePath wgArticlePath, e.g. '/wiki/$1'
 * @return {string} e.g. 'Special:PersonalDashboard/ext.foo'
 */
function titleFromLocation( pathname, search, articlePath ) {
	const title = new URLSearchParams( search ).get( 'title' );
	if ( title !== null ) {
		return title;
	}
	const prefix = articlePath.replace( '$1', '' );
	return decodeURIComponent( pathname.slice( prefix.length ) );
}

/**
 * Build the on-wiki URL for a page name, in the requested form. The short form
 * defers to mw.util.getUrl (which emits the wiki's configured default); the long
 * form forces index.php so a visitor who arrived on ?title= keeps it. Ambient
 * query params (uselang, variant, ...) ride along in both forms so a client
 * navigation never strips them from the address bar; every write rebuilds the
 * whole URL, so anything not carried here is lost.
 *
 * @param {string} pageName Full title, e.g. 'Special:PersonalDashboard/ext.foo'
 * @param {string} hash Leading '#', or ''
 * @param {boolean} longForm Preserve the index.php?title= form
 * @param {Object} [query] Ambient query params to carry forward, sans title
 * @return {string}
 */
function hrefFor( pageName, hash, longForm, query ) {
	if ( longForm ) {
		const params = new URLSearchParams( query );
		params.set( 'title', pageName );
		return mw.config.get( 'wgScript' ) + '?' + params + hash;
	}
	return mw.util.getUrl( pageName, query ) + hash;
}

/**
 * A RouterHistory (vue-router) that routes a MediaWiki special page off its
 * title rather than its raw pathname. Drop-in for createWebHistory/
 * createWebHashHistory in createRouter().
 *
 * @return {Object} RouterHistory
 */
function createMediaWikiHistory() {
	const articlePath = mw.config.get( 'wgArticlePath' );
	// The special page never changes during navigation, so its base is the stable
	// first segment of the page we loaded on. Only the subpage moves.
	const specialPage = mw.config.get( 'wgPageName' ).split( '/' )[ 0 ];
	// The title in the query means the long index.php URL; in the path, the short
	// URL. We preserve whichever the visitor arrived on for every write.
	const arrivalParams = new URLSearchParams( window.location.search );
	const longForm = arrivalParams.has( 'title' );
	// The rest of the query isn't the route (uselang, variant, ...) but rides along
	// on every write: each one rebuilds the URL from scratch, so a param not
	// carried here would vanish the instant the client router takes over.
	arrivalParams.delete( 'title' );
	const query = {};
	arrivalParams.forEach( ( value, key ) => {
		query[ key ] = value;
	} );
	const teardowns = [];

	// The route string vue-router matches on: '/' + subpage + fragment, read live
	// from the URL so it stays correct after browser back/forward.
	function currentRoute() {
		const title = titleFromLocation(
			window.location.pathname, window.location.search, articlePath
		);
		return '/' + subpage( title ) + window.location.hash;
	}

	function toHref( loc ) {
		const hashAt = loc.indexOf( '#' );
		const hash = hashAt === -1 ? '' : loc.slice( hashAt );
		const path = hashAt === -1 ? loc : loc.slice( 0, hashAt );
		const pageName = path === '/' ? specialPage : specialPage + path;
		return hrefFor( pageName, hash, longForm, query );
	}

	let location = currentRoute();
	// state is a bare position counter. Vue Router's scroll restoration and
	// precise back/forward direction want its fuller state shape; wire that up
	// if the dashboard grows navigation that needs restored scroll.
	let state = window.history.state || {};
	let position = state.position || 0;

	// Seed state on a fresh entry so back/forward has something to restore to,
	// without touching the URL the visitor arrived on.
	if ( window.history.state === null ) {
		state = { position };
		window.history.replaceState( state, '' );
	}

	function changeLocation( to, data, replace ) {
		position += replace ? 0 : 1;
		state = Object.assign( { position }, data );
		window.history[ replace ? 'replaceState' : 'pushState' ]( state, '', toHref( to ) );
		location = to;
	}

	return {
		base: '',
		get location() {
			return location;
		},
		get state() {
			return state;
		},
		push( to, data ) {
			changeLocation( to, data, false );
		},
		replace( to, data ) {
			changeLocation( to, data, true );
		},
		// go() ignores vue-router's triggerListeners flag, so a guard that aborts a
		// browser back/forward would double-fire the popstate listener via the
		// corrective go(). Nothing triggers it while the router has no navigation
		// guards; wire up the vendored pauseListeners mechanism if one is ever added.
		go( delta ) {
			window.history.go( delta );
		},
		createHref( to ) {
			return toHref( to );
		},
		listen( callback ) {
			const handler = () => {
				const from = location;
				const fromPosition = position;
				location = currentRoute();
				state = window.history.state || {};
				position = state.position || 0;
				const delta = position - fromPosition;
				// 'back'/'forward' is best-effort off the position delta; confirm
				// against vue-router's NavigationDirection when scroll behavior is
				// wired up.
				callback( location, from, {
					delta,
					type: 'pop',
					direction: delta > 0 ? 'forward' : ( delta < 0 ? 'back' : '' )
				} );
			};
			window.addEventListener( 'popstate', handler );
			const teardown = () => window.removeEventListener( 'popstate', handler );
			teardowns.push( teardown );
			return teardown;
		},
		destroy() {
			teardowns.forEach( ( fn ) => fn() );
			teardowns.length = 0;
		}
	};
}

module.exports = { createMediaWikiHistory, subpage, titleFromLocation, hrefFor };
