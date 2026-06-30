<template>
	<a
		v-if="rendermode.startsWith( 'mobile-' ) && module.expandable"
		:href="`#/${ module.name }`"
		class="personal-dashboard-module-anchor">
		<module-card
			:module
			:rendermode
			:active>
		</module-card>
	</a>

	<module-card
		v-else
		:module
		:rendermode
		:active>
	</module-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const ModuleCard = require( './ModuleCard.vue' );

module.exports = defineComponent( {
	name: 'ModuleAnchor',
	components: {
		ModuleCard
	},
	props: {
		module: {
			type: Object,
			required: true
		},
		rendermode: {
			type: String,
			required: true
		},
		active: {
			type: Boolean,
			required: true
		}
	}
} );
</script>
