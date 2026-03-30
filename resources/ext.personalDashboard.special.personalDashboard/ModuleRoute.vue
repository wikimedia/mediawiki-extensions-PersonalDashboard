<template>
	<teleport to="#mw-content-text">
		<div
			v-show="open.value"
			:title="title"
			:data-module-name="module"
			:data-mode="rendermode"
			:class="`personal-dashboard-container personal-dashboard-route open-${open.value}`">
			<div :class="moduleClasses">
				<div class="personal-dashboard-module-header">
					<cdx-icon
						class="personal-dashboard-module-header-back-icon"
						:icon="cdxIconArrowPrevious"
						@click="$emit( 'close' )">
					</cdx-icon>

					<div class="personal-dashboard-module-header-text">
						{{ title }}
					</div>

					<div class="personal-dashboard-module-header-info-icon"></div>
				</div>

				<div class="personal-dashboard-module-body">
					<slot>Module Content</slot>
				</div>
			</div>
		</div>
	</teleport>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxIcon } = require( './codex.js' );
const { cdxIconArrowPrevious } = require( './icons.json' );

module.exports = defineComponent( {
	components: {
		CdxIcon
	},
	props: {
		title: {
			type: String,
			required: true
		},
		module: {
			type: String,
			required: true
		},
		rendermode: {
			type: String,
			required: true
		},
		open: {
			type: Object,
			required: true
		}
	},
	emits: [ 'close' ],
	setup( props ) {
		const moduleClasses = [
			'personal-dashboard-module',
			'personal-dashboard-module-route',
			`personal-dashboard-module-route-${ props.module }`
		];
		return {
			cdxIconArrowPrevious,
			moduleClasses
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-container.personal-dashboard-route.open-false {
	display: none;
}
</style>
