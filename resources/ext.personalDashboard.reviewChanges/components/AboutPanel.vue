<template>
	<module-panel :title>
		<p>{{ body }}</p>
	</module-panel>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { ModulePanel } = require( 'ext.personalDashboard.common' );

module.exports = defineComponent( {
	components: { ModulePanel },
	setup() {
		return {
			// The menu item names the panel it opens, so both read one message.
			title: mw.msg( 'personal-dashboard-review-changes-menu-about' ),
			// The sentence the card used to carry as its subheader, until design
			// moved it in here (T433725).
			body: mw.msg( 'personal-dashboard-risky-article-edits-subheader-info' )
		};
	}
} );
</script>
