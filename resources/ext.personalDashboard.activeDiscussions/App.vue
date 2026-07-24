<template>
	<div :class="{ 'personal-dashboard-active-discussions--mobile': isSummary }">
		<div v-if="loading">
			<cdx-progress-bar inline :aria-label="progressBarAriaLabel"></cdx-progress-bar>
		</div>

		<div v-else-if="error">
			<p>Error: {{ error.message }}</p>
		</div>

		<div
			v-if="activeDiscussionsResult"
			class="personal-dashboard-active-discussions__container">
			<template v-if="isSummary">
				<list-card-mobile
					v-for="ac in activeDiscussionsResult.slice( 0, 1 )"
					v-bind="ac"
					:key="ac.discussionTitle">
				</list-card-mobile>
			</template>

			<template v-else>
				<list-card
					v-for="ac in activeDiscussionsResult"
					v-bind="ac"
					:key="ac.discussionTitle"
					:is-mobile="isMobile">
				</list-card>
			</template>
		</div>

		<div
			v-if="isSummary"
			class="personal-dashboard-active-discussions__footer">
			<cdx-button
				id="personal-dashboard-go-to-active-discussions"
				:aria-label="buttonAriaLabel"
				action="progressive"
				weight="primary">
				{{ footerLinkText }}
			</cdx-button>
		</div>
	</div>
</template>

<script>
const ListCard = require( './components/ListCard.vue' );
const ListCardMobile = require( './components/ListCardMobile.vue' );
const { CdxButton, CdxProgressBar } = require( './codex.js' );
const { defineComponent } = require( 'vue' );
const useFetchActiveDiscussionsResult = require( './composables/useFetchActiveDiscussionsResult.js' );

module.exports = defineComponent( {
	components: { ListCard, ListCardMobile, CdxButton, CdxProgressBar },
	// Branches on detail only (compact summary vs full); no other dashboard app
	// props reach its markup.
	inheritAttrs: false,
	props: {
		detail: {
			type: String,
			default: 'full'
		}
	},
	setup() {
		const isMobile = mw.config.get( 'wgMFMode' ) !== null;
		const limit = isMobile ? 10 : 3;

		const {
			activeDiscussionsResult,
			loading,
			error,
			fetchActiveDiscussions
		} = useFetchActiveDiscussionsResult();

		return {
			isMobile,
			limit,
			activeDiscussionsResult,
			loading,
			error,
			fetchActiveDiscussions,
			footerLinkText: mw.message( 'personal-dashboard-active-discussions-mobile-summary-footer-link-text' ).parse(),
			buttonAriaLabel: mw.msg( 'personal-dashboard-active-discussions-mobile-summary-footer-button-aria-label' ),
			progressBarAriaLabel: mw.msg( 'personal-dashboard-active-discussions-progress-bar-aria-label' )
		};
	},
	computed: {
		isSummary() {
			return this.detail === 'compact';
		}
	},
	mounted() {
		this.fetchActiveDiscussions( this.limit );
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard {
	&-module {
		&-activeDiscussions &-section-body {
			margin: @spacing-0;
		}
	}

	&-active-discussions {
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
