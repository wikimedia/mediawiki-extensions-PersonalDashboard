<template>
	<teleport
		:to="activeInternal ? '#personal-dashboard-teleport' : undefined"
		:disabled="!activeInternal">
		<component :is="viewport" :rendermode></component>
	</teleport>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );

module.exports = defineComponent( {
	name: 'ModuleViewport',
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
	},
	async setup( props ) {
		const viewport = props.module.component;

		return {
			activeInternal: ref( props.active ),
			viewport: typeof viewport === 'function' ?
				await viewport() :
				viewport
		};
	},
	watch: {
		active( value ) {
			// Avoid race condition of teleporting before dialog has mounted
			this.$nextTick( () => {
				this.activeInternal = value;
			} );
		}
	}
} );
</script>
