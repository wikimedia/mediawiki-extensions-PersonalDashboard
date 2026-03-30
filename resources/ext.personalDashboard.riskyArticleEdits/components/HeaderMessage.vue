<template>
	<cdx-message
		:allow-user-dismiss="true"
		class="ext-personal-dashboard-recent-activity-header"
		@user-dismissed="$emit( 'dismissed' )">
		{{ message }}

		<cdx-toggle-button
			ref="triggerElement"
			v-model="showPopover"
			:aria-label="buttonAriaLabel"
			class="cdx-button--icon-only ext-personal-dashboard-recent-activity-header-link"
			quiet>
			{{ messagePopover }}
		</cdx-toggle-button>

		<cdx-popover
			v-if="triggerElement"
			v-model:open="showPopover"
			:anchor="triggerElement"
			:placement="popoverPlacement"
			:primary-action="primaryAction"
			:title="messagePopover"
			:use-close-button="true"
			class="ext-personal-dashboard-recent-activity-header-popover"
			@primary="showPopover = false">
			<!-- eslint-disable-next-line max-len -->
			<div v-i18n-html:personal-dashboard-risky-article-edits-subheader-info-popover="[ popoverUrl ]"></div>
		</cdx-popover>
	</cdx-message>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxMessage, CdxPopover, CdxToggleButton } = require( '../codex.js' );

module.exports = defineComponent( {
	name: 'HeaderMessage',
	components: { CdxMessage, CdxPopover, CdxToggleButton },
	props: {
		isMobile: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'dismissed' ],
	setup() {
		const confirmMessage = mw.message( 'personal-dashboard-risky-article-edits-subheader-info-popover-confirm' );
		return {
			showPopover: ref( false ),
			triggerElement: ref( null ),
			buttonAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-info-button-aria-label' ),
			primaryAction: { label: confirmMessage, actionType: 'progressive' }
		};
	},
	computed: {
		message() {
			return mw.message( 'personal-dashboard-risky-article-edits-subheader-info' ).text();
		},
		messagePopover() {
			return mw.message( 'personal-dashboard-risky-article-edits-subheader-info-popover-title' ).text();
		},
		popoverUrl() {
			return 'https://meta.wikimedia.org/wiki/Machine_learning_models/Production/Language-agnostic_revert_risk';
		},
		popoverPlacement() {
			return this.isMobile ? 'right' : 'bottom';
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-personal-dashboard-recent-activity-header {
	.cdx-toggle-button&-link {
		color: @color-progressive;
		padding: @spacing-0;
		height: @size-125;
		min-height: @size-125;
	}

	&-popover {
		width: @size-3200;
	}
}
</style>
