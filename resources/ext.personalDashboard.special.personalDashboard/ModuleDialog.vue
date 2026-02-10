<template>
	<cdx-dialog
		:open="open.value"
		:title="title"
		class="personal-dashboard-container">
		<template #header>
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
		</template>
		<div class="personal-dashboard-module-body">
			<slot>Module Content</slot>
		</div>
	</cdx-dialog>
</template>

<script>
const { defineComponent } = require( 'vue' );

const {
	CdxIcon,
	CdxDialog
} = require( './codex.js' );
const {
	cdxIconArrowPrevious
} = require( './icons.json' );

module.exports = defineComponent( {
	components: {
		CdxIcon,
		CdxDialog
	},
	props: {
		title: {
			type: String,
			required: true
		},
		open: {
			type: Object,
			required: true
		}
	},
	emits: [ 'close' ],
	setup() {
		return {
			cdxIconArrowPrevious
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-container.cdx-dialog {
	/* @TODO: replace placeholder value */
	max-width: @max-width-breakpoint-tablet;

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
}
</style>
