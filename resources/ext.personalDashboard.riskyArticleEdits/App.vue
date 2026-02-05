<template>
	<recent-activity></recent-activity>
</template>

<script>
const { defineComponent } = require( 'vue' );
const RecentActivity = require( './components/RecentActivity.vue' );

module.exports = defineComponent( {
	name: 'RiskyArticleEdits',
	components: { RecentActivity }
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-module-riskyArticleEdits .personal-dashboard-module-header .cdx-popover__body {
	font-weight: @font-weight-normal;
	font-size: @font-size-small;
}

@media all and ( max-width: @max-width-breakpoint-mobile ) {
	.personal-dashboard-module-header {
		/* stylelint-disable-next-line declaration-no-important */
		margin: 0 0.75rem !important;
	}
}
</style>
