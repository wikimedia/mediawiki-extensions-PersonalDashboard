<template>
	<cdx-card
		class="personal-dashboard-feed__card"
		:separation
		:class="{
			'cdx-card--is-link': url,
			'personal-dashboard-feed__card--visited': markVisited && hasVisited
		}"
	>
		<template #title>
			<a
				v-if="url"
				class="personal-dashboard-feed__card__link"
				:href="url"
				:target="openInNewTab ? '_blank' : null"
				:aria-label="ariaLabel"
				@click="hasVisited = true">
			</a>

			<span class="personal-dashboard-feed__card__header">
				<slot name="header"></slot>
			</span>
		</template>

		<template v-if="$slots.meta" #description>
			<span class="personal-dashboard-feed__card__container">
				<span class="personal-dashboard-feed__card__meta">
					<slot name="meta"></slot>
				</span>

				<span v-if="$slots.description" class="personal-dashboard-feed__card__description">
					<slot name="description"></slot>
				</span>
			</span>
		</template>

		<template v-else-if="$slots.description" #description>
			<slot name="description"></slot>
		</template>

		<template v-if="$slots[ 'supporting-text' ]" #supporting-text>
			<slot name="supporting-text"></slot>
		</template>
	</cdx-card>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxCard } = require( '../codex.js' );

/**
 * The shared feed card chrome: the Codex card, the whole-card overlay link and
 * its stacking context, the visited state, and the header/meta/description rows
 * a feed item is laid out in.
 *
 * The whole card is one click target as an overlay rather than a wrapping <a>:
 * inner links (the page title, the editor, a comment permalink) have to stay
 * separately clickable, and nesting them inside an anchor is invalid HTML.
 *
 * A module fills the slots from its own item shape and keeps its own class on
 * the element for anything specific to it. Classes and data attributes fall
 * through as attrs, so a module's selectors keep resolving against the rendered
 * card.
 */
module.exports = defineComponent( {
	name: 'FeedCard',
	components: { CdxCard },
	inject: {
		// FeedPanel switches its cards to divider rows in the narrow full list.
		separation: {
			from: 'personalDashboardFeedSeparation',
			default: 'outline'
		}
	},
	props: {
		// Where the whole card leads. Omit it for a card with no single
		// destination; only the slotted links are then clickable.
		url: {
			type: String,
			default: ''
		},
		// When the card is clicked, navigate to the url in a new tab.
		openInNewTab: {
			type: Boolean,
			default: false
		},
		// Dims the card after a click on its link, so a user can see which items
		// they opened. Codex cards have no visited state, so it is opt-in.
		markVisited: {
			type: Boolean,
			default: false
		},
		// Labels the overlay link, which has no text of its own.
		ariaLabel: {
			type: String,
			default: ''
		}
	},
	setup() {
		return {
			hasVisited: ref( false )
		};
	}
} );
</script>
