<template>
	<div ref="moduleRef">
		<feed-panel
			v-bind="{ ...$attrs, ...feedState }"
			module-name="ext.personalDashboard.reviewChanges"
			:is-narrow="isNarrow"
			summary-mode="card"
			footer-id="personal-dashboard-go-to-recentchanges"
			:footer-label="footerLabel"
			:progress-bar-aria-label="progressBarAriaLabel"
			@load-more="loadMore">
			<!-- Renamed, because isNarrow is a prop of this component as well.
				The scaffold owns the value the slot supplies. -->
			<template #item="{ item, isNarrow: itemIsNarrow }">
				<list-card v-bind="item" :is-narrow="itemIsNarrow"></list-card>
			</template>
		</feed-panel>
	</div>

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
			@select="openPanel = $event">
			<module-panel
				:open="openPanel === 'personalization'"
				:anchor="anchor"
				:is-narrow="isNarrow"
				:title="personalizationTitle"
				@update:open="closePanel">
				<p class="personal-dashboard-review-changes__panel-label">
					{{ personalizationLabel }}
				</p>
				<p v-i18n-html:personal-dashboard-review-changes-personalization-description></p>
			</module-panel>

			<module-panel
				:open="openPanel === 'about'"
				:anchor="anchor"
				:is-narrow="isNarrow"
				:title="aboutTitle"
				@update:open="closePanel">
				<p>{{ aboutBody }}</p>
			</module-panel>
		</module-header-menu>
	</teleport>
</template>

<script>
const { defineComponent, ref, watch } = require( 'vue' );
const {
	FeedPanel,
	FULL_LIMIT,
	ModuleHeaderMenu,
	ModulePanel
} = require( 'ext.personalDashboard.common' );
const { useReviewChangesFeed } = require( './composables/useReviewChangesFeed.js' );
const ListCard = require( './components/ListCard.vue' );
const { cdxIconConfigure } = require( './icons.json' );

module.exports = defineComponent( {
	components: {
		FeedPanel,
		ListCard,
		ModuleHeaderMenu,
		ModulePanel
	},
	// The compact/full island props (detail, focused, active) are never declared
	// here: they ride in $attrs and are forwarded untouched to the scaffold,
	// which owns the compact/full derivation. This module only decides which
	// rule it follows, via summary-mode, and hands over the feed contract.
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
		const moduleRef = ref();
		const { feedState, load, loadMore } = useReviewChangesFeed();

		// Which panel the menu has opened, or null for none. One value rather
		// than a flag each: the menu opens one panel at a time.
		const openPanel = ref( null );

		// Fires once the module has both scrolled into view and its feed has
		// finished loading, so a module still off-screen when the fetch resolves
		// doesn't count as an impression (T417757), and the footer link the
		// instrument looks for isn't queried before load() renders it.
		let hasIntersected = false;
		let hasFiredLoadedHook = false;
		function fireLoadedHookWhenReady() {
			if ( hasFiredLoadedHook || !hasIntersected || feedState.isLoading ) {
				return;
			}
			hasFiredLoadedHook = true;
			mw.hook( 'personaldashboard.recentactivity.loaded' ).fire();
		}

		const observer = new IntersectionObserver( ( entries ) => {
			if ( entries[ 0 ].isIntersecting ) {
				hasIntersected = true;
				fireLoadedHookWhenReady();
			}
		} );

		watch( () => feedState.isLoading, fireLoadedHookWhenReady );

		return {
			moduleRef,
			observer,
			feedState,
			load,
			loadMore,
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
			footerLabel: mw.msg( 'personal-dashboard-risky-article-edits-mobile-summary-footer-link-text' ),
			progressBarAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-progress-bar-aria-label' ),
			menuButtonLabel: mw.msg( 'personal-dashboard-review-changes-menu-button-label' ),
			// The menu item names the panel it opens, so both read one message.
			aboutTitle: mw.msg( 'personal-dashboard-review-changes-menu-about' ),
			// The sentence the card used to carry as its subheader, until design
			// moved it in here (T433725).
			aboutBody: mw.msg( 'personal-dashboard-risky-article-edits-subheader-info' ),
			personalizationTitle: mw.msg( 'personal-dashboard-review-changes-personalization-title' ),
			personalizationLabel: mw.msg( 'personal-dashboard-review-changes-menu-personalization' )
		};
	},
	mounted() {
		this.observer.observe( this.moduleRef );
		this.load( FULL_LIMIT );
	},
	unmounted() {
		this.observer.disconnect();
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-review-changes {
	// A panel teleports to <body>, so its content cannot be styled from inside
	// the card container.
	&__panel-label {
		font-weight: @font-weight-bold;
	}
}
</style>
