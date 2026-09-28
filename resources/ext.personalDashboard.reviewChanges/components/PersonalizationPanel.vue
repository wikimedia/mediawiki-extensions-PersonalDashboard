<template>
	<module-panel :title>
		<cdx-toggle-switch
			v-model="enabled"
			:disabled="disabled || isSaving"
			@update:model-value="onToggle"
		>
			{{ label }}
			<template #description>
				<!-- eslint-disable-next-line max-len -->
				<span v-i18n-html:personal-dashboard-review-changes-personalization-description></span>
			</template>
		</cdx-toggle-switch>
	</module-panel>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { ModulePanel } = require( 'ext.personalDashboard.common' );
const { CdxToggleSwitch } = require( '../codex.js' );
const { isPersonalized, savePersonalized } = require( '../utils.js' );

module.exports = defineComponent( {
	components: { CdxToggleSwitch, ModulePanel },
	props: {
		// Declared, so that the toggle gets it and the panel does not.
		disabled: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'change' ],
	setup() {
		return {
			enabled: ref( isPersonalized() ),
			isSaving: ref( false ),
			title: mw.msg( 'personal-dashboard-review-changes-personalization-title' ),
			label: mw.msg( 'personal-dashboard-review-changes-menu-personalization' )
		};
	},
	methods: {
		async onToggle( value ) {
			this.isSaving = true;
			try {
				await savePersonalized( value );
				this.$emit( 'change' );
			} catch ( err ) {
				// The feed did not change, so show the setting it still uses.
				this.enabled = !value;
				mw.log.error( err );
			} finally {
				this.isSaving = false;
			}
		}
	}
} );
</script>
