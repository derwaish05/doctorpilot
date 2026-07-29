# DoctorPilot (developer)

Structured doctor directory and intelligent search for hospitals, built for
Elementor. This repository contains the WordPress plugin. Product, requirements,
architecture, decisions, AI rules, and the technical specification live in
[`docs/`](docs/); the development plan lives in [`tasks/`](tasks/).

## Status

Milestones 1–2 are implemented. Milestone 1 (Foundation): safe bootstrap and
lifecycle, identifiers and packages, capabilities, versioned settings, REST
foundation, admin health/settings shell, logging, and the schema migrator.
Milestone 2 (Doctor Directory): the `doctorpilot_doctor` post type, seven
taxonomies, the canonical `FieldRegistry`, the guided editor with review and
completeness, Free booking adapters, a public view model with native
single/archive/card templates and shortcodes, and a single SEO owner with
Physician schema. Milestone 3 (Search & Frontend): bounded `SearchRequest`,
deterministic ranking over a rebuildable search index, public REST
(`/doctors`, `/doctors/{id}`, `/doctors/suggest`, `/facets`) with rate limiting
and a generation-keyed cache, and the `[doctorpilot_search]` no-reload UI with a
server-rendered GET fallback. Milestone 4 (Elementor): a guarded integration that
degrades safely when Elementor is absent, plus six Free widgets — Doctor Search,
Doctor Grid / List, Department Grid, Symptom Directory, Doctor Profile, and
Booking Button — all rendering through the shared services. Complete dynamic tags
and Theme Builder conditions are Pro-tier (Milestone 6).

Search verification (fixtures, benchmark, ranking set, a11y checklist) lives in
`tests/perf/` — see its README.

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Layout

```
doctorpilot.php          Bootstrap, constants, runtime guard, lifecycle hooks
uninstall.php            Opt-in, data-safe uninstall
includes/
  Support/               Autoloader, RuntimeGuard, Capabilities, Logger, errors, ExtensionRegistry
  Infrastructure/        Plugin orchestrator, Lifecycle (Activator/Deactivator), Migrations
  Admin/                 Menu, Settings
  Rest/                  RouteRegistry (doctorpilot/v1)
tests/                   PHPUnit (WordPress test harness)
```

Identifiers: namespace `BrainStudioz\DoctorPilot`; code/option prefix
`bstdz_doctorpilot_`; private meta prefix `_doctorpilot_`; text domain
`doctorpilot`; REST namespace `doctorpilot/v1`.

## Development setup

```bash
composer install
```

## Verification commands

```bash
# PHP syntax lint (all source files)
composer lint

# WordPress Coding Standards
composer phpcs
composer phpcbf   # auto-fix where possible

# Unit/integration tests (requires the WordPress PHPUnit test library)
#   Install once, e.g.:
#   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
#   export WP_TESTS_DIR=/tmp/wordpress-tests-lib
composer test
```

> Note: `composer lint`, `phpcs`, and `phpunit` require PHP and the WordPress
> test harness. They must be run in a PHP-capable environment; they were not
> executed in the authoring sandbox. A structural token check (balanced
> braces/parentheses/brackets and `<?php` openers) passed on all source files.
