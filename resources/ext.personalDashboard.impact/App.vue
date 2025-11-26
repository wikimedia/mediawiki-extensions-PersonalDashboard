<template>
	<div class="personal-dashboard-impact__container">
		<div class="personal-dashboard-impact__category">
			<div class="personal-dashboard-impact__value">
				<cdx-icon :icon="cdxIconUserTalk"></cdx-icon>
				<a :href="thanksUrl" target="_blank">{{ thanksCount }}</a>
			</div>

			<div class="personal-dashboard-impact__name">
				<div>{{ msgThanksSent }}</div>
			</div>
		</div>

		<div class="personal-dashboard-impact__divider"></div>

		<div class="personal-dashboard-impact__category">
			<div class="personal-dashboard-impact__value">
				<cdx-icon :icon="cdxIconCheckAll"></cdx-icon>
				<div>{{ reviewCount }}</div>
			</div>

			<div class="personal-dashboard-impact__name">
				<div>{{ msgEditsReviewed }}</div>

				<cdx-button
					ref="infoButton"
					weight="quiet"
					size="small"
					:aria-label="msgInfoButton"
					@click="showInfo = !showInfo">
					<cdx-icon :icon="cdxIconInfo"></cdx-icon>
				</cdx-button>
			</div>
		</div>
	</div>

	<cdx-popover
		v-model:open="showInfo"
		class="personal-dashboard-impact__popover"
		:anchor="infoButton"
		:title="msgEditsReviewed"
		:icon="cdxIconInfoFilled"
		use-close-button
		placement="bottom-end">
		{{ msgEditsReviewedDescription }}
	</cdx-popover>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton, CdxIcon, CdxPopover } = require( './codex.js' );
const {
	cdxIconCheckAll,
	cdxIconInfo,
	cdxIconInfoFilled,
	cdxIconUserTalk
} = require( './icons.json' );

module.exports = defineComponent( {
	name: 'PoliciesGuidelines',
	components: { CdxButton, CdxIcon, CdxPopover },
	setup() {
		const thanksCount = mw.config.get( 'wgPersonalDashboardImpactThanksCount', 0 );
		const reviewCount = mw.config.get( 'wgPersonalDashboardImpactReviewCount', 0 );

		return {
			thanksCount: mw.language.convertNumber( thanksCount ),
			reviewCount: mw.language.convertNumber( reviewCount ),
			infoButton: ref( null ),
			showInfo: ref( false ),
			thanksUrl: mw.util.getUrl( 'Special:Log', {
				type: 'thanks',
				user: mw.user.getName()
			} ),
			msgThanksSent: mw.msg( 'personal-dashboard-impact-thanks-sent' ),
			msgEditsReviewed: mw.msg( 'personal-dashboard-impact-edits-reviewed' ),
			msgEditsReviewedDescription: mw.msg( 'personal-dashboard-impact-edits-reviewed-description' ),
			msgInfoButton: mw.msg( 'personal-dashboard-impact-info-button' ),
			cdxIconCheckAll,
			cdxIconInfo,
			cdxIconInfoFilled,
			cdxIconUserTalk
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-impact {
	&__container {
		display: flex;
		gap: @spacing-100;
	}

	&__category {
		display: flex;
		flex: 1;
		flex-direction: column;
		gap: @spacing-50;
	}

	&__value {
		display: flex;
		align-items: center;
		gap: @spacing-50;

		:nth-child( 1 ) {
			color: @color-subtle;
		}

		:nth-child( 2 ) {
			font-weight: bold;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}

	&__name {
		display: flex;
		justify-content: space-between;
		gap: @spacing-100;
		color: @color-subtle;

		.cdx-button:enabled {
			color: @color-subtle;
		}
	}

	&__divider {
		border-left: 1px solid @border-color-subtle;
	}

	&__popover {
		width: 16.25rem;
	}

	@media screen and ( max-width: @max-width-breakpoint-mobile ) {
		&__container {
			flex-direction: column;
			gap: @spacing-50;
		}

		&__category {
			flex-direction: row;
			gap: @spacing-100;
		}

		&__divider {
			display: none;
		}
	}
}
</style>
