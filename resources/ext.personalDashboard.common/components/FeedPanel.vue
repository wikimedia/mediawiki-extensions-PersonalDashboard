<template>
	<div class="personal-dashboard-feed">
		<div v-if="isLoading">
			<cdx-progress-bar inline :aria-label="progressBarAriaLabel"></cdx-progress-bar>
		</div>
		<div v-else-if="error">
			<p>{{ $i18n( 'personal-dashboard-feed-error', error.message ) }}</p>
		</div>

		<div
			v-show="items.length"
			class="personal-dashboard-feed__container">
			<div
				class="personal-dashboard-feed__list"
				:class="{ 'personal-dashboard-feed__list--summary': showsSummaryFooter }"
			>
				<template v-for="item in visibleItems" :key="item.id">
					<slot
						name="item"
						:item="item"
						:is-narrow="isNarrow">
					</slot>
				</template>
			</div>
		</div>

		<cdx-button
			v-if="showsSummaryFooter"
			:id="footerId || undefined"
			:aria-label="footerAriaLabel"
			action="progressive"
			weight="quiet"
			class="personal-dashboard-feed__show-more"
			@click="showMore"
		>
			{{ footerLabel }}
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxProgressBar } = require( '../codex.js' );

/**
 * The shared feed scaffold: loading, error, compact-versus-full, and the footer
 * control every feed module would otherwise re-implement.
 *
 * A module supplies the normalized feed state (see composables/useFeedState.js),
 * its labels, and one `#item` slot rendering a card per item; everything else
 * lives here.
 */

module.exports = defineComponent( {
	name: 'FeedPanel',
	components: {
		CdxButton,
		CdxProgressBar
	},
	props: {
		/**
		 * Feed items to render. Each needs a unique `id`; the list keys on it.
		 */
		items: {
			type: Array,
			required: true
		},
		isLoading: {
			type: Boolean,
			default: false
		},
		error: {
			type: Object,
			default: null
		},
		// How many items the compact summary shows. The module fetches the full
		// count once and the summary slices it, so there is no re-fetch on the
		// summary/full transition: the card and the dialog share one teleported
		// component instance rather than remounting.
		summaryLimit: {
			type: Number,
			default: 3
		},
		/**
		 * Which compact-versus-full rule this module follows:
		 *
		 *  - 'card': the grid card is always a summary, on every viewport, and the
		 *    full list belongs to the dialog or the focused page. This is the
		 *    desktop/mobile convergence from T426181.
		 *  - 'viewport': the dashboard-wide rule, where a wide viewport shows the
		 *    full list in the card itself. See docs/decisions.md.
		 */
		summaryMode: {
			type: String,
			default: 'viewport',
			validator: ( value ) => [ 'card', 'viewport' ].includes( value )
		},
		// The module name, which is also its route: the footer control pushes it
		// to open this module's full list.
		moduleName: {
			type: String,
			required: true
		},
		footerLabel: {
			type: String,
			required: true
		},
		footerAriaLabel: {
			type: String,
			default: ''
		},
		footerId: {
			type: String,
			default: ''
		},
		progressBarAriaLabel: {
			type: String,
			default: ''
		},
		// The island props the dashboard app hands every module. The scaffold
		// owns the compact/full derivation over them, so a feed module never
		// declares or interprets them itself.
		detail: {
			type: String,
			default: 'full'
		},
		focused: {
			type: Boolean,
			default: false
		},
		active: {
			type: Boolean,
			default: false
		},
		isNarrow: {
			type: Boolean,
			default: false
		}
	},
	computed: {
		isSummary() {
			return this.summaryMode === 'card' ?
				!( this.focused || this.active ) :
				this.detail === 'compact';
		},
		visibleItems() {
			return this.isSummary ?
				this.items.slice( 0, this.summaryLimit ) :
				this.items;
		},
		// The "show more" footer and the fade hinting at it above the list must
		// agree, or the fade points at a button that isn't there.
		showsSummaryFooter() {
			return this.isSummary && !this.isLoading && this.items.length > this.summaryLimit;
		}
	},
	methods: {
		showMore() {
			this.$router.push( '/' + this.moduleName );
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-feed {
	&__container {
		background: @background-color-neutral;
		padding: @spacing-25;
	}

	&__list {
		display: flex;
		flex-direction: column;
		gap: @spacing-25;

		// Hints at the "Show more" button below, scoped to the last card itself
		// so it can never leak into a card beneath it.
		&--summary > :last-child::after {
			content: '';
			position: absolute;
			bottom: 0;
			left: 0;
			right: 0;
			height: 64px;
			z-index: 2;
			pointer-events: none;
			background: linear-gradient( @background-color-transparent, @background-color-base );
		}
	}

	&__show-more.cdx-button {
		width: 100%;
		max-width: none;
		padding: @spacing-75 0;
	}
}
</style>
