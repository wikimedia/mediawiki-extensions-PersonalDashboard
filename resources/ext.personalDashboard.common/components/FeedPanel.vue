<template>
	<div class="personal-dashboard-feed">
		<template v-if="isLoading">
			<div
				v-if="showsLoadingCards"
				class="personal-dashboard-feed__list"
				aria-hidden="true">
				<template v-for="index in summaryLimit" :key="index">
					<slot name="loading-card"></slot>
				</template>
			</div>
			<cdx-progress-bar v-else inline></cdx-progress-bar>
		</template>
		<div v-else-if="error">
			<div class="personal-dashboard-feed__error">
				{{ $i18n( 'personal-dashboard-feed-error', error.message ) }}
			</div>
		</div>
		<div v-else-if="!items.length && isIncomplete" class="personal-dashboard-feed__empty">
			{{ $i18n( 'personal-dashboard-feed-incomplete' ) }}
		</div>
		<div v-else-if="!items.length && emptyLabel" class="personal-dashboard-feed__empty">
			{{ emptyLabel }}
		</div>

		<div
			v-show="items.length && !showsLoadingCards"
			ref="list"
			class="personal-dashboard-feed__list"
			:class="{ 'personal-dashboard-feed__list--dividers': usesDividers }"
			role="group"
			tabindex="-1"
			:aria-busy="isLoadingMore || null"
			:aria-label="busyLabel">
			<template v-for="item in visibleItems" :key="item.id">
				<slot
					name="item"
					:item="item"
					:is-narrow="isNarrow">
				</slot>
			</template>
		</div>

		<div
			v-if="!isSummary && ( isLoadingMore || hasMore && !isLoading )"
			class="personal-dashboard-feed__show-more-row"
		>
			<cdx-progress-bar v-if="isLoadingMore" inline></cdx-progress-bar>
			<!-- Absent, not disabled, while a page is in flight or the first
				one is still loading: there is nothing to ask for yet. -->
			<cdx-button
				v-else
				class="personal-dashboard-feed__load-more"
				@click="$emit( 'load-more' )"
			>
				{{ $i18n( 'personal-dashboard-feed-load-more' ) }}
			</cdx-button>
		</div>

		<div
			v-else-if="hasShowMoreButton || isExpanded || showsLoadingCards"
			class="personal-dashboard-feed__show-more-row"
		>
			<cdx-progress-bar v-if="isLoadingMore" inline></cdx-progress-bar>
			<cdx-button
				v-else-if="hasShowMoreButton || isLoading"
				:id="footerId || undefined"
				ref="showMoreButton"
				:disabled="isLoading"
				:aria-label="showMoreAriaLabel || undefined"
				class="personal-dashboard-feed__show-more"
				@click="showMore"
			>
				{{ showMoreLabel || $i18n( 'personal-dashboard-feed-load-more' ) }}
			</cdx-button>
			<cdx-button
				v-if="isExpanded"
				ref="showLess"
				weight="quiet"
				:aria-label="showLessAriaLabel || $i18n( 'personal-dashboard-feed-show-less' )"
				class="personal-dashboard-feed__show-less"
				@click="collapse"
			>
				<cdx-icon :icon="cdxIconCollapse"></cdx-icon>
			</cdx-button>
		</div>
	</div>
</template>

<script>
const { computed, defineComponent } = require( 'vue' );
const { CdxButton, CdxIcon, CdxProgressBar } = require( '../codex.js' );
const { cdxIconCollapse } = require( '../icons.json' );

const FOCUSABLE = 'a[href], button:not( [disabled] ), [tabindex]:not( [tabindex="-1"] )';

/**
 * The shared feed scaffold: loading, error, empty, compact-versus-full, and the
 * show more button every feed module would otherwise re-implement.
 *
 * A module supplies the normalized feed state (see composables/useFeedState.js),
 * its labels, and one `#item` slot rendering a card per item; everything else
 * lives here.
 */

module.exports = defineComponent( {
	name: 'FeedPanel',
	components: {
		CdxButton,
		CdxIcon,
		CdxProgressBar
	},
	// FeedCard reads this, so a module card gets the divider rows without a prop.
	provide() {
		return {
			personalDashboardFeedSeparation: computed( () => this.usesDividers ? 'divider' : 'outline' )
		};
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
		// Separate from isLoading because the list stays on screen while a
		// further page loads, with the progress bar below it.
		isLoadingMore: {
			type: Boolean,
			default: false
		},
		hasMore: {
			type: Boolean,
			default: false
		},
		// A source couldn't be read in full, so an empty list isn't proof that
		// there is nothing to show.
		isIncomplete: {
			type: Boolean,
			default: false
		},
		error: {
			type: Object,
			default: null
		},
		// How many items the summary shows, and how many the show more button adds
		// when it expands in place.
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
		// The module route, which the show more button opens unless it expands in
		// place. The dashboard app passes the slug that the module group gives the
		// module. Outside the dashboard there is no route, and the button then opens
		// nothing.
		route: {
			type: String,
			default: ''
		},
		// Empty means "Show more".
		showMoreLabel: {
			type: String,
			default: ''
		},
		// Names the module, so screen readers can tell the show more buttons
		// on one page apart. It must start with the visible label.
		showMoreAriaLabel: {
			type: String,
			default: ''
		},
		// For the icon-only show less button. Empty means "Show less".
		showLessAriaLabel: {
			type: String,
			default: ''
		},
		footerId: {
			type: String,
			default: ''
		},
		// Shown in place of the list when a load succeeds with nothing in it.
		emptyLabel: {
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
		},
		// On a wide viewport, the show more button adds items to the card instead of
		// opening the full list.
		expandInPlace: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'load-more' ],
	setup() {
		return { cdxIconCollapse };
	},
	data() {
		return {
			// How many items the expanded summary shows. Null means summaryLimit.
			shownCount: null,
			// The item to focus when it shows, so keyboard users reach the new items.
			// The button they used can disappear while a page loads.
			focusIndex: null
		};
	},
	computed: {
		isSummary() {
			return this.summaryMode === 'card' ?
				!( this.focused || this.active ) :
				this.detail === 'compact';
		},
		showsLoadingCards() {
			return this.isLoading && this.isSummary && !!this.$slots[ 'loading-card' ];
		},
		// The full list on a narrow viewport shows rows with dividers, not cards.
		usesDividers() {
			return !this.isSummary && this.isNarrow;
		},
		expandsInPlace() {
			return this.expandInPlace && this.isSummary && !this.isNarrow;
		},
		summaryCount() {
			return ( this.expandsInPlace && this.shownCount ) || this.summaryLimit;
		},
		visibleItems() {
			return this.isSummary ?
				this.items.slice( 0, this.summaryCount ) :
				this.items;
		},
		hasShowMoreButton() {
			return this.isSummary && !this.isLoading && (
				this.items.length > this.summaryCount ||
				( this.expandsInPlace && this.hasMore )
			);
		},
		isExpanded() {
			return this.expandsInPlace && !this.isLoading &&
				this.visibleItems.length > this.summaryLimit;
		},
		// Names the list while it holds focus during a load.
		busyLabel() {
			return this.isLoadingMore ? mw.msg( 'personal-dashboard-feed-loading-more' ) : null;
		}
	},
	methods: {
		showMore() {
			if ( !this.expandsInPlace ) {
				if ( this.route ) {
					this.$router.push( '/' + this.route );
				}

				return;
			}

			// After a failed page, the count can be ahead of the items that loaded.
			const firstNew = Math.min( this.summaryCount, this.items.length );

			this.shownCount = firstNew + this.summaryLimit;

			if ( this.shownCount > this.items.length && this.hasMore ) {
				this.requestNextPage( firstNew );
				return;
			}

			this.focusIndex = firstNew;
			this.$nextTick( this.focusNewItem );
		},
		requestNextPage( firstNew ) {
			this.focusIndex = firstNew;
			this.$emit( 'load-more' );
		},
		collapse() {
			this.shownCount = null;
			// A page that is still loading must not move focus after the collapse.
			this.focusIndex = null;
			this.$nextTick( () => {
				// While a page loads, the progress bar replaces the show more button.
				const button = this.$refs.showMoreButton;

				( button ? button.$el : this.$refs.list ).focus();
			} );
		},
		// Without an item at focusIndex (the next page was empty), focus goes to a button.
		focusNewItem() {
			if ( this.focusIndex === null ) {
				return;
			}

			const item = this.$refs.list.children[ this.focusIndex ];
			const target = item &&
				( item.matches( FOCUSABLE ) ? item : item.querySelector( FOCUSABLE ) );
			const fallback = this.$refs.showMoreButton || this.$refs.showLess;

			this.focusIndex = null;

			if ( target ) {
				target.focus();
			} else if ( fallback ) {
				fallback.$el.focus();
			}
		}
	},
	watch: {
		// A reload (e.g. new filters) starts the list again from the top.
		isLoading( value ) {
			if ( value ) {
				this.shownCount = null;
				this.focusIndex = null;
			}
		},
		isLoadingMore( value ) {
			if ( !value ) {
				this.$nextTick( this.focusNewItem );
				return;
			}

			// The progress bar replaces the button that had focus, and an inline
			// Codex progress bar is aria-hidden. Focus goes to the show less button,
			// or to the list, which is named for the load while it is busy.
			if ( this.focusIndex !== null ) {
				( this.$refs.showLess ? this.$refs.showLess.$el : this.$refs.list ).focus();
			}
		}
	}
} );
</script>
