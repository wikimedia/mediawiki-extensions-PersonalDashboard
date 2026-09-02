<template>
	<cdx-menu-button
		ref="menuButton"
		v-model:selected="selected"
		class="personal-dashboard-module-menu__button"
		:class="{ 'personal-dashboard-module-menu__button--has-footer': footerItem }"
		type="button"
		:menu-items="allItems"
		:menu-config="menuConfig"
		:aria-label="buttonLabel"
		@update:selected="onSelect">
		<cdx-icon :icon="cdxIconEllipsis"></cdx-icon>
	</cdx-menu-button>

	<!--
		Whatever the items open. It stays a slot so the messages, and the panels
		themselves, belong to the module the menu describes. The button goes with
		it, since a popover anchors to an element the caller cannot otherwise
		reach.
	-->
	<slot :anchor="menuButton"></slot>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxIcon, CdxMenuButton } = require( '../codex.js' );
const { cdxIconEllipsis } = require( '../icons.json' );

/**
 * The overflow menu for a module header: a quiet icon button that opens a menu
 * of actions about the module.
 *
 * A module opts in server-side with BaseModule::hasHeaderMenu(), which emits an
 * empty mount slot at the end of the header row. The module's own component then
 * teleports this component into that slot. A menu holds client state, so the
 * header cannot render it as server HTML; see ./docs/render-contract.md.
 */
module.exports = defineComponent( {
	name: 'ModuleHeaderMenu',
	components: { CdxIcon, CdxMenuButton },
	// The trigger, and whatever the caller's items open. Vue cannot pick one of
	// those to inherit attributes, so say so rather than warn.
	inheritAttrs: false,
	props: {
		// Codex MenuItemData for each action, in the order they should read.
		menuItems: {
			type: Array,
			required: true
		},
		// One more action, last in the menu with a divider above it. Design asks
		// for the divider Codex gives a menu footer, but not for Codex's own
		// `footer` prop: see the style block below. Reports through `select` as
		// any item does.
		footerItem: {
			type: Object,
			default: null
		},
		// Accessible name for the icon-only button.
		buttonLabel: {
			type: String,
			required: true
		}
	},
	emits: [ 'select' ],
	setup( props, { emit } ) {
		const selected = ref( null );

		/**
		 * CdxMenuButton is a select control, so it holds the item it was given
		 * last. These items are actions, not a choice, so clear the selection at
		 * once: the menu would otherwise reopen with an item marked as chosen.
		 *
		 * @param {string|number|null} value
		 */
		function onSelect( value ) {
			if ( value === null ) {
				return;
			}
			selected.value = null;
			emit( 'select', value );
		}

		return {
			// The menu must stay inside this component. A teleported one goes to
			// the target createMwApp provides, which is also where CdxDialog
			// goes: the menu is z-index 50 there and the dialog's backdrop is
			// 400, so an opened menu would hide behind the dialog whenever this
			// header stands in for a card's own. In place it shares the dialog's
			// stacking context and opens over the body. Set here rather than
			// left to the app default, so no later app-wide provide can move it.
			menuConfig: { renderInPlace: true },
			menuButton: ref( null ),
			selected,
			onSelect,
			cdxIconEllipsis
		};
	},
	computed: {
		// The footer item is an ordinary menu item to Codex. Only our own style
		// rule tells it apart.
		allItems() {
			return this.footerItem ? [ ...this.menuItems, this.footerItem ] : this.menuItems;
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-module-menu {
	&__button .cdx-button:enabled {
		color: @color-subtle;
	}

	// Codex renders this menu in place, so its list lands in the page content,
	// where a skin's own content list styles reach it: Minerva pads `.content ul`
	// at the inline start and gives `.content li` a bottom margin (T433725). Both
	// beat Codex's reset, which names the listbox by one class and says nothing
	// about the items, so the items come out narrower than the menu and spaced
	// apart. Put the menu's own geometry back.
	&__button .cdx-menu__listbox {
		margin: @spacing-0;
		padding: @spacing-0;
	}

	&__button .cdx-menu-item {
		margin: @spacing-0;
	}

	// The divider Codex draws for a menu footer, drawn here instead.
	//
	// Codex's own `footer` prop pins the footer item with position: absolute and
	// keeps room for it by setting margin-bottom on the list from the item's
	// measured height. That measure runs as the menu expands, before Floating UI
	// has given the menu a width, so a label that wraps at zero width reserves
	// two rows and the first open shows an empty row. A second open measures the
	// sized menu and is correct (T433725). Nothing outside Codex can reorder
	// that measure, so the item stays an ordinary one and this rule does the
	// rest. Never on a lone item: the rule separates, it does not underline.
	&__button--has-footer .cdx-menu-item:last-of-type:not( :first-of-type ) {
		border-top: @border-subtle;
	}
}
</style>
