import { vi, beforeEach, afterEach, test, expect } from 'vitest';
import { mount } from '@vue/test-utils';

import { useViewport } from '/resources/ext.personalDashboard.special/useViewport.js';

let matchMediaSpy;
let capturedHandler;
let mql;

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

// Mount a tiny host so onUnmounted fires; expose isNarrow for assertions.
function mountViewport() {
	return mount( {
		setup() {
			return useViewport();
		},
		template: '<div></div>'
	} );
}

beforeEach( () => {
	capturedHandler = undefined;
	mql = makeMql( false );
	matchMediaSpy = vi.fn( () => mql );
	window.matchMedia = matchMediaSpy;

	vi.spyOn( window, 'getComputedStyle' ).mockReturnValue( {
		getPropertyValue: () => '500px'
	} );
	vi.spyOn( document, 'querySelector' ).mockReturnValue( {} );
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

test( 'removes the change listener on unmount', () => {
	const wrapper = mountViewport();
	expect( mql.removeEventListener ).not.toHaveBeenCalled();

	wrapper.unmount();
	expect( mql.removeEventListener ).toHaveBeenCalledWith( 'change', capturedHandler );
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
