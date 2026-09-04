<template>
	<feed-panel
		v-bind="{ ...$attrs, ...feedState }"
		module-name="ext.personalDashboard.activeDiscussions"
		summary-mode="viewport"
		footer-id="personal-dashboard-go-to-active-discussions"
		:footer-label="footerLabel"
		:footer-aria-label="footerAriaLabel"
		:progress-bar-aria-label="progressBarAriaLabel">
		<template #item="{ item, isNarrow }">
			<!-- id is minted only for FeedPanel's :key; ListCard never declares it as
				a prop, so left in place it would fall through as a malformed DOM id. -->
			<list-card v-bind="{ ...item, id: undefined }" :is-narrow="isNarrow"></list-card>
		</template>
	</feed-panel>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { FeedPanel, FULL_LIMIT } = require( 'ext.personalDashboard.common' );
const ListCard = require( './components/ListCard.vue' );
const { useActiveDiscussionsFeed } = require( './composables/useActiveDiscussionsFeed.js' );

module.exports = defineComponent( {
	components: {
		FeedPanel,
		ListCard
	},
	// The island props (detail, focused, active, isNarrow) are never declared
	// here: they ride in $attrs and are forwarded untouched to the scaffold,
	// which owns the compact/full derivation. This module only decides which
	// rule it follows, via summary-mode, and hands over the feed contract.
	inheritAttrs: false,
	setup() {
		const { feedState, load } = useActiveDiscussionsFeed();

		return {
			feedState,
			load,
			footerLabel: mw.msg( 'personal-dashboard-active-discussions-mobile-summary-footer-link-text' ),
			footerAriaLabel: mw.msg( 'personal-dashboard-active-discussions-mobile-summary-footer-button-aria-label' ),
			progressBarAriaLabel: mw.msg( 'personal-dashboard-active-discussions-progress-bar-aria-label' )
		};
	},
	mounted() {
		this.load( FULL_LIMIT );
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-module-activeDiscussions .personal-dashboard-module-section-body {
	margin: @spacing-0;
}
</style>
