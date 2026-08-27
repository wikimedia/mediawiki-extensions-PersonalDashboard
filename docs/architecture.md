# PersonalDashboard architecture {#architecture}

This is the entry point to PersonalDashboard's code-facing architecture; each major piece has its own doc.

For product background (what the dashboard is, deployment history, survey results), see [Extension:PersonalDashboard](https://www.mediawiki.org/wiki/Extension:PersonalDashboard) and [Moderator Tools/Dashboard](https://www.mediawiki.org/wiki/Moderator_Tools/Dashboard) on mediawiki.org. For where the platform is heading, see [T434340](https://phabricator.wikimedia.org/T434340), the epic tracking PD as a toolkit other teams build on. The code today renders one dashboard, at `Special:PersonalDashboard`.

## What it is

Personal Dashboard is a platform: it provides the wiring that modules plug into. It registers them, arranges them into groups, and puts them on the page, but it doesn't know what any module does inside. Where a module fetches its data, how it renders itself, how it handles its own navigation: all of it is orthogonal to the dashboard code, which never reaches in. When you're tracing how a feature works, the answer is almost always in that module, not in the dashboard.

The one place the platform and a module meet is the render boundary. The server draws every module's frame; from there the module chooses how much of its body is server-rendered. Most modules leave the body empty and let a Vue component fill it in the browser; these are the default, and we call them islands. Some render their whole body server-side. A module can also render server-side and let a client script enhance it. That range exists because MediaWiki can't run Vue on the server, so each module decides how much it can show without JavaScript.

## How a dashboard loads

Here is one request to `Special:PersonalDashboard`, end to end.

1. `SpecialPersonalDashboard::execute( $par )` requires a logged-in user, loads the app JS and styles, emits the `#personal-dashboard-root` mount point, and splits `$par` into a module name and an optional deeper sub-path.
2. `PersonalDashboardModuleFactory` builds each module from the `PersonalDashboard.Modules` specs; the `PersonalDashboard.ModuleGroups` attribute arranges them into groups and subgroups. See [`./modules.md`](./modules.md).
3. Each module's `render()` emits its card frame. An island leaves the body an empty `#pd-slot-<name>`; a server-rendered module emits its full body. `getJsData()` carries bootstrap data only (`enabled`, `header`, `expandable`, `serverRendered`), never body HTML. See [`./render-contract.md`](./render-contract.md).
4. The group tree and each module's bootstrap ship to the browser as JS config vars. Island bodies aren't loaded by PHP; `init.js` lazy-loads each island's ResourceLoader module when it mounts.
5. `init.js` builds a Vue Router over one catch-all route (`/:module(.*)`) bound to real subpage URLs; `Dashboard.vue` mounts one `IslandMount` per island. See [`./routing.md`](./routing.md).
6. `IslandMount.vue` teleports each island's Vue component into its server slot (`#pd-slot-<name>`, CSS-escaped), inside a `<suspense>`. Compact-versus-full detail is a client viewport computation.

Two more paths through the same code:

- A module name in the subpath (`Special:PersonalDashboard/ext.personalDashboard.reviewChanges`) renders that one module as a whole server page (`renderFocusedFrame()`). At a narrow viewport `ModuleDialog.vue` opens over that page, full-screen and free of the skin's chrome; at a wide viewport the server's own render already is the intended design, chrome and all, so nothing opens over it. An expandable card's header links to a module's own URL, but with JavaScript `Dashboard.vue` catches the click and pushes the route instead of loading it: at a narrow viewport that opens the dialog with no page load, at a wide viewport it opens `FocusedFrame.vue` in place instead, keeping the skin's chrome visible. Without JavaScript the link navigates to the server's inline render ([T433896](https://phabricator.wikimedia.org/T433896)). See [`./routing.md`](./routing.md).
- With no JavaScript, server-rendered bodies show as-is; island cards show empty framed cards under a page-level notice explaining why. See [`./render-contract.md`](./render-contract.md).

## The pieces

**Module registration and composition.** Modules register as ObjectFactory specs under the `PersonalDashboard.Modules` attribute; placement into groups and subgroups is a separate `PersonalDashboard.ModuleGroups` attribute, mergeable across extensions. This is the code-facing "how do I add a module" mechanics. See [`./modules.md`](./modules.md).

**The server render contract (islands architecture).** How a module's server HTML and its client Vue behavior fit together: which modules are islands, which are server-static, which are progressively enhanced, and why MediaWiki's lack of Vue SSR makes that split load-bearing rather than cosmetic. Also covers the platform-axis removal (detail is now viewport-driven, client-side, not a server render mode). See [`./render-contract.md`](./render-contract.md).

**Routing and the focused module.** The Vue Router migration, the module-name-as-subpage convention, and the two presentations one module URL can produce: the whole-page server render, or the dialog over the dashboard. See [`./routing.md`](./routing.md).

**The shared feed scaffold.** Review Changes and Active Discussions are both feeds, and the loading state, the error state, the compact-versus-full derivation, the footer control and the card chrome are one implementation in `ext.personalDashboard.common` rather than a copy per module ([T433900](https://phabricator.wikimedia.org/T433900)). A feed module supplies its queries, its labels and a card body; the scaffold consumes a normalized feed-data contract (`items`, `isLoading`, `error`) that a Pinia store, a fetch composable, or a server-fed `getJsData()` payload can all satisfy. See [`./modules.md`](./modules.md#feed-modules).

**Design decisions.** Deliberate divergences from MediaWiki convention, with the reasoning and a receipt for each: the dropped platform axis, and why registration stays declarative attributes rather than a runtime registry. See [`./decisions.md`](./decisions.md).

**Type safety.** `IModule`, `BaseModule` and the module classes carry declared types, `strict_types` and enums (`ModuleStateEnum` is the first). `Banner` is the one module still missing its `strict_types` declaration; the hook handlers, the factory and the utility classes haven't had the sweep at all. No design change either way; `41413ef` was the first pass.

**Instrumentation.** The baseline health-metrics instrument moved out of WikimediaEvents into PD itself ([T430716](https://phabricator.wikimedia.org/T430716)); see [`../README.md`](../README.md#instrumentation).
