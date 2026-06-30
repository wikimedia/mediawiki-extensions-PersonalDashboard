<template>
	<module-dialog v-model:open="open"></module-dialog>

	<div ref="container" class="personal-dashboard-container">
		<div
			v-for="group in groups.filter( ( g ) => g.enabled )"
			v-show="!group.hidden"
			:key="group.name"
			:class="`${ groupPrefix }${ group.name }`">
			<div
				v-for="subgroup in group.subgroups.filter( ( s ) => s.enabled )"
				v-show="!subgroup.hidden"
				:key="subgroup.name"
				:class="`${ groupPrefix }${ group.name }-subgroup-${ subgroup.name }`">
				<module-anchor
					v-for="module in subgroup.modules.filter( ( m ) => m.enabled )"
					v-show="!module.hidden"
					:key="module.name"
					:module
					:rendermode="getRenderMode( module )"
					:active="isActive( module.name )">
				</module-anchor>
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const ModuleAnchor = require( './ModuleAnchor.vue' );
const ModuleDialog = require( './ModuleDialog.vue' );

module.exports = defineComponent( {
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Dashboard',
	components: {
		ModuleAnchor,
		ModuleDialog
	},
	props: {
		groups: {
			type: Array,
			default: () => []
		}
	},
	setup() {
		return {
			groupPrefix: 'personal-dashboard-group-'
		};
	},
	computed: {
		isMobile() {
			return mw.config.get( 'skin' ) === 'minerva';
		},
		open: {
			get() {
				return this.$route.path !== '/';
			},
			set( value ) {
				if ( !value ) {
					this.$router.push( '/' );
				}
			}
		}
	},
	methods: {
		isActive( name ) {
			return this.$route.name === name;
		},
		getRenderMode( module ) {
			return module.hidden ? 'hidden' :
				!this.isMobile ? 'desktop' :
					this.isActive( module.name ) ?
						'mobile-details' :
						'mobile-summary';
		}
	},
	mounted() {
		// Responsive breakpoint for dashboard layout on non-Minerva skins
		if ( !this.isMobile ) {
			new ResizeObserver( ( entries ) => {
				const entry = entries[ 0 ];

				// Toggle between 2-column and 1-column mode based on container width
				if ( entry.contentBoxSize[ 0 ].inlineSize < 800 ) {
					entry.target.classList.add( 'personal-dashboard-container__compact' );
				} else {
					entry.target.classList.remove( 'personal-dashboard-container__compact' );
				}
			} ).observe( this.$refs.container );
		}
	}
} );
</script>
