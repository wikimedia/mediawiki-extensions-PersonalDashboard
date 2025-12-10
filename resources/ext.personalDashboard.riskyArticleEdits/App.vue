<template>
	<teleport
		to=".personal-dashboard-module-riskyArticleEdits > .personal-dashboard-module-header > span"
	>
		<info-popover></info-popover>
	</teleport>
	<recent-activity></recent-activity>
</template>

<script>
const { defineComponent } = require( 'vue' );
const RecentActivity = require( './components/RecentActivity.vue' );
const InfoPopover = require( './components/InfoPopover.vue' );

module.exports = defineComponent( {
	name: 'RiskyArticleEdits',
	components: { RecentActivity, InfoPopover }
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-module-riskyArticleEdits .personal-dashboard-module-header .cdx-popover__body {
	font-weight: @font-weight-normal;
	font-size: @font-size-small;
}
</style>
