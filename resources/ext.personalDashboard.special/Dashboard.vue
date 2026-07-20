<template>
	<module-dialog
		v-model:open="open"
		:title="activeHeader">
	</module-dialog>

	<island-mount
		v-for="island in islands"
		:key="island.name"
		:name="island.name"
		:component="island.component"
		:platform="platform"
		:focused="island.name === focusedModule"
		:active="island.name === activeName">
	</island-mount>
</template>

<script>
const { defineComponent } = require( 'vue' );
const IslandMount = require( './IslandMount.vue' );
const ModuleDialog = require( './ModuleDialog.vue' );

// The server owns the group and card tree now; this app owns only the
// dialog and the islands it teleports into the server's mount slots.
module.exports = defineComponent( {
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Dashboard',
	components: {
		IslandMount,
		ModuleDialog
	},
	props: {
		islands: {
			type: Array,
			default: () => []
		},
		platform: {
			type: String,
			default: 'desktop'
		},
		// The focused module's name on a whole-page render, or null on the grouped
		// dashboard. An island matching it is the whole page, so it shows its full
		// body rather than the compact mobile card summary.
		focusedModule: {
			type: String,
			default: null
		}
	},
	computed: {
		activeName() {
			// No dialog on a focused render: the module is the whole page, so a
			// stale hash naming it must not spring a modal over the page.
			if ( this.focusedModule ) {
				return '';
			}
			// A step within a module lives in the URL hash and belongs to that
			// module's own routing, not the dashboard app's dialog: a policy hash
			// like #neutral-point-of-view opens the policies walkthrough, so the
			// module dialog must stay shut over its card.
			if ( this.$route.hash ) {
				return '';
			}
			// Only a known island opens the dialog; an unknown or server-rendered
			// name in the path would otherwise open an empty, untitled dialog.
			const name = this.$route.params.module || '';
			return this.islands.some( ( island ) => island.name === name ) ? name : '';
		},
		activeHeader() {
			const active = this.islands.find( ( island ) => island.name === this.activeName );
			return active ? active.header : '';
		},
		open: {
			get() {
				return !!this.activeName;
			},
			set( value ) {
				if ( !value ) {
					this.$router.push( '/' );
				}
			}
		}
	}
} );
</script>
