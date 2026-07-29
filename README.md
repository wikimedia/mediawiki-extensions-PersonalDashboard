
This mediawiki extension provides a personal dashboard.

Installation:
```
cd extensions
git clone https://gerrit.wikimedia.org/r/mediawiki/extensions/PersonalDashboard.git
```

For product-side background (what the dashboard is, deployment history), see [Extension:PersonalDashboard](https://www.mediawiki.org/wiki/Extension:PersonalDashboard). For writing a new module, see [./docs/modules.md](./docs/modules.md).

## Instrumentation

The baseline health-metrics instrument (`personal-dashboard-health-metrics`) lives at `resources/ext.personalDashboard.special/instrumentation/onPersonalDashboardBaseline.js`, a packageFile of `ext.personalDashboard.special` that `init.js` requires so it runs as part of the module. It previously lived in WikimediaEvents and was moved here in [T430716](https://phabricator.wikimedia.org/T430716).

It uses the Test Kitchen Instrument API, which is provided by WikimediaEvents — an optional dependency. TestKitchen is therefore treated as a soft dependency: the instrumentation only loads it when `ext.wikimediaEvents.testKitchen` is registered, pulling it in lazily via `mw.loader.using()`, so the dashboard keeps working when WikimediaEvents is not installed.
