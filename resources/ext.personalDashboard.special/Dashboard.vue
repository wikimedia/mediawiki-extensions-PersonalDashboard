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
		}
	},
	computed: {
		activeName() {
			// Only a known island opens the dialog; an unknown or server-rendered
			// name in the hash would otherwise open an empty, untitled dialog.
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
