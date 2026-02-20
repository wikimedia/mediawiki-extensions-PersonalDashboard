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
		const msgPrefix = 'personal-dashboard-policies-guidelines-';
		let iconData = cdxIconSuccess;

		if ( this.icon === 'warning' ) {
			iconData = cdxIconAlert;
		} else if ( this.icon === 'error' ) {
			iconData = cdxIconError;
		}

		return {
			/* eslint-disable mediawiki/msg-doc */
			msgHeader: mw.msg( `${ msgPrefix }examples-header`, this.step ),
			msgExample: mw.msg( `${ msgPrefix }${ this.name }-example-${ this.step }` ),
			msgAnswerLabel: mw.msg( `${ msgPrefix }${ this.name }-answer-${ this.step }-label` ),
			msgAnswerText: mw.msg( `${ msgPrefix }${ this.name }-answer-${ this.step }-text` ),
			/* eslint-enable mediawiki/msg-doc */
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
