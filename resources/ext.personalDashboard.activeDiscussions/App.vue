<template>
	<div v-if="loading">
		<cdx-progress-bar inline :aria-label="progressBarAriaLabel"></cdx-progress-bar>
	</div>

	<div v-else-if="error">
		<p>Error: {{ error.message }}</p>
	</div>

	<template v-if="activeDiscussionsResult">
		<div v-if="rendermode === 'mobile-summary'">
			<list-card-mobile
				v-for="ac in activeDiscussionsResult.slice( 0, 1 )"
				v-bind="ac"
				:key="ac.discussionTitle">
			</list-card-mobile>
		</div>

		<div v-else class="personal-dashboard-active-discussions__container">
			<list-card
				v-for="ac in activeDiscussionsResult"
				v-bind="ac"
				:key="ac.discussionTitle"
				:is-mobile="isMobile">
			</list-card>
		</div>
	</template>

	<div
		v-if="rendermode === 'mobile-summary'"
		class="personal-dashboard-active-discussions__footer">
		<cdx-button
			id="personal-dashboard-go-to-active-discussions"
			:aria-label="buttonAriaLabel"
			action="progressive"
			weight="primary">
			{{ footerLinkText }}
		</cdx-button>
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
	props: {
		rendermode: {
			type: String,
			default: ''
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
	mounted() {
		this.fetchActiveDiscussions( this.limit );
		mw.hook( 'personaldashboard.activediscussions.loaded' ).fire();
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard {
	&-module {
		&-activeDiscussions&-desktop & {
			&-header {
				border-bottom: @border-subtle;
				margin: 0;
				padding: @spacing-100;
			}

			&-body {
				margin: 0;
			}
		}
	}

	&-active-discussions {
		&__container {
			display: flex;
			flex-direction: column;
			gap: @spacing-25;
			background: @background-color-neutral;
			padding: @spacing-25;

			.personal-dashboard-module-route-activeDiscussions & {
				margin: @spacing-0;

				@media screen and ( max-width: @max-width-breakpoint-mobile ) {
					margin-left: -@spacing-100;
					margin-right: -@spacing-100;
				}
			}
		}

		&__footer .cdx-button {
			width: 100%;
			max-width: none;
		}
	}
}
</style>
