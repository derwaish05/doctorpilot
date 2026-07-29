# Milestone 2 — Application Services

> Legacy planning reference superseded by the canonical six-milestone plan in `tasks/README.md`, primarily `milestone-2-doctor-directory.md` and `milestone-3-search-and-frontend.md`.

## Objective

Implement the shared application services that turn canonical doctor data into secure public views, deterministic discovery, safe booking actions, SEO artifacts, and native WordPress output.

## Release and dependency context

- **Tier:** DoctorPilot Free, with versioned extension contracts for Pro
- **Depends on:** Milestone 1
- **Blocks:** Milestones 3 and 4
- **Primary requirements:** FR-015–FR-030, FR-037–FR-040, FR-048–FR-052
- **Primary acceptance criteria:** AC-002, AC-004–AC-010, AC-013–AC-014, AC-023–AC-024

## Module 2.1 — Doctor read/write services

### Deliverables

- Create domain IDs/value objects for doctor and location references.
- Implement application commands/handlers for validated doctor writes.
- Implement a canonical doctor reader and public doctor view-model factory.
- Apply field visibility through `FieldRegistry`; never serialize raw meta.
- Centralize primary taxonomy, location-limit, email-visibility, prefix, review, and publication invariants.
- Emit internal change notifications for derived-data rebuilding and cache invalidation.

### Completion checks

- Admin, REST, native templates, Elementor, search, schema, and migration can reuse the same services.
- Private fields remain absent from every public view model.
- Published visibility is derived solely from WordPress post status.

## Module 2.2 — Search request and normalization

### Deliverables

- Implement one immutable normalized search request.
- Support Free modes: `name`, `department`, `specialty`, `symptom`, and `universal`.
- Support Free filters: department, specialty, symptom, gender, language, accepting-new, and online.
- Reserve Pro filters: location, insurance, condition, treatment, consultation type, date/schedule, and availability.
- Bound query length, term counts, page size, page offsets, and allowed sort/filter values.
- Preserve Unicode and apply approved accent removal/case folding without requiring transliteration.
- Match only reviewed synonyms and curated clinical relationships.
- Normalize equivalent parameter sets to the same canonical order and cache key.

### Completion checks

- Invalid values produce stable field-specific errors.
- Unknown/unapproved clinical relationships do not affect results.
- Equivalent requests serialize to one canonical URL state.

## Module 2.3 — Search candidate and ranking service

### Deliverables

- Apply publication visibility and taxonomy filters before ranking.
- Retrieve bounded candidates without hydrating all doctor profiles.
- Implement the scoring contract from `06-technical-spec.md` §8.2.
- Apply deterministic tie order: score, featured, manual order, normalized name, and post ID.
- Support Free sorting: relevance, name, featured, experience, and recent.
- Reserve earliest verified availability sorting for Pro.
- Make configured ranking-weight extensions versioned and incapable of bypassing privacy or publication.

### Completion checks

- Ranking fixtures cover exact name, prefixes/tokens, primary/assigned terms, direct symptoms, curated relationships, clinical keywords, featured boost, and ties.
- Search returns only published doctors.
- The approved accuracy fixture reaches the documented target.

## Module 2.4 — Facets, suggestions, and no-result service

### Deliverables

- Calculate bounded facets for enabled public filters.
- Implement bounded suggestions after the configured minimum query length.
- Use only doctor names, curated synonyms, approved terms, and reviewed relationships.
- Return configured featured doctors and hospital contact/emergency content for no-result states where appropriate.
- Never infer diagnosis, urgency, treatment, or unverified availability.

### Completion checks

- Suggestions are bounded and public-safe.
- No-result output is non-diagnostic and remains useful without Pro analytics.
- Facet counts correspond to the normalized request policy.

## Module 2.5 — Public REST API

### Deliverables

- Register namespace `doctorpilot/v1`.
- Implement:
  - `GET /doctors`
  - `GET /doctors/suggest`
  - `GET /doctors/{id}`
  - `GET /facets`
- Give every route explicit methods, permission callback, argument schema, validation, sanitization, and stable errors.
- Keep public cacheable GET routes nonce-free.
- Serialize published allowlisted view models using the response envelope in `06-technical-spec.md` §7.
- Return stable request IDs without exposing SQL, paths, secrets, stack traces, or private data.
- Apply bounded abuse controls that do not permanently block legitimate shared networks.

### Completion checks

- Public/private REST fixture tests pass.
- Unpublished doctors and private fields never enter responses or caches.
- Error contracts use the documented stable codes and HTTP statuses.

## Module 2.6 — Cache and derived-data services

### Deliverables

- Use `bstdz_doctorpilot_cache_generation` as a monotonic generation.
- Include API schema version, generation, locale, normalized request, and relevant capability state in public keys.
- Cache only published public-safe view models.
- Keep correctness independent of persistent object caching or TTL expiry.
- Rebuild one doctor synchronously after a normal edit.
- Expose bounded background rebuilding for bulk changes.
- Never place previews, imports, secrets, provider internals, or private fields in public caches.

### Completion checks

- Doctor/taxonomy/settings/visibility changes invalidate affected derived state.
- Public and authenticated preview cache keys cannot collide.
- Performance tests record query count plus p50/p95.

## Module 2.7 — Booking service

### Deliverables

- Implement `BookingProviderInterface`, `BookingCapabilities`, and `BookingCta`.
- Implement Free providers:
  - external URL/hospital booking page;
  - telephone;
  - enquiry.
- Validate configured input and resolved provider output.
- Support only `_self` and `_blank` targets and approved URL/phone schemes.
- Use only an explicitly configured safe fallback.
- Reserve location-aware resolution and `BookPilotProvider` for Pro.
- Never claim verified availability unless a compatible provider explicitly supplies it.

### Completion checks

- Unsafe schemes and malformed destinations are rejected.
- Provider failures render only an explicit safe fallback.
- Provider credentials/raw payloads never enter view models, caches, or logs.

## Module 2.8 — Native frontend services

### Deliverables

- Implement shortcodes:
  - `[doctorpilot_search]`
  - `[doctorpilot_directory]`
  - `[doctorpilot_profile id=""]`
- Implement theme override lookup under `doctorpilot/`.
- Render accessible doctor cards, grid/list results, result count, pagination, filters, chips, mobile drawer, loading/error/empty states, and A–Z symptoms.
- Render only non-empty single-doctor sections in the order from `06-technical-spec.md` §11.
- Use the same normalized request and services for JavaScript/REST and server-rendered GET fallback.
- Encode filter, sort, view, and page state in the URL and restore it on reload/back/forward.
- Debounce suggestions and cancel/ignore stale requests.

### Completion checks

- Native output works with Elementor absent.
- Keyboard and screen-reader flows cover filters, drawer, chips, results, pagination, profile, and booking.
- Filter combinations are not exposed as indexable duplicate pages.

## Module 2.9 — SEO ownership service

### Deliverables

- Implement `SeoOwner` resolution for DoctorPilot, WordPress core, and supported SEO plugins.
- Ensure exactly one owner emits canonical, Open Graph, breadcrumbs, schema, and sitemap behavior.
- Build Physician and MedicalOrganization structured data from reviewed public fields only.
- Keep REST and filter URLs out of XML sitemaps.
- Enable taxonomy indexing only for enabled, templated, useful archives.
- Derive hosts and permalinks from site configuration; do not hardcode Aman Hospital.

### Completion checks

- Core, Yoast, and Rank Math ownership fixtures emit no duplicate canonical/schema artifacts.
- Canonicals never point to staging through reusable-code defaults.
- Structured data validation is included in release QA.

## Module 2.10 — Service extension API

### Deliverables

- Publish and document versioned hooks:
  - `doctorpilot/fields/register`
  - `doctorpilot/search/request`
  - `doctorpilot/search/weights`
  - `doctorpilot/search/result`
  - `doctorpilot/booking/providers`
  - `doctorpilot/templates/locations`
  - `doctorpilot/public/doctor_view`
- Define argument, return, error, compatibility, privacy, and authorization contracts.
- Reject extensions that expose private fields, bypass validation, or alter publication authority.

### Completion checks

- Contract tests cover valid and invalid extensions.
- DoctorPilot Pro can extend services without duplicating Free ownership.

## Milestone acceptance

- AC-002, AC-004–AC-010, AC-013–AC-014, AC-023, and AC-024 pass.
- REST, search, booking, caching, native frontend, SEO, privacy, accessibility, and performance tests pass.
- The 1,000-doctor benchmark records its environment, query count, p50, and p95; target REST p95 is at most 500 ms excluding network time.
- Manual desktop/tablet/mobile, keyboard, screen-reader, theme override, no-JavaScript, and supported SEO-owner smoke tests are recorded.
- Documentation and changelog are synchronized.

## Explicitly deferred

Advanced Pro filters/widgets, complete dynamic tags, BookPilot, multiple locations, migration jobs, redirects, analytics storage, and native appointments.
