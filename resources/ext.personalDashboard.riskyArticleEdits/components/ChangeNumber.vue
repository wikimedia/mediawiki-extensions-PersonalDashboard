<template>
	<span :class="changeClass">
		{{ changeNumber }}
	</span>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = defineComponent( {
	name: 'ChangeNumber',
	props: {
		newlen: { type: Number, required: true },
		oldlen: { type: Number, required: true }
	},
	computed: {
		changeValue() {
			return this.oldlen - this.newlen;
		},
		changeClass() {
			if ( this.changeValue > 0 ) {
				return 'ext-personal-dashboard-moderation-card-info-change-number-positive';
			} else if ( this.changeValue < 0 ) {
				return 'ext-personal-dashboard-moderation-card-info-change-number-negative';
			} else {
				return 'ext-personal-dashboard-moderation-card-info-change-number-none';
			}
		},
		changeNumber() {
			let formatted = mw.language.convertNumber( this.changeValue );

			if ( this.changeValue > 0 ) {
				formatted = '+' + formatted;
			}

			return formatted;
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-personal-dashboard-moderation-card-info-change-number-positive {
	color: @color-success;
}

.ext-personal-dashboard-moderation-card-info-change-number-negative {
	color: @color-destructive;
}

.ext-personal-dashboard-moderation-card-info-change-number-none {
	color: @color-subtle;
}
</style>
