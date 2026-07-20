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
				<component
					:is="component"
					:platform="platform"
					:detail="detail">
				</component>
			</template>
		</suspense>
	</teleport>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );

module.exports = defineComponent( {
	name: 'IslandMount',
	props: {
		name: {
			type: String,
			required: true
		},
		component: {
			type: [ Object, Function ],
			required: true
		},
		platform: {
			type: String,
			required: true
		},
		active: {
			type: Boolean,
			default: false
		},
		// True when this island is the whole focused page rather than a card in
		// the grouped dashboard.
		focused: {
			type: Boolean,
			default: false
		}
	},
	setup( props ) {
		return {
			activeInternal: ref( props.active ),
			// The server emits a mount slot for every card-bearing island. A
			// behavior-only island (onboarding) has none, so it renders in
			// place and manages its own portal.
			hasSlot: !!document.getElementById( 'pd-slot-' + props.name )
		};
	},
	computed: {
		target() {
			if ( this.activeInternal ) {
				return '#personal-dashboard-teleport';
			}
			// Module names carry dots, so escape them for the selector; an
			// unescaped '#pd-slot-ext.foo.bar' reads the dots as class selectors
			// and the teleport target is never found.
			return this.hasSlot ? '#pd-slot-' + CSS.escape( this.name ) : null;
		},
		detail() {
			// Mobile shows a compact summary in the card and the full body in
			// the dialog; desktop is always full. A focused render is the module's
			// whole page, so it shows the full body there too.
			const full = this.activeInternal || this.focused;
			return ( this.platform === 'mobile' && !full ) ? 'compact' : 'full';
		}
	},
	watch: {
		active( value ) {
			// The dialog only mounts its teleport target when it opens, so defer
			// the move a tick until that target exists.
			this.$nextTick( () => {
				this.activeInternal = value;
			} );
		}
	}
} );
</script>
