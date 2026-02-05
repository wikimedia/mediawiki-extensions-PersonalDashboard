<template>
	<multi-step-dialog
		v-model:open="open"
		v-model:step="step"
		:title="msgTitle"
		:no-padding="true"
		class="personal-dashboard-onboarding"
		@update:open="onClose">
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
		const length = 3;
		const bannerPath = mw.config.get( 'wgExtensionAssetsPath' ) +
			'/PersonalDashboard/resources/ext.personalDashboard.onboarding/images/';
		const msgPrefix = 'personal-dashboard-onboarding-step-';

		return {
			open: ref( true ),
			step: ref( 1 ),
			steps: Array.from( { length }, ( _, i ) => ( {
				slot: `step-${ ++i }`,
				banner: `${ bannerPath }${ i }.svg`,
				// * personal-dashboard-onboarding-step-1-alt
				alt: mw.msg( `${ msgPrefix }${ i }-alt` ),
				// * personal-dashboard-onboarding-step-2-title
				title: mw.msg( `${ msgPrefix }${ i }-title` ),
				// * personal-dashboard-onboarding-step-3-body
				body: mw.msg( `${ msgPrefix }${ i }-body` )
			} ) ),
			dontShowAgain: ref( false ),
			chance: parseInt( mw.user.options.get( 'personaldashboard-onboarding', 0 ) ),
			msgTitle: mw.msg( 'personal-dashboard-onboarding-title' ),
			msgDontShowAgain: mw.msg( 'personal-dashboard-onboarding-dont-show-again' )
		};
	},
	methods: {
		async onClose( _, done ) {
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
