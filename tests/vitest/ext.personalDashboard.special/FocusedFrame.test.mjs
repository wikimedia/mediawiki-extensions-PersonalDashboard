import { afterEach, test, expect } from 'vitest';
import { mount, enableAutoUnmount } from '@vue/test-utils';
import FocusedFrame from '/resources/ext.personalDashboard.special/FocusedFrame.vue';
import FocusedHeader from '/resources/ext.personalDashboard.special/FocusedHeader.vue';
import { FRAME_TARGET_ID } from '/resources/ext.personalDashboard.special/teleportTargets.js';

// mountFrame() attaches to the real document so .focus() on mount has
// somewhere real to land; drop each frame before the next test mounts one.
enableAutoUnmount( afterEach );

function mountFrame( props = {} ) {
	return mount( FocusedFrame, {
		props: Object.assign( { title: 'Some Module' }, props ),
		attachTo: document.body,
		global: {
			stubs: {
				FocusedHeader: true
			}
		}
	} );
}

test( "renders the shared header with the frame's title", () => {
	const header = mountFrame().findComponent( FocusedHeader );
	expect( header.props( 'title' ) ).toBe( 'Some Module' );
} );

test( 'carries its own teleport target', () => {
	const wrapper = mountFrame();
	expect( wrapper.find( '#' + FRAME_TARGET_ID ).exists() ).toBe( true );
} );

test( "forwards the header's back emit", () => {
	const wrapper = mountFrame();
	wrapper.findComponent( FocusedHeader ).vm.$emit( 'back' );
	expect( wrapper.emitted( 'back' ) ).toBeTruthy();
} );

test( 'takes focus on mount', () => {
	const wrapper = mountFrame();
	expect( document.activeElement ).toBe( wrapper.find( '.personal-dashboard-focused-frame' ).element );
} );
