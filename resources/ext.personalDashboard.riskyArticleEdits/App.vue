<template>
	<div ref="moduleRef" :class="{ 'personal-dashboard-review-changes--mobile': isSummary }">
		<div v-if="reviewChangesStore.isLoading">
			<cdx-progress-bar inline :aria-label="progressBarAriaLabel"></cdx-progress-bar>
		</div>

		<div v-else-if="reviewChangesStore.error">
			<p>Error: {{ reviewChangesStore.error.message }}</p>
		</div>

		<div
			v-else-if="reviewChangesStore &&
				reviewChangesStore.feed &&
				reviewChangesStore.pages"
			class="personal-dashboard-review-changes__container">
			<list-card-mobile
				v-if="isSummary"
				v-bind="reviewChangesStore.feed[ 0 ]"
				:pages="reviewChangesStore.pages">
			</list-card-mobile>

			<template v-else>
				<list-card
					v-for="rc in reviewChangesStore.feed"
					v-bind="rc"
					:key="`${detail}-${rc.feedorigin}-${rc.revid}`"
					:pages="reviewChangesStore.pages"
					:is-mobile="isMobile">
				</list-card>
			</template>
		</div>

		<div class="personal-dashboard-review-changes__footer">
			<!-- eslint-disable max-len -->
			<cdx-button
				v-if="isSummary"
				:aria-label="buttonAriaLabel"
				action="progressive"
				weight="primary">
				<span v-i18n-html:personal-dashboard-risky-article-edits-mobile-summary-footer-link-text></span>
			</cdx-button>

			<span
				v-else-if="isFullDetail"
				id="personal-dashboard-go-to-recentchanges"
				v-i18n-html:personal-dashboard-risky-article-edits-footer-preamble>
			</span>
			<!-- eslint-enable max-len -->
		</div>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton, CdxProgressBar } = require( './codex.js' );
const { useReviewChangesStore } = require( './store/reviewChangesStore.js' );
const ListCard = require( './components/ListCard.vue' );
const ListCardMobile = require( './components/ListCardMobile.vue' );

module.exports = defineComponent( {
	components: {
		CdxButton,
		CdxProgressBar,
		ListCard,
		ListCardMobile
	},
	props: {
		detail: {
			type: String,
			default: 'full'
		}
	},
	setup() {
		const moduleRef = ref();
		// eslint-disable-next-line compat/compat
		const observer = new IntersectionObserver( ( entries ) => {
			if ( entries[ 0 ].isIntersecting ) {
				mw.hook( 'personaldashboard.recentactivity.loaded' ).fire();
			}
		} );

		const isMobile = mw.config.get( 'wgMFMode' ) !== null;
		const limit = isMobile ? 10 : 5;
		const reviewChangesStore = useReviewChangesStore();

		return {
			moduleRef,
			observer,
			isMobile,
			limit,
			reviewChangesStore,
			buttonAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-mobile-summary-footer-link-text' ),
			progressBarAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-progress-bar-aria-label' )
		};
	},
	computed: {
		isSummary() {
			return this.detail === 'compact';
		},
		isFullDetail() {
			return this.detail === 'full';
		}
	},
	mounted() {
		this.observer.observe( this.moduleRef );
		this.reviewChangesStore.fetchRecentActivity( this.limit );
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard {
	&-module {
		&-riskyArticleEdits &-section-body {
			margin: @spacing-0;
		}
	}

	&-review-changes {
		&__container {
			display: flex;
			flex-direction: column;
			gap: @spacing-25;
			background: @background-color-neutral;
			padding: @spacing-25;
		}

		&__footer {
			margin: @spacing-50 @spacing-100 @spacing-100 @spacing-100;

			.cdx-button {
				width: 100%;
				max-width: none;
			}
		}

		&--mobile {
			margin: @spacing-100;
		}

		&--mobile &__container {
			padding: @spacing-0;
		}

		&--mobile &__footer {
			margin: @spacing-0;
		}
	}
}
</style>
