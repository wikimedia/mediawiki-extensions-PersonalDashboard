<template>
	<h4>{{ msgHeader }}</h4>
	<p>{{ msgExample }}</p>

	<div class="personal-dashboard-policies-guidelines__answer">
		<cdx-icon :class="iconClass" :icon="iconData"></cdx-icon>

		<div class="personal-dashboard-policies-guidelines__answer__text">
			<strong>{{ msgAnswerLabel }}</strong>
			{{ msgAnswerText }}
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxIcon } = require( '../codex.js' );
const { cdxIconAlert, cdxIconError, cdxIconSuccess } = require( '../icons.json' );

module.exports = defineComponent( {
	name: 'ExampleStep',
	components: { CdxIcon },
	props: {
		step: {
			type: Number,
			required: true
		},
		name: {
			type: String,
			required: true
		},
		icon: {
			type: String,
			required: true
		}
	},
	data() {
		let iconData = cdxIconSuccess;

		if ( this.icon === 'warning' ) {
			iconData = cdxIconAlert;
		} else if ( this.icon === 'error' ) {
			iconData = cdxIconError;
		}

		return {
			msgHeader: mw.msg( 'personal-dashboard-policies-guidelines-examples-header', this.step ),
			// Messages used here include:
			// * personal-dashboard-policies-guidelines-neutral-point-of-view-example-1
			// * personal-dashboard-policies-guidelines-neutral-point-of-view-example-2
			// * personal-dashboard-policies-guidelines-no-original-research-example-1
			// * personal-dashboard-policies-guidelines-no-original-research-example-2
			// * personal-dashboard-policies-guidelines-verifiability-example-1
			// * personal-dashboard-policies-guidelines-verifiability-example-2
			// * personal-dashboard-policies-guidelines-verifiability-example-3
			// * personal-dashboard-policies-guidelines-assume-good-faith-example-1
			// * personal-dashboard-policies-guidelines-assume-good-faith-example-2
			msgExample: mw.msg( `personal-dashboard-policies-guidelines-${ this.name }-example-${ this.step }` ),
			// Messages used here include:
			// * personal-dashboard-policies-guidelines-neutral-point-of-view-answer-1-label
			// * personal-dashboard-policies-guidelines-neutral-point-of-view-answer-2-label
			// * personal-dashboard-policies-guidelines-no-original-research-answer-1-label
			// * personal-dashboard-policies-guidelines-no-original-research-answer-2-label
			// * personal-dashboard-policies-guidelines-verifiability-answer-1-label
			// * personal-dashboard-policies-guidelines-verifiability-answer-2-label
			// * personal-dashboard-policies-guidelines-verifiability-answer-3-label
			// * personal-dashboard-policies-guidelines-assume-good-faith-answer-1-label
			// * personal-dashboard-policies-guidelines-assume-good-faith-answer-2-label
			msgAnswerLabel: mw.msg( `personal-dashboard-policies-guidelines-${ this.name }-answer-${ this.step }-label` ),
			// Messages used here include:
			// * personal-dashboard-policies-guidelines-neutral-point-of-view-answer-1-text
			// * personal-dashboard-policies-guidelines-neutral-point-of-view-answer-2-text
			// * personal-dashboard-policies-guidelines-no-original-research-answer-1-text
			// * personal-dashboard-policies-guidelines-no-original-research-answer-2-text
			// * personal-dashboard-policies-guidelines-verifiability-answer-1-text
			// * personal-dashboard-policies-guidelines-verifiability-answer-2-text
			// * personal-dashboard-policies-guidelines-verifiability-answer-3-text
			// * personal-dashboard-policies-guidelines-assume-good-faith-answer-1-text
			// * personal-dashboard-policies-guidelines-assume-good-faith-answer-2-text
			msgAnswerText: mw.msg( `personal-dashboard-policies-guidelines-${ this.name }-answer-${ this.step }-text` ),

			// The following CSS classes are used here:
			// * personal-dashboard-policies-guidelines__icon--success
			// * personal-dashboard-policies-guidelines__icon--warning
			// * personal-dashboard-policies-guidelines__icon--error
			iconClass: `personal-dashboard-policies-guidelines__icon--${ this.icon }`,
			iconData
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-policies-guidelines {
	h4 {
		margin: @spacing-0;
		padding: @spacing-0;
	}

	p {
		margin: @spacing-0;
		padding: @spacing-50 @spacing-0;
	}

	&__answer {
		display: flex;
		gap: @spacing-50;

		&__text {
			overflow: hidden;
			overflow-wrap: break-word;
		}
	}

	.cdx-icon&__icon {
		&--success {
			color: @color-icon-success;
		}

		&--warning {
			color: @color-icon-warning;
		}

		&--error {
			color: @color-icon-error;
		}
	}
}
</style>
