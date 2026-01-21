<template>
	<cdx-dialog
		:open
		:title
		:use-close-button="true"
		class="cdx-dialog--multi-step"
		:class="{ 'cdx-dialog--no-padding': noPadding }"
		@update:open="onClose">
		<template #header>
			<div class="cdx-dialog__header--default">
				<div class="cdx-dialog__header__title-group">
					<h3 class="cdx-dialog__header__title">
						{{ title }}
					</h3>

					<div class="cdx-dialog__header__stepper">
						<div class="cdx-dialog__header__stepper__label">
							{{ msgProgress }}
						</div>

						<div
							v-for="i in total"
							:key="i"
							:class="getDotClass( i )">
						</div>
					</div>
				</div>

				<cdx-button
					class="cdx-dialog__header__close-button"
					weight="quiet"
					:aria-label="msgCloseButtonLabel"
					@click="onClose">
					<cdx-icon :icon="cdxIconClose"></cdx-icon>
				</cdx-button>
			</div>
		</template>

		<div
			v-for="i in total"
			v-show="step === i"
			:key="i">
			<slot :name="'step-' + i"></slot>
		</div>

		<template #footer>
			<div class="cdx-dialog__footer--default">
				<slot name="footer"></slot>

				<div class="cdx-dialog__footer__actions">
					<cdx-button
						v-if="step < total"
						action="progressive"
						weight="primary"
						:aria-label="msgNextButton"
						@click="onNext">
						<cdx-icon :icon="cdxIconNext"></cdx-icon>
					</cdx-button>

					<cdx-button
						v-else
						action="progressive"
						weight="primary"
						@click="onClose( true )">
						{{ msgGotItButton }}
					</cdx-button>

					<cdx-button
						v-if="step > 1"
						:aria-label="msgPreviousButton"
						@click="onPrevious">
						<cdx-icon :icon="cdxIconPrevious"></cdx-icon>
					</cdx-button>
				</div>
			</div>
		</template>
	</cdx-dialog>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxDialog, CdxIcon } = require( '../codex.js' );
const { cdxIconClose, cdxIconNext, cdxIconPrevious } = require( '../icons.json' );

module.exports = defineComponent( {
	name: 'MultiStepDialog',
	components: { CdxButton, CdxDialog, CdxIcon },
	props: {
		open: {
			type: Boolean,
			default: false
		},
		step: {
			type: Number,
			default: 1
		},
		title: {
			type: String,
			default: 'Multi-Step Dialog'
		},
		noPadding: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'update:open', 'update:step' ],
	setup( _, { slots } ) {
		return {
			total: Object.keys( slots ).filter( ( key ) => key.startsWith( 'step-' ) ).length,
			msgCloseButtonLabel: mw.msg( 'cdx-dialog-close-button-label' ),
			msgNextButton: mw.msg( 'personal-dashboard-multi-step-dialog-next-button' ),
			msgPreviousButton: mw.msg( 'personal-dashboard-multi-step-dialog-previous-button' ),
			msgGotItButton: mw.msg( 'personal-dashboard-multi-step-dialog-got-it-button' ),
			cdxIconClose,
			cdxIconNext,
			cdxIconPrevious
		};
	},
	computed: {
		msgProgress() {
			return mw.msg(
				'personal-dashboard-multi-step-dialog-progress',
				this.step,
				this.total
			);
		}
	},
	methods: {
		getDotClass( step ) {
			let result = 'cdx-dialog__header__stepper__dot';

			if ( step === this.step ) {
				result += ' ' + result + '--active';
			}

			return result;
		},
		onClose( done = false ) {
			this.$emit( 'update:open', false, done );
			this.$emit( 'update:step', 1 );
		},
		onNext() {
			if ( this.step < this.total ) {
				this.$emit( 'update:step', this.step + 1 );
			}
		},
		onPrevious() {
			if ( this.step > 1 ) {
				this.$emit( 'update:step', this.step - 1 );
			}
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-dialog {
	&__header__stepper {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		padding-top: @spacing-35;
		padding-bottom: @spacing-12;

		&__label {
			color: @color-subtle;
			font-size: @font-size-small;
		}

		&__dot {
			display: inline-block;
			min-width: @size-50;
			min-height: @size-50;
			background-color: @color-disabled;
			border-radius: @border-radius-circle;

			&--active {
				background-color: @background-color-backdrop-dark;
			}
		}
	}

	&--no-padding &__body {
		padding: 0;
	}

	&--multi-step &__footer {
		&--default {
			align-items: center;
			flex-wrap: nowrap;
			overflow-wrap: anywhere;

			.cdx-checkbox {
				margin-bottom: 0;
			}
		}

		&__actions {
			width: auto;
			flex-direction: row-reverse;
		}
	}
}
</style>
