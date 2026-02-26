<template>
	<teleport to=".mw-body">
		<div
			v-if="open.value"
			:title="title"
			:data-module-name="module"
			:class="containerClasses"
		>
			<div class="personal-dashboard-module">
				<span class="personal-dashboard-module-header">
					<cdx-icon
						class="personal-dashboard-module-header-back-icon"
						:icon="cdxIconArrowPrevious"
						@click="$emit( 'close' )"
					></cdx-icon>
					<div class="personal-dashboard-module-header-text">
						{{ title }}
					</div>
				</span>
			</div>
			<div class="personal-dashboard-module-body">
				<slot>Module Content</slot>
			</div>
		</div>
	</teleport>
</template>

<script>
const { defineComponent } = require( 'vue' );

const { CdxIcon } = require( './codex.js' );
const {
	cdxIconArrowPrevious
} = require( './icons.json' );

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
		open: {
			type: Object,
			required: true
		}
	},
	emits: [ 'close' ],
	setup( props ) {
		const containerClasses = [
			`personal-dashboard-module-route-${ props.module }`,
			'personal-dashboard-module-route',
			'personal-dashboard-container'
		];
		return {
			cdxIconArrowPrevious,
			containerClasses
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-container.personal-dashboard-module-route {
	/* @TODO: replace placeholder value */
	max-width: @max-width-breakpoint-tablet;
	margin-left: auto;
	margin-right: auto;

	.cdx-dialog__header {
		padding: unset;
	}

	.personal-dashboard-module {
		border: unset;

		.personal-dashboard-module-header {
			font-size: 1.2em;
			text-align: center;
			margin: 0 2rem;
			height: 2.4em;
		}
	}

	.ext-personal-dashboard-recent-activity-footer {
		display: none;
	}
}
</style>
