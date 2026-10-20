<template>
	<cdx-dialog
		v-model:open="openInternal"
		:title="title"
		class="personal-dashboard-dialog">
		<template #header>
			<focused-header
				:title="title"
				:back-href="backHref"
				:actions-target-id="DIALOG_HEADER_TARGET_ID"
				@back="openInternal = false">
			</focused-header>
		</template>

		<div :id="DIALOG_TARGET_ID"></div>
	</cdx-dialog>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxDialog } = require( './codex.js' );
const FocusedHeader = require( './FocusedHeader.vue' );
const { DIALOG_TARGET_ID, DIALOG_HEADER_TARGET_ID } = require( './teleportTargets.js' );

module.exports = defineComponent( {
	name: 'ModuleDialog',
	components: {
		CdxDialog,
		FocusedHeader
	},
	props: {
		open: {
			type: Boolean,
			required: true
		},
		title: {
			type: String,
			default: ''
		},
		// The dashboard's own URL on a focused whole-page render, or '' when the
		// dashboard is already behind the dialog and closing it is enough.
		backHref: {
			type: String,
			default: ''
		}
	},
	emits: [ 'update:open' ],
	setup() {
		return { DIALOG_TARGET_ID, DIALOG_HEADER_TARGET_ID };
	},
	computed: {
		openInternal: {
			get() {
				return this.open;
			},
			set( value ) {
				this.$emit( 'update:open', value );
			}
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import '../ext.personalDashboard.styles/variables.less';

.personal-dashboard-dialog.cdx-dialog {
	width: @size-full;
	height: @size-full;
	max-width: none;
	max-height: none;
	border: 0;

	// FocusedHeader.vue draws the header and its divider. Remove the Codex header
	// padding, and the divider that Codex adds when the body scrolls.
	.cdx-dialog__header {
		border-bottom: 0;
		padding: @spacing-0;
	}

	// On the header itself, so the divider spans the full width.
	.personal-dashboard-focused-header {
		padding: @personal-dashboard-focused-header-padding;

		// The design centers the title.
		&__title {
			.personal-dashboard-centered-header-title();
		}
	}

	// 16px on every side, as in the design, not the Codex dialog padding. A long
	// word breaks, as in a module, because the island content is outside one.
	.cdx-dialog__body {
		padding: @spacing-100;
		overflow-wrap: anywhere;
	}
}
</style>
