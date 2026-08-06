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

.personal-dashboard-focused-header {
	display: flex;
	align-items: center;
	// flex-start, not the centered default Codex uses: arrow hugs the left-aligned
	// title, matching the old focused-page header.
	justify-content: flex-start;
	gap: @spacing-50;
	box-sizing: border-box;
	width: 100%;
	// 46px per T433896 follow-up (design feedback): no Codex spacing token
	// lands there, so rem instead of raw px keeps it scaling with the user's
	// font-size preference. Matched top and bottom comes for free from
	// align-items: center inside that fixed height rather than equal padding.
	height: 2.875rem;
	padding: @spacing-0 @spacing-12;
	border-bottom: @border-subtle;

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
			margin: 0;
			padding: 0;
			border: 0;
			font-family: inherit;
			font-size: 1rem;
			font-weight: @font-weight-bold;
			line-height: 1.5;
			text-overflow: ellipsis;
			overflow: hidden;
			white-space: nowrap;
		}
	}
}
</style>
