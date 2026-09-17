<template>
	<div ref="moduleRef">
		<feed-panel
			v-bind="{ ...$attrs, ...feedState }"
			module-name="ext.personalDashboard.reviewChanges"
			summary-mode="card"
			footer-id="personal-dashboard-go-to-recentchanges"
			:footer-label="footerLabel"
			:progress-bar-aria-label="progressBarAriaLabel"
			@load-more="loadMore">
			<template #item="{ item, isNarrow }">
				<list-card v-bind="item" :is-narrow="isNarrow"></list-card>
			</template>
		</feed-panel>
	</div>
</template>

<script>
const { defineComponent, ref, watch } = require( 'vue' );
const { FeedPanel, FULL_LIMIT } = require( 'ext.personalDashboard.common' );
const { useReviewChangesFeed } = require( './composables/useReviewChangesFeed.js' );
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
		const { feedState, load, loadMore } = useReviewChangesFeed();

		// Fires once the module has both scrolled into view and its feed has
		// finished loading, so a module still off-screen when the fetch resolves
		// doesn't count as an impression (T417757), and the footer link the
		// instrument looks for isn't queried before load() renders it.
		let hasIntersected = false;
		let hasFiredLoadedHook = false;
		function fireLoadedHookWhenReady() {
			if ( hasFiredLoadedHook || !hasIntersected || feedState.isLoading ) {
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

		watch( () => feedState.isLoading, fireLoadedHookWhenReady );

		return {
			moduleRef,
			observer,
			feedState,
			load,
			loadMore,
			footerLabel: mw.msg( 'personal-dashboard-risky-article-edits-mobile-summary-footer-link-text' ),
			progressBarAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-progress-bar-aria-label' )
		};
	},
	mounted() {
		this.observer.observe( this.moduleRef );
		this.load( FULL_LIMIT );
	},
	unmounted() {
		this.observer.disconnect();
	}
} );
</script>
