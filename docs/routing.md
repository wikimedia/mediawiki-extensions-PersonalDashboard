# Routing and the focused module {#routing}

See [`./architecture.md`](./architecture.md) for how this fits with the rest of the extension, and [`./render-contract.md`](./render-contract.md) for what an island actually is (this doc covers how one gets opened).

## Vue Router

`./resources/lib/vue-router` is vendored (core ships `vue` and `pinia` but not `vue-router`). `createMediaWikiHistory()` (`./resources/ext.personalDashboard.special/mediaWikiHistory.js`) binds the router to real MediaWiki subpage URLs rather than a hash router.

## The module-name-as-subpage route

The route is `/:module(.*)`, matching the special page's `$par`. The module name is the whole meaningful part of the route; it's simultaneously:

- the subpage `SpecialPersonalDashboard::execute()` reads to decide what to render server-side,
- the destination an expandable card's header links to, with or without JavaScript, and
- the name `ModuleDialog.vue` opens over the dashboard when a client-side route push carries it.

A subpath deeper than the module name (`ext.personalDashboard.policiesGuidelines/neutral-point-of-view`) is a step within that module, not dashboard-app routing: it rides in the URL hash and the module reads it for itself (`BaseModule::acceptsFocusedSubPath()`/`setFocusedSubPath()` server-side for the no-JS destination; the module's own client router for anything deeper). `Dashboard.vue` explicitly keeps the module dialog shut when `$route.hash` is set, so a module's own deep link doesn't collide with the card dialog opening over it.

The router keys on the full module name, which makes for long URLs; shortening them with group-defined slugs is [T434346](https://phabricator.wikimedia.org/T434346).

## Reaching a focused module

An expandable card's header is a server-rendered link to the module's own URL: `BaseModule::getCardHeader()` emits an `<a>` when `shouldWrapModuleWithLink()` is set. Clicking it is a real navigation, because the dashboard app owns only the dialog and the islands and never the card tree, so nothing intercepts it. What loads is `SpecialPersonalDashboard::renderFocusedFrame()`, a whole-page server render of just that module with a page-owned back link injected into its header. A card click, a bookmark, a cold load and a no-JS request all arrive at the same render.

`ModuleDialog.vue` presents the same module over the dashboard instead, teleporting the same island body into `#personal-dashboard-teleport`. It opens when a client-side route push names a known island, which today only Review Changes' "View more edits" button does. So one URL can still present two ways depending on how it was reached.

Which presentation wins is a product question rather than a coding one; [T433896](https://phabricator.wikimedia.org/T433896) is open to settle it. Keeping the whole page means keeping the skin's chrome around it; `3f39014` went that way, styling the focused layout to fill the page but leaving the chrome in place. Opening a focused load in the dialog is chrome-free and full-bleed, but a dialog can't exist without JavaScript, so the URL stops rendering the same way however it's reached. Both directions have a patch behind them.
