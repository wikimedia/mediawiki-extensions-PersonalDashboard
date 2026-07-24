/**
 * @file useViewport.js
 *
 * Composable exposing whether the viewport is at or below the mobile
 * breakpoint, so a card can drive its compact/full detail off width
 * rather than the server-seeded platform.
 */

const { ref, onUnmounted } = require( 'vue' );

/**
 * @return {{ isNarrow: import('vue').Ref<boolean> }}
 */
function useViewport() {
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
	onUnmounted( () => {
		mql.removeEventListener( 'change', onChange );
	} );

	return { isNarrow };
}

module.exports = { useViewport };
