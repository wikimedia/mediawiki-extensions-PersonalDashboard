<template>
	<module-dialog
		v-model:open="open"
		:title="activeHeader"
		:back-href="backHref">
	</module-dialog>
	<focused-frame
		v-if="frameName"
		:title="activeHeader"
		@back="onFrameBack">
	</focused-frame>

	<island-mount
		v-for="island in islands"
		v-slot="{ detail, focused, isNarrow: islandIsNarrow, active }"
		:key="island.name"
		:name="island.name"
		:focused="island.name === focusedModule || island.name === mountedName"
		:active-target="island.name === mountedName ? activeTargetId : ''">
		<component
			:is="island.component"
			:detail="detail"
			:focused="focused"
			:is-narrow="islandIsNarrow"
			:active="active">
		</component>
	</island-mount>
</template>

<script>
const { defineComponent } = require( 'vue' );
const FocusedFrame = require( './FocusedFrame.vue' );
const IslandMount = require( './IslandMount.vue' );
const ModuleDialog = require( './ModuleDialog.vue' );
const { DIALOG_TARGET_ID, FRAME_TARGET_ID } = require( './teleportTargets.js' );
const { useViewport } = require( './useViewport.js' );

// The server's card wrapper, which outlives this app and holds every card.
const CARD_CONTAINER = '.personal-dashboard-container';
// Hides the card tree while the app's own focused frame stands in for it.
// Distinct from the server's personal-dashboard-focused body class: there the
// container already holds the one right module, and hiding it would hide the
// page.
const CONTAINER_REPLACED = 'personal-dashboard-container__replaced';

/*
 * The click a router link would act on: a primary click, unmodified, that
 * nothing has handled yet. A modifier or middle click means "open this
 * somewhere else", which is the link's own job. Mirrors vue-router's internal
 * guardEvent, which it doesn't export.
 */
function isPlainClick( event ) {
	return !event.defaultPrevented && event.button === 0 &&
		!event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
}

// The server owns the group and card tree now; this app owns only the
// dialog, the in-page frame, and the islands it teleports into the server's
// mount slots.
module.exports = defineComponent( {
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Dashboard',
	components: {
		FocusedFrame,
		IslandMount,
		ModuleDialog
	},
	props: {
		islands: {
			type: Array,
			default: () => []
		},
		// The focused module's name on a whole-page render, or null on the grouped
		// dashboard. An island matching it is the whole page, so it shows its full
		// body rather than the compact card summary.
		focusedModule: {
			type: String,
			default: null
		}
	},
	setup() {
		const { isNarrow } = useViewport();
		return { isNarrow };
	},
	computed: {
		activeName() {
			// A step within a module lives in the URL hash and belongs to that
			// module's own routing, not the dashboard app's dialog: a policy hash
			// like #neutral-point-of-view opens the policies walkthrough, so the
			// module dialog must stay shut over its card.
			if ( this.$route.hash ) {
				return '';
			}
			/*
			 * Only a known island opens a presentation; an unknown or
			 * server-rendered name in the path would otherwise open an empty,
			 * untitled one. A focused whole-page load names its module here too:
			 * reading the path rather than the module the server named is what
			 * lets a narrow dialog close, and what lets serverFocused below tell
			 * a true server render apart from a soft nav into a different module.
			 */
			const name = this.$route.params.module || '';
			return this.islands.some( ( island ) => island.name === name ) ? name : '';
		},
		activeHeader() {
			const active = this.islands.find( ( island ) => island.name === this.activeName );
			return active ? active.header : '';
		},
		backHref() {
			/*
			 * A focused render is one module's frame, so there is no dashboard
			 * underneath for the dialog to close back onto and we have to load one.
			 * Resolving through the router keeps whichever URL form we arrived on.
			 */
			return this.focusedModule ? this.$router.resolve( '/' ).href : '';
		},
		// A wide load of the focused subpage is already the server's own correct
		// render (one module, chrome intact, its own header); the client's only
		// job is filling that module's slot, same as any other island, so
		// neither the dialog nor the frame should open over it.
		serverFocused() {
			return !!this.focusedModule && this.activeName === this.focusedModule;
		},
		frameName() {
			return ( !this.isNarrow && !this.serverFocused ) ? this.activeName : '';
		},
		// The island that leaves its card slot for whichever presentation is
		// showing: the dialog at a narrow viewport, or the frame at a wide one.
		mountedName() {
			return ( this.isNarrow && this.activeName ) ? this.activeName : this.frameName;
		},
		// Distinct ids per presentation, not one shared between them: crossing
		// the viewport breakpoint while a module is open swaps which component
		// renders the target div, and <teleport> only re-resolves when the `to`
		// value itself changes. A shared id left that swap invisible to
		// <teleport>, so the island's DOM stayed parented to whichever
		// component had just torn down. See teleportTargets.js.
		activeTargetId() {
			if ( this.isNarrow && this.activeName ) {
				return '#' + DIALOG_TARGET_ID;
			}
			return this.frameName ? '#' + FRAME_TARGET_ID : '';
		},
		open: {
			get() {
				return this.isNarrow && !!this.activeName;
			},
			set( value ) {
				if ( value ) {
					return;
				}
				// Escape and the backdrop dismiss the dialog without going through the
				// header's link, so a focused render has to make the same trip here.
				if ( this.backHref ) {
					window.location.assign( this.backHref );
					return;
				}
				this.$router.push( '/' );
			}
		}
	},
	methods: {
		/**
		 * Open an island in the dialog instead of loading its focused page.
		 *
		 * The server makes a card header a real link so the dashboard works
		 * without JS, but an island is already mounted here: pushing its route
		 * opens the dialog over the dashboard with no page load. A module the
		 * client doesn't own has nothing to open, so its link navigates.
		 *
		 * @param {MouseEvent} event
		 */
		onCardLinkClick( event ) {
			if ( !isPlainClick( event ) ) {
				return;
			}
			const link = event.target.closest( 'a.personal-dashboard-module-header-container' );
			const card = link ? link.closest( '[data-module-name]' ) : null;
			const name = card ? card.dataset.moduleName : '';
			if ( !this.islands.some( ( island ) => island.name === name ) ) {
				return;
			}
			event.preventDefault();
			this.$router.push( '/' + name );
		},
		// The frame only ever shows over a live grouped dashboard, so there's
		// always something to push back to; unlike the dialog's open setter,
		// there's no focused-render backHref branch to consider here.
		onFrameBack() {
			this.$router.push( '/' );
		}
	},
	watch: {
		frameName: {
			immediate: true,
			handler( value ) {
				const container = document.querySelector( CARD_CONTAINER );
				if ( container ) {
					container.classList.toggle( CONTAINER_REPLACED, !!value );
				}
			}
		}
	},
	mounted() {
		/*
		 * The cards are the server's DOM, outside this app's tree, so we listen on
		 * their container rather than binding each header. Listening there rather
		 * than at the document also means a click always has an element under it
		 * to search upwards from.
		 */
		const container = document.querySelector( CARD_CONTAINER );
		if ( container ) {
			container.addEventListener( 'click', this.onCardLinkClick );
		}
	},
	unmounted() {
		const container = document.querySelector( CARD_CONTAINER );
		if ( container ) {
			container.removeEventListener( 'click', this.onCardLinkClick );
			container.classList.remove( CONTAINER_REPLACED );
		}
	}
} );
</script>
