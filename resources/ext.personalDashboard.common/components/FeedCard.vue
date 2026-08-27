<template>
	<cdx-card
		class="personal-dashboard-feed__card"
		:class="{ 'personal-dashboard-feed__card--visited': hasVisited }"
	>
		<!-- Header content often contains a link, and because we need the whole card to also
			be a link (when `url` is set), the header/title goes inside the description slot. -->
		<template #description>
			<span class="personal-dashboard-feed__card__container">
				<a
					v-if="url"
					class="personal-dashboard-feed__card__link"
					:href="url"
					target="_blank"
					:aria-label="ariaLabel"
					@click="hasVisited = true">
				</a>

				<span class="personal-dashboard-feed__card__header">
					<slot name="header"></slot>
				</span>

				<span v-if="$slots.meta" class="personal-dashboard-feed__card__meta">
					<slot name="meta"></slot>
				</span>

				<span v-if="$slots.description" class="personal-dashboard-feed__card__description">
					<slot name="description"></slot>
				</span>
			</span>
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
	props: {
		// Where the whole card leads. Omit it for a card with no single
		// destination; only the slotted links are then clickable.
		url: {
			type: String,
			default: ''
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

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-feed__card {
	&.cdx-card {
		border-color: @border-color-transparent;
		color: @color-emphasized;
		padding: @spacing-100;
		position: relative;

		&:hover {
			border-color: @border-color-subtle;
		}
	}

	&__header {
		align-items: center;
		display: flex;
		font-weight: @font-weight-bold;
		gap: @spacing-35;
	}

	&__header a,
	&__meta a {
		color: inherit;
	}

	// Doubled class on purpose: a module sets its own text colour on the card
	// from its own stylesheet, which ResourceLoader loads after this one, so the
	// visited treatment needs the extra specificity to win.
	.cdx-card&.personal-dashboard-feed__card--visited {
		color: @color-subtle;
		background-color: @background-color-neutral-subtle;
	}

	.cdx-card__text {
		width: @size-full;

		&__title,
		&__description {
			color: inherit;
		}

		&__description {
			margin-top: @spacing-0;
		}
	}

	&__container {
		display: flex;
		flex-direction: column;
		gap: @spacing-25;
		line-height: @line-height-x-small;
	}

	&__link {
		position: absolute;
		inset: 0;
		z-index: 0;
	}

	// Lifted above the overlay link so slotted links and buttons stay clickable.
	// The overlay itself is excluded: it has to keep its absolute positioning.
	&__container a:not( .personal-dashboard-feed__card__link ),
	&__container .cdx-button {
		position: relative;
		z-index: 1;
	}

	&__meta {
		display: flex;
		align-items: center;
		// A row that runs long wraps rather than overflowing the card, so a
		// module can let its leading group truncate only once the trailing one
		// has moved down. min-height, not height: a fixed one can't hold the
		// second line.
		flex-wrap: wrap;
		gap: @spacing-25;
		min-height: 20px;
	}

	// How much of this row is shown is the module's call, since only it knows
	// what it put there. All the row guarantees is that a word longer than the
	// line breaks rather than overflowing the card; overflow-wrap inherits, so
	// this covers the module's own elements too.
	&__description {
		overflow-wrap: break-word;
	}
}
</style>
