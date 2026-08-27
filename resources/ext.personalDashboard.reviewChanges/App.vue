<template>
	<div ref="moduleRef">
		<feed-panel
			v-bind="{ ...$attrs, ...reviewChangesStore.feedState }"
			module-name="ext.personalDashboard.reviewChanges"
			summary-mode="card"
			:footer-label="footerLabel"
			:footer-aria-label="footerLabel"
			:progress-bar-aria-label="progressBarAriaLabel">
			<template #item="{ item, isNarrow }">
				<list-card
					v-bind="item"
					:pages="reviewChangesStore.pages"
					:is-narrow="isNarrow">
				</list-card>
			</template>
		</feed-panel>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { FeedPanel } = require( 'ext.personalDashboard.common' );
const { useReviewChangesStore } = require( './store/reviewChangesStore.js' );
const ListCard = require( './components/ListCard.vue' );

// The dialog and the card share one component instance (teleported, not
// remounted), so a fixed fetch covers both: the panel slices it down to a
// preview, the dialog shows it whole, with no re-fetch on the summary/full
// transition.
const FULL_LIMIT = 10;

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
		const moduleRef = ref();
		const observer = new IntersectionObserver( ( entries ) => {
			if ( entries[ 0 ].isIntersecting ) {
				mw.hook( 'personaldashboard.recentactivity.loaded' ).fire();
			}
		} );

		const reviewChangesStore = useReviewChangesStore();

		return {
			moduleRef,
			observer,
			reviewChangesStore,
			footerLabel: mw.msg( 'personal-dashboard-risky-article-edits-mobile-summary-footer-link-text' ),
			progressBarAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-progress-bar-aria-label' )
		};
	},
	mounted() {
		this.observer.observe( this.moduleRef );
		this.reviewChangesStore.fetchRecentActivity( FULL_LIMIT );
	}
} );
</script>
