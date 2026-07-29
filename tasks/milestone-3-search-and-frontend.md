# Milestone 3 — Search and Frontend

## Milestone overview

- **Objective/User value:** Deliver deterministic, fast, shareable, accessible doctor discovery with native fallback.
- **Included:** Request normalization, ranking, Free filters/sorts, REST, cache, grid/list UI, URL state, no-result, accessibility, performance.
- **Excluded:** Pro filters/availability/analytics storage, Elementor.
- **Boundary/dependencies:** Free search owns public routes; Pro extends allowed contracts. Depends on M2.
- **Traceability:** FR-015–FR-030, FR-057; AC-002, AC-004–AC-009, AC-024; ADR-0004/0009/0012/0016; spec §§7–10,17,20.
- **Risks:** Private/unpublished leakage, slow meta queries, diagnostic suggestions, inaccessible dynamic UI.
- **Completion:** Six modules pass contract, ranking, frontend, privacy, accessibility, and 1,000-doctor benchmark checks.

## M3-MOD-01 — Search request and ranking

**Purpose:** Normalize once and rank deterministically. **Tier:** Free. **Dependencies:** M2 view model/taxonomies. **Deliverables:** immutable request, candidate/scoring services. **Validation/DoD:** ranking fixtures cover every rule.

### M3-TASK-001 — Implement normalized search request and ranking service

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Bound/normalize `q`, mode, filters, sort/view/page/per_page; Free modes/filters only; Unicode-safe comparison; curated synonyms; visibility/filter before documented scoring/ties.
- **Expected code:** `Domain/Search/SearchRequest.php`, normalizer/validator, candidate repository, ranker, ranking fixtures.
- **Dependencies:** M2-TASK-002/005. **Controls:** published-only candidates; reject/bound expensive input; no diagnostic equivalence.
- **A11y/performance:** service-neutral; retrieve IDs/required fields and paginate before hydration.
- **Edges/failures:** non-Latin names, initials, duplicate names, invalid/unknown terms, unreasonable offsets, ties.
- **Tests/manual:** normalization, bounds, Unicode, each score/tie/filter fixture; execute approved reference queries.
- **Acceptance/DoD/references:** AC-002/004; FR-015–025; ADR-0004/0012; spec §§7.1,8.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M3-MOD-02 — Suggestions, facets, pagination and sorting

**Purpose:** Supply bounded discovery helpers. **Tier:** Free. **Dependencies:** M3-TASK-001. **Deliverables:** suggestion/facet/page services. **Validation/DoD:** combined filters and no-result safety pass.

### M3-TASK-002 — Implement facets, suggestions, combined filters and no-result contract

- **Status:** Not Started. **Priority:** High. **Tier:** Free.
- **Objective/implementation:** Debounced-source suggestions after minimum length; facet counts; combined Free filters; relevance/name/featured/experience/recent sorting; mandatory pagination; curated no-result suggestions/contact/featured doctors; configurable discovery-not-diagnosis/emergency disclaimer.
- **Expected code:** `Application/Search/{Suggestion,Facet,NoResult}Service.php`, repository queries/fixtures.
- **Dependencies:** M3-TASK-001. **Controls:** allowlisted published values; no raw/session-linked analytics; no diagnosis/urgency/treatment inference.
- **A11y/performance:** bounded limits/count queries; labels ready for accessible UI.
- **Edges/failures:** zero results, stale term, empty query, last page deleted, conflicting filters.
- **Tests/manual:** combined-filter, facet, sort, pagination, suggestion/no-result fixtures.
- **Acceptance/DoD/references:** AC-006/009; FR-016–025/057; ADR-0009/0016; spec §§7–8.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M3-MOD-03 — Public REST

**Purpose:** Expose one safe public search contract. **Tier:** Free. **Dependencies:** M3-TASK-001/002. **Deliverables:** four GET endpoints and stable errors. **Validation/DoD:** contract/public-private tests pass.

### M3-TASK-003 — Implement DoctorPilot public REST endpoints

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Add `/doctors`, `/doctors/suggest`, `/doctors/{id}`, `/facets` under `doctorpilot/v1`; exact args/envelope/errors/headers; explicit public permission callbacks.
- **Expected code:** `Rest/DoctorsController.php`, `SuggestController.php`, `FacetsController.php`, serializer/error schema.
- **Dependencies:** M1 REST registry; M3-TASK-001/002. **Controls:** no nonce on public GET; strict bounds/rate abuse control; published allowlist; no SQL/path/secret errors.
- **A11y/performance:** response supports announcements; cacheable GET, page max 48.
- **Edges/failures:** invalid filter, missing doctor, throttling/shared IP, internal error.
- **Tests/manual:** request/response/error schema, unpublished/private fixtures, totals, permissions; issue representative HTTP requests.
- **Acceptance/DoD/references:** AC-004/005; FR-018–025; ADR-0004/0009; spec §7.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M3-MOD-04 — Cache and invalidation

**Purpose:** Meet performance without stale/private data. **Tier:** Shared. **Dependencies:** M3-TASK-001–003. **Deliverables:** generation keys, derived rebuild handlers. **Validation/DoD:** invalidation and isolation tests pass.

### M3-TASK-004 — Implement public cache generation and derived search rebuilding

- **Status:** Not Started. **Priority:** Critical. **Tier:** Shared.
- **Objective/implementation:** Cache keys include API version/generation/locale/normalized request/capability state; increment on relevant doctor/term/settings/provider/migration events; sync single rebuild, bounded bulk jobs.
- **Expected code:** `Infrastructure/Cache/*`, search document builder, save/term/settings hooks, `bstdz_doctorpilot_cache_generation`.
- **Dependencies:** M1 job primitives; M3-TASK-003. **Controls:** never cache preview/private/import/secrets; correctness independent of TTL/object cache.
- **A11y/performance:** no UI; avoid full-cache enumeration and N+1 hydration.
- **Edges/failures:** publish/unpublish, term deletion, provider change, missing object cache, failed bulk rebuild.
- **Tests/manual:** cache key/isolation/invalidation/rebuild tests; warm, mutate, and confirm fresh response.
- **Acceptance/DoD/references:** AC-004/024; NFR-004/006; ADR-0012; spec §9.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M3-MOD-05 — Interactive native frontend

**Purpose:** Provide shareable discovery with graceful fallback. **Tier:** Free. **Dependencies:** M3-TASK-003/004. **Deliverables:** search/directory UI and state controller. **Validation/DoD:** dynamic and no-JS journeys pass.

### M3-TASK-005 — Build grid/list search UI, URL state and accessible interactions

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** `[doctorpilot_search]`; GET fallback; grid/list, result count, chips/clear, mobile drawer, loading/error/empty/no-result, pagination; canonical History API state; debounce and stale-request cancellation.
- **Expected code:** `Frontend/SearchRenderer.php`, templates, scoped CSS, JS state/request modules.
- **Dependencies:** M2-TASK-005, M3-TASK-003/004. **Controls:** escape output/URLs; noindex filtered combinations; display only server allowlist.
- **A11y/performance:** keyboard drawer/chips/suggestions/pagination; focus restore; polite count announcements; reduced motion/RTL; skeleton dimensions.
- **Edges/failures:** REST/JS offline, aborted/out-of-order request, back/forward, empty/malformed URL, mobile resize.
- **Tests/manual:** URL/history/stale/no-JS/error frontend tests; keyboard/screen-reader desktop/tablet/mobile.
- **Acceptance/DoD/references:** AC-005–AC-010; FR-019–030; ADR-0004; spec §§10–11.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M3-MOD-06 — Performance and release evidence

**Purpose:** Prove target behavior at scale. **Tier:** Shared. **Dependencies:** all M3 tasks. **Deliverables:** 1,000-doctor fixture/report and accuracy/a11y evidence. **Validation/DoD:** release budgets pass.

### M3-TASK-006 — Run search accuracy, accessibility and performance verification

- **Status:** Not Started. **Priority:** Critical. **Tier:** Shared.
- **Objective/implementation:** Reproducible fixture/queries; record environment, p50/p95, query count; accuracy first-five target; automated/manual accessibility suite.
- **Expected code:** benchmark/fixture scripts, ranking query set, reports under approved test artifacts.
- **Dependencies:** M3-TASK-001–005. **Controls:** synthetic/reviewed data only; no telemetry or real search histories.
- **A11y/performance:** WCAG 2.2 AA; REST p95 ≤500 ms for 1,000 published doctors excluding network; ≥95% approved query success.
- **Edges/failures:** cold/warm cache, no object cache, Unicode, combined filters; failure blocks release and is reported.
- **Tests/manual:** benchmark, query set, automated scan, keyboard/screen-reader smoke.
- **Acceptance/DoD/references:** AC-024 and product success targets; NFR-002/004/005; spec §20.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## Deferred scope

Location/insurance/condition/treatment/consultation/date/availability filters, availability sorting, Pro analytics, AI matching.
