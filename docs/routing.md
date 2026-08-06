# Routing and the focused module {#routing}

See [`./architecture.md`](./architecture.md) for how this fits with the rest of the extension, and [`./render-contract.md`](./render-contract.md) for what an island actually is (this doc covers how one gets opened).

## Vue Router

`./resources/lib/vue-router` is vendored (core ships `vue` and `pinia` but not `vue-router`). `createMediaWikiHistory()` (`./resources/ext.personalDashboard.special/mediaWikiHistory.js`) binds the router to real MediaWiki subpage URLs rather than a hash router.

## The module-name-as-subpage route

The route is `/:module(.*)`, matching the special page's `$par`. The module name is the whole meaningful part of the route; it's simultaneously:

- the subpage `SpecialPersonalDashboard::execute()` reads to decide what to render server-side,
- the destination an expandable card's header links to, and
- the name `ModuleDialog.vue` opens over the dashboard when a client-side route push carries it.

A subpath deeper than the module name (`ext.personalDashboard.policiesGuidelines/neutral-point-of-view`) is a step within that module, not dashboard-app routing: it rides in the URL hash and the module reads it for itself (`BaseModule::acceptsFocusedSubPath()`/`setFocusedSubPath()` server-side for the no-JS destination; the module's own client router for anything deeper). `Dashboard.vue` explicitly keeps the module dialog shut when `$route.hash` is set, so a module's own deep link doesn't collide with the card dialog opening over it.

The router keys on the full module name, which makes for long URLs; shortening them with group-defined slugs is [T434346](https://phabricator.wikimedia.org/T434346).

## Reaching a focused module

An expandable card's header is a server-rendered link to the module's own URL: `BaseModule::getCardHeader()` emits an `<a>` when `shouldWrapModuleWithLink()` is set. With JavaScript, clicking it doesn't load that URL. `Dashboard.vue` listens for clicks on `.personal-dashboard-container`, the server's card container, since the cards sit outside the app's own tree; it reads the clicked card's `data-module-name`, and when that name is an island the client has mounted, cancels the click and pushes the route instead. The dialog opens over the dashboard with no page load.

`isPlainClick()` decides which clicks to take, mirroring `guardEvent`, which vue-router doesn't export: a modified or middle click means "open this somewhere else" and belongs to the link. The `href` is untouched, so those clicks still open the page in a tab, and a module the client doesn't own as an island is left to navigate.

A real load of that URL, whether from a bookmark, a cold start, a modified click or a browser with no JavaScript, gets `SpecialPersonalDashboard::renderFocusedFrame()`: a whole-page server render of just that module, with a page-owned back link injected into its header. What happens next with JavaScript splits by viewport.

At a narrow viewport, the client opens that same module in the dialog over the page it just loaded, because `activeName` reads the module out of the route path rather than the one the server named. The code used to read the server's name, and a dialog keyed to it could never close: that name is fixed for the whole page load. The server's own render stays underneath with its `#pd-slot-` body empty, since the island teleports into the dialog rather than into its slot.

At a wide viewport, the server's render is already exactly what the design wants: one module, the skin's chrome intact, its own back-arrow header. `Dashboard.vue`'s `serverFocused` computed property catches this case (`activeName` equal to the static `focusedModule` bootstrap prop) and opens neither the dialog nor the frame, so the island fills the server's own slot in place, same as any card in the grouped dashboard. Opening a frame over this render would double the header and empty the module's own slot for no reason, which is why `serverFocused` exists as its own check rather than folding into the frame's condition below.

A wide viewport takes a third presentation for a soft nav into a module that is *not* the one the server rendered as the whole page (a card click, a "view more" button): `FocusedFrame.vue`, an in-page, non-modal frame reusing the chrome-intact, one-module treatment the server's own focused render already has, without a page load. `ModuleDialog.vue` stays mounted at every width (it closes via its own `open` prop rather than being torn out while open, so its `CdxDialog` never tears down mid-scroll-lock), and `FocusedFrame.vue` mounts only while its module is open; each mints its own teleport id (`teleportTargets.js`) rather than sharing one, because crossing the viewport breakpoint with a module open swaps which of the two an island belongs to, and `<teleport>` only re-resolves its target when the `to` value itself changes between renders. `Dashboard.vue`'s `activeTargetId` computed picks the right one, so the shared underlying mechanic, "hand the active island the id to teleport into", still holds; only the id itself differs by presentation. Its hide-class, `personal-dashboard-container__replaced`, is deliberately not the server's `personal-dashboard-focused` body class: that one marks a render where the container already holds the single right module and must never be hidden, while the frame's hide-class hides a container that still holds every other card underneath it. Hiding a live card container with `display: none` drops keyboard focus to `<body>` if nothing claims it, so `FocusedFrame.vue` calls `.focus()` on its own root on mount; `CdxDialog` gets this for free from its own focus trap, and the non-modal frame has to do it by hand.

| entry | narrow | focusedModule | activeName | dialog | frame | container hidden |
|---|---|---|---|---|---|---|
| grouped, no route | any | null | '' | closed | - | no |
| grouped, card click | true | null | X | open | - | no |
| grouped, card click | false | null | X | - | X | yes |
| focused subpage cold load | true | X | X | open | - | no |
| focused subpage cold load | false | X | X | - | - | no (server already right) |
| step hash | any | any | '' | closed | - | no |

That dialog, which only ever opens at a narrow viewport, has nowhere to close back onto: a focused render has no dashboard behind it. So `backHref` resolves the dashboard's own URL through the router, and the dialog header's arrow is a real link to it rather than a close button. Escape and the backdrop bypass that link, so the `open` setter makes the same trip with `window.location.assign()`. The frame never needs this branch: it only ever shows over a live grouped dashboard, so `onFrameBack()` can always just push `/`.

Full-screen and chrome-free is the design at a narrow viewport; a wide viewport keeps the skin's chrome and shows one module in place, whether that's `FocusedFrame.vue` standing in for a soft nav or the server's own render on a cold load. The whole-page render under the skin's chrome, at every width, was the interim we took when the islands work and the focused view shipped: `3f39014` styled that layout to fill the page but left the chrome in place. [T433896](https://phabricator.wikimedia.org/T433896) covers the split above. A dialog (or a frame) can't exist without JavaScript, so a no-JS visitor still gets the server's inline focused render; at a narrow viewport that render covers the skin's chrome with an opaque layer on `.personal-dashboard-viewport`, rather than only reading as full-screen once the dialog's JavaScript opens over it. MobileFrontend's editor takeover gets its full-screen view the same way. The layer alone isn't enough everywhere: Vector 2022, Vector legacy, and MonoBook each position their own content wrapper with an explicit z-index, which caps the layer below a header sitting outside that wrapper regardless of the layer's own z-index, so those three also carry a `skinStyles` override (`resources/ext.personalDashboard.styles/skinStyles/`) raising that wrapper to the same `@z-index-off-canvas-backdrop` token the layer uses. That's four skin-owned selectors across four files rather than the twenty the approach before this named in one, but it isn't immune to the same rename risk; [T433896](https://phabricator.wikimedia.org/T433896) carries the measurements and the per-skin values. The layer covers the chrome rather than removing it, so the chrome stays in the tab order and the accessibility tree until the focused container's ancestors are marked inert.
