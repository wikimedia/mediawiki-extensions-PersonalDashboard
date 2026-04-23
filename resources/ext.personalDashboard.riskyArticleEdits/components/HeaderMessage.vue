<template>
	<div class="personal-dashboard-review-changes__message">
		<cdx-message :allow-user-dismiss="true" @user-dismissed="$emit( 'dismissed' )">
			{{ message }}

			<cdx-toggle-button
				ref="triggerElement"
				v-model="showPopover"
				:aria-label="buttonAriaLabel"
				class="cdx-button--icon-only personal-dashboard-review-changes__message__link"
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
				class="personal-dashboard-review-changes__message__popover"
				@primary="showPopover = false">
				<!-- eslint-disable-next-line max-len -->
				<div v-i18n-html:personal-dashboard-risky-article-edits-subheader-info-popover="[ popoverUrl ]"></div>
			</cdx-popover>
		</cdx-message>
	</div>
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

.personal-dashboard-review-changes__message {
	background: @background-color-neutral;
	padding: @spacing-25;

	&__link:enabled {
		color: @color-progressive;
		padding: @spacing-0;
		height: @size-125;
		min-height: @size-125;
	}

	&__popover {
		width: @size-3200;
	}
}
</style>
