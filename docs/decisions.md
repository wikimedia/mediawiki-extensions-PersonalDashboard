# Design decisions {#decisions}

Deliberate divergences from MediaWiki convention, each with the reasoning and a receipt. New entries append; nothing here is a status lifecycle, just the record of a choice and why.

## Detail is viewport-driven, not a server platform axis

**The convention.** MediaWiki decides platform server-side: MobileFrontend sniffs the device and swaps in the Minerva skin and mobile transforms, and GrowthExperiments' Homepage renders desktop, mobile-summary, and mobile-details as three server-side modes. PD inherited that `render( $platform )` argument when it forked from GrowthExperiments.

**What PD does instead.** `render()` takes no platform argument. The server emits one device-agnostic frame, and how much detail to show (a compact card summary versus the full view) is a purely client-side computation keyed off actual viewport width. `isMobile` survives only as an analytics dimension, never a logic branch.

**Why.** The platform string ran two unrelated decisions through one value: which platform the server renders for, and how much detail to show. Only the second is real, and it's a viewport question, not a device one. One frame regardless of device is also one cacheable response, where server platform branching fragments the cache; and the island contract already hands body-filling to the client, so detail belongs there too. It's coherent with going Codex-native, which is mobile-first responsive.

**Consequence.** One cacheable server response, no platform `Vary`; detail never reaches PHP. A reviewer expecting server-side platform handling won't find it, by design. `useViewport()` and `IslandMount.vue` own the compact/full decision; see [`./render-contract.md`](./render-contract.md) for the mechanism.

**Amendment.** Viewport is the default rule, not the only one. A module whose card is a summary on every viewport — full detail belonging to the dialog or the focused page rather than to a wide screen — says so with `summaryMode="card"` on the shared feed scaffold, and the viewport stops deciding for it. Review Changes converged its desktop and mobile experiences that way (T426181, `7516d7e`). The divergence above still holds: the choice is client-side and per module, and nothing about it reaches PHP.

**Receipt.** `55aa0d3` ("Drop the platform axis; drive detail from viewport"); `7516d7e` for the amendment.

## Registration is declarative, not a runtime API

**The convention.** A platform that lets other code plug in often exposes a runtime registration API (Echo's attribute manager is the model): extensions call in to register, and the platform validates and orders the entries as they arrive.

**What PD does instead.** Modules and module groups declare themselves as `extension.json` attributes (`PersonalDashboard.Modules`, `PersonalDashboard.ModuleGroups`), and registered dashboards will join them as a third. `PersonalDashboardModuleFactory` aggregates those attributes and instantiates through ObjectFactory. So PD does have a registry service; it reads declarations rather than accepting them at runtime.

Feed sources follow the same rule: `PersonalDashboard.FeedSources` and `PersonalDashboardFeedSourceFactory` are a second instance of the pattern, not a new one. One deliberate divergence: an unregistered feed source resolves to `null` rather than to a placeholder, because a feed drops the missing source and renders the rest, where a module occupies a card that would otherwise be empty.

**Why.** The driver was duplication: hardcoded module definitions meant copying code every time someone added or customized a module. Declarative attributes are the cheapest MediaWiki-idiomatic fix, they merge across extensions for free, and ObjectFactory supplies dependency injection without bespoke wiring.

**Consequence.** Registration is static and load-time, and cross-extension registration works through attribute merge. What's absent isn't coupling between the attributes, since a group already names modules across extension boundaries. It's validation of that coupling: a group naming an unregistered module logs at error and falls back to `ext.personalDashboard.placeholder`, so a dangling reference shows up as an empty card in production instead of failing at registration time.

**Trigger to revisit.** When finding a dangling reference at render time stops being good enough. [T434341](https://phabricator.wikimedia.org/T434341) pushes toward that by adding a third attribute that references the second.

**Receipt.** [Proposed Architectural Changes for PersonalDashboard](https://docs.google.com/document/d/1-ajWUaqEATw7z2x7XTWsacyk1hMpepwbX7IVRD-v8iI/view) (WMF-internal, 2026-04-17), which introduced both declarative module registration and the aggregating registry service.

## The feed is merged and paged on the server

**The convention.** A dashboard widget queries the Action API from the browser. Several sources mean several queries, and combining them — interleaving, deduplicating, sampling — is the client's problem.

**What PD does instead.** `GET /personaldashboard/v0/feed` answers one request with items from every source a caller names, merged newest first, plus an opaque token that resumes where the page stopped. The browser asks once and renders what comes back.

**Why.** The client version could not page. Each source was fetched, sampled down to a share of a fixed limit, and whatever it did not use was thrown away, so "Show more" had nothing to ask for ([T426182](https://phabricator.wikimedia.org/T426182)). It also filtered after the fact: repeated titles and already-reverted edits were dropped in JavaScript, spending items out of a limit already paid for, where the database can exclude them in the query. And a source the server prefetched carried no page description, because the description came from a generator on the recentchanges query alone; a lookup beside the query gives every source the same field ([T437491](https://phabricator.wikimedia.org/T437491)).

**Consequence.** The response is per-viewer and so uncacheable by design, and it refuses anonymous and temporary accounts with a 401 rather than serving a feed nobody can personalize. One thing stayed on the client: a machine-learning score rides on the item and the wiki's threshold rides in the page as a config var, so the card decides which edits to flag. The endpoint describes an edit and never selects one, which is what let the revert-risk filter become a chip ([T433724](https://phabricator.wikimedia.org/T433724)). Merge order, deduplication and cursors are now covered by PHP tests instead of living in the browser. A feed module's client half is one composable: ask, render, hand the token back.

**Receipt.** [T436570](https://phabricator.wikimedia.org/T436570) for the endpoint; [T426182](https://phabricator.wikimedia.org/T426182) for the paging it made possible.
