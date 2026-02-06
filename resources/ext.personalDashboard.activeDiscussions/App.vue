<template>
	<div v-if="showActiveDiscussions" class="personal-dashboard-active-discussions__container">
		<div v-if="loading">
			<cdx-progress-bar inline :aria-label="progressBarAriaLabel"></cdx-progress-bar>
		</div>

		<div v-else-if="error">
			<p>Error: {{ error.message }}</p>
		</div>
		<div v-if="activeDiscussionsResult">
			<template v-if="isMobile && rendermode === 'mobile-summary'">
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
					:key="ac.discussionTitle">
				</list-card>
			</template>
		</div>
	</div>

	<div v-if="isMobile">
		<span class="ext-personal-dashboard-active-discussions-footer">
			<cdx-button
				id="personal-dashboard-go-to-active-discussions"
				:aria-label="buttonAriaLabel"
				action="progressive"
				weight="primary">
				{{ footerLinkText }}
			</cdx-button>
		</span>
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
		const showActiveDiscussions = mw.config.get( 'wgPersonalDashboardShowActiveDiscussions' ) || false;
		const isMobile = mw.config.get( 'wgMFMode' ) !== null;
		const limit = isMobile ? 10 : 3;
		const {
			activeDiscussionsResult,
			loading,
			error,
			fetchActiveDiscussions
		} = useFetchActiveDiscussionsResult();
		return {
			showActiveDiscussions,
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

.personal-dashboard-module-activeDiscussions {
	.personal-dashboard-module-body {
		padding-right: 1rem;
	}
}

.ext-personal-dashboard-active-discussions-footer {
	.cdx-button {
		width: 100%;
		max-width: none;
	}

	@media all and ( max-width: @max-width-breakpoint-mobile ) {
		.cdx-button {
			width: 100%;
		}
	}
}
</style>
