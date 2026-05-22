<template>
	<div ref="moduleRef">
		<div v-if="reviewChangesStore.isLoading">
			<cdx-progress-bar inline :aria-label="progressBarAriaLabel"></cdx-progress-bar>
		</div>

		<div v-else-if="reviewChangesStore.error">
			<p>Error: {{ reviewChangesStore.error.message }}</p>
		</div>

		<template
			v-else-if="reviewChangesStore &&
				reviewChangesStore.feed &&
				reviewChangesStore.pages">
			<div
				v-if="rendermode === 'mobile-summary'"
				class="personal-dashboard-review-changes__container--mobile">
				<list-card-mobile
					v-bind="reviewChangesStore.feed[ 0 ]"
					:pages="reviewChangesStore.pages">
				</list-card-mobile>
			</div>

			<div
				v-else
				class="personal-dashboard-review-changes__container">
				<list-card
					v-for="rc in reviewChangesStore.feed"
					v-bind="rc"
					:key="`${rendermode}-${rc.feedorigin}-${rc.revid}`"
					:pages="reviewChangesStore.pages"
					:is-mobile="isMobile">
				</list-card>
			</div>
		</template>

		<!-- eslint-disable max-len -->
		<div v-if="rendermode === 'mobile-summary'">
			<cdx-button
				:aria-label="buttonAriaLabel"
				action="progressive"
				weight="primary">
				<span v-i18n-html:personal-dashboard-risky-article-edits-mobile-summary-footer-link-text></span>
			</cdx-button>
		</div>

		<div
			v-else-if="rendermode === 'mobile-details'"
			class="personal-dashboard-module-footer">
			<span
				id="personal-dashboard-go-to-recentchanges"
				v-i18n-html:personal-dashboard-risky-article-edits-footer-preamble>
			</span>
		</div>
		<!-- eslint-enable max-len -->
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
		rendermode: {
			type: String,
			default: ''
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
		&-riskyArticleEdits&-desktop & {
			&-footer {
				border-top: @border-subtle;
				padding: @spacing-100;
				margin: 0;
			}

			&-body {
				margin: @spacing-100 0 0 0;
			}
		}

		&-riskyArticleEdits &-body .cdx-button {
			width: 100%;
			max-width: none;
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
	}

	@media screen and ( max-width: @max-width-breakpoint-mobile ) {
		&-module-route-riskyArticleEdits &-review-changes {
			&__message,
			&__container {
				margin-left: -@spacing-100;
				margin-right: -@spacing-100;
			}
		}
	}
}
</style>
