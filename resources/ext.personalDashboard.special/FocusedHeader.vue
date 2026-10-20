<template>
	<div class="personal-dashboard-focused-header">
		<!--
			A focused whole-page render has no dashboard behind it to close back
			onto, so there the arrow is a real link that loads one, the same link
			the server puts in a focused module's header.
		-->
		<a
			v-if="backHref"
			:href="backHref"
			:aria-label="msgBackToDashboard"
			class="cdx-button cdx-button--fake-button
				cdx-button--fake-button--enabled cdx-button--weight-quiet
				cdx-button--icon-only">
			<cdx-icon :icon="cdxIconArrowPrevious"></cdx-icon>
		</a>

		<cdx-button
			v-else
			weight="quiet"
			:aria-label="msgBackToDashboard"
			@click="$emit( 'back' )">
			<cdx-icon :icon="cdxIconArrowPrevious"></cdx-icon>
		</cdx-button>

		<h2 class="personal-dashboard-focused-header__title">
			{{ title }}
		</h2>

		<!--
			The mount slot for the module's header menu while this header stands
			in for the card's own. Minted whenever the stand-in names an id, since
			neither stand-in knows whether the module it shows declares a menu;
			for one that does not, the slot stays empty and shows nothing.
		-->
		<div
			v-if="actionsTargetId"
			:id="actionsTargetId"
			class="personal-dashboard-focused-header__actions">
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxIcon } = require( './codex.js' );
const { cdxIconArrowPrevious } = require( './icons.json' );

module.exports = defineComponent( {
	name: 'FocusedHeader',
	components: {
		CdxButton,
		CdxIcon
	},
	props: {
		title: {
			type: String,
			default: ''
		},
		// The dashboard's own URL on a focused whole-page render, or '' when
		// there's a dashboard already behind this render and closing is enough.
		backHref: {
			type: String,
			default: ''
		},
		// The id to mint for the header menu's mount slot, or '' to mint none.
		// The dialog and the frame pass different ids: <teleport> only
		// re-resolves when `to` changes, so a shared id would leave a
		// breakpoint-crossing swap between the two invisible to it.
		actionsTargetId: {
			type: String,
			default: ''
		}
	},
	emits: [ 'back' ],
	setup() {
		return {
			msgBackToDashboard: mw.msg( 'personal-dashboard-back-to-dashboard' ),
			cdxIconArrowPrevious
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import '../ext.personalDashboard.styles/variables.less';

.personal-dashboard-focused-header {
	.personal-dashboard-focused-header-frame();
	.personal-dashboard-focused-header-content();
	width: @size-full;

	// Pushed to the far end, opposite the back arrow, as the card header's own
	// slot is. CSSJanus flips this for RTL.
	&__actions {
		display: flex;
		flex: none;
		align-items: center;
		margin-left: auto;
		min-width: @min-size-interactive-pointer;
		min-height: @min-size-interactive-pointer;
	}

	// Heading 4 (T433896). Reset the skin styles for h2 in the page body
	// (`.mw-body h2`), as a CdxDialog header does. The parent class makes the
	// selector more specific than the skin selector.
	& &__title {
		.personal-dashboard-module-header-title();
		margin: @spacing-0;
		padding: @spacing-0;
		border: 0;
		font-family: inherit;
		line-height: inherit;
		color: inherit;
	}
}
</style>
