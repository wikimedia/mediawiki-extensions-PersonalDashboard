# The server render contract: islands architecture {#render_contract}

See [`./architecture.md`](./architecture.md) for how this fits with the rest of the extension, and [`./modules.md`](./modules.md) for the module-authoring mechanics this contract governs.

## The contract

One `serverRendered()` flag on `BaseModule` distinguishes three module kinds, with a fourth that sits outside it:

- **Island** (the default, `serverRendered()` false): the server frame carries an empty mount slot (`getSlot()`); a Vue component matching the module name teleports its body into that slot client-side. `ReviewChanges` is the fullest example.
- **Server-static** (`serverRendered()` true, no `getModules()`): `render()` emits the full body; the client leaves it alone. `Banner`, `ReturnToHomepage`.
- **Progressively enhanced** (`serverRendered()` true, with `getModules()` returning a behavior module): `render()` emits a full server body; a client behavior module runs against that DOM adding interactivity, with no re-render. The shipped examples are in GrowthExperiments rather than here: its Mentorship module serves the card as HTML and lets a behavior module upgrade the "ask a question" link into a Codex dialog ([T432039](https://phabricator.wikimedia.org/T432039)), and an Impact module of the same kind is in review ([T432036](https://phabricator.wikimedia.org/T432036)). No module in this extension is this kind yet; `PoliciesGuidelines` was built server-side, but its accordions are CSS-only and its `getModules()` returns `[]`, so it's server-static in practice. The behavior module noted in its source would make it the first in-tree instance.
- **Behavior-only** (`render()` returns `''`, no card at all): a module that puts nothing on the page and exists purely for client behavior. `Onboarding` is the one instance; it implements `IModule` directly, owns no slot, and the dashboard app keeps mounting it even where slot-bearing card islands are dropped.

`render()` is the sole source of a module's server HTML. `getJsData()` carries only bootstrap data (`enabled`, `header`, `expandable`, `serverRendered`), never body or footer HTML.

Client-side, `IslandMount.vue` (`./resources/ext.personalDashboard.special/`) does the mounting: a `<teleport>` targets either the module's server slot (`#pd-slot-<name>`, CSS-escaped since module names carry dots) or the dialog's mount point, wrapped in `<suspense>` so the whole loading-then-loaded sequence moves as one unit. A production (minified) Vue build renders a stray whitespace text node into the slot if the async child isn't wrapped in an explicit `#default` template; that's a documented gotcha in the component, not a hypothetical.

A no-JS visitor sees real content instead of a blank page: server-rendered modules show their bodies outright, island cards show bordered-but-empty frames, and a page-level Codex warning (`emitNoJsNotice()`, emitted on every render and hidden once the client JS runs, not a literal `<noscript>` element) explains the empty frames. A module name in the URL subpath (see [`./routing.md`](./routing.md)) renders that module's dedicated focused page.

Codex ships a server-side PHP library (`Wikimedia\Codex`), but it only covers presentation and form components: card, button, info chip, message, field, text input. Anything with client state (dialog, popover, menu, combobox) has no PHP builder and lives only in the Vue library. That's a hard ceiling on how much of the dashboard can ever be server-static; PHP and Vue Codex are two implementations of one design, not one isomorphic render, so a component with real interaction has to be a Vue island or progressively enhanced, never pure server HTML.

## Why this exists

MediaWiki has no Vue SSR or hydration. A prior stretch of work (the Vue Router migration) moved module body HTML into `getJsData()` so the client could inject it on mount, which stranded `render()`: it stopped being the thing that put a module's body on the page server-side. That broke silently in a few places at once: module bodies dropping, the `Banner` module rendering nothing, a no-JS visitor served a blank page. [T432595](https://phabricator.wikimedia.org/T432595) (resolved) restored the contract above and named the pattern the codebase had half-built by accident: the server was already emitting a card frame, the client was already filling in bodies. [Islands architecture](https://www.patterns.dev/vanilla/islands-architecture/) is the term for pinning down who owns which half.

## Platform axis dropped; viewport-driven detail

`render()` used to take a platform argument (desktop, mobile-summary, mobile-details, inherited from GrowthExperiments). That string ran two independent decisions through one value: which platform the server renders for, and how much detail to show. The platform axis is gone (`55aa0d3`, "Drop the platform axis; drive detail from viewport"): `render()` takes no platform argument, and the server renders one frame regardless of device. Detail (a compact card summary versus the full view) is a purely client-side computation, keyed off actual viewport width rather than a server-side device sniff, and never reaches PHP. See [`./decisions.md`](./decisions.md) for why this is a deliberate divergence from MediaWiki's server-side platform awareness.

`useViewport()` (`./resources/ext.personalDashboard.special/useViewport.js`) exposes a reactive `isNarrow` off a `matchMedia` query against a breakpoint read from a CSS custom property (LESS remains the single source of the actual pixel value). `IslandMount.vue` computes `detail` from `isNarrow` plus whether the island is active (open in the dialog) or focused (the whole-page render): narrow and not full means `compact`, everything else means `full`. Column collapse at the wider breakpoint is a CSS container query on `.personal-dashboard-container` at first paint, with a `ResizeObserver` fallback in `init.js` for browsers that don't support `@container`.

`IslandMount` hands the island `focused`, `active` and the raw `isNarrow` alongside `detail`, so a module can decide for itself rather than take the computed answer. `ActiveDiscussions` branches on `detail`; `ReviewChanges` uses `focused` and `active` instead, showing a summary card in the dashboard grid on every viewport and the full list only when focused or open in the dialog.
