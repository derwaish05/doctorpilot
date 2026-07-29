# Milestone 7 — Global Content Library

## Milestone overview

- **Objective/User value:** Reusable, centrally-managed Procedures, Experience &
  Education, Publications, and FAQs assignable to many doctors, with independent
  doctor-specific items and full backward compatibility.
- **Nature:** **Cross-cutting architecture change** (canonical field model, admin
  editor, REST, search documents, native rendering, Elementor). Must complete
  **before** release verification; Pro parts follow the M6 add-on.
- **Included:** Library CPTs + schema, reference storage, `DoctorContentResolver`,
  reverse-reference index + invalidation, assignment UI, authenticated
  `library/search`, lifecycle + dedupe, migration + promote-to-global.
- **Excluded (Pro):** bulk assign/promote, merge/repoint, impact reporting,
  library CSV import/export. **Deferred:** unified interleaved ordering.
- **Boundary/dependencies:** Free owns CPTs, schema, resolver, references, UI, and
  rendering. Depends on M1 (registry/caps/jobs), M2 (editor/native render),
  M3 (search document/cache generation), M4 (Elementor profile).
- **Traceability:** FR-058–FR-063; AC-025–AC-035; ADR-0017 (supersedes parts of
  ADR-0001/ADR-0012 §5.2); ADR-0010/0013/0014; spec §§5.4,7.0,10–11; design
  `../docs/11-global-content-library.md`.
- **Risks:** Public exposure of non-published globals; dangling references on
  delete; missed invalidation on global edit; duplicate records; migration data
  loss.
- **Completion:** All six modules meet their DoD; schema, resolver, invalidation,
  lifecycle, security, accessibility, and migration checks pass.

## M7-MOD-01 — Documentation and ADR

**Purpose:** Establish the approved architecture and contracts. **Tier:** Shared.
**Dependencies:** none. **Deliverables:** ADR-0017; FR-058–FR-063; AC-025–AC-035;
spec §5.4/§7.0; design spec. **Module DoD:** all source docs agree; ADR Accepted.

### M7-TASK-001 — Author ADR, requirements, spec, and design
- **Status:** Ready for Review. **Priority:** Critical. **Tier:** Shared.
- **Objective/implementation:** ADR-0017 (Accepted, implementation gated);
  FR-058–FR-063; AC-025–AC-035; capability-matrix rows; spec §5.4 + §7.0 route
  contract; design spec with reverse-index/invalidation, ordering, computed
  title, resolved schemas, dedupe, blast-radius.
- **Controls:** identifiers ≤ 20 chars; no normative conflicts across docs.
- **Tests/manual:** cross-document consistency review.
- **Acceptance/DoD/references:** docs synchronized; ADR-0017; spec §§5.4,7.0.
- [x] Implementation task (documentation)
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [x] Manual verification (doc review)
- [x] Documentation updated

## M7-MOD-02 — Library CPTs, schema, capability, admin

**Purpose:** Canonical global records with central management. **Tier:** Free.
**Dependencies:** M7-TASK-001, M1-TASK-002/003. **Deliverables:** four CPTs, library
field schemas in the one registry, `manage_doctorpilot_library`, admin submenus.
**Module DoD:** records manage safely with `post_status` authority; no duplicate menu.

### M7-TASK-002 — Register library CPTs, schema, capability, admin
- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Register `doctorpilot_proc`, `doctorpilot_edu_exp`,
  `doctorpilot_pub`, `doctorpilot_faq` (`show_in_rest=false`, no public archive,
  mapped caps); add library-item field definitions to `FieldRegistry`; compute the
  education title read-only; grant `manage_doctorpilot_library`; nest admin
  screens under the DoctorPilot menu.
- **Expected code:** `Library/PostTypes.php`, `Library/LibraryFields.php` (into the
  existing registry), `Support/Capabilities` additions, `Admin` submenus.
- **Controls:** `post_status` is the only publication authority; capability + nonce
  on all writes; late escaping; unique slugs.
- **A11y/performance:** standard WP list tables; accessible editor fields/labels.
- **Edges/failures:** long titles, missing fields, invalid year/date, private records.
- **Tests/manual:** registration/schema snapshot, computed-title, capability tests;
  create/edit/publish/unpublish/delete each type.
- **Acceptance/DoD/references:** AC-025; FR-058; ADR-0010/0013/0017; spec §5.4.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M7-MOD-03 — References, resolver, and integration

**Purpose:** Combine globals + locals through one service. **Tier:** Free.
**Dependencies:** M7-TASK-002, M2-TASK-005, M3-TASK-004. **Deliverables:** reference
meta, `DoctorContentResolver`, reverse index, consumer wiring. **Module DoD:** all
consumers show identical resolved content; no second data source.

### M7-TASK-003 — Reference storage, resolver, reverse index, consumers
- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Ordered unique `_doctorpilot_{proc|edu_exp|pub|faq}_ids`
  with stable local `_key`s; `DoctorContentResolver` (public = published globals
  first then locals; admin = origin/status/id/_key); reverse-reference index
  maintained on doctor save; integrate `DoctorView::full()`, `profile-content.php`,
  the search-document builder, the Elementor Doctor Profile widget, and
  `GET /doctors/{id}`.
- **Expected code:** `Library/DoctorContentResolver.php`, `Library/ReverseIndex.php`,
  `FieldRegistry` reference fields, `SaveDoctor` diff/dedupe/index hooks,
  `SearchIndexBuilder` inclusion of resolved content.
- **Dependencies:** M7-TASK-002. **Controls:** published-only in public; defensive
  ignore of missing/non-published; no meta `LIKE`; templates/widgets read the
  resolver, not raw meta.
- **A11y/performance:** batched hydration; no N+1; O(1) reverse lookup.
- **Edges/failures:** deleted global mid-render, empty lists, duplicate IDs, large
  assignment sets.
- **Tests/manual:** resolver ordering/origin/visibility, reverse-index diff/rebuild,
  parity across consumers.
- **Acceptance/DoD/references:** AC-026/030/031/034; FR-059/061; spec §§5.4,10–11.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M7-MOD-04 — Assignment editor UI and search route

**Purpose:** Let editors assign globals and manage locals accessibly. **Tier:** Free.
**Dependencies:** M7-TASK-003, M1-TASK-003. **Deliverables:** `GET /library/search`
and the editor UI. **Module DoD:** keyboard/screen-reader usable; object-scoped auth.

### M7-TASK-004 — Assignment UI + authenticated search route
- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Register `GET doctorpilot/v1/library/search`
  (params `type`, `search`, `doctor_id`, `page`, `per_page≤50`, `exclude[]≤100`;
  no-store; object permission when `doctor_id` present); build the editor
  multi-select with ordering, add/edit/remove local items, global/local badges,
  and duplicate-assignment prevention.
- **Expected code:** `Rest/LibraryController.php`, `Admin` assignment components,
  scoped JS/CSS.
- **Dependencies:** M7-TASK-003. **Controls:** explicit permission callback
  (`edit_doctorpilot_doctor` object check when `doctor_id` supplied, else
  `edit_doctorpilot_doctors`); nonce for admin state; bounded inputs; escape output.
- **A11y/performance:** keyboard operation, visible focus, labels, status
  announcements, non-color-only distinction; debounced bounded search.
- **Edges/failures:** unauthorized doctor, empty/oversized queries, stale results,
  JS off (graceful degrade to selectable lists).
- **Tests/manual:** route contract/permission/bounds tests; keyboard/screen-reader
  assignment smoke at desktop/mobile.
- **Acceptance/DoD/references:** AC-026/028/029/035; FR-059/062; spec §7.0.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M7-MOD-05 — Lifecycle, invalidation, and duplicate handling

**Purpose:** Safe edit/unpublish/trash/delete/merge and dedupe. **Tier:** Free
(merge is Pro). **Dependencies:** M7-TASK-003, M1-TASK-004, M3-TASK-004.
**Deliverables:** invalidation, cleanup, dedupe key/override. **Module DoD:** no
public leak, no dangling references, immediate generation bump + bounded rebuilds.

### M7-TASK-005 — Lifecycle, invalidation, dedupe-on-create
- **Status:** Not Started. **Priority:** Critical. **Tier:** Free/Pro.
- **Objective/implementation:** On global create/edit/status-change/delete: bump
  `bstdz_doctorpilot_cache_generation` immediately and enqueue bounded search-doc
  rebuilds for affected doctors; trash retains references (restore re-links);
  `before_delete_post` removes references in bounded batches; normalized dedupe key
  (NFC/case-fold/whitespace/punctuation/DOI), publish+draft scope, exact-key block
  with `manage_doctorpilot_library` override + audit log; Pro merge repoints
  references then trashes duplicates.
- **Expected code:** `Library/LifecycleHooks.php`, `Library/Dedupe.php`, jobs for
  bounded rebuild/cleanup; Pro `Library/Merge.php`.
- **Dependencies:** M7-TASK-003. **Controls:** capability/nonce; race protection via
  unique slug + post-insert re-check; defensive resolver.
- **A11y/performance:** confirmation dialogs accessible; bounded batches; no
  unbounded scans.
- **Edges/failures:** concurrent identical creates, restore after delete, mass
  reassignment, rebuild job failure/resume.
- **Tests/manual:** invalidation on each event, trash-vs-delete reference behavior,
  dedupe/override/audit, Pro merge repoint; manual blast-radius warnings.
- **Acceptance/DoD/references:** AC-029/030/031/032/035; FR-062/063; spec §§5.4,9.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M7-MOD-06 — Migration, promotion, and verification

**Purpose:** Preserve existing data and prove correctness. **Tier:** Shared.
**Dependencies:** M7-TASK-002–005. **Deliverables:** idempotent migration,
promote-to-global, index rebuild, test suite, docs. **Module DoD:** pre-upgrade
parity; isolation of promotion; documented commands.

### M7-TASK-006 — Migration, promote-to-global, tests, docs
- **Status:** Not Started. **Priority:** Critical. **Tier:** Shared.
- **Objective/implementation:** Idempotent schema migration (register CPTs/meta/caps,
  bump version, preserve repeaters as local); reverse-index rebuild action; opt-in
  "promote local → global" (Free single; Pro bulk + de-dupe); unit/migration/
  resolver/security/accessibility tests; README and CHANGELOG updates; blast-radius
  visibility surfaced.
- **Expected code:** `Infrastructure/Migrations` entry, `Library/Promote.php`,
  tests, docs.
- **Dependencies:** M7-TASK-002–005. **Controls:** additive migration; no data loss;
  capability/nonce on promotion.
- **A11y/performance:** bounded migration/rebuild batches.
- **Edges/failures:** partial upgrade, large libraries, promotion of an item already
  global elsewhere, multisite bounds.
- **Tests/manual:** pre/post parity, promote isolation, trash/delete reference,
  index rebuild; run documented commands from a clean checkout.
- **Acceptance/DoD/references:** AC-033/034; FR-058–063; spec §§5.4,18.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## Acceptance criteria

Canonical in `../docs/02-requirements.md` as AC-025–AC-035 (mirrored in
`../docs/11-global-content-library.md` §11 as AC-L1–AC-L11).

## Definition of done

Global items are managed centrally; doctors assign multiple globals and add
independent local items; global edits propagate (with immediate cache-generation
bump and bounded rebuilds) while local items stay isolated; global vs local is
clearly distinguished; duplicate assignments and duplicate globals are prevented;
non-published globals never surface publicly and leave no dangling references
(trash retains, delete cleans); existing repeater data renders unchanged after
upgrade; native/REST/search/Elementor show the same resolved content;
capability/nonce/sanitization/validation/escaping verified; documentation and
changelog synchronized; the final report states exactly what ran.
