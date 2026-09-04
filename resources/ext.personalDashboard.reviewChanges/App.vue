<template>
	<div ref="moduleRef">
		<feed-panel
			v-bind="{ ...$attrs, ...reviewChangesStore.feedState }"
			module-name="ext.personalDashboard.reviewChanges"
			summary-mode="card"
			footer-id="personal-dashboard-go-to-recentchanges"
			:footer-label="footerLabel"
			:footer-aria-label="footerLabel"
			:progress-bar-aria-label="progressBarAriaLabel">
			<template #item="{ item, isNarrow }">
				<!-- id, minor, bot, new and tags are FeedItem fields ListCard never
					declares as props, so left in place they would fall through as
					DOM attributes (id malformed, the rest meaningless on a card). -->
				<list-card
					v-bind="{
						...item,
						id: undefined,
						minor: undefined,
						bot: undefined,
						new: undefined,
						tags: undefined
					}"
					:pages="reviewChangesStore.pages"
					:is-narrow="isNarrow">
				</list-card>
			</template>
		</feed-panel>
	</div>
</template>

<script>
const { defineComponent, ref, watch } = require( 'vue' );
const { FeedPanel, FULL_LIMIT } = require( 'ext.personalDashboard.common' );
const { useReviewChangesStore } = require( './store/reviewChangesStore.js' );
const ListCard = require( './components/ListCard.vue' );

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
		const reviewChangesStore = useReviewChangesStore();

		// Fires once the module has both scrolled into view and its feed has
		// finished loading, so a module still off-screen when the fetch resolves
		// doesn't count as an impression (T417757), and the footer link the
		// instrument looks for isn't queried before fetchRecentActivity() renders it.
		let hasIntersected = false;
		let hasFiredLoadedHook = false;
		function fireLoadedHookWhenReady() {
			if ( hasFiredLoadedHook || !hasIntersected || reviewChangesStore.isLoading ) {
				return;
			}
			hasFiredLoadedHook = true;
			mw.hook( 'personaldashboard.recentactivity.loaded' ).fire();
		}

		const observer = new IntersectionObserver( ( entries ) => {
			if ( entries[ 0 ].isIntersecting ) {
				hasIntersected = true;
				fireLoadedHookWhenReady();
			}
		} );

		watch( () => reviewChangesStore.isLoading, fireLoadedHookWhenReady );

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
	},
	unmounted() {
		this.observer.disconnect();
	}
} );
</script>
