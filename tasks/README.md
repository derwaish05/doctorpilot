# DoctorPilot Development Task Plan

## Purpose

This directory translates the approved DoctorPilot product, requirements, architecture, ADRs, AI rules, and technical specification into development-ready work. Source documents in `../docs/` remain authoritative; tasks may implement but must not silently change them.

Each task carries its own status in the table below and in its milestone file; a task is **Not Started** until verified against the current implementation, and no status advances without evidence.

## Canonical milestone order

1. [Milestone 1 — Foundation](milestone-1-foundation.md)
2. [Milestone 2 — Doctor Directory](milestone-2-doctor-directory.md)
3. [Milestone 3 — Search and Frontend](milestone-3-search-and-frontend.md)
4. [Milestone 4 — Elementor](milestone-4-elementor.md)
5. [Milestone 5 — Migration and Operations](milestone-5-migration-and-operations.md)
6. [Milestone 6 — Pro Features](milestone-6-pro-features.md)
7. [Milestone 7 — Global Content Library](milestone-7-global-content-library.md) — cross-cutting architecture; lands **before** the release milestone

The critical path is M1 → M2 → M3 → M4 → M5 release verification. M6 bootstrap may begin after M1; individual Pro modules depend on their corresponding Free contracts. **M7 is a cross-cutting architecture change** (canonical field model, admin editor, REST, search documents, native rendering, and Elementor) and must complete before release verification; its Pro parts follow the M6 add-on. Tasks explicitly marked parallel may run concurrently only after their dependencies pass.

## Ownership

| Ownership | Meaning |
|---|---|
| Free | Ships in the independently useful WordPress.org base plugin |
| Pro | Ships only in the separate DoctorPilot Pro add-on |
| Shared | A contract/test/release concern spanning both packages; Free owns canonical data and base interfaces |

Free owns CPTs, taxonomies, canonical fields, public routes, menus, core rendering, security, privacy, accessibility, and lifecycle safety. Pro extends versioned Free interfaces; it must not duplicate ownership.

## Status legend

| Status | Meaning |
|---|---|
| Not Started | No verified implementation work |
| In Progress | Implementation is actively underway |
| Blocked | A documented dependency or decision prevents progress |
| Ready for Review | Implementation and required checks are complete; independent review remains |
| Completed | Acceptance criteria and Definition of Done are verified |
| Deferred | Explicitly outside the current approved milestone/release |

## Status update rules

- Change status only with evidence in the current codebase.
- A coding task may move to **Ready for Review** only when its implementation, automated checks, security/privacy checks, applicable accessibility checks, manual verification, and documentation boxes are resolved or explicitly documented as blocked.
- Only a reviewer/release owner marks **Completed** after acceptance criteria pass.
- Record blockers beside the task; never mark skipped checks as passed.
- Scope/tier changes require `02-requirements.md` updates; durable architecture changes require a new or superseding ADR.
- Keep requirement, API, schema, migration, readme, and changelog documentation synchronized with behavior.

## Dependencies and parallel work

| Milestone | Depends on | Safe parallel work after dependency |
|---|---|---|
| M1 Foundation | None | Bootstrap, test harness, logging design |
| M2 Directory | M1 registry/CPT/capabilities | Taxonomies, editor UI, native profile, SEO adapters |
| M3 Search/Frontend | M2 public view model | REST, frontend state, ranking fixtures, accessibility fixtures |
| M4 Elementor | M2 view model + M3 search contracts | Core widgets, style system, compatibility tests |
| M5 Migration/Ops | M1 schema + M2 write services + M3 invalidation | Import UI, redirect validator, Aman discovery |
| M6 Pro | M1 base API; feature-specific Free contract | Pro bootstrap, tables, advanced UI/tests |
| M7 Global Content Library | M1 registry/caps/jobs + M2 editor/native render + M3 search-doc/cache + M4 Elementor profile | Library CPTs/schema, resolver, reverse index, assignment UI; Pro merge/bulk/promote follow M6 |

## Requirement traceability

| Requirements | Primary milestone/task |
|---|---|
| FR-001–FR-009 | M2-TASK-001–M2-TASK-004 |
| FR-010–FR-014 | M2-TASK-002 |
| FR-015–FR-025 | M3-TASK-001–M3-TASK-004 |
| FR-026–FR-030 | M2-TASK-005, M3-TASK-005 |
| FR-031–FR-036 | M4-TASK-001–M4-TASK-005 |
| FR-037–FR-040 | M2-TASK-004, M6-TASK-004 |
| FR-041–FR-047 | M5-TASK-001–M5-TASK-005 |
| FR-048–FR-052 | M2-TASK-005, M3-TASK-005 |
| FR-053 | M5-TASK-004–M5-TASK-005 |
| FR-054–FR-056 | M6-TASK-005 |
| FR-057 | M3-TASK-002, M3-TASK-005 |
| FR-058–FR-063 | M7-TASK-002–M7-TASK-006 |
| NFR-001–NFR-010 | Cross-cutting task controls and milestone completion criteria |

## Acceptance-criteria traceability

| Criteria | Primary milestone/task |
|---|---|
| AC-001–AC-004 | M2-TASK-001–M2-TASK-003 |
| AC-005–AC-009 | M3-TASK-003–M3-TASK-005 |
| AC-010–AC-012 | M4-TASK-001–M4-TASK-004 |
| AC-013–AC-014 | M2-TASK-004, M6-TASK-004 |
| AC-015–AC-017 | M5-TASK-002–M5-TASK-004 |
| AC-018–AC-019 | M6-TASK-005 |
| AC-020–AC-022 | M1-TASK-001, M1-TASK-005 |
| AC-023 | M2-TASK-005 |
| AC-024 | M3-TASK-006 |
| AC-025–AC-035 | M7-TASK-002–M7-TASK-006 |

No requirement or acceptance criterion is intentionally unmapped.

## Overall progress

| Milestone | Modules | Tasks | Status |
|---|---:|---:|---|
| M1 Foundation | 5 | 5 | In Progress (implementation complete; PHP lint/PHPCS/PHPUnit pending in a PHP environment) |
| M2 Doctor Directory | 5 | 5 | In Progress (implementation complete; PHP lint/PHPCS/PHPUnit pending in a PHP environment) |
| M3 Search and Frontend | 6 | 6 | In Progress (implementation complete; PHPUnit + benchmark + a11y runs pending in a PHP environment) |
| M4 Elementor | 5 | 5 | In Progress (Free tasks 001/002/003/005 implemented; TASK-004 dynamic tags/Theme Builder is Pro-tier, deferred to M6; Elementor matrix runs pending) |
| M5 Migration and Operations | 5 | 5 | In Progress (implemented in the separate DoctorPilot Pro add-on; M6-TASK-001 Pro bootstrap done as prerequisite; live Aman cutover + PHPUnit pending) |
| M6 Pro Features | 5 | 5 | In Progress (implemented in the DoctorPilot Pro add-on; excludes deferred licensing/favourites/AI; PHPUnit + compatibility matrix pending) |
| M7 Global Content Library | 6 | 6 | Proposed (design + ADR-0017 documented; implementation gated on approval) — see milestone-7-global-content-library.md |
| **Total** | **37** | **37** | M1–M6 In Progress (implementation complete; verification pending in a PHP/WP environment); M7 Proposed (design only) |

## Global Definition of Done

- Linked requirements and acceptance criteria pass.
- Canonical identifiers, field registry, services, and tier ownership are respected.
- Capability checks, nonces for authenticated mutations, validation, sanitization, late escaping, REST allowlists, privacy, and medical-content safeguards are verified.
- Applicable WCAG 2.2 AA keyboard/screen-reader/responsive checks pass.
- Automated, compatibility, performance, migration, and lifecycle checks appropriate to the change pass.
- Documentation, schema/API versions, readmes, and changelogs agree.
- No serious security/accessibility defect remains and the final report states exactly what ran.

## Legacy task-plan note

`task-1.md` through `task-4.md`, `milestone-2-services.md`, and `milestone-3-events.md` are preserved as historical planning references. This README and the six canonical milestone files listed above supersede their sequencing. Conflicts must be resolved in favor of `../docs/` and then reflected here.

## Pro add-on note

Milestone 5 (migration/redirects/operations) and the Pro-tier parts of M4 and M6
ship in a **separate** `doctorpilot-pro` plugin, not in this free base, per the
AI rules (Pro code must not be bundled as disabled trialware in the free
package). The Pro add-on registers against the base extension API
(`doctorpilot/registered`) and fails safely if the base is absent or too old.

## Known ambiguities and ADR review

- The requested phrase “locations and hospitals” does not add a Hospital entity; approved scope models Location terms/records only.
- A standalone Elementor Filter or Doctor Card widget is not explicitly approved in the capability matrix. M4 uses the approved Doctor Search/Grid/List widgets and treats separable subcomponents as internal components, not new public widgets.
- Licensing implementation is not specified and is therefore deferred; adding it requires approved requirements/architecture.
- No new ADR is currently required. Any change to minimum versions, tier allocation, storage, public API, or booking ownership requires ADR review.

## Deferred scope

Native appointments, patient accounts/records, EHR/EMR, payments, telemedicine, diagnosis/recommendations, identifiable analytics, remote telemetry, AI medical features, favourites/comparison, external directory synchronization, and licensing not already specified.

## Recommended first task

Start with **M1-TASK-001 — Implement bootstrap, runtime guards, activation, deactivation, and uninstall skeleton**. It establishes the safe loading and lifecycle boundary required by every other module.
