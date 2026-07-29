# Milestone 3 — Events, Jobs & Pro Operations

> Legacy planning reference superseded by the canonical six-milestone plan in `tasks/README.md`, primarily `milestone-5-migration-and-operations.md` and `milestone-6-pro-features.md`.

## Objective

Implement DoctorPilot’s event-driven reactions and asynchronous Pro operations: change notifications, cache/search rebuilding, lifecycle jobs, resumable imports, redirects, bulk work, and privacy-safe aggregate analytics.

“Events” here means internal WordPress/domain events and scheduled/background work. It does not introduce appointments, a calendar, patient events, or a new public event post type.

## Release and dependency context

- **Tier:** Free event infrastructure plus DoctorPilot Pro operations
- **Depends on:** Milestones 1 and 2
- **Blocks:** production migration and release
- **Primary requirements:** FR-041–FR-047, FR-053–FR-056, NFR-003–NFR-010
- **Primary acceptance criteria:** AC-015–AC-022

## Module 3.1 — Internal event contracts

### Deliverables

- Define typed/internal contracts for significant changes:
  - doctor saved/deleted;
  - publication changed;
  - relevant taxonomy or term metadata changed;
  - public settings changed;
  - booking capability changed;
  - import chunk/job completed;
  - redirect mapping changed; and
  - Pro enabled/disabled or compatibility changed.
- Adapt WordPress hooks into application-level event messages.
- Prevent recursion, duplicate processing, autosave/revision noise, and unbounded fan-out.
- Include safe correlation/job identifiers without private doctor data or request bodies.
- Keep event handlers idempotent where WordPress may deliver equivalent hooks more than once.

### Completion checks

- Saving once does not trigger infinite or duplicate rebuild loops.
- Logs identify the operation without credentials, nonces, CSV rows, or private fields.
- Event contracts do not become a second source of canonical data.

## Module 3.2 — Cache invalidation events

### Deliverables

- Increment cache generation after relevant doctor, taxonomy, settings, visibility, provider-capability, or completed migration changes.
- Avoid invalidation for irrelevant metadata and autosaves.
- Rebuild a single doctor’s derived search/sort/completeness state synchronously after a normal edit.
- Queue bounded rebuild batches for bulk taxonomy/settings/schema changes.
- Expose admin health for current generation, pending rebuilds, and failures.

### Completion checks

- Previously cached unpublished/changed data is not served after a visibility change.
- Repeated equivalent events remain safe.
- Background failures are actionable and do not corrupt canonical data.

## Module 3.3 — Scheduler and job runner

### Deliverables

- Use WordPress-safe scheduling/background mechanisms.
- Create bounded batches, locks/leases, checkpoints, retry limits, and stable job states.
- Make jobs resumable and idempotent.
- Prevent overlapping workers from processing the same item.
- Unschedule/stop plugin-owned work on deactivation without deleting content.
- Apply bounded retention cleanup for temporary imports, job logs, and Pro aggregate rows.
- Avoid autoloading job queues, logs, imports, or caches.

### Completion checks

- Interrupted work resumes at the stored checkpoint.
- Duplicate delivery does not duplicate doctors or increments.
- Deactivation stops processing and preserves recoverable job state.

## Module 3.4 — Pro database migrations

### Deliverables

- Install versioned Pro tables from `06-technical-spec.md` §14:
  - `bstdz_doctorpilot_redirects`
  - `bstdz_doctorpilot_import_jobs`
  - `bstdz_doctorpilot_import_items`
  - `bstdz_doctorpilot_analytics_daily`
- Use `$wpdb->prefix`, documented indexes, UTC timestamps where specified, and compatible `dbDelta()` SQL.
- Implement ordered idempotent forward migrations and schema verification.
- Keep Pro schema/version guards separate from Free canonical storage.
- Preserve Pro-only data when Pro is disabled.

### Completion checks

- Fresh install and upgrade-with-data tests produce the expected schema/indexes.
- Re-running migrations is safe.
- Incompatible Free/Pro base API versions stop Pro operations with an actionable notice and no fatal.

## Module 3.5 — CSV upload, mapping, preview, and dry run

### Deliverables

- Require `manage_doctorpilot_migrations` and a valid nonce.
- Validate extension, MIME/type, size, storage location, encoding, headers, duplicate mapped columns, and CSV structure.
- Parse using bounded memory and store temporary files outside public execution.
- Implement upload → map → preview → dry-run states.
- Require explicit permission to create unknown terms and retain review requirements.
- Return row-specific safe errors without persisting full rows/private data in logs.
- Define modes: create-only, update-by-source-key, and upsert.

### Completion checks

- Malformed, oversized, ambiguous, or unauthorized files are rejected.
- Dry run writes no doctors, terms, redirects, or analytics.
- Uploaded temporary data is deleted according to retention policy.

## Module 3.6 — Resumable import execution

### Deliverables

- Use external source key plus checksum for idempotency.
- Queue bounded chunks and persist the checkpoint before scheduling the next chunk.
- Import doctors only as draft/pending review.
- Track created, updated, skipped, and failed counts/items.
- Retry without duplicating successful source keys.
- Permit rollback only for unpublished records and mappings created by that job.
- Trigger derived-data rebuild and cache generation after safe completion.

### Completion checks

- AC-015 and AC-016 pass.
- Killing and restarting a worker resumes without duplication.
- No import path can auto-publish a doctor.

## Module 3.7 — Export and bulk operations

### Deliverables

- Export only fields the current user may access.
- Protect cells beginning with `=`, `+`, `-`, or `@` from formula execution.
- Add Pro bulk actions:
  - assign taxonomy;
  - set review state;
  - publish/unpublish with capability checks;
  - duplicate doctor; and
  - generate completeness report.
- Process large bulk selections in bounded jobs.
- Revalidate every doctor at execution time rather than trusting selected IDs/client state.

### Completion checks

- CSV formula-safety fixtures pass.
- Users cannot bulk publish or export private data beyond their capabilities.
- Bulk work invalidates/rebuilds derived state once through the event layer.

## Module 3.8 — Explicit redirect events and resolver

### Deliverables

- Store normalized same-site source paths mapped to reviewed doctor destinations.
- Reject duplicate sources, query-only sources, self-redirects, chains, loops, staging/external destinations, guessed mappings, and directory-home fallbacks.
- Resolve the destination from the current doctor permalink and revalidate after slug/base changes.
- Return a normal 404 if no explicit valid mapping exists.
- Report broken mappings to administrators.

### Completion checks

- AC-017 passes.
- A doctor slug/base change triggers mapping revalidation.
- No unknown legacy URL is redirected to the homepage or a guessed doctor.

## Module 3.9 — Aggregate analytics events

### Deliverables

- Keep analytics Pro-only, local by default, disabled until explicit administrator opt-in.
- Present collected dimensions, storage location, retention, and deletion controls before enabling.
- Accept only a strict approved aggregate event schema.
- Normalize events before storage and atomically increment daily aggregate keys.
- Store no IP, user ID, cookie ID, session ID, raw user-agent, or person-query association.
- Retain no raw event body.
- Return `204` for an accepted public aggregate event.
- Delete expired aggregate rows in bounded scheduled batches.

### Completion checks

- AC-018 and AC-019 pass.
- Disabled analytics creates no database rows or external tracking request.
- Table/schema tests prove prohibited identity columns are absent.

## Module 3.10 — Aman Hospital migration events

### Deliverables

- Use the reviewed inventory:
  `doctor_id, name, old_url, new_url, department, specialty, profile_status, review_status, redirect_status`.
- Apply the approved mapping in `06-technical-spec.md` §15.3.
- Flag wrong-doctor “Dr. Kanso” references, placeholder `1+` counters, copied testimonials, staging URLs, and unverified identity/credentials/pronouns/specialties/contact/booking.
- Import every profile as draft/pending review.
- Require human approval before publication and redirect activation.
- Explicitly map `/doctor/{slug}/` to the reviewed current DoctorPilot permalink.
- Reject `amanhospital.tempurl.host` from final canonical and redirect destinations; verify `amanhospital.org` at launch.

### Completion checks

- Every published legacy doctor URL has one reviewed destination.
- No migration record auto-publishes.
- The Dr. Ali A. Haydar reference fixture satisfies the migration acceptance criteria in the task/release documentation.

## Milestone acceptance

- AC-015–AC-022 pass.
- Import dry run, malformed CSV, idempotency, resume, draft-only, rollback, export formula safety, redirects, analytics opt-in/default-off, retention, schema upgrades, deactivation, and uninstall tests pass.
- Job concurrency, retry, logging privacy, bounded processing, and cache invalidation are verified.
- Aman migration dry run and reviewed sample migration are documented before production execution.
- Documentation, schema versions, base API compatibility, readmes, and changelogs agree.

## Explicitly deferred

Native appointments, patient accounts, identifiable search history, remote telemetry, automatic diagnosis, AI-generated clinical relationships, and guessed migration mappings.
