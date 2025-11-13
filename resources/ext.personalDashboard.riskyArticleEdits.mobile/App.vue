<template>
	<div class="ext-personal-dashboard-recent-activity-wrapper">
		<recent-activity></recent-activity>

		<span class="ext-personal-dashboard-recent-activity-footer">
			<cdx-button
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
const RecentActivity = require( './components/RecentActivity.vue' );

module.exports = defineComponent( {
	name: 'RiskyArticleEdits',
	components: { CdxButton, RecentActivity },
	setup() {
		return {
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
