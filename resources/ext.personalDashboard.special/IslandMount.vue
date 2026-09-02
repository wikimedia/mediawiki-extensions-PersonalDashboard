<template>
	<!--
		Teleport-outer, Suspense-inner: the whole loading-then-loaded sequence
		moves into the server slot, so the card body fills in place. This is the
		inverse of the old ModuleCard/ModuleViewport nesting and the arrangement
		the islands model needs. The async child must sit in an explicit
		#default template or a production (minified) build renders a stray
		whitespace text node into the slot alongside it.
	-->
	<teleport :to="target" :disabled="!target">
		<suspense>
			<template #default>
				<slot
					:detail="detail"
					:focused="focused"
					:is-narrow="isNarrow"
					:active="active"
					:header-target="headerTarget">
				</slot>
			</template>
		</suspense>
	</teleport>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { useViewport } = require( './useViewport.js' );

module.exports = defineComponent( {
	name: 'IslandMount',
	props: {
		name: {
			type: String,
			required: true
		},
		// The selector to teleport into when this island is the whole focused
		// view (the dialog or the in-page frame), or '' to stay in its own card
		// slot. A string, not a boolean: crossing the viewport breakpoint swaps
		// which selector is "active" (dialog vs. frame), and a boolean staying
		// true through that swap would never tell <teleport> the target moved.
		activeTarget: {
			type: String,
			default: ''
		},
		// The same, for the header menu's slot: the header of whichever stand-in
		// is showing, or '' to stay in the card's own header slot. A string for
		// the same reason activeTarget is one.
		activeHeaderTarget: {
			type: String,
			default: ''
		},
		// True when this island is the whole focused page rather than a card in
		// the grouped dashboard.
		focused: {
			type: Boolean,
			default: false
		}
	},
	setup( props ) {
		const { isNarrow } = useViewport();
		return {
			isNarrow,
			activeTargetInternal: ref( props.activeTarget ),
			activeHeaderTargetInternal: ref( props.activeHeaderTarget ),
			// The server emits a mount slot for every card-bearing island. A
			// behavior-only island (onboarding) has none, so it renders in
			// place and manages its own portal.
			hasSlot: !!document.getElementById( 'pd-slot-' + props.name ),
			// A second slot, emitted only for a module that declares
			// hasHeaderMenu() server-side. It holds the header's menu button and
			// what the menu opens, which need client state and so cannot be
			// server HTML.
			hasHeaderSlot: !!document.getElementById( 'pd-header-slot-' + props.name )
		};
	},
	computed: {
		target() {
			if ( this.activeTargetInternal ) {
				return this.activeTargetInternal;
			}
			// Module names carry dots, so escape them for the selector; an
			// unescaped '#pd-slot-ext.foo.bar' reads the dots as class selectors
			// and the teleport target is never found.
			return this.hasSlot ? '#pd-slot-' + CSS.escape( this.name ) : null;
		},
		headerTarget() {
			// Gated on the card's own slot throughout, not just in the card
			// branch: the server emits that slot only for a module that declared
			// a header menu, while a stand-in mints its slot for every module it
			// shows. Without the gate a module that opted out would be handed a
			// target the moment it opened.
			if ( !this.hasHeaderSlot ) {
				return null;
			}
			// A stand-in replaces the whole card, header included, so the menu
			// follows the body into the dialog or the frame rather than staying
			// behind in a card the one hides and the other covers.
			return this.activeHeaderTargetInternal ||
				'#pd-header-slot-' + CSS.escape( this.name );
		},
		active() {
			return !!this.activeTargetInternal;
		},
		detail() {
			// A narrow viewport shows a compact summary in the card and the full
			// body in the dialog; a wide viewport is always full. An opened dialog
			// or a focused whole-page render is always full regardless of width.
			const full = this.active || this.focused;
			return ( this.isNarrow && !full ) ? 'compact' : 'full';
		}
	},
	watch: {
		activeTarget( value ) {
			// The dialog or frame only mounts its teleport target when it opens,
			// so defer the move a tick until that target exists.
			this.$nextTick( () => {
				this.activeTargetInternal = value;
			} );
		},
		activeHeaderTarget( value ) {
			// Same deferral, for the same reason: the stand-in's header slot does
			// not exist until the stand-in has rendered.
			this.$nextTick( () => {
				this.activeHeaderTargetInternal = value;
			} );
		}
	}
} );
</script>
