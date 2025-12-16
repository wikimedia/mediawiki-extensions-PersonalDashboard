<template>
	<cdx-toggle-button
		ref="triggerElement"
		v-model="showPopover"
		:aria-label="buttonAriaLabel"
		class="cdx-button--icon-only"
		quiet>
		<cdx-icon :icon="cdxIconInfoFilled"></cdx-icon>
	</cdx-toggle-button>

	<cdx-popover
		v-model:open="showPopover"
		:anchor="triggerElement"
		:placement="popoverPlacement"
		:render-in-place="true"
		:title="title"
		:use-close-button="true"
		:icon="cdxIconInfoFilled"
		class="ext-personal-dashboard-moderation-card-header-popover"
	>
		{{ subtitle }}
		<ul>
			<li>{{ articleText }}</li>
			<li>{{ editSummaryText }}</li>
			<li>{{ authorText }}</li>
			<li>{{ byteText }}</li>
		</ul>
	</cdx-popover>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxIcon, CdxPopover, CdxToggleButton } = require( '../codex.js' );
const { cdxIconInfoFilled } = require( '../icons.json' );

module.exports = defineComponent( {
	name: 'InfoPopover',
	components: { CdxIcon, CdxPopover, CdxToggleButton },
	setup() {
		return {
			showPopover: ref( false ),
			triggerElement: ref(),
			title: mw.msg( 'personal-dashboard-risky-article-edits-info-title' ),
			subtitle: mw.msg( 'personal-dashboard-risky-article-edits-info-subtitle' ),
			articleText: mw.msg( 'personal-dashboard-risky-article-edits-info-article-title' ),
			authorText: mw.msg( 'personal-dashboard-risky-article-edits-info-author' ),
			byteText: mw.msg( 'personal-dashboard-risky-article-edits-info-bytes' ),
			editSummaryText: mw.msg( 'personal-dashboard-risky-article-edits-info-edit-summary' ),
			buttonAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-info-button-aria-label' ),
			cdxIconInfoFilled
		};
	},
	computed: {
		popoverPlacement() {
			if ( mw.config.get( 'skin' ) === 'minerva' ) {
				return 'left-end';
			}
			return 'bottom';
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-module-header > .ext-personal-dashboard-mobile-details-header-icon > .cdx-toggle-button:enabled {
	color: @color-subtle;
}

.personal-dashboard-module-footer {
	color: @color-subtle;
}

.ext-personal-dashboard-moderation-card-header-popover {
	text-align: left;

	li {
		margin-bottom: unset;
	}
}
</style>
