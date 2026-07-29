# Milestone 4 — Elementor

## Milestone overview

- **Objective/User value:** Let designers style approved DoctorPilot views without making Elementor the data owner.
- **Included:** Compatibility loader, six Free widgets, controls/query parity, guarded Pro tags/conditions, regression/a11y testing.
- **Excluded:** Unapproved standalone Filter/Card widgets, canonical content in Elementor JSON, migration/analytics.
- **Boundary/dependencies:** Core widgets work with Elementor Free; complete dynamic tags and Theme Builder require both DoctorPilot Pro and Elementor Pro. Depends on M2/M3.
- **Traceability:** FR-031–FR-036; AC-010–AC-012; ADR-0001/0008/0011; spec §§10.2,11,19–20.
- **Risks:** Fatals when absent, duplicated services, private tag leakage, asset bloat, editor/frontend drift.
- **Completion:** Five modules pass absent/Free/Pro compatibility, parity, accessibility, and asset checks.

## M4-MOD-01 — Compatibility and registration

**Purpose:** Load optional integration safely. **Tier:** Shared. **Dependencies:** M1 base API. **Deliverables:** compatibility loader/registrars/notices. **Validation/DoD:** all dependency states boot without fatal.

### M4-TASK-001 — Implement guarded Elementor integration bootstrap

- **Status:** Not Started. **Priority:** Critical. **Tier:** Shared.
- **Objective/implementation:** Detect supported Elementor/Pro versions before class references; register widget category/dependencies; keep native output; safe notices for incompatibility.
- **Expected code:** `Elementor/Compatibility.php`, `Integration.php`, registrars; dependency/version declarations.
- **Dependencies:** M1-TASK-002, M2-TASK-005. **Controls:** editor previews respect publication/private rules; no duplicate menu/route/schema.
- **A11y/performance:** accessible notices; load integration/assets only when applicable.
- **Edges/failures:** Elementor absent/old, Pro absent/old, load-order differences, DoctorPilot Pro mismatch.
- **Tests/manual:** compatibility matrix and boot smoke for every state.
- **Acceptance/DoD/references:** AC-010/012; FR-034/035; ADR-0011; spec §10.2.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M4-MOD-02 — Free search and directory widgets

**Purpose:** Expose approved discovery widgets. **Tier:** Free. **Dependencies:** M3 contracts. **Deliverables:** Doctor Search, Doctor Grid/List, Department Grid, Symptom Directory. **Validation/DoD:** frontend/native parity.

### M4-TASK-002 — Build Free search and directory widgets

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Register four approved widgets using shared search/view services; query/pagination/URL state parity; relevant responsive/style/loading/empty controls.
- **Expected code:** `Elementor/Widgets/{DoctorSearch,DoctorGridList,DepartmentGrid,SymptomDirectory}.php`, shared render components.
- **Dependencies:** M4-TASK-001, M3-TASK-003–005. **Controls:** validated control values; published allowlist; no raw meta/direct parallel query engine.
- **A11y/performance:** keyboard/focus/status parity; Elementor dependency assets only when widget present; avoid N+1.
- **Edges/failures:** empty results, editor preview, REST unavailable, invalid saved control, missing term.
- **Tests/manual:** registration/render/query/assets tests; build responsive directory and compare native state/results.
- **Acceptance/DoD/references:** AC-011; FR-031/033/034/036; ADR-0001/0011; spec §10.2.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M4-MOD-03 — Free profile and booking widgets

**Purpose:** Render canonical doctor profiles/actions. **Tier:** Free. **Dependencies:** M2 views/booking. **Deliverables:** Doctor Profile and Booking Button widgets. **Validation/DoD:** data and booking parity.

### M4-TASK-003 — Build Free Doctor Profile and Booking Button widgets

- **Status:** Not Started. **Priority:** High. **Tier:** Free.
- **Objective/implementation:** Render profile section order and safe CTA via shared view/booking services; expose relevant layout/style/responsive controls; live preview fixtures.
- **Expected code:** `Elementor/Widgets/{DoctorProfile,BookingButton}.php`, render adapters.
- **Dependencies:** M4-TASK-001, M2-TASK-004/005. **Controls:** private/unpublished preview authorization; validate URLs/targets; escape exact context.
- **A11y/performance:** heading hierarchy, CTA labels/focus/new-window meaning; omit empty wrappers; no duplicate hydration.
- **Edges/failures:** missing context/image/field, provider failure, unsafe saved legacy control.
- **Tests/manual:** render/privacy/provider/fallback tests; build profile, edit canonical doctor, confirm propagation.
- **Acceptance/DoD/references:** AC-011/013/014; FR-028/031/033/036/040; spec §§10–12.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M4-MOD-04 — Pro dynamic data and Theme Builder

**Purpose:** Add approved Elementor Pro-only binding. **Tier:** Pro. **Dependencies:** M4-TASK-001, M6 bootstrap. **Deliverables:** complete tags and custom conditions. **Validation/DoD:** guards/privacy/context tests pass.

### M4-TASK-004 — Implement guarded dynamic tags and Theme Builder conditions

- **Status:** Not Started. **Priority:** High. **Tier:** Pro.
- **Objective/implementation:** Generate eligible tags from `FieldRegistry`; register doctor/archive/department/specialty/search contexts through compatible Elementor Pro APIs only.
- **Expected code:** Pro `Elementor/DynamicTags/*`, `ThemeConditions/*`, compatibility adapter.
- **Dependencies:** M6-TASK-001; M4-TASK-001. **Controls:** never expose private/internal fields or unpublished data; server-side preview authorization.
- **A11y/performance:** tags preserve semantic render context; lazy registries, no repeated hydration.
- **Edges/failures:** Elementor Pro absent/incompatible, empty values, invalid preview context, downgraded Pro.
- **Tests/manual:** absent/compatible API, tag allowlist, condition, privacy tests; bind/update field and verify template.
- **Acceptance/DoD/references:** AC-012; FR-035; ADR-0008/0011; spec §§10.2,19.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M4-MOD-05 — Compatibility, styles and parity

**Purpose:** Prove integration quality. **Tier:** Shared. **Dependencies:** M4-TASK-001–004. **Deliverables:** regression matrix and reports. **Validation/DoD:** supported matrix passes.

### M4-TASK-005 — Verify controls, parity, assets, accessibility and compatibility

- **Status:** Not Started. **Priority:** Critical. **Tier:** Shared.
- **Objective/implementation:** Test global-token/widget override cascade, selector scope, responsive/editor/frontend parity, themes, Elementor states, Pro states, upgrades.
- **Expected code:** widget snapshots/integration/browser tests, compatibility fixtures/reports.
- **Dependencies:** M4-TASK-001–004. **Controls:** no private preview leakage or unsafe control output.
- **A11y/performance:** WCAG keyboard/screen-reader/RTL/reduced-motion/zoom; widget-only assets and query budgets.
- **Edges/failures:** stale widget settings, theme CSS collision, missing dependencies, Pro disable/re-enable.
- **Tests/manual:** full automated and manual matrix at breakpoints with representative themes.
- **Acceptance/DoD/references:** AC-010–012; FR-033–036; ADR-0011; spec §20.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## Deferred scope

Standalone widgets not approved by FR-031/032, licensing, canonical data in Elementor, and deprecated API compatibility outside the declared matrix.
