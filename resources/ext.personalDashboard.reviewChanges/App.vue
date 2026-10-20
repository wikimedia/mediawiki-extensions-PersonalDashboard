<template>
	<feed-panel
		v-bind="{ ...$attrs, ...feedState }"
		:is-narrow="isNarrow"
		summary-mode="card"
		expand-in-place
		:show-more-aria-label
		:show-less-aria-label
		:empty-label="emptyLabel"
		@load-more="loadMore"
	>
		<!-- Renamed, because isNarrow is a prop of this component as well.
			The scaffold owns the value the slot supplies. -->
		<template #item="{ item, isNarrow: itemIsNarrow }">
			<list-card v-bind="item" :is-narrow="itemIsNarrow"></list-card>
		</template>
	</feed-panel>

	<!--
		The menu button belongs in the module header, which the server renders.
		The header slot is the client's mount point there. This teleport nests
		inside the island's own teleport, and follows the body into the dialog or
		the in-page frame, since either stands in for the whole card.
	-->
	<teleport v-if="headerTarget" :to="headerTarget">
		<module-header-menu
			v-slot="{ anchor }"
			:menu-items="menuItems"
			:footer-item="menuFooterItem"
			:button-label="menuButtonLabel"
			@select="openPanel = $event"
		>
			<personalization-panel
				:open="openPanel === 'personalization'"
				:anchor
				:is-narrow
				:disabled="feedState.isLoading || feedState.isLoadingMore"
				@update:open="closePanel"
				@change="reloadFeed"
			></personalization-panel>

			<about-panel
				:open="openPanel === 'about'"
				:anchor
				:is-narrow
				@update:open="closePanel"
			></about-panel>
		</module-header-menu>
	</teleport>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const {
	FeedPanel,
	FULL_LIMIT,
	ModuleHeaderMenu
} = require( 'ext.personalDashboard.common' );
const { useReviewChangesFeed } = require( './composables/useReviewChangesFeed.js' );
const AboutPanel = require( './components/AboutPanel.vue' );
const ListCard = require( './components/ListCard.vue' );
const PersonalizationPanel = require( './components/PersonalizationPanel.vue' );
const { cdxIconConfigure } = require( './icons.json' );

module.exports = defineComponent( {
	components: {
		AboutPanel,
		FeedPanel,
		ListCard,
		ModuleHeaderMenu,
		PersonalizationPanel
	},
	// FeedPanel gets the module route and the island props detail, focused,
	// and active through $attrs.
	inheritAttrs: false,
	props: {
		// Selector for the mount slot in the server-rendered module header, or
		// null when the frame emits none. IslandMount resolves it. Declared
		// rather than left to ride in $attrs because this module consumes it
		// itself: it is orthogonal to detail, and the scaffold has no use for
		// it.
		headerTarget: {
			type: String,
			default: null
		},
		// Declared, unlike its fellow island props, because the header panels
		// switch on it: a dialog on a wide viewport, a bottom sheet on a narrow
		// one. Declaring it takes it out of $attrs, so the template hands it to
		// the scaffold by name instead.
		isNarrow: {
			type: Boolean,
			default: false
		}
	},
	setup() {
		const { feedState, load, loadMore } = useReviewChangesFeed();

		// Which panel the menu has opened, or null for none. One value rather
		// than a flag each: the menu opens one panel at a time.
		const openPanel = ref( null );

		return {
			feedState,
			load,
			loadMore,
			reloadFeed: () => load( FULL_LIMIT ),
			openPanel,
			// A panel only ever reports itself closed: it is opened from the menu
			// above, never by its own model.
			closePanel: () => {
				openPanel.value = null;
			},
			menuItems: [
				{
					value: 'personalization',
					label: mw.msg( 'personal-dashboard-review-changes-menu-personalization' ),
					icon: cdxIconConfigure
				}
			],
			// The footer, not a second ordinary item: design shows a divider
			// above it, and that is what Codex gives a menu footer.
			menuFooterItem: {
				value: 'about',
				label: mw.msg( 'personal-dashboard-review-changes-menu-about' )
			},
			showMoreAriaLabel: mw.msg( 'personal-dashboard-review-changes-show-more-aria-label' ),
			showLessAriaLabel: mw.msg( 'personal-dashboard-review-changes-show-less-aria-label' ),
			emptyLabel: mw.msg( 'personal-dashboard-review-changes-empty' ),
			menuButtonLabel: mw.msg( 'personal-dashboard-review-changes-menu-button-label' )
		};
	},
	mounted() {
		this.load( FULL_LIMIT );
	}
} );
</script>
