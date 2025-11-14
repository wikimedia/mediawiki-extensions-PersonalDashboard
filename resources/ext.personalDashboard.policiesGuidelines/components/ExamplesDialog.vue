<template>
	<cdx-dialog
		:class="commonPrefix + '__dialog'"
		:open
		:title="msgTitle"
		use-close-button
		@update:open="close">
		<div :class="commonPrefix + '__stepper'">
			<div :class="commonPrefix + '__stepper__label'">
				{{ msgProgress }}
			</div>

			<div
				v-for="page in totalPages"
				:key="page"
				:class="getDotClass( page )">
			</div>
		</div>

		<h4>{{ msgHeader }}</h4>
		<p>{{ msgExample }}</p>

		<div :class="commonPrefix + '__answer'">
			<cdx-icon
				:class="'personal-dashboard-policies-guidelines__icon--' + iconName"
				:icon="iconData">
			</cdx-icon>

			<div :class="commonPrefix + '__answer__text'">
				<strong>{{ msgAnswerLabel }}</strong>
				{{ msgAnswerText }}
			</div>
		</div>

		<template #footer>
			<div :class="commonPrefix + '__footer'">
				<cdx-button
					v-if="currentPage > 1"
					:aria-label="msgPreviousButton"
					@click="previousPage">
					<cdx-icon :icon="cdxIconPrevious"></cdx-icon>
				</cdx-button>

				<cdx-button
					v-if="currentPage < totalPages"
					action="progressive"
					weight="primary"
					:aria-label="msgNextButton"
					@click="nextPage">
					<cdx-icon :icon="cdxIconNext"></cdx-icon>
				</cdx-button>

				<cdx-button
					v-else
					action="progressive"
					weight="primary"
					@click="close">
					{{ msgGotItButton }}
				</cdx-button>
			</div>
		</template>
	</cdx-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton, CdxDialog, CdxIcon } = require( '../codex.js' );
const {
	cdxIconAlert,
	cdxIconError,
	cdxIconNext,
	cdxIconPrevious,
	cdxIconSuccess
} = require( '../icons.json' );

module.exports = defineComponent( {
	name: 'ExamplesDialog',
	components: { CdxButton, CdxDialog, CdxIcon },
	props: {
		open: {
			type: Boolean,
			default: false
		},
		prefix: {
			type: String,
			required: true
		},
		pages: {
			type: Array,
			required: true
		}
	},
	emits: [ 'update:open' ],
	setup( props ) {
		const commonPrefix = 'personal-dashboard-policies-guidelines';
		const msgPrefix = commonPrefix + '-' + props.prefix;

		return {
			currentPage: ref( 1 ),
			totalPages: ref( props.pages.length ),
			/* eslint-disable mediawiki/msg-doc */
			msgTitle: mw.msg( msgPrefix + '-title' ),
			commonPrefix,
			msgPrefix,
			msgNextButton: mw.msg( commonPrefix + '-next-button' ),
			msgPreviousButton: mw.msg( commonPrefix + '-previous-button' ),
			msgGotItButton: mw.msg( commonPrefix + '-got-it-button' ),
			cdxIconNext,
			cdxIconPrevious
		};
	},
	computed: {
		iconName() {
			return this.$props.pages[ this.currentPage - 1 ];
		},
		iconData() {
			switch ( this.iconName ) {
				case 'warning':
					return cdxIconAlert;
				case 'error':
					return cdxIconError;
				default:
					return cdxIconSuccess;
			}
		},
		msgProgress() {
			return mw.msg( this.commonPrefix + '-examples-progress', this.currentPage, this.totalPages );
		},
		msgHeader() {
			return mw.msg( this.commonPrefix + '-examples-header', this.currentPage );
		},
		msgExample() {
			return mw.msg( this.msgPrefix + '-example-' + this.currentPage );
		},
		msgAnswerLabel() {
			return mw.msg( this.msgPrefix + '-answer-' + this.currentPage + '-label' );
		},
		msgAnswerText() {
			return mw.msg( this.msgPrefix + '-answer-' + this.currentPage + '-text' );
		}
	},
	methods: {
		getDotClass( page ) {
			let result = this.commonPrefix + '__stepper__dot';

			if ( page === this.currentPage ) {
				result += ' ' + result + '--active';
			}

			return result;
		},
		nextPage() {
			if ( this.currentPage < this.totalPages ) {
				this.currentPage++;
			}
		},
		previousPage() {
			if ( this.currentPage > 1 ) {
				this.currentPage--;
			}
		},
		close() {
			this.$emit( 'update:open', false );
			this.currentPage = 1;
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-dialog__body {
	padding-top: @spacing-0;

	h4 {
		margin: @spacing-0;
		padding: @spacing-0;
	}

	p {
		margin: @spacing-0;
		padding: @spacing-50 @spacing-0;
	}
}

.personal-dashboard-policies-guidelines {
	&__stepper {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		padding-bottom: @spacing-150;

		&__label {
			color: @color-subtle;
			font-size: @font-size-small;
			// Fix label alignment next to the dots
			margin-bottom: -3px;
		}

		&__dot {
			display: inline-block;
			min-width: @size-50;
			min-height: @size-50;
			background-color: @color-disabled;
			border-radius: @border-radius-circle;

			&--active {
				background-color: @color-base;
			}
		}
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

	&__footer {
		display: flex;
		justify-content: end;
		gap: @spacing-75;
	}
}
</style>
