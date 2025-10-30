<template>
	{{ subheader }}
	<info-popover></info-popover>
	<recent-activity></recent-activity>
	{{ footer }}
	<a
		:href="footerUrl"
		target="_blank"
		rel="noopener noreferrer">
		{{ footerLinkText }}
	</a>
</template>

<script>
const { defineComponent } = require( 'vue' );
const RecentActivity = require( './components/RecentActivity.vue' );
const InfoPopover = require( './components/InfoPopover.vue' );
module.exports = defineComponent( {
	name: 'RiskyArticleEdits',
	components: {
		RecentActivity,
		InfoPopover
	},
	computed: {
		subheader() {
			return mw.message( 'personal-dashboard-risky-article-edits-subheader' ).parse();
		},
		footer() {
			return mw.message( 'personal-dashboard-risky-article-edits-footer-preamble' ).parse();
		},
		footerUrl() {
			return mw.util.getUrl( 'Special:RecentChanges', { revertrisklanguageagnostic: 'all' } );
		},
		footerLinkText() {
			return mw.message( 'personal-dashboard-risky-article-edits-footer-link-text' ).parse();
		}
	}
} );
</script>
