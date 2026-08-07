# Design decisions {#decisions}

Deliberate divergences from MediaWiki convention, each with the reasoning and a receipt. New entries append; nothing here is a status lifecycle, just the record of a choice and why.

## Detail is viewport-driven, not a server platform axis

**The convention.** MediaWiki decides platform server-side: MobileFrontend sniffs the device and swaps in the Minerva skin and mobile transforms, and GrowthExperiments' Homepage renders desktop, mobile-summary, and mobile-details as three server-side modes. PD inherited that `render( $platform )` argument when it forked from GrowthExperiments.

**What PD does instead.** `render()` takes no platform argument. The server emits one device-agnostic frame, and how much detail to show (a compact card summary versus the full view) is a purely client-side computation keyed off actual viewport width. `isMobile` survives only as an analytics dimension, never a logic branch.

**Why.** The platform string ran two unrelated decisions through one value: which platform the server renders for, and how much detail to show. Only the second is real, and it's a viewport question, not a device one. One frame regardless of device is also one cacheable response, where server platform branching fragments the cache; and the island contract already hands body-filling to the client, so detail belongs there too. It's coherent with going Codex-native, which is mobile-first responsive.

**Consequence.** One cacheable server response, no platform `Vary`; detail never reaches PHP. A reviewer expecting server-side platform handling won't find it, by design. `useViewport()` and `IslandMount.vue` own the compact/full decision; see [`./render-contract.md`](./render-contract.md) for the mechanism.

**Receipt.** `55aa0d3` ("Drop the platform axis; drive detail from viewport").

## Registration is declarative, not a runtime API

**The convention.** A platform that lets other code plug in often exposes a runtime registration API (Echo's attribute manager is the model): extensions call in to register, and the platform validates and orders the entries as they arrive.

**What PD does instead.** Modules and module groups declare themselves as `extension.json` attributes (`PersonalDashboard.Modules`, `PersonalDashboard.ModuleGroups`), and registered dashboards will join them as a third. `PersonalDashboardModuleFactory` aggregates those attributes and instantiates through ObjectFactory. So PD does have a registry service; it reads declarations rather than accepting them at runtime.

**Why.** The driver was duplication: hardcoded module definitions meant copying code every time someone added or customized a module. Declarative attributes are the cheapest MediaWiki-idiomatic fix, they merge across extensions for free, and ObjectFactory supplies dependency injection without bespoke wiring.

**Consequence.** Registration is static and load-time, and cross-extension registration works through attribute merge. What's absent isn't coupling between the attributes, since a group already names modules across extension boundaries. It's validation of that coupling: a group naming an unregistered module logs at error and falls back to `ext.personalDashboard.placeholder`, so a dangling reference shows up as an empty card in production instead of failing at registration time.

**Trigger to revisit.** When finding a dangling reference at render time stops being good enough. [T434341](https://phabricator.wikimedia.org/T434341) pushes toward that by adding a third attribute that references the second.

**Receipt.** [Proposed Architectural Changes for PersonalDashboard](https://docs.google.com/document/d/1-ajWUaqEATw7z2x7XTWsacyk1hMpepwbX7IVRD-v8iI/view) (WMF-internal, 2026-04-17), which introduced both declarative module registration and the aggregating registry service.
