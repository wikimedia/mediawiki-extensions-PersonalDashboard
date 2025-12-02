<template>
	<div class="ext-personal-dashboard-recent-activity-card-header">
		<h3>{{ header }}</h3>
		<info-popover></info-popover>
	</div>

	<div class="ext-personal-dashboard-recent-activity-card-subheader">
		{{ subheader }}
	</div>

	<recent-activity></recent-activity>

	<div class="ext-personal-dashboard-recent-activity-footer" v-html="footer">
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const RecentActivity = require( './components/RecentActivity.vue' );
const InfoPopover = require( './components/InfoPopover.vue' );

module.exports = defineComponent( {
	name: 'RiskyArticleEdits',
	components: { RecentActivity, InfoPopover },
	computed: {
		header() {
			return mw.message( 'personal-dashboard-risky-article-edits-header' ).parse();
		},
		subheader() {
			return mw.message( 'personal-dashboard-risky-article-edits-subheader' ).parse();
		},
		footer() {
			return mw.message( 'personal-dashboard-risky-article-edits-footer-preamble' ).parse();
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-personal-dashboard-recent-activity-card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding-bottom: 0.5rem;

	h3 {
		padding: 0;
		margin: 0;
	}
}

.ext-personal-dashboard-recent-activity-card-subheader {
	padding-bottom: 1rem;
}

.ext-personal-dashboard-recent-activity-footer {
	padding-top: 1rem;
}
</style>
