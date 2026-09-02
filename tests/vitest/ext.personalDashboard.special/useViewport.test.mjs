import { vi, beforeEach, afterEach, test, expect } from 'vitest';

const MODULE = '/resources/ext.personalDashboard.special/useViewport.js';

let matchMediaSpy;
let capturedHandler;
let mql;
let mount;
let useViewport;

function makeMql( matches ) {
	return {
		matches,
		media: '',
		addEventListener: vi.fn( ( event, handler ) => {
			capturedHandler = handler;
		} ),
		removeEventListener: vi.fn()
	};
}

// Mount a tiny host so onUnmounted fires.
function mountViewport() {
	return mount( {
		setup() {
			return useViewport();
		},
		template: '<div></div>'
	} );
}

beforeEach( async () => {
	capturedHandler = undefined;
	mql = makeMql( false );
	matchMediaSpy = vi.fn( () => mql );
	window.matchMedia = matchMediaSpy;

	vi.spyOn( window, 'getComputedStyle' ).mockReturnValue( {
		getPropertyValue: () => '500px'
	} );
	vi.spyOn( document, 'querySelector' ).mockReturnValue( {} );

	// useViewport() caches its media query in module scope, so every test needs a
	// fresh copy of the module. Re-import Vue Test Utils from the same reset
	// registry, otherwise the composable and the test host get separate copies of
	// Vue and the refs they create do not share reactivity state.
	vi.resetModules();
	( { mount } = await import( '@vue/test-utils' ) );
	( { useViewport } = await import( MODULE ) );
} );

afterEach( () => {
	vi.restoreAllMocks();
} );

test( 'seeds isNarrow from the media query matches', () => {
	mql = makeMql( true );
	matchMediaSpy.mockReturnValue( mql );

	const wrapper = mountViewport();
	expect( wrapper.vm.isNarrow ).toBe( true );
} );

test( 'seeds isNarrow false when the media query does not match', () => {
	const wrapper = mountViewport();
	expect( wrapper.vm.isNarrow ).toBe( false );
} );

test( 'updates isNarrow reactively when the change handler fires', async () => {
	const wrapper = mountViewport();
	expect( wrapper.vm.isNarrow ).toBe( false );

	capturedHandler( { matches: true } );
	await wrapper.vm.$nextTick();
	expect( wrapper.vm.isNarrow ).toBe( true );
} );

test( 'keeps the change listener after a caller unmounts', async () => {
	const first = mountViewport();
	const second = mountViewport();

	first.unmount();
	expect( mql.removeEventListener ).not.toHaveBeenCalled();

	// The one listener still drives the ref for whoever is left.
	capturedHandler( { matches: true } );
	await second.vm.$nextTick();
	expect( second.vm.isNarrow ).toBe( true );
} );

test( 'queries matchMedia with the CSS-variable breakpoint', () => {
	mountViewport();
	expect( matchMediaSpy ).toHaveBeenCalledWith( '(max-width: 500px)' );
} );

test( 'falls back to 639px when the CSS variable read is empty', () => {
	window.getComputedStyle.mockReturnValue( {
		getPropertyValue: () => ''
	} );

	mountViewport();
	expect( matchMediaSpy ).toHaveBeenCalledWith( '(max-width: 639px)' );
} );

test( 'falls back to 639px when the container is missing', () => {
	document.querySelector.mockReturnValue( null );

	mountViewport();
	expect( matchMediaSpy ).toHaveBeenCalledWith( '(max-width: 639px)' );
} );

test( 'adds one media query listener however many callers there are', () => {
	mountViewport();
	mountViewport();
	useViewport();

	expect( matchMediaSpy ).toHaveBeenCalledTimes( 1 );
	expect( mql.addEventListener ).toHaveBeenCalledTimes( 1 );
} );

test( 'reads the breakpoint once and reuses it', () => {
	mountViewport();
	mountViewport();

	expect( document.querySelector ).toHaveBeenCalledTimes( 1 );
	expect( window.getComputedStyle ).toHaveBeenCalledTimes( 1 );
} );

test( 'hands every caller the same isNarrow ref, outside setup() too', () => {
	const first = useViewport();
	const second = useViewport();

	expect( second ).toBe( first );
	expect( second.isNarrow ).toBe( first.isNarrow );
} );

test( 'the shared listener updates a later caller too', async () => {
	const first = mountViewport();
	const second = mountViewport();
	expect( second.vm.isNarrow ).toBe( false );

	capturedHandler( { matches: true } );
	await second.vm.$nextTick();

	expect( first.vm.isNarrow ).toBe( true );
	expect( second.vm.isNarrow ).toBe( true );
} );
