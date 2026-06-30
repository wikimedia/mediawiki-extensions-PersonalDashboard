<template>
	<cdx-dialog
		v-model:open="openInternal"
		:title="dialogTitle"
		:use-close-button="true"
		class="personal-dashboard-dialog">
		<template #header>
			<cdx-button
				weight="quiet"
				:aria-label="msgCloseButtonLabel"
				@click="openInternal = false">
				<cdx-icon :icon="cdxIconArrowPrevious"></cdx-icon>
			</cdx-button>

			<div class="cdx-dialog__header__title-group">
				<h2 class="cdx-dialog__header__title">
					{{ dialogTitle }}
				</h2>
			</div>

			<div class="cdx-dialog__header__placeholder"></div>
		</template>

		<div id="personal-dashboard-teleport"></div>
	</cdx-dialog>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxDialog, CdxIcon } = require( './codex.js' );
const { cdxIconArrowPrevious } = require( './icons.json' );

module.exports = defineComponent( {
	name: 'ModuleDialog',
	components: {
		CdxButton,
		CdxDialog,
		CdxIcon
	},
	props: {
		open: {
			type: Boolean,
			required: true
		}
	},
	emits: [ 'update:open' ],
	setup() {
		return {
			msgCloseButtonLabel: mw.msg( 'cdx-dialog-close-button-label' ),
			cdxIconArrowPrevious
		};
	},
	computed: {
		openInternal: {
			get() {
				return this.open;
			},
			set( value ) {
				this.$emit( 'update:open', value );
			}
		},
		dialogTitle() {
			return ( this.$route.meta || {} ).header || '';
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

	.cdx-dialog__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: @spacing-50;
		box-sizing: border-box;
		width: 100%;

		&__title {
			text-overflow: ellipsis;
			overflow: hidden;
			white-space: nowrap;

			&-group {
				flex-grow: 0;
				overflow: hidden;
			}
		}

		&__placeholder {
			width: 32px;
			height: 32px;
		}
	}
}
</style>
