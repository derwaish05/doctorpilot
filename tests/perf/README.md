# Search verification harness (M3-TASK-006)

Reproducible accuracy, accessibility, and performance evidence for the search
feature. All data is synthetic and reviewed — no real names, credentials,
medical relationships, or search histories.

## 1. Generate fixtures

```bash
wp eval-file wp-content/plugins/doctorpilot/tests/perf/generate-fixtures.php 1000
```

Creates 1,000 published synthetic doctors across 8 departments, 8 specialties,
6 symptoms, and 4 languages, rebuilding each search index.

## 2. Performance benchmark

```bash
wp eval-file wp-content/plugins/doctorpilot/tests/perf/benchmark.php
```

Reports p50/p95 latency and average DB queries per request for the service
layer. **Target (NFR / spec §20): REST p95 ≤ 500 ms for 1,000 published
doctors, excluding network.** Record the environment, cold vs. warm cache, and
whether a persistent object cache is present.

### Report template

| Metric | Cold cache | Warm cache |
|---|---:|---:|
| p50 latency (ms) | _tbd_ | _tbd_ |
| p95 latency (ms) | _tbd_ | _tbd_ |
| Avg DB queries/request | _tbd_ | _tbd_ |
| Object cache present | yes/no | yes/no |

## 3. Ranking accuracy query set

Target: ≥95% of approved queries return the expected doctor in the first five
results. With the fixture data above:

| # | Query | Expectation |
|---|---|---|
| 1 | `q=test doctor 0042` | Exact-name doctor ranks first |
| 2 | `q=cardiology` | Cardiology department/specialty doctors in top results |
| 3 | `q=chest pain` | Doctors tagged with the Chest Pain symptom |
| 4 | `specialty=interventional-cardiology` | Only that specialty returned |
| 5 | `department=radiology&sort=experience` | Radiology, most experienced first |
| 6 | `symptom=headache&language=arabic` | Combined filter intersection only |
| 7 | `q=` empty, `sort=name` | Alphabetical by normalized name |

Automated coverage of the ranking tiers and tie order lives in
`tests/test-search.php`.

## 4. Accessibility checks

- Automated: run an axe/pa11y scan against a page containing
  `[doctorpilot_search]`.
- Manual: keyboard-only operation of the search field, filters, sort,
  pagination, and chips; screen-reader announcement of the result count
  (`#dp-count` is an `aria-live="polite"` region); reduced-motion and mobile
  (filter drawer) behavior.

## 5. Status

Implementation and automated fixtures are complete. The benchmark and manual
accessibility runs must be executed in a WordPress + PHP environment; they were
not run in the authoring sandbox. Record results here before marking
M3-TASK-006 complete. Skipped checks are never reported as passed.
