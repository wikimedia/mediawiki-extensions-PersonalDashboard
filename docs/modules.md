# Authoring a Personal Dashboard module {#modules}

Personal Dashboard is experimental; the details below will change.

A module is a discrete unit of content on `Special:PersonalDashboard` (Impact, Active Discussions, Review Changes, and so on). Modules render their own content; the module-group registry places them into the page.

Any extension can contribute a module via the `PersonalDashboard.Modules` attribute. This doc uses [BoilerPlate](https://www.mediawiki.org/wiki/Extension:BoilerPlate) as a stand-in; it isn't actually shipping a module. For a real one, GrowthExperiments registers its Mentorship module this way ([T432039](https://phabricator.wikimedia.org/T432039)), extending `BaseModule` rather than implementing `IModule` directly.

Registering a module means one entry in the extension's `./extension.json` and a PHP class. Placing it on the dashboard is either a change to Personal Dashboard (for the default layout) or a new group in your own `./extension.json` (for experiments).

## Register the module

Add an entry to the `PersonalDashboard.Modules` attribute in BoilerPlate's `./extension.json`. The key is the module name (conventionally `ext.<extensionName>.<moduleName>`); the value is an ObjectFactory spec.

```json
"attributes": {
    "PersonalDashboard": {
        "Modules": {
            "ext.boilerPlate.example": {
                "class": "MediaWiki\\Extension\\BoilerPlate\\ModuleExample"
            }
        }
    }
}
```

If the module needs services, list them under `"services"`:

```json
"ext.boilerPlate.example": {
    "class": "MediaWiki\\Extension\\BoilerPlate\\ModuleExample",
    "services": ["ConnectionProvider"]
}
```

Multiple extensions can contribute entries under `PersonalDashboard.Modules`; MediaWiki merges the attribute across all loaded extensions.

If a group references a name that isn't registered, `PersonalDashboardModuleFactory` logs an error and substitutes `ext.personalDashboard.placeholder`. Broken modules render as empty containers instead of throwing, which is easy to miss in production.

## Write the module class

Modules implement `MediaWiki\Extension\PersonalDashboard\IModule`. Six methods:

- `render()`: returns the module's server-side card frame, one frame regardless of device. The detail level within it (a compact summary versus the full view) is derived client-side from viewport width and never reaches PHP.
- `getJsData()`: return value is packed into `wgPersonalDashboardGroups` on the page for the client to read. It carries what the client needs to mount and coordinate the module (enabled, header, expandable, serverRendered), not the body or footer HTML.
- `getJsConfigVars()`: additional `mw.config` keys the module's client-side code needs.
- `supports()`: return false to skip the module entirely; `render()` and `getJsData()` are then never called.
- `setName($name)`, `setPageURL($url)`: called by the factory and the special page before render; typically empty stubs.

For BoilerPlate, the class lives at `src/ModuleExample.php` under `MediaWiki\Extension\BoilerPlate\`. `./src/Modules/ReturnToHomepage.php` is the smallest working example in Personal Dashboard: no context, no services, `render()` returns a static link.

If the module needs a context or services, the constructor takes an `IContextSource` first, then any services declared in the registry. `SpecialPersonalDashboard` supplies the context via `getRequestedModule()` in `./src/Specials/SpecialPersonalDashboard.php`; services come from the `"services"` list above.

Personal Dashboard's own modules extend an internal helper, `MediaWiki\Extension\PersonalDashboard\Modules\BaseModule`, that composes `render()` from a header/subheader/body/footer skeleton so subclasses only supply the pieces. The helper isn't part of the platform contract, but it's a decent example of what a helper can look like. Typical overrides:

- `getHeaderText()`: the card title as a string.
- `getModules()`: ResourceLoader module names to load for this module.
- `getJsConfigVars()`: additional `mw.config` keys the module's client-side code needs.

By default BaseModule emits a card frame whose body is an empty mount slot; the module's Vue app teleports its content into that slot once it loads. This is an *island* module, the common case. Two overrides opt out of it:

- `serverRendered()`: return true for a module whose whole body is server HTML with no client mount. The client leaves it in place.
- `getBody()`: the server-rendered body HTML. Consulted only when `serverRendered()` is true; override one without the other and the body silently never renders.

GrowthExperiments' `includes/PersonalDashboard/Mentorship.php` extends BaseModule from outside this extension and overrides both, so its body is server HTML. `./src/Modules/Impact.php` extends BaseModule but overrides neither, staying an island that queries the database and hands its counts to Vue as config vars.

Client-side (Vue) modules are the island default: BaseModule emits the mount slot, and a matching ResourceLoader module registered under `ResourceModules` in the same extension's `./extension.json` supplies the Vue app the dashboard teleports in. The dashboard app hands it `detail`, `focused`, `active` and `isNarrow`, and the module decides its own presentation from those; it needs no PHP beyond the frame. `./src/Modules/ReviewChanges.php` paired with `./resources/ext.personalDashboard.reviewChanges/` is the fullest in-tree example.

## Feed modules

A feed module renders a list of items with a loading state, an error state, a compact card summary versus a full list, and a footer control. None of that is yours to write: `ext.personalDashboard.common` ships the scaffold, and a feed module supplies its queries, its labels, and the body of one card ([T433900](https://phabricator.wikimedia.org/T433900)).

Three pieces, all exported from `ext.personalDashboard.common`:

- **`useFeedState( loader )`** produces the normalized feed-data contract: `{ items, isLoading, error }`, where each item carries a unique `id`. It owns the state transitions a client fetch repeats — flags up, flags down, log and surface the failure — so your loader is just the query. The contract is what matters, not this helper: a Pinia store satisfies it with a getter (`./resources/ext.personalDashboard.reviewChanges/store/reviewChangesStore.js`, which merges three sources and so keeps its own state), and a module handed server-normalized data through `getJsData()` satisfies it with a plain object and no fetch layer at all.
- **`FeedPanel`** is the scaffold. Bind the contract to it, pass `moduleName` (its route, for the footer control) and the label props, and fill its one `#item` slot. It owns the compact/full derivation: pick the rule with `summaryMode`, either `"card"` or `"viewport"` (see [`./render-contract.md`](./render-contract.md)). Don't declare the island props in your module — let them ride in `$attrs` and forward them, so the derivation lives in one place.
- **`FeedCard`** is the card chrome: the Codex card, the whole-card overlay link and its stacking context, the visited state, and the `#header` / `#meta` / `#description` rows. Your card component fills those slots from your item shape and keeps its own class on the element for anything specific to it.

`./resources/ext.personalDashboard.activeDiscussions/` is the smaller of the two consumers and the better one to read first: one composable holding the API query, an `App.vue` that is little more than the labels and the fetch limit, and a `ListCard.vue` that is all slot content.

## Show it on the dashboard

The default dashboard layout lives at `PersonalDashboard.ModuleGroups.default` in **Personal Dashboard's** own `./extension.json`. A group contains subgroups, and a subgroup contains modules. The `default` group has three top-level groups: `utils` (hidden, holds the onboarding module), `main`, and `sidebar`.

Two paths for a BoilerPlate module to appear:

**Add to the default dashboard.** Submit a change to Personal Dashboard adding the module to a subgroup, for example the `primary` subgroup inside `main`:

```json
{
    "name": "ext.boilerPlate.example",
    "enabled": true
}
```

Modules can carry optional per-placement keys such as `"style": "thin"` and `"styleMobile": "minimized"`; see existing entries in Personal Dashboard's `./extension.json` for examples.

**Register a named group.** For an experiment or opt-in preview, register a new group in BoilerPlate's own `./extension.json` under `PersonalDashboard.ModuleGroups`. GrowthExperiments' `home` group is a working example ([T432039](https://phabricator.wikimedia.org/T432039)).

Registering the group is the platform mechanism today. User-facing `?moduleGroup=` routing, which let anyone silently re-target their own dashboard, is gone as part of [T430805](https://phabricator.wikimedia.org/T430805). `?pdo=<ModuleGroupId>` replaces it: a dev/QA-only override gated by the `PersonalDashboardAllowOverride` kill switch. The switch defaults to `false` in the extension's own config, so a third-party install or local dev checkout gets `pdo` disabled out of the box; WMF's deployment config sets it `true`. Setting `pdo` via URL param also sets a `pdo` session cookie, so a single tagged link keeps routing a QA session across reloads until the browser session ends. A real TestKitchen enrollment always wins over `pdo`, and PersonalDashboard suppresses its own instrumentation entirely while `pdo` is active, so dev/QA traffic never contaminates analysis data.

`pdo` and TestKitchen's own `?mpo=` solve different problems: `pdo` previews a module group before any TestKitchen experiment exists to validate it, while `mpo` validates a specific variant once a real experiment is live.

## Serve a group through a TestKitchen experiment

Registering a group puts it on the shelf; a TestKitchen experiment is what serves it to real users. Add one entry to `ROUTING` in `./src/Experiments.php`, keyed by the experiment name, mapping each variant to a registered module group ID:

```php
private const ROUTING = [
    'my-experiment' => [
        'control' => 'default',
        'treatment' => 'boilerPlate',
    ],
];
```

Three things have to line up outside that file. The key must match the experiment name registered in TestKitchen's own config, or nobody ever resolves and the experiment reports no enrollment. Each variant's value must name a module group registered on the wikis being served, because a group whose extension isn't enabled there is skipped. And the experiment has to use the `mw-user` identifier type: `edge-unique` can't be read server-side, so module-group routing only works for logged-in users.

Personal Dashboard handles the rest. It reads the user's assignment on each dashboard render, sends the TestKitchen exposure event for the experiment that resolves (control and treatment alike, and only for users who actually get the group), and tags every health-metrics event with the resolved module group in `action_context` and the variant in `action_subtype`. An unenrolled user's events carry `action_context` alone, which is how analysis tells them from an enrolled control user.

Only one experiment routes at a time today: the first registered name that resolves wins, and the rest never take effect.

## Existing modules as reference

Personal Dashboard ships most modules itself, and GrowthExperiments ships the Mentorship module from its own tree ([T432039](https://phabricator.wikimedia.org/T432039)). All of them use the same registration mechanism a third-party extension would.

- `./src/Modules/ReturnToHomepage.php`: smallest server-side module; implements `IModule` directly.
- `./src/Modules/Banner.php`: static content from a wiki message; server-rendered (no client mount), extends `BaseModule`.
- `./src/Modules/Impact.php`: DB-backed island, passes its counts to Vue via `getJsConfigVars()`; extends `BaseModule`.
- `./src/Modules/ReviewChanges.php`: full client-side Vue module, the Review Changes experience; extends `BaseModule`.
- `./src/Modules/Placeholder.php`: fallback used when a registered module fails to load; implements `IModule` directly.

## See also

[`./architecture.md`](./architecture.md) covers how registration and module groups fit with the rest of the extension: the server render contract an `IModule` implements, and how a module gets routed to and opened. The [Extension:PersonalDashboard](https://www.mediawiki.org/wiki/Extension:PersonalDashboard) page on mediawiki.org covers the product side: what the dashboard is, which wikis it runs on, deployment history.
