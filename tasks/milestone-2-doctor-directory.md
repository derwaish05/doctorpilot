# Milestone 2 — Doctor Directory

## Milestone overview

- **Objective/User value:** Let hospital editors maintain one validated doctor record and visitors view accurate native profiles.
- **Included:** Canonical fields, taxonomies, editor, workflow, media, booking basics, permalinks, native archive/profile, SEO.
- **Excluded:** Interactive search, Elementor, Pro multi-location/import/analytics.
- **Boundary/dependencies:** Free owns canonical data and native output; Pro may extend limits. Depends on M1.
- **Traceability:** FR-001–FR-014, FR-026–FR-030, FR-037–FR-040, FR-048–FR-052; AC-001–AC-004, AC-010, AC-013–AC-014, AC-023; ADR-0001–0003, 0006, 0012–0015; spec §§3–5,10–13,16.
- **Risks:** Duplicate schemas, private-field leakage, invalid clinical relations, duplicate SEO output.
- **Completion:** Five modules pass schema, workflow, rendering, booking, SEO, lifecycle and accessibility checks.

## M2-MOD-01 — Canonical doctor model

**Purpose:** Store validated doctor data once. **Tier:** Free. **Dependencies:** M1. **Deliverables:** CPT and `FieldRegistry`; canonical fields/repeaters/invariants. **Validation/DoD:** schema snapshot and invariant tests pass; no second schema.

### M2-TASK-001 — Register CPT and canonical field registry

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Register `doctorpilot_doctor` (`show_in_rest=false`, mapped caps), canonical post/meta/media fields and bounded repeaters; compute review/completeness/search/sort derivatives; WordPress `post_status` is publication authority.
- **Expected code:** `PostTypes/DoctorPostType.php`, `Fields/FieldRegistry.php`, definitions/validators/sanitizers, doctor value objects.
- **Dependencies:** M1-TASK-002/003. **Controls:** public flag allowlist; recursive sanitation; private license/internal fields; explicit logged publish override.
- **A11y/performance:** validation messages are associated and translated; derived values rebuildable without N+1 queries.
- **Edges/failures:** “Dr. Dr.” prefix, negative experience, malformed repeaters, incomplete publish, deleted media.
- **Tests/manual:** registry snapshot, sanitizer/invariant/completeness/publication tests; create draft, invalid publish, valid publish.
- **Acceptance/DoD/references:** AC-001/004; FR-001–009; ADR-0001/0012/0013; spec §§3,5; all checkboxes verified.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M2-MOD-02 — Taxonomies and relationships

**Purpose:** Model curated discovery relationships. **Tier:** Free. **Dependencies:** M2-TASK-001. **Deliverables:** seven Free taxonomies, symptom metadata, relationship validation. **Validation/DoD:** assignment/merge/delete/primary invariants pass.

### M2-TASK-002 — Implement directory taxonomies and clinical relationships

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Register prefixed Department, Specialty, Symptom, Condition, Treatment, Language, Location; keep clinical types separate; validate primary terms, curated synonyms/relations/review audit; surface affected doctors on destructive term actions.
- **Expected code:** `Taxonomies/*`, term-meta registry/editor, relationship service/hooks.
- **Dependencies:** M2-TASK-001. **Controls:** `manage_doctorpilot_terms`, nonce, term-ID validation; never invent diagnosis/treatment relationships.
- **A11y/performance:** usable term-editor errors; bounded relation selectors and queries.
- **Edges/failures:** removed primary term, merge/delete, non-Latin terms, missing related term.
- **Tests/manual:** registration/meta/authorization/invariant tests; assign multiple specialties/symptoms and remove a primary.
- **Acceptance/DoD/references:** AC-002/003; FR-010–014; ADR-0003/0012; spec §4.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M2-MOD-03 — Admin editor and workflow

**Purpose:** Provide a guided, safe editorial experience. **Tier:** Free. **Dependencies:** M2-TASK-001/002. **Deliverables:** field groups, media handling, completeness/review UI, admin health. **Validation/DoD:** editor workflow is capability-safe and keyboard usable.

### M2-TASK-003 — Build doctor editor, validation, media and publication workflow

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Build all ten approved editor groups; featured image/placeholder; save through registry; separate review status; completeness/missing fields; draft/pending/publish workflow.
- **Expected code:** `Admin/DoctorEditor.php`, field-group components, save handler, `Application/Doctors/SaveDoctor.php`, media helper.
- **Dependencies:** M2-TASK-001/002. **Controls:** nonce plus mapped object caps; ignore client completeness/status escalation; late escape.
- **A11y/performance:** semantic tabs/fields/errors, focus and non-color statuses; avoid repeated term/media queries.
- **Edges/failures:** autosave/revision, concurrent update, deleted image, empty optional fields, unauthorized publish.
- **Tests/manual:** save/security/media/workflow tests; keyboard create/edit/pending/publish smoke.
- **Acceptance/DoD/references:** AC-001/003/004; FR-002–008; ADR-0013/0014; spec §§5–6.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M2-MOD-04 — Booking and location/contact

**Purpose:** Render safe core actions without storing appointments. **Tier:** Free. **Dependencies:** M2-TASK-001. **Deliverables:** one Free location record and three Free booking providers. **Validation/DoD:** unsafe destinations rejected; explicit fallback only.

### M2-TASK-004 — Implement Free location/contact and booking adapters

- **Status:** Not Started. **Priority:** High. **Tier:** Free.
- **Objective/implementation:** Enforce max-one `_doctorpilot_locations`; public-email consent; implement `BookingProviderInterface`, capabilities/CTA, External URL, Phone, Enquiry; validate resolved output.
- **Expected code:** `Booking/*`, location validator/view model, booking settings/field integration, `doctorpilot/booking/providers`.
- **Dependencies:** M2-TASK-001/003. **Controls:** capability/nonce on config; safe schemes/phones/targets; credentials never public/logged.
- **A11y/performance:** meaningful CTA labels/new-window behavior; no remote provider call for core actions.
- **Edges/failures:** malformed URL/phone, missing fallback, provider error, unverified availability.
- **Tests/manual:** provider contract/unsafe scheme/fallback tests; save and activate each action.
- **Acceptance/DoD/references:** AC-013/014; FR-037–040; ADR-0002; spec §§5.2,12.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M2-MOD-05 — Native rendering, permalinks and SEO

**Purpose:** Publish accessible, indexable doctor views without Elementor. **Tier:** Free. **Dependencies:** M2-TASK-001–004. **Deliverables:** view model, templates/shortcodes, URLs, SEO owner. **Validation/DoD:** native/profile/SEO ownership tests pass.

### M2-TASK-005 — Implement native archive/profile and SEO integration

- **Status:** Not Started. **Priority:** Critical. **Tier:** Free.
- **Objective/implementation:** Public allowlisted view model; theme `doctorpilot/` overrides; profile order; cards/archive; `[doctorpilot_directory]`/profile shortcode; configurable `/find-a-doctor/{post_name}/` without forced `dr-`; `SeoOwner`, canonical/OG/breadcrumb/schema/sitemap integration.
- **Expected code:** `Application/Doctors/DoctorView.php`, `Frontend/*`, `templates/*`, `Seo/*`, rewrite/settings hooks.
- **Dependencies:** M2-TASK-001–004. **Controls:** published-only, escape by context, exactly one SEO artifact owner, reviewed public schema fields.
- **A11y/performance:** semantic headings, omit empty sections, stable placeholder dimensions; batch view-model lookups.
- **Edges/failures:** draft/private/deleted doctor, duplicate names, missing media, disabled archive, SEO plugin active, staging host.
- **Tests/manual:** public/private/template/permalink/SEO-owner/schema tests; native render with Elementor off and supported themes.
- **Acceptance/DoD/references:** AC-004/010/023; FR-026–030/048–052; ADR-0006/0015; spec §§3,10–11,16.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## Deferred scope

Multiple locations, schedules/availability, BookPilot, imports, analytics, Elementor, patient/appointment storage.
