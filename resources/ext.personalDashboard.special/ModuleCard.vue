<template>
	<div :class="cardClasses">
		<div v-if="module.header" class="personal-dashboard-module-header">
			<div class="personal-dashboard-module-header-text">
				{{ module.header }}
			</div>

			<div
				v-if="isMobile && module.expandable"
				class="personal-dashboard-module-header-nav-icon">
			</div>
		</div>

		<div v-if="module.subheader" class="personal-dashboard-module-subheader">
			<div class="personal-dashboard-module-subheader-text">
				{{ module.subheader }}
			</div>
		</div>

		<div v-show="cardStyle !== 'minimized'" class="personal-dashboard-module-body">
			<suspense>
				<module-viewport
					:module
					:rendermode
					:active>
				</module-viewport>
			</suspense>
		</div>

		<!-- eslint-disable vue/no-v-html -->
		<div
			v-if="module.footer"
			class="personal-dashboard-module-footer"
			v-html="module.footer">
		</div>
		<!-- eslint-enable vue/no-v-html -->
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const ModuleViewport = require( './ModuleViewport.vue' );

module.exports = defineComponent( {
	name: 'ModuleCard',
	components: {
		ModuleViewport
	},
	props: {
		module: {
			type: Object,
			required: true
		},
		rendermode: {
			type: String,
			required: true
		},
		active: {
			type: Boolean,
			required: true
		}
	},
	computed: {
		isMobile() {
			return this.rendermode.startsWith( 'mobile-' );
		},
		cardMode() {
			return this.isMobile ? 'mobile-summary' : 'desktop';
		},
		cardStyle() {
			return ( this.isMobile && this.module.styleMobile ) ||
				this.module.style ||
				'default';
		},
		cardClasses() {
			const prefix = 'personal-dashboard-module';

			return [
				prefix,
				`${ prefix }-${ this.cardMode }`,
				`${ prefix }-${ this.cardStyle }`,
				`${ prefix }-${ this.module.name }`
			];
		}
	}
} );
</script>
