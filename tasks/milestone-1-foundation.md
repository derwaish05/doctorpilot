# Milestone 1 — Foundation

## Milestone overview

- **Objective:** Establish safe loading, lifecycle, identifiers, base contracts, administration, and verification infrastructure.
- **User value:** A stable Free plugin can activate, preserve data, and provide a secure base for directory features.
- **Included:** Bootstrap, Free/Pro boundary, identifiers, capabilities, settings, REST foundation, admin shell, logging, lifecycle, test/release tooling.
- **Excluded:** Doctor field UI, public directory/search, Elementor, imports, redirects, analytics.
- **Boundary:** Free owns bootstrap and canonical base APIs; Pro is a separate guarded add-on.
- **Dependencies:** None. M1-MOD-03 and M1-MOD-05 may follow M1-TASK-001 in parallel.
- **Traceability:** NFR-001–NFR-010; AC-020–AC-022; ADR-0005, 0008–0011, 0014; spec §§1–2, 6–7, 13, 18–20.
- **Risks:** Identifier collisions, fatal optional dependencies, destructive lifecycle, authorization gaps.
- **Completion:** All five modules meet their DoD; static, lifecycle, security, compatibility, and documentation checks pass.

## M1-MOD-01 — Bootstrap and lifecycle

**Purpose:** Provide safe runtime and lifecycle entry points. **Tier:** Free. **Dependencies:** none. **Traceability:** NFR-001, NFR-007; AC-020–AC-022; ADR-0005/0008; spec §§1–2,18.
**Deliverables:** `doctorpilot.php`, bootstrap/runtime guard, version constants, activator/deactivator, `uninstall.php`, `readme.txt`. **Validation:** unsupported/supported runtime, repeated activation, both uninstall modes. **Module DoD:** lifecycle is idempotent, translated, and data-safe.

### M1-TASK-001 — Implement bootstrap and lifecycle skeleton

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective:** Load DoctorPilot safely and own activation, deactivation, upgrade, and uninstall entry points.
- **Implementation:** WordPress 6.4/PHP 8.1 guards; namespace bootstrap; plugin/schema/base-API versions; ordered activation; one-time rewrite flush; deactivation unscheduling; explicit opt-in uninstall.
- **Expected code:** `doctorpilot.php`, `includes/Support/Bootstrap.php`, `RuntimeGuard.php`, `Lifecycle/*`, `uninstall.php`; options `bstdz_doctorpilot_plugin_version`, `bstdz_doctorpilot_schema_version`.
- **Dependencies:** none. **Parallel:** test fixtures may be prepared concurrently.
- **Controls:** require uninstall context; capability/nonce on delete-setting mutation; no optional classes before guards; no unbounded multisite loops.
- **A11y/performance:** actionable admin notices; no normal-request rewrite flush or heavy network iteration.
- **Failures/edges:** incompatible runtime, repeated activation, partial upgrade, multisite, Pro active without Free; fail safely without deletion/fatal.
- **Tests:** runtime guards, fresh/repeated activation, upgrade, deactivation, both uninstall settings, bounded multisite.
- **Manual:** activate/deactivate with data; inspect notices and preservation.
- **Acceptance:** AC-020–AC-022; no content deletion unless explicitly enabled.
- **DoD/references:** implementation, tests, lifecycle docs/changelog pass; NFR-001/007, ADR-0005/0008, spec §§1,18.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M1-MOD-02 — Package boundaries and identifiers

**Purpose:** Prevent collisions and duplicate ownership. **Tier:** Shared. **Dependencies:** M1-TASK-001. **Traceability:** NFR-008/010; ADR-0008/0010/0011; spec §§2,11,19.
**Deliverables:** autoload/package layout, identifier policy, versioned extension registry. **Validation:** identifier/dependency scan. **Module DoD:** Free boots alone; Pro can extend but not duplicate.

### M1-TASK-002 — Establish autoloading, packages, prefixes, and base API

- **Status:** Not Started. **Priority:** Critical. **Tier:** Shared.
- **Objective:** Create approved module boundaries and stable extension contracts.
- **Implementation:** `BrainStudioz\DoctorPilot`; `bstdz_doctorpilot_` and `_doctorpilot_` prefixes; Domain/Application/Infrastructure packages; base API constant; guarded Pro registration.
- **Expected code:** autoloader/composer config if approved, `includes/{Domain,Application,Infrastructure,Support}`, `Support/ExtensionRegistry.php`; initial documented hooks.
- **Dependencies:** M1-TASK-001.
- **Controls:** extensions cannot expose private fields, bypass validation, or change publication authority.
- **A11y/performance:** no frontend output; autoload only requested classes, no eager optional SDKs.
- **Failures/edges:** incompatible Pro/API and missing dependencies produce notice, not fatal.
- **Tests:** namespace/prefix scan, autoload tests, incompatible extension fixture.
- **Manual:** boot Free alone and with an incompatible mock add-on.
- **Acceptance:** no generic `doctor`, `department`, option, route, or meta identifier.
- **DoD/references:** package graph and API docs verified; ADR-0008/0010/0011, spec §§2,11,19.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M1-MOD-03 — Authorization, settings, REST and admin shell

**Purpose:** Provide shared secure control-plane foundations. **Tier:** Free. **Dependencies:** M1-TASK-002. **Traceability:** NFR-003/008; ADR-0014; spec §§6–7,13.
**Deliverables:** capability service, settings schema, REST registrar, one admin navigation owner. **Validation:** permission/settings/route tests. **Module DoD:** all mutations have intent and authorization checks.

### M1-TASK-003 — Implement control-plane foundations

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective:** Centralize capabilities, settings validation, REST conventions, and admin navigation.
- **Implementation:** mapped primitive capabilities; idempotent administrator grant; versioned `bstdz_doctorpilot_settings`; secret separation; route registrar with explicit permission/schema/error requirements; single menu owner.
- **Expected code:** `Support/Capabilities.php`, `Admin/Menu.php`, `Admin/Settings.php`, `Rest/RouteRegistry.php`, settings option and admin screens.
- **Dependencies:** M1-TASK-002. **Parallel:** M1-TASK-004 after logging interface exists.
- **Controls:** capability plus object scope; nonces for authenticated mutations; public GET routes nonce-free; sanitize input/escape late.
- **A11y/performance:** semantic settings UI, labels/errors/focus; do not autoload large settings or secrets.
- **Failures/edges:** unknown settings discarded; invalid enum/range rejected; role name never authorizes.
- **Tests:** capabilities, option schema, nonce/capability failures, route-registration contract.
- **Manual:** denied/allowed settings save; keyboard admin navigation.
- **Acceptance:** secure foundation supports later routes without duplicated menus/controllers.
- **DoD/references:** controls and docs verified; NFR-003/008, ADR-0014, spec §§6,7,13.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M1-MOD-04 — Logging, errors, migrations and jobs foundation

**Purpose:** Make failures actionable without leaking data. **Tier:** Shared. **Dependencies:** M1-TASK-002/003. **Traceability:** NFR-007/009; ADR-0009; spec §§14,18.
**Deliverables:** logger/error contracts, schema migrator, bounded job abstraction. **Validation:** safe-log/error/migration tests. **Module DoD:** failures are stable, private, resumable where applicable.

### M1-TASK-004 — Implement observability and schema/job primitives

- **Status:** Not Started. **Priority:** High. **Tier:** Shared.
- **Objective:** Supply safe errors, structured logs, versioned migrations, and bounded background-work primitives.
- **Implementation:** stable error codes/request IDs; redaction; ordered idempotent migrator; locks/checkpoints/retry limits; deactivation stop behavior.
- **Expected code:** `Support/LoggerInterface.php`, `ErrorFactory.php`, `Infrastructure/Migrations/*`, `Infrastructure/Jobs/*`.
- **Dependencies:** M1-TASK-002/003.
- **Controls:** never log secrets, nonces, private fields, CSV rows, full bodies, IP/user/session identity.
- **A11y/performance:** translated actionable admin summaries; bounded batches and non-autoloaded logs/state.
- **Failures/edges:** duplicate job delivery, stale lock, partial migration, storage error; preserve canonical data and report safely.
- **Tests:** redaction, stable errors, migration rerun, lock/retry/checkpoint fixtures.
- **Manual:** induce safe failure and inspect user/admin/log output.
- **Acceptance:** failures contain correlation context but no prohibited data.
- **DoD/references:** contracts tested/documented; NFR-007/009, ADR-0009, spec §§14,18.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M1-MOD-05 — Engineering quality gates

**Purpose:** Make verification repeatable. **Tier:** Shared. **Dependencies:** M1-TASK-001. **Traceability:** NFR-001–NFR-010; spec §20; AI rules §§12,15.
**Deliverables:** unit/integration/contract harness, fixtures, PHPCS/lint/Plugin Check scripts, CI matrix. **Validation:** clean harness run. **Module DoD:** checks are documented and reproducible.

### M1-TASK-005 — Build test and release-check infrastructure

- **Status:** Not Started. **Priority:** Critical. **Tier:** Shared.
- **Objective:** Establish automated/static/compatibility gates before feature development.
- **Implementation:** PHPUnit WordPress harness; JS test/lint placeholder; fixture factories; PHPCS WordPress ruleset; PHP lint; Plugin Check; translation/secret/artifact checks; supported-version matrix.
- **Expected code:** `tests/`, test configuration, PHPCS config, package/composer scripts, CI workflow where repository policy permits.
- **Dependencies:** M1-TASK-001. **Parallel:** may run beside M1-TASK-002.
- **Controls:** fixtures contain no real private/medical data or secrets.
- **A11y/performance:** reserve accessibility/performance suites and reproducible benchmark metadata.
- **Failures/edges:** absent optional dependencies are explicit matrix states; skipped checks are reported, never passed.
- **Tests:** run a self-test plus lifecycle smoke suite.
- **Manual:** execute documented local commands from a clean checkout.
- **Acceptance:** AC-020–AC-022 are executable in the harness.
- **DoD/references:** all configured gates run and docs list exact commands; AI rules §§12/15, spec §20.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## Deferred scope

Feature implementation, patient data, native appointments, licensing, and any unapproved Pro capability.
