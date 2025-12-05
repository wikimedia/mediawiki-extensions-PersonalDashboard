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

const { defineComponent, onMounted } = require( 'vue' );
const ListCard = require( './ListCard.vue' );
const { useFetchActivityResult } = require( 'ext.personalDashboard.common' );

module.exports = defineComponent( {
	name: 'RecentActivity',
	components: { ListCard },
	setup() {
		const {
			recentActivityResult,
			loading,
			error,
			fetchRecentActivity
		} = useFetchActivityResult();
		onMounted( () => {
			fetchRecentActivity( 1 );
		} );
		return {
			recentActivityResult,
			loading,
			error
		};
	}
} );
</script>
