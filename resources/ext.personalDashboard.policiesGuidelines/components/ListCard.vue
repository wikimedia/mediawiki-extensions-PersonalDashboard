<template>
	<cdx-card>
		<template #title>
			{{ msgTitle }}
		</template>

		<template #description>
			{{ msgDefinition }}

			<a href="#" @click="openDialog = true">
				{{ msgExample }}
			</a>
		</template>
	</cdx-card>

	<examples-dialog
		v-model:open="openDialog"
		:prefix
		:pages>
	</examples-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxCard } = require( '../codex.js' );
const ExamplesDialog = require( './ExamplesDialog.vue' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: { CdxCard, ExamplesDialog },
	props: {
		prefix: {
			type: String,
			required: true
		},
		pages: {
			type: Array,
			required: true
		}
	},
	setup( props ) {
		const msgPrefix = 'personal-dashboard-policies-guidelines-';

		return {
			openDialog: ref( false ),
			/* eslint-disable mediawiki/msg-doc */
			msgTitle: mw.msg( msgPrefix + props.prefix + '-title' ),
			msgDefinition: mw.msg( msgPrefix + props.prefix + '-definition' ),
			msgExample: mw.msg( msgPrefix + 'examples-button' )
		};
	}
} );
</script>
