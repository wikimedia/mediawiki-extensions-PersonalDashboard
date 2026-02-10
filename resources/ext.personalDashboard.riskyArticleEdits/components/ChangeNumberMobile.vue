<template>
	<span>
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
		getChangeNumber( oldlen, newlen ) {
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
