/**
 * @file useViewport.js
 *
 * Composable exposing whether the viewport is at or below the mobile
 * breakpoint, so a card can drive its compact/full detail off width
 * rather than the server-seeded platform.
 */

const { ref } = require( 'vue' );

/**
 * The one media query and ref that all callers share. The listener stays for the
 * lifetime of the page, so there is no teardown; the browser releases it when the
 * user leaves. This also keeps useViewport() free of the Vue lifecycle, so a
 * caller can use it outside of setup().
 *
 * @type {{ isNarrow: import('vue').Ref<boolean> }|undefined}
 */
let shared;

/**
 * @return {{ isNarrow: import('vue').Ref<boolean> }}
 */
function useViewport() {
	if ( shared ) {
		return shared;
	}

	const container = document.querySelector( '.personal-dashboard-container' );
	// The mobile breakpoint lives in LESS; index.less feeds it to us as a CSS
	// variable since we can't read a LESS variable at runtime. Fall back to the
	// @max-width-breakpoint-mobile value if the read fails or the element is gone.
	const breakpoint = ( container && parseInt(
		getComputedStyle( container ).getPropertyValue( '--personal-dashboard-mobile-breakpoint' ), 10
	) ) || 639;

	const mql = window.matchMedia( '(max-width: ' + breakpoint + 'px)' );
	const isNarrow = ref( mql.matches );

	function onChange( event ) {
		isNarrow.value = event.matches;
	}
	mql.addEventListener( 'change', onChange );

	shared = { isNarrow };
	return shared;
}

module.exports = { useViewport };
