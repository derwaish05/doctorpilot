# Milestone 5 — Migration and Operations

## Milestone overview

- **Objective/User value:** Migrate Aman Hospital safely through reusable, resumable, auditable Pro operations.
- **Included:** Discovery/mapping, CSV dry-run/import, idempotency, errors, redirects, rollback, reconciliation, operational tools, retention.
- **Excluded:** Hardcoded Aman behavior in reusable services, auto-publishing, guessed redirects.
- **Boundary/dependencies:** Migration tooling is Pro; safety/privacy are never paywalled. Depends on M1/M2/M3; Pro bootstrap M6-TASK-001.
- **Traceability:** FR-041–FR-047, FR-053; AC-015–AC-017; ADR-0006/0007/0009/0013; spec §§14–18,20.
- **Risks:** Data loss/duplication, wrong-doctor content, staging URLs, formula injection, long-running failures.
- **Completion:** Five modules pass dry-run/idempotency/resume/rollback/redirect/privacy/reconciliation checks.

## M5-MOD-01 — Discovery and mapping

**Purpose:** Separate reusable mapping from Aman evidence. **Tier:** Pro. **Dependencies:** M2 registry, M6 bootstrap. **Deliverables:** inventory, mapping schema, review flags. **Validation/DoD:** mapping is explicit and approved.

### M5-TASK-001 — Inventory Aman data and define reviewed field mapping

- **Status:** Not Started. **Priority:** Critical. **Tier:** Pro.
- **Objective/implementation:** Inventory `doctor_id,name,old_url,new_url,department,specialty,profile_status,review_status,redirect_status`; map only approved legacy values to registry fields; flag Dr. Kanso/wrong doctor, `1+`, testimonials, credentials/pronouns/contact/booking/staging issues.
- **Expected code/components:** versioned mapping configuration/fixtures and review report; no reusable-code hospital constants.
- **Dependencies:** M2-TASK-001/002, M6-TASK-001. **Controls:** authorized access; avoid copying private/unapproved content; human approval.
- **A11y/performance:** accessible tabular report; bounded discovery batches.
- **Edges/failures:** duplicate/missing identity, another doctor’s content, ambiguous term/URL; quarantine, never infer.
- **Tests/manual:** mapping fixture validation; editorial review of representative doctors including Ali A. Haydar.
- **Acceptance/DoD/references:** explicit reviewed source mapping exists; FR-041/042/044; ADR-0006/0007; spec §15.3.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M5-MOD-02 — Upload, map and dry run

**Purpose:** Validate imports before mutation. **Tier:** Pro. **Dependencies:** M5-TASK-001. **Deliverables:** secure upload/map/preview/dry-run UI. **Validation/DoD:** dry run writes nothing.

### M5-TASK-002 — Implement secure CSV validation, mapping, preview and dry run

- **Status:** Not Started. **Priority:** Critical. **Tier:** Pro.
- **Objective/implementation:** Validate capability/nonce/size/extension/MIME/encoding/headers/duplicate mappings/storage; bounded parsing; optional reviewed term creation; modes create/update/upsert.
- **Expected code:** `ImportExport/UploadValidator.php`, mapper/preview/dry-run services/controllers/admin UI; import job/item tables.
- **Dependencies:** M1 jobs/migrations, M5-TASK-001, M6-TASK-002. **Controls:** `manage_doctorpilot_migrations`; uploads outside public execution; no full rows in logs.
- **A11y/performance:** labeled mapping/errors; bounded memory/preview.
- **Edges/failures:** malformed/oversize/ambiguous CSV, unknown terms, formula cells, unauthorized upload.
- **Tests/manual:** malformed/valid/dry-run/security fixtures; upload-map-preview with zero canonical writes.
- **Acceptance/DoD/references:** dry run safe; FR-041/045/047; ADR-0007/0009; spec §§14.2–14.3,15.1.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M5-MOD-03 — Batched import, deduplication and recovery

**Purpose:** Execute restartable draft-only imports. **Tier:** Pro. **Dependencies:** M5-TASK-002. **Deliverables:** chunk worker, source/checksum idempotency, rollback. **Validation/DoD:** retry never duplicates.

### M5-TASK-003 — Implement resumable import execution and export safety

- **Status:** Not Started. **Priority:** Critical. **Tier:** Pro.
- **Objective/implementation:** checkpoint before next chunk; external source key+SHA checksum; created/updated/skipped/failed counts; draft/pending only; rollback only unpublished job-created records/mappings; CSV formula protection and permission-filtered export.
- **Expected code:** import job runner/item repository/rollback/exporter; cron/job hooks.
- **Dependencies:** M5-TASK-002. **Controls:** re-check capability/object access; transactional-safe checkpoints; sanitized safe errors.
- **A11y/performance:** progress/status understandable; bounded chunks/locks/retries, no autoload.
- **Edges/failures:** process death, duplicate delivery/source, changed checksum, partial term creation, rollback after publish.
- **Tests/manual:** AC-015/016, interruption/resume, duplicate, draft-only, rollback, formula injection; kill/restart worker.
- **Acceptance/DoD/references:** AC-015/016; FR-042/043/045/047; ADR-0007/0013; spec §15.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M5-MOD-04 — Redirects and URL migration

**Purpose:** Preserve SEO with exact mappings. **Tier:** Pro. **Dependencies:** M5-TASK-001/003. **Deliverables:** redirect validator/store/resolver/admin UI. **Validation/DoD:** invalid/guessed mappings never redirect.

### M5-TASK-004 — Implement explicit redirects and Aman URL cutover

- **Status:** Not Started. **Priority:** Critical. **Tier:** Pro.
- **Objective/implementation:** normalize unique same-site source paths; map to current doctor permalink; reject loops/chains/self/query-only/home fallback/staging/external/duplicates; 301 only when reviewed active; otherwise 404.
- **Expected code:** `Redirects/*`, redirects table/repository, admin controller/UI, request resolver, revalidation hooks.
- **Dependencies:** M5-TASK-001/003. **Controls:** migration capability+nonce; escape output; no open redirect.
- **A11y/performance:** accessible errors; indexed exact path lookup, no scan.
- **Edges/failures:** slug/base change, deleted/unpublished doctor, staging destination, conflicting source.
- **Tests/manual:** AC-017 plus exact mapping/404/revalidation; test `/doctor/{slug}/` against production-reviewed destination.
- **Acceptance/DoD/references:** AC-017; FR-044/053; ADR-0006; spec §§14.1,16.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## M5-MOD-05 — Operations and reconciliation

**Purpose:** Finish and monitor migration safely. **Tier:** Shared. **Dependencies:** M5-TASK-001–004. **Deliverables:** reports, bulk tools, retention, launch checklist. **Validation/DoD:** inventory reconciles and launch evidence exists.

### M5-TASK-005 — Build operational reports, reconcile migration and verify launch

- **Status:** Not Started. **Priority:** Critical. **Tier:** Shared.
- **Objective/implementation:** completeness/error/failed-job/redirect reports; Pro capability-checked bulk taxonomy/review/publish/duplicate; bounded retention; reconcile source/import/publish/redirect counts; invalidate caches; Aman canonical/sitemap/internal links/search/booking/404 checks.
- **Expected code:** `Admin/Operations/*`, report/bulk services, retention jobs, reconciliation/launch artifacts.
- **Dependencies:** M5-TASK-001–004, M3-TASK-004. **Controls:** reauthorize each item; no private row logs; no destructive reset.
- **A11y/performance:** accessible progress/errors; chunk large operations and cleanup.
- **Edges/failures:** partial launch, cache/CDN stale, unmatched URL, failed job, published rollback request.
- **Tests/manual:** bulk caps, retention, reconciliation, cache invalidation; execute documented Aman launch checklist.
- **Acceptance/DoD/references:** all legacy published URLs explicitly reviewed; FR-046/047; NFR-007/009; spec §§15–18,20.
- [ ] Implementation task
- [ ] Automated test
- [ ] Security verification
- [ ] Accessibility verification
- [ ] Manual verification
- [ ] Documentation updated

## Deferred scope

Guessed mappings, auto-publish, destructive site reset, external patient data, generic redirects unrelated to doctors.
