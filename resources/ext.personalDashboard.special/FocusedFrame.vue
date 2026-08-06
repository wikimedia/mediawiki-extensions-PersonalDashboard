<template>
	<div
		ref="frameRef"
		class="personal-dashboard-focused-frame"
		tabindex="-1"
	>
		<focused-header :title="title" @back="$emit( 'back' )"></focused-header>
		<div :id="FRAME_TARGET_ID"></div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const FocusedHeader = require( './FocusedHeader.vue' );
const { FRAME_TARGET_ID } = require( './teleportTargets.js' );

module.exports = defineComponent( {
	name: 'FocusedFrame',
	components: {
		FocusedHeader
	},
	props: {
		title: {
			type: String,
			default: ''
		}
	},
	emits: [ 'back' ],
	setup() {
		return { FRAME_TARGET_ID };
	},
	mounted() {
		// A soft nav hides .personal-dashboard-container with display: none,
		// dropping focus to <body> if nothing claims it; CdxDialog handles this
		// for the dialog path via its own focus trap, so the non-modal frame has
		// to do it by hand.
		this.$refs.frameRef.focus();
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-focused-frame {
	display: flex;
	flex-direction: column;
	gap: @spacing-25;
	background: @background-color-base;
	border: @border-subtle;
	border-radius: @border-radius-base;

	// A soft nav focuses this element programmatically (see mounted() above)
	// so a keyboard user lands somewhere real; it is not an interactive control
	// in its own right, so skip the default browser focus ring here.
	&:focus {
		outline: 0;
	}
}
</style>
