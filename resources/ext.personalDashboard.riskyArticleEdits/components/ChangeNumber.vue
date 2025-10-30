<template>
	<span
		class="ext-personal-dashboard-moderation-card-info-change-number"
		:class="{
			'ext-personal-dashboard-moderation-card-info-change-number-positive': oldlen - newlen > 0,
			'ext-personal-dashboard-moderation-card-info-change-number-negative': oldlen - newlen < 0
		}"
	>
		{{ getChangeNumber( oldlen, newlen ) }}
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
	methods: {
		getChangeNumber: function ( oldlen, newlen ) {
			const value = oldlen - newlen;
			if ( value > 0 ) {
				return `+${ value }`;
			} else if ( value < 0 ) {
				return `${ value }`;
			}
			return '';
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-personal-dashboard-moderation-card-info-change-number {
	margin-left: auto;
}

.ext-personal-dashboard-moderation-card-info-change-number-positive {
	color: @color-success;
}

.ext-personal-dashboard-moderation-card-info-change-number-negative {
	color: @color-destructive;
}
</style>
