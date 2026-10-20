<template>
	<feed-panel
		v-bind="{ ...$attrs, ...feedState }"
		:is-narrow="isNarrow"
		summary-mode="card"
		expand-in-place
		:show-more-aria-label
		:show-less-aria-label
		:empty-label="emptyLabel"
		@load-more="loadMore">
		<template #item="{ item, isNarrow: itemIsNarrow }">
			<list-card v-bind="item" :is-narrow="itemIsNarrow"></list-card>
		</template>
	</feed-panel>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { FeedPanel, FULL_LIMIT } = require( 'ext.personalDashboard.common' );
const ListCard = require( './components/ListCard.vue' );
const { useActiveDiscussionsFeed } = require( './composables/useActiveDiscussionsFeed.js' );

module.exports = defineComponent( {
	components: {
		FeedPanel,
		ListCard
	},
	// The island props (detail, focused, active, isNarrow) and the module's
	// route are never declared here: they ride in $attrs and are forwarded
	// untouched to the scaffold, which owns the compact/full derivation. This
	// module only decides which rule it follows, via summary-mode, and hands
	// over the feed contract.
	inheritAttrs: false,
	props: {
		isNarrow: {
			type: Boolean,
			default: false
		}
	},
	setup() {
		const { feedState, load, loadMore } = useActiveDiscussionsFeed();

		return {
			feedState,
			load,
			loadMore,
			showMoreAriaLabel: mw.msg( 'personal-dashboard-active-discussions-show-more-aria-label' ),
			showLessAriaLabel: mw.msg( 'personal-dashboard-active-discussions-show-less-aria-label' ),
			emptyLabel: mw.msg( 'personal-dashboard-active-discussions-fetch-error' )
		};
	},
	mounted() {
		this.load( FULL_LIMIT );
	}
} );
</script>
