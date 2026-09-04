<template>
	<cdx-dialog
		v-model:open="openInternal"
		:title="title"
		class="personal-dashboard-dialog">
		<template #header>
			<focused-header
				:title="title"
				:back-href="backHref"
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
const { DIALOG_TARGET_ID } = require( './teleportTargets.js' );

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
		return { DIALOG_TARGET_ID };
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

.personal-dashboard-dialog.cdx-dialog {
	width: 100%;
	height: 100%;
	max-width: none;
	max-height: none;
	border: 0;

	// FocusedHeader.vue carries the real header spec now; zero the padding
	// Codex applies here so its spec is not applied twice.
	.cdx-dialog__header {
		padding: 0;
	}
}
</style>
