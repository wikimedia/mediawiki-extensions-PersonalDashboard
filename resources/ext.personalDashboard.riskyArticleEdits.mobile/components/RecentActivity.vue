<template>
	<div v-if="loading">
		Loading...
	</div>

	<div v-else-if="error">
		<p>Error: {{ error.message }}</p>
	</div>

	<div v-if="recentActivityResult">
		<list-card
			v-for="rc in recentActivityResult.query.recentchanges"
			v-bind="rc"
			:key="rc.title"
			:pages="recentActivityResult.query.pages">
		</list-card>
	</div>
</template>

<script>
// TODO: this is duplicated, can probably pull out into a common module
const { defineComponent } = require( 'vue' );
const ListCard = require( './ListCard.vue' );
const {
	recentActivityResult,
	loading,
	error,
	fetchRecentActivity
} = require( '../composables/useFetchActivityResult.js' );

module.exports = defineComponent( {
	name: 'RecentActivity',
	components: { ListCard },
	setup() {
		return {
			recentActivityResult,
			loading,
			error
		};
	},
	mounted() {
		fetchRecentActivity();
	}
} );
</script>
