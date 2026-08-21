# Running a TestKitchen experiment on the Personal Dashboard {#experiments}

Personal Dashboard is experimental; the details below will change.

This doc covers serving a module group through a TestKitchen (TK) experiment
and how that experiment's enrollment shows up in health metrics. It assumes
the module group already exists; see [./modules.md](./modules.md) for
registering a module and a group. This doc uses
[BoilerPlate](https://www.mediawiki.org/wiki/Extension:BoilerPlate) as a
stand-in for a third-party team's own extension.

## Before you start

An experiment needs, outside `./src/Experiments.php`:

- A module group already registered on the wikis the experiment targets (see
  [./modules.md](./modules.md)).
- The experiment itself registered in TestKitchen's own config, under the
  **exact same name** used in `./src/Experiments.php` — not a close variant.
  A mismatch fails silently: TK enrolls and tags users under its own name
  just fine, but `getExperiment()` calls with `MANIFEST`'s name never find
  it, so Personal Dashboard treats every user as unenrolled. There's no
  error, unroutable counter, or log line for this case, only the
  "TestKitchen reports no enrollment" symptom below. Easy to trip over when
  registering a local-only test double for an experiment that isn't live
  yet, since it's tempting to give the test double a distinguishing suffix
  (e.g. `T426615x`) — don't; use the manifest name exactly.
- The `mw-user` identifier type. `edge-unique` can't be read server-side, so
  module-group routing and server-side variant tagging only work for
  logged-in users.

## Register the experiment

Add one entry to `MANIFEST` in `./src/Experiments.php`, keyed by the
experiment name. There are two shapes:

**Routing** — the experiment picks which module group a user sees. List only
the variants that override the baseline; a variant you omit (conventionally
`control`) falls through to normal baseline resolution:

```php
private const MANIFEST = [
    'my-experiment' => [
        'treatment' => 'boilerPlate',
    ],
];
```

**Tag-only** — the experiment doesn't change what's shown, it only needs its
assignment recorded in health metrics (for example, an experiment that
changes something inside an existing module rather than which module group
is served):

```php
private const MANIFEST = [
    'my-tagging-experiment' => [],
];
```

Rules that apply to both: never map a variant to the baseline group
(`default`) explicitly — an omitted variant already means "use the
baseline," and once a wiki's baseline varies by eligibility, naming it
explicitly measures the wrong contrast. Order in `MANIFEST` is arbitration
order (see "Concurrency and conflicts" below). Don't give an experiment a
purely numeric name — see "Variant tagging in health metrics."

## What Personal Dashboard does on each render

Each dashboard render resolves in this order: experiment override, then the
`pdo` dev/QA override, then the default group. Whether an experiment's
assignment produces a TestKitchen exposure event depends on whether it
actually determined what the user saw:

| Assignment | Module group shown | Exposure sent |
|---|---|---|
| Overriding variant, first to resolve | The override | Yes |
| Overriding variant, preempted by an earlier one | Baseline (or `pdo`) | No |
| Overriding variant, maps to an unregistered group | Baseline (or `pdo`) | No |
| Non-overriding variant (`control`), or tag-only | Baseline | Yes |
| Non-overriding variant (`control`), or tag-only, with `pdo` active | `pdo` | No |
| Not enrolled | Baseline (or `pdo`) | No |

A non-overriding or tag-only assignment always gets exposure, since "no
override" is what happens regardless of any other experiment or of `pdo`.
An overriding assignment only gets exposure when it's the one that actually
won the module-group slot — the user never saw the effect of a preempted or
unroutable one, so recording exposure for it would be a false positive.

## Variant tagging in health metrics

Every `personal-dashboard-health-metrics` event carries a JSON `action_context`
with the resolved module group and a map of every experiment whose assignment
took effect this render, keyed by experiment name:

```jsonc
// Unenrolled
{ "module_group": "default", "module_variants": {} }

// One experiment, routing
{ "module_group": "boilerPlate", "module_variants": { "my-experiment": "treatment" } }

// Two experiments enrolled at once: one routing, one tag-only
{
  "module_group": "boilerPlate",
  "module_variants": { "my-experiment": "treatment", "my-tagging-experiment": "control" }
}
```

`module_group` alone can't identify a cohort: one group ID can be the
baseline for unenrolled users, one experiment's control, and another
experiment's treatment at the same time. Filter on `module_variants`, keyed
by your experiment's name, not on `module_group`.

Experiment names become PHP array keys and, through `module_variants`, JSON
object keys — a purely numeric name (e.g. `"426615"` instead of `"T426615"`)
gets reordered as an integer key and can confuse consumers expecting string
keys. Use a name with a letter in it (a TK experiment slug or a task ID like
`T426615`).

## Concurrency and conflicts

More than one experiment can be enrolled and tagged in the same request —
that's normal. Only one can win the module-group slot: the first experiment
in `MANIFEST` order whose assignment maps to a real, registered override
wins, and any other experiment that also resolves to an override that
request is preempted (no tag, no exposure, per the table above).

If your experiment is unexpectedly reporting no enrollment or unexpectedly
losing exposures, check whether another team's experiment is also routing to
an overlapping population — that's a coordination problem between the two
experiments, not something Personal Dashboard arbitrates further. It's
visible via:

- A `special_dashboard_experiment_routing_conflicts_total` counter
  (labels: `wiki`, `winner`, `preempted`), and a matching error log line, when
  two experiments both resolve to a real override in the same request.
- A `special_dashboard_experiment_unroutable_total` counter (labels: `wiki`,
  `experiment`, `module_group`), and a matching error log line, when an
  experiment's variant maps to a module group this wiki doesn't have.

Two routing experiments over genuinely disjoint populations (say, different
edit-count bands) never compete for the same request and won't trigger
either counter.

## Dev and QA overrides

`?pdo=<ModuleGroupId>` previews a module group directly, gated by the
`PersonalDashboardAllowOverride` config switch (`false` by default; WMF's
deployment config sets it `true`). Setting it via URL param also sets a
session cookie, so a single tagged link keeps routing a QA session across
reloads. `pdo` is suppressed by an active experiment override, but not by a
non-overriding or tag-only assignment: a control-arm test account can still
use `pdo`, since nothing about that assignment claims the module-group slot.
No exposure fires when `pdo` wins — dev/QA traffic never contaminates
analysis data.

TestKitchen's own `?mpo=experiment:variant` forces a specific variant of an
already-registered experiment. Use `pdo` to preview a module group before any
experiment exists for it; switch to `mpo` once the experiment is registered
in TestKitchen, to validate a specific variant under real enrollment
semantics.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| TestKitchen reports no enrollment | Name mismatch between `MANIFEST` and TK's config, or the experiment isn't using the `mw-user` identifier type |
| Module group never served | Not registered on this wiki — check `special_dashboard_experiment_unroutable_total` |
| Enrolled, but health metrics show no variant | `pdo` is active for this session, or the user genuinely isn't enrolled |
| Exposure or variant missing despite enrollment | This experiment was preempted by another routing experiment — check `special_dashboard_experiment_routing_conflicts_total` |

## See also

- [./modules.md](./modules.md): registering a module and a module group.
- [./architecture.md](./architecture.md): how the pieces fit together.
- [Test Kitchen: Conduct an experiment](https://wikitech.wikimedia.org/wiki/Test_Kitchen/Conduct_an_experiment)
- [Test Kitchen: Experiment exposure logging](https://wikitech.wikimedia.org/wiki/Test_Kitchen/Experiment_exposure_logging)
- [T428679](https://phabricator.wikimedia.org/T428679): the task this machinery was built for.
