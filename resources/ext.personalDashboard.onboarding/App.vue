<template>
	<multi-step-dialog
		v-model:open="open"
		v-model:step="step"
		:title="msgTitle"
		:final-button-label="msgGetStartedButton"
		:no-padding="true"
		class="personal-dashboard-onboarding">
		<template
			v-for="( data, index ) in steps"
			:key="index"
			#[data.slot]>
			<img :src="data.banner" :alt="data.alt">

			<div class="personal-dashboard-onboarding__text">
				<h4>{{ data.title }}</h4>
				<p>{{ data.body }}</p>
			</div>
		</template>
	</multi-step-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { MultiStepDialog } = require( 'ext.personalDashboard.common' );

module.exports = defineComponent( {
	name: 'OnboardingDialog',
	components: { MultiStepDialog },
	setup() {
		const bannerPath = mw.config.get( 'wgExtensionAssetsPath' ) +
			'/PersonalDashboard/resources/ext.personalDashboard.onboarding/images/';
		const msgPrefix = 'personal-dashboard-onboarding-step-';

		return {
			open: ref( true ),
			step: ref( 1 ),
			steps: [
				{
					slot: 'step-1',
					banner: `${ bannerPath }1.svg`,
					// * personal-dashboard-onboarding-step-1-alt
					alt: mw.msg( `${ msgPrefix }1-alt` ),
					// * personal-dashboard-onboarding-step-2-title
					title: mw.msg( `${ msgPrefix }2-title` ),
					// * personal-dashboard-onboarding-step-1-body
					body: mw.msg( `${ msgPrefix }1-body` )
				},
				{
					slot: 'step-2',
					banner: `${ bannerPath }3.svg`,
					// * personal-dashboard-onboarding-step-3-alt
					alt: mw.msg( `${ msgPrefix }3-alt` ),
					// * personal-dashboard-onboarding-step-3-title
					title: mw.msg( `${ msgPrefix }3-title` ),
					// * personal-dashboard-onboarding-step-3-body
					body: mw.msg( `${ msgPrefix }3-body` )
				}
			],
			msgTitle: mw.msg( 'personal-dashboard-onboarding-title' ),
			msgGetStartedButton: mw.msg( 'personal-dashboard-onboarding-get-started-button' )
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-onboarding {
	img {
		width: 100%;
	}

	&__text {
		padding: @spacing-125 @spacing-150;

		h4 {
			margin: 0;
			padding: 0;
		}

		p {
			margin: @spacing-75 0 0 0;
			padding: 0;
		}
	}
}
</style>
