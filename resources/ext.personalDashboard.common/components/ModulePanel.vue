<template>
	<cdx-popover
		v-if="isNarrow"
		v-model:open="openInternal"
		class="personal-dashboard-module-panel__popover"
		:anchor="anchor"
		:title="title"
		:use-close-button="true"
		:use-bottom-sheet="true"
		placement="bottom-end">
		<slot></slot>
	</cdx-popover>

	<cdx-dialog
		v-else
		v-model:open="openInternal"
		class="personal-dashboard-module-panel__dialog"
		:title="title"
		:use-close-button="true">
		<slot></slot>
	</cdx-dialog>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxDialog, CdxPopover } = require( '../codex.js' );

/**
 * A panel of explanatory copy opened from a module's header menu: a dialog on a
 * wide viewport, a bottom sheet on a narrow one, per T433725.
 *
 * The switch lives here rather than in each caller so the two presentations
 * cannot drift apart. `isNarrow` comes in as a prop because useViewport() lives
 * in ext.personalDashboard.special, which this module must not depend on; it
 * reads the same breakpoint CdxPopover uses to decide the bottom sheet, so the
 * two flip together.
 */
module.exports = defineComponent( {
	name: 'ModulePanel',
	components: { CdxDialog, CdxPopover },
	props: {
		open: {
			type: Boolean,
			default: false
		},
		// Title at the top of the panel.
		title: {
			type: String,
			required: true
		},
		// The element the popover positions against. CdxPopover warns without
		// one even in bottom sheet mode, where nothing is positioned.
		anchor: {
			type: Object,
			default: null
		},
		isNarrow: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'update:open' ],
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

.personal-dashboard-module-panel {
	// A bottom sheet stretches to its backdrop, which is the full viewport, and
	// the panel itself is position: static with no width. An explicit width would
	// pin it to one edge instead, so only the floating variant takes one. That
	// variant is reachable here only where this component's breakpoint and
	// CdxPopover's own disagree by a pixel.
	//
	// 32rem is Codex's own max clip width. Its size middleware sets an inline
	// max-width below that when there is less room beside the anchor.
	&__popover:not( .cdx-popover--bottom-sheet ) {
		width: 32rem;
	}
}
</style>
