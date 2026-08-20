<template>
	<div ref="moduleRef">
		<div v-if="reviewChangesStore.isLoading">
			<cdx-progress-bar inline :aria-label="progressBarAriaLabel"></cdx-progress-bar>
		</div>

		<div v-else-if="reviewChangesStore.error">
			<p>Error: {{ reviewChangesStore.error.message }}</p>
		</div>

		<div
			v-show="reviewChangesStore.hasFeed"
			class="personal-dashboard-review-changes__container">
			<div
				class="personal-dashboard-review-changes__list"
				:class="{ 'personal-dashboard-review-changes__list--summary': isSummary }">
				<list-card
					v-for="rc in visibleFeed"
					v-bind="rc"
					:key="`${rc.feedorigin}-${rc.revid}`"
					:pages="reviewChangesStore.pages"
					:is-narrow="isNarrow">
				</list-card>
			</div>
		</div>

		<cdx-button
			v-if="isSummary"
			:aria-label="buttonAriaLabel"
			action="progressive"
			weight="quiet"
			class="personal-dashboard-review-changes__show-more"
			@click="showMore">
			<!-- eslint-disable max-len -->
			<span v-i18n-html:personal-dashboard-risky-article-edits-mobile-summary-footer-link-text></span>
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton, CdxProgressBar } = require( './codex.js' );
const { useReviewChangesStore } = require( './store/reviewChangesStore.js' );
const ListCard = require( './components/ListCard.vue' );

// The dialog and the card share one component instance (teleported, not
// remounted), so a fixed fetch covers both: the card slices it down to a
// preview, the dialog shows it whole, with no re-fetch on the summary/full
// transition.
const FULL_LIMIT = 10;
const SUMMARY_LIMIT = 3;

module.exports = defineComponent( {
	components: {
		CdxButton,
		CdxProgressBar,
		ListCard
	},
	inheritAttrs: false,
	props: {
		// True for a whole-page focused render or an open module dialog; the
		// card in the dashboard grid is the only place the summary shows,
		// on every viewport, per T426181's desktop+mobile convergence.
		focused: {
			type: Boolean,
			default: false
		},
		active: {
			type: Boolean,
			default: false
		},
		isNarrow: {
			type: Boolean,
			default: false
		}
	},
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
			buttonAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-mobile-summary-footer-link-text' ),
			progressBarAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-progress-bar-aria-label' )
		};
	},
	computed: {
		isSummary() {
			return !( this.focused || this.active );
		},
		visibleFeed() {
			return this.isSummary ?
				this.reviewChangesStore.feed.slice( 0, SUMMARY_LIMIT ) :
				this.reviewChangesStore.feed;
		}
	},
	methods: {
		showMore() {
			this.$router.push( '/ext.personalDashboard.reviewChanges' );
		}
	},
	mounted() {
		this.observer.observe( this.moduleRef );
		this.reviewChangesStore.fetchRecentActivity( FULL_LIMIT );
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard {
	&-review-changes {
		&__container {
			background: @background-color-neutral;
			padding: @spacing-25;
		}

		&__list {
			display: flex;
			flex-direction: column;
			gap: @spacing-25;

			// Hints at the "Show more" button below, scoped to the last card
			// itself so it can never leak into a card beneath it.
			&--summary .personal-dashboard-review-changes__card:last-child::after {
				content: '';
				position: absolute;
				bottom: 0;
				left: 0;
				right: 0;
				height: 64px;
				z-index: 2;
				pointer-events: none;
				background: linear-gradient( @background-color-transparent, @background-color-base );
			}
		}

		&__show-more.cdx-button {
			width: 100%;
			max-width: none;
			padding: @spacing-75 0;
		}
	}
}
</style>
