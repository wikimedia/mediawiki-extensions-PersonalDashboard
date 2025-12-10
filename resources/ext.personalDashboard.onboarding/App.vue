<template>
	<multi-step-dialog
		v-model:open="open"
		v-model:step="step"
		:total
		:title="msgTitle"
		:no-padding="true"
		class="personal-dashboard-onboarding"
		@update:open="onClose">
		<template #step-1>
			<img :src="bannerPath + '1.svg'" :alt="msgStep1Alt">

			<div class="personal-dashboard-onboarding__text">
				<h4>{{ msgStep1Title }}</h4>
				<p>{{ msgStep1Body }}</p>
			</div>
		</template>

		<template #step-2>
			<img :src="bannerPath + '2.svg'" :alt="msgStep2Alt">

			<div class="personal-dashboard-onboarding__text">
				<h4>{{ msgStep2Title }}</h4>
				<p>{{ msgStep2Body }}</p>
			</div>
		</template>

		<template #step-3>
			<img :src="bannerPath + '3.svg'" :alt="msgStep3Alt">

			<div class="personal-dashboard-onboarding__text">
				<h4>{{ msgStep3Title }}</h4>
				<p>{{ msgStep3Body }}</p>
			</div>
		</template>

		<template #footer>
			<cdx-checkbox v-model="dontShowAgain">
				{{ msgDontShowAgain }}
			</cdx-checkbox>
		</template>
	</multi-step-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxCheckbox } = require( './codex.js' );
const { MultiStepDialog } = require( 'ext.personalDashboard.common' );
const api = new mw.Api();

module.exports = defineComponent( {
	name: 'OnboardingDialog',
	components: { CdxCheckbox, MultiStepDialog },
	setup() {
		const chance = parseInt( mw.user.options.get( 'personaldashboard-onboarding', 0 ) );

		return {
			chance,
			open: ref( chance > 0 ),
			step: ref( 1 ),
			total: ref( 3 ),
			dontShowAgain: ref( false ),
			bannerPath: mw.config.get( 'wgExtensionAssetsPath' ) +
				'/PersonalDashboard/resources/ext.personalDashboard.onboarding/images/',
			msgTitle: mw.msg( 'personal-dashboard-onboarding-title' ),
			msgStep1Title: mw.msg( 'personal-dashboard-onboarding-step-1-title' ),
			msgStep1Body: mw.msg( 'personal-dashboard-onboarding-step-1-body' ),
			msgStep1Alt: mw.msg( 'personal-dashboard-onboarding-step-1-alt' ),
			msgStep2Title: mw.msg( 'personal-dashboard-onboarding-step-2-title' ),
			msgStep2Body: mw.msg( 'personal-dashboard-onboarding-step-2-body' ),
			msgStep2Alt: mw.msg( 'personal-dashboard-onboarding-step-2-alt' ),
			msgStep3Title: mw.msg( 'personal-dashboard-onboarding-step-3-title' ),
			msgStep3Body: mw.msg( 'personal-dashboard-onboarding-step-3-body' ),
			msgStep3Alt: mw.msg( 'personal-dashboard-onboarding-step-3-alt' ),
			msgDontShowAgain: mw.msg( 'personal-dashboard-onboarding-dont-show-again' )
		};
	},
	methods: {
		async onClose( value, done ) {
			this.chance = this.dontShowAgain || done ? 0 : Math.max( this.chance - 1, 0 );

			await api.postWithEditToken( {
				action: 'options',
				optionname: 'personaldashboard-onboarding',
				optionvalue: this.chance.toString(),
				formatversion: 2
			} );
		}
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
