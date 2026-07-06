# Authoring a Personal Dashboard module

Personal Dashboard is experimental; the details below will change.

A module is a discrete unit of content on `Special:PersonalDashboard` (Impact, Active Discussions, Review Changes, and so on). Modules render their own content; the module-group registry places them into the page.

Any extension can contribute a module via the `PersonalDashboard.Modules` attribute. This doc uses [BoilerPlate](https://www.mediawiki.org/wiki/Extension:BoilerPlate) as a stand-in; it isn't actually shipping a module, but [Gerrit change 1295084](https://gerrit.wikimedia.org/r/c/mediawiki/extensions/BoilerPlate/+/1295084) is a PoC that implements the integration (extends `BaseModule` rather than implementing `IModule` directly; may be stale).

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

- `render($mode)`: returns the module's HTML for one of `RENDER_DESKTOP`, `RENDER_MOBILE_SUMMARY`, `RENDER_MOBILE_DETAILS` (constants on `IModule`).
- `getJsData($mode)`: return value is packed into `wgPersonalDashboardGroups` on the page for Vue components to read.
- `getJsConfigVars()`: additional `mw.config` keys the module's client-side code needs.
- `supports($mode)`: return false to skip a mode.
- `setName($name)`, `setPageURL($url)`: called by the factory and the special page before render; typically empty stubs.

For BoilerPlate, the class lives at `src/ModuleExample.php` under `MediaWiki\Extension\BoilerPlate\`. `./src/Modules/ReturnToHomepage.php` is the smallest working example in Personal Dashboard: no context, no services, `render()` returns a static link.

If the module needs a context or services, the constructor takes an `IContextSource` first, then any services declared in the registry. `SpecialPersonalDashboard` supplies the context via `getRequestedModule()` in `./src/Specials/SpecialPersonalDashboard.php`; services come from the `"services"` list above.

Personal Dashboard's own modules extend an internal helper, `MediaWiki\Extension\PersonalDashboard\Modules\BaseModule`, that composes `render()` from a header/subheader/body/footer skeleton so subclasses only supply the pieces. The helper isn't part of the platform contract, but it's a decent example of what a helper can look like. Typical overrides:

- `getHeaderText()`: the card title as a string.
- `getBody()`: HTML for the module's content, usually a mounting `<div>` for the Vue app plus a no-JS fallback message.
- `getModules()`: ResourceLoader module names to load for this module.
- `getJsConfigVars()`: additional `mw.config` keys the module's client-side code needs.

The [PoC](https://gerrit.wikimedia.org/r/c/mediawiki/extensions/BoilerPlate/+/1295084)'s `src/ModuleExample.php` extends BaseModule; `./src/Modules/Impact.php` does the same with a database-backed body.

Client-side (Vue) modules use the same registration path. `render()` (or `getBody()` if you extend BaseModule) emits a mounting div; a matching ResourceLoader module registered under `ResourceModules` in the same extension's `./extension.json` loads the Vue app. `./src/Modules/RiskyArticleEdits.php` paired with `./resources/ext.personalDashboard.riskyArticleEdits/` is the fullest in-tree example.

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

**Register a named group.** For an experiment or opt-in preview, register a new group in BoilerPlate's own `./extension.json` under `PersonalDashboard.ModuleGroups`. See the [PoC](https://gerrit.wikimedia.org/r/c/mediawiki/extensions/BoilerPlate/+/1295084)'s `boilerPlate` group for a concrete example.

Registering the group is the platform mechanism today, but there is no stable user-facing way to switch to a non-default group. User-facing `?moduleGroup=` routing is going away as part of T430805; a dev/override affordance to select alternative groups is planned but TBD. Register the group now if it's the right structural home for an experiment; the switching affordance can come later.

## Existing modules as reference

All shipped modules live in Personal Dashboard itself and use the same registration mechanism a third-party extension would.

- `./src/Modules/ReturnToHomepage.php`: smallest server-side module; implements `IModule` directly.
- `./src/Modules/Banner.php`: static content from a wiki message; extends `BaseModule`.
- `./src/Modules/Impact.php`: DB-backed, passes data to Vue via `getJsData()`; extends `BaseModule`.
- `./src/Modules/RiskyArticleEdits.php`: full client-side Vue module, the Review Changes experience; extends `BaseModule`.
- `./src/Modules/Placeholder.php`: fallback used when a registered module fails to load; implements `IModule` directly.

## See also

The [Extension:PersonalDashboard](https://www.mediawiki.org/wiki/Extension:PersonalDashboard) page on mediawiki.org covers the product side: what the dashboard is, which wikis it runs on, deployment history. This doc covers the code-facing side of writing a module for it.
