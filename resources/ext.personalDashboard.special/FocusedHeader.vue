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

		<div class="personal-dashboard-focused-header__title-group">
			<h2 class="personal-dashboard-focused-header__title">
				{{ title }}
			</h2>
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
	box-sizing: border-box;
	width: 100%;

	&__title-group {
		flex-grow: 0;
		overflow: hidden;

		// Heading 4 (T433896). Hard-coded rather than @font-size-medium because
		// Vector reports this special page as excluded from font modes, which
		// pins that token to @font-size-small. Same workaround, same reason, as
		// the card headers in ext.personalDashboard.styles.
		//
		// The margin/padding/border/font-family reset undoes the content-heading
		// styling the skin applies (`.mw-body h2`, `.mw-body-content h2`), which
		// applies to any h2 in the page body, not just ones inside parser
		// output; a CdxDialog header carries that reset for free, but
		// this element renders outside one. Qualified by the parent class
		// rather than the bare `&__title` class alone: the selectors the skin uses
		// pair a class with the `h2` type, so matching their specificity
		// exactly instead of beating it would leave the winner up to
		// stylesheet order.
		.personal-dashboard-focused-header__title {
			.personal-dashboard-module-header-title();
			margin: 0;
			padding: 0;
			border: 0;
			font-family: inherit;
			line-height: 1.5;
		}
	}
}
</style>
