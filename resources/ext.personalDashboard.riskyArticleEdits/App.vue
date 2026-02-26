<template>
	<div v-if="loading">
		Loading...
	</div>

	<div v-if="error">
		<p>Error: {{ error.message }}</p>
	</div>
	<div
		v-if="recentActivityResult &&
			recentActivityResult.query &&
			recentActivityResult.query.recentchanges &&
			recentActivityResult.query.pages
		">
		<template v-if="isMobile && rendermode === 'mobile-summary'">
			<list-card-mobile
				v-for="rc in recentActivityResult.query.recentchanges.slice( 0, 1 )"
				v-bind="rc"
				:key="`${rendermode}-${rc.rcid}`"
				:pages="recentActivityResult.query.pages">
			</list-card-mobile>
		</template>
		<template v-else>
			<list-card
				v-for="rc in recentActivityResult.query.recentchanges.slice( 0, limit )"
				v-bind="rc"
				:key="`${rendermode}-${rc.rcid}`"
				:pages="recentActivityResult.query.pages">
			</list-card>
		</template>
	</div>

	<div v-if="isMobile">
		<span class="ext-personal-dashboard-recent-activity-footer">
			<cdx-button
				id="personal-dashboard-go-to-recentchanges"
				:aria-label="buttonAriaLabel"
				action="progressive"
				weight="primary"
				@click="goToRecentChanges">
				{{ footerLinkText }}
			</cdx-button>
		</span>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( './codex.js' );
const { useFetchActivityResult } = require( 'ext.personalDashboard.common' );
const ListCard = require( './components/ListCard.vue' );
const ListCardMobile = require( './components/ListCardMobile.vue' );

module.exports = defineComponent( {
	components: { CdxButton, ListCard, ListCardMobile },
	props: {
		rendermode: {
			type: String,
			default: ''
		}
	},
	setup() {
		const isMobile = mw.config.get( 'wgMFMode' ) !== null;
		const limit = isMobile ? 10 : 5;
		const {
			recentActivityResult,
			loading,
			error,
			fetchRecentActivity
		} = useFetchActivityResult();

		return {
			isMobile,
			limit,
			recentActivityResult,
			loading,
			error,
			fetchRecentActivity,
			footerUrl: mw.util.getUrl( 'Special:RecentChanges', { revertrisklanguageagnostic: 'all' } ),
			footerLinkText: mw.message( 'personal-dashboard-risky-article-edits-mobile-summary-footer-link-text' ).parse(),
			buttonAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-mobile-summary-footer-button-aria-label' )
		};
	},
	methods: {
		goToRecentChanges() {
			event.preventDefault();
			event.stopPropagation();
			window.open( this.footerUrl, '_blank', 'noopener noreferrer' );
		}
	},
	mounted() {
		mw.hook( 'personaldashboard.recentactivity.loaded' ).fire();
		this.fetchRecentActivity( this.limit );
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-personal-dashboard-recent-activity-header {
	display: flex;
	justify-content: space-between;
}

.ext-personal-dashboard-recent-activity-footer {
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
