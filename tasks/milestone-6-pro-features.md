# Milestone 6 — Pro Features

## Milestone overview

- **Objective/User value:** Add only approved advanced directory capabilities through a separate compatible add-on.
- **Included:** Pro bootstrap, tables, roles/scopes, multi-location/advanced search/widgets, BookPilot, import/redirect/bulk hooks, opt-in aggregate analytics.
- **Excluded:** Licensing (unspecified), native appointments, patient records, AI, future ideas.
- **Boundary/dependencies:** Free owns canonical services/data/routes; Pro extends versioned contracts. Bootstrap depends on M1; feature modules depend on matching Free milestones.
- **Traceability:** FR-009/017/032/035/039/041–047/054–056; AC-012, AC-014–AC-019; ADR-0002/0008/0009/0011/0014/0016; spec §§6–7,10–19.
- **Risks:** Duplicate ownership, fatal dependency states, public private-data leakage, unverified availability, identifying analytics.
- **Completion:** Five modules pass compatibility, scope, security, downgrade, analytics and contract tests.

## M6-MOD-01 — Pro bootstrap and compatibility

**Purpose:** Load a separate add-on safely. **Tier:** Pro. **Dependencies:** M1 base API. **Deliverables:** add-on bootstrap/version negotiation/notices. **Validation/DoD:** all Free/Pro states are nonfatal.

### M6-TASK-001 — Implement Pro bootstrap and Free dependency negotiation

- **Status:** Not Started. **Priority:** Critical. **Tier:** Pro.
- **Objective/implementation:** Separate package; min/max base API; guarded registration; safe missing/incompatible Free notice; preserve Pro data on disable; Free continues after Pro deactivation.
- **Expected code:** Pro plugin entry/bootstrap/compatibility registry; Pro readme/changelog.
- **Dependencies:** M1-TASK-002. **Controls:** no remotely unlocked Pro code in Free; no public data unreadable on license/add-on state.
- **A11y/performance:** accessible notices; no Pro boot work when incompatible.
- **Edges/failures:** Free missing/old/new, Pro disable/re-enable, Free removed while Pro active.
- **Tests/manual:** compatibility matrix and data-preservation smoke.
- **Acceptance/DoD/references:** safe states; ADR-0008/0011; spec §11.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M6-MOD-02 — Pro schema, scopes and workflow

**Purpose:** Add approved operational storage and permissions. **Tier:** Pro. **Dependencies:** M6-TASK-001. **Deliverables:** tables/migrations, migration/analytics caps, department/self-edit presets. **Validation/DoD:** schema and object scopes pass.

### M6-TASK-002 — Implement Pro migrations, capabilities and scoped workflows

- **Status:** Not Started. **Priority:** Critical. **Tier:** Pro.
- **Objective/implementation:** Install four spec tables/indexes; `manage_doctorpilot_migrations`/`view_doctorpilot_analytics`; optional department-manager/self-editor presets with server object scope; bounded upgrades.
- **Expected code:** Pro migrations/repositories, capability/scope services, admin health.
- **Dependencies:** M1-TASK-004, M6-TASK-001. **Controls:** role names never authorize; client department/ownership meta cannot bypass scope.
- **A11y/performance:** accessible scope UI; indexed tables, bounded upgrades/non-autoload.
- **Edges/failures:** incompatible schema, scope reassignment, Pro disable, multisite.
- **Tests/manual:** schema/index/upgrade and cross-department/self-publish denial tests.
- **Acceptance/DoD/references:** FR-009/041–047; ADR-0014; spec §§6,14,18.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M6-MOD-03 — Advanced directory and Elementor

**Purpose:** Add approved advanced filters/data/widgets. **Tier:** Pro. **Dependencies:** M2/M3/M4, M6-TASK-001. **Deliverables:** multi-location, advanced filters/sorts, Pro widgets/tags/conditions. **Validation/DoD:** extensions reuse Free services.

### M6-TASK-003 — Implement Pro directory/search and Elementor extensions

- **Status:** Not Started. **Priority:** High. **Tier:** Pro.
- **Objective/implementation:** Multiple locations/location contact; insurance; structured schedule/availability; location/insurance/condition/treatment/consultation/date filters; verified availability sort; approved Pro widgets; complete tags/Theme conditions with Elementor Pro.
- **Expected code:** Pro registry extensions/search adapters/widgets/tags/conditions; Free hooks only.
- **Dependencies:** M3-TASK-001–005, M4-TASK-001, M6-TASK-001. **Controls:** published allowlist; strict filter/date bounds; private schedule/provider data excluded.
- **A11y/performance:** advanced filters/widgets meet Free keyboard/status standards; capability state in cache key; bounded queries.
- **Edges/failures:** Elementor Pro/provider absent, no verified availability, multiple-location downgrade to Free.
- **Tests/manual:** advanced combinations, cache, widget/dependency/privacy matrix; disable Pro and verify Free output.
- **Acceptance/DoD/references:** AC-012; FR-009/017/032/035; ADR-0008/0011; spec §§5,7,10.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M6-MOD-04 — BookPilot and location-aware booking

**Purpose:** Extend booking without owning appointments. **Tier:** Pro. **Dependencies:** M2 booking, M6-TASK-003. **Deliverables:** BookPilot provider and location-aware resolver. **Validation/DoD:** provider contract/fallback passes.

### M6-TASK-004 — Implement guarded BookPilot provider integration

- **Status:** Not Started. **Priority:** High. **Tier:** Pro.
- **Objective/implementation:** Register `BookPilotProvider`; declare location/schedule/verified availability capabilities; resolve/revalidate CTA; explicit safe fallback only.
- **Expected code:** Pro booking adapter/client boundary/settings/contract tests.
- **Dependencies:** M2-TASK-004, M6-TASK-003. **Controls:** secret storage; authenticated settings; safe outbound/request logging; no raw provider response public.
- **A11y/performance:** accessible CTA/status; timeout/circuit/cache only verified safe data, never secrets.
- **Edges/failures:** provider timeout/error, malformed CTA, expired/unverified availability, missing location.
- **Tests/manual:** provider contract, unsafe output, timeout/fallback, secret-leak tests; sandbox/manual provider smoke if available.
- **Acceptance/DoD/references:** AC-014; FR-039/040; ADR-0002; spec §12.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M6-MOD-05 — Aggregate analytics and downgrade security

**Purpose:** Offer non-identifying local insight. **Tier:** Pro. **Dependencies:** M3 events/routes, M6-TASK-002. **Deliverables:** opt-in endpoint, daily aggregates, UI/retention, downgrade tests. **Validation/DoD:** default-off and prohibited identity tests pass.

### M6-TASK-005 — Implement opt-in aggregate analytics and Pro security matrix

- **Status:** Not Started. **Priority:** High. **Tier:** Pro.
- **Objective/implementation:** Disclosure/consent; strict aggregate POST returning 204; normalize approved dimensions; atomic daily upsert; retention/deletion; popular doctors/symptoms/no-results; verify Pro disable/upgrade/downgrade.
- **Expected code:** Pro analytics controller/service/repository/admin UI/retention job; analytics table.
- **Dependencies:** M3-TASK-003/004, M6-TASK-002. **Controls:** no IP/user/cookie/session/raw UA/person-query association/raw body; rate control is not auth.
- **A11y/performance:** accessible disclosure/dashboard; indexed atomic aggregate and bounded cleanup.
- **Edges/failures:** disabled consent, malformed event, shared IP, duplicate increments, expired data, Pro off.
- **Tests/manual:** AC-018/019, schema forbidden columns, endpoint, upsert, retention, no external tracking, upgrade/downgrade.
- **Acceptance/DoD/references:** AC-018/019; FR-053–056; ADR-0009/0016; spec §§7,14.4,17.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## Deferred scope

Licensing implementation, native appointments, favourites/comparison, telemedicine, synchronization, multilingual synonym management, dedicated search index, and AI-assisted medical features.
