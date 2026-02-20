<template>
	<cdx-card>
		<template #title>
			{{ msgTitle }}
		</template>

		<template #description>
			{{ msgDefinition }}

			<a
				:id="name"
				href="#"
				@click="open = true">
				{{ msgButton }}
			</a>
		</template>
	</cdx-card>

	<multi-step-dialog
		v-model:open="open"
		v-model:step="step"
		:title="msgTitle"
		class="personal-dashboard-policies-guidelines">
		<template
			v-for="( icon, index ) in steps"
			:key="index"
			#[`step-${++index}`]>
			<example-step
				:step="index"
				:name
				:icon>
			</example-step>
		</template>
	</multi-step-dialog>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { MultiStepDialog } = require( 'ext.personalDashboard.common' );
const ExampleStep = require( './ExampleStep.vue' );
const { CdxCard } = require( '../codex.js' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: { CdxCard, MultiStepDialog, ExampleStep },
	props: {
		name: {
			type: String,
			required: true
		},
		steps: {
			type: Object,
			required: true
		}
	},
	data() {
		const msgPrefix = 'personal-dashboard-policies-guidelines-';

		return {
			open: false,
			step: 1,
			/* eslint-disable mediawiki/msg-doc */
			msgTitle: mw.msg( `${ msgPrefix }${ this.name }-title` ),
			msgDefinition: mw.msg( `${ msgPrefix }${ this.name }-definition` ),
			msgButton: mw.msg( `${ msgPrefix }examples-button` )
			/* eslint-enable mediawiki/msg-doc */
		};
	}
} );
</script>
