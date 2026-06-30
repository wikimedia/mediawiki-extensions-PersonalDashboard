<template>
	<router-view v-slot="{ Component }">
		<component :is="Component"></component>
	</router-view>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = defineComponent( {
	name: 'App'
} );
</script>
