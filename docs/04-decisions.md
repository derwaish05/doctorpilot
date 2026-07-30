# 04 — Architecture Decision Records

Each ADR records a durable decision. A later ADR supersedes an accepted decision; implementation must not silently rewrite history.

## ADR-0001 — Store doctors as structured data

- **Status:** Accepted — 2026-07-27 (partially superseded by ADR-0017 for reusable library content)
- **Context:** Page-builder-owned profiles make search, reuse, migration, and validation unreliable.
- **Decision:** Store doctors as a dedicated WordPress post type with taxonomy relationships and registered structured metadata. Rendering systems consume this data but do not own it.
- **Alternatives:** Elementor-authored pages; generic pages plus ad hoc custom fields; third-party directory as system of record.
- **Consequences:** One edit can propagate everywhere. DoctorPilot must maintain a field registry, serializers, migration path, and rendering integrations.

## ADR-0002 — Directory and discovery first; booking is an adapter

- **Status:** Accepted — 2026-07-27
- **Context:** Native scheduling would expand v1 into clinic management.
- **Decision:** DoctorPilot resolves safe booking calls to action through providers. It stores no appointments or patient records in v1.
- **Alternatives:** Native appointments; one hard-coded booking integration.
- **Consequences:** Smaller, clearer scope and a stable BookPilot integration boundary. Verified availability cannot be promised without provider support.

## ADR-0003 — Keep Symptom, Condition, and Treatment separate

- **Status:** Accepted — 2026-07-27
- **Context:** Patient experiences, diagnoses, and interventions are medically and editorially distinct.
- **Decision:** Model them as separate prefixed taxonomies. Symptom-to-department/specialty relationships are explicitly curated.
- **Alternatives:** One “medical” taxonomy; free-text tags; automatically generated relationships.
- **Consequences:** Clearer search and SEO; more editorial work. Results require a non-diagnostic disclaimer.

## ADR-0004 — REST search with canonical URL state and server fallback

- **Status:** Accepted — 2026-07-27
- **Context:** Patients need fast filtering, shareable results, browser history, and resilience.
- **Decision:** Use public GET REST endpoints for progressive enhancement, encode normalized state in the URL, and render the same query server-side without JavaScript.
- **Alternatives:** Full-page-only search; `admin-ajax.php`; client-only SPA.
- **Consequences:** Better UX and accessibility. Requires bounded inputs, cache rules, history tests, and noindex control for search/filter combinations.

## ADR-0005 — Product name and code identity

- **Status:** Accepted for product name; directory slug reservation pending — 2026-07-27
- **Context:** The name must fit the BrainStudioz product family without making unsupported AI or Elementor trademark claims.
- **Decision:** Use **DoctorPilot – Doctor Directory & Search**, text domain `doctorpilot`, namespace `BrainStudioz\DoctorPilot`, and procedural prefix `bstdz_doctorpilot_`. Preferred WordPress.org slug is `doctorpilot`; fallback is `doctorpilot-directory`.
- **Alternatives:** Doctor Directory Manager; Find a Doctor Directory; Physician Directory Search.
- **Consequences:** Availability is final only when WordPress.org accepts/reserves the slug. “Elementor” may appear descriptively, not as the leading product identity.

## ADR-0006 — Separate reusable permalink policy from deployment migrations

- **Status:** Accepted — 2026-07-27
- **Context:** Aman Hospital needs `/find-a-doctor/dr-{slug}/`, but a reusable plugin must not hardcode one hospital’s host or force a `dr-` prefix.
- **Decision:** Default public base is `find-a-doctor`; the WordPress post slug remains explicit/editorial. Base changes are controlled settings. Aman preserves its reviewed `dr-` destinations through explicit mappings.
- **Alternatives:** Force `dr-` for every install; keep `/doctor/`; hardcode the Aman domain.
- **Consequences:** Clean reusable behavior and safe reference migration. Rewrite settings require validation, a one-time flush, and redirect planning.

## ADR-0007 — Migration imports as unpublished, review-required content

- **Status:** Accepted — 2026-07-27
- **Context:** Legacy content may contain copied testimonials, placeholder counters, wrong pronouns, or outdated booking data.
- **Decision:** Imports create/update draft or pending profiles and a separate review state. Only a capable human publisher may publish.
- **Alternatives:** Auto-publish; manual re-entry.
- **Consequences:** Safer data quality with an additional workflow step. Import must provide completeness, row errors, and review reporting.

## ADR-0008 — Free/Pro split must not gate safety or duplicate ownership

- **Status:** Accepted — 2026-07-27
- **Context:** A freemium product needs a clear upgrade path and WordPress.org-compliant Free value.
- **Decision:** Free owns canonical data, core search/rendering, essential SEO, external booking, security, privacy, and accessibility. Pro extends locations, advanced filters, BookPilot, migration tools, aggregate analytics, advanced Elementor integration, and workflow controls.
- **Alternatives:** Paid-only; search locked behind Pro; Pro fork of the Free codebase.
- **Consequences:** Free remains complete. Pro must register extensions, declare compatibility, and fail gracefully without a compatible base.

## ADR-0009 — No identifiable patient or medical-search data

- **Status:** Accepted — 2026-07-27
- **Context:** A directory does not need patient identity, and symptom searches may be sensitive.
- **Decision:** Store no patient records and no person/session-linked search history. Optional analytics use daily aggregates only and are disabled until administrator opt-in.
- **Alternatives:** Full search sessions; cookies for favourites; patient accounts.
- **Consequences:** Lower privacy and compliance risk. Patient features require a separate product and privacy/security ADR.

## ADR-0010 — Prefix all internal WordPress identifiers

- **Status:** Accepted — 2026-07-27
- **Context:** Generic keys such as `doctor`, `department`, and `specialty` can collide with themes or other plugins.
- **Decision:** Use prefixed internal post-type, taxonomy, meta, option, transient, cron, handle, table, capability-group, and hook identifiers. Public rewrite slugs remain clean and configurable.
- **Alternatives:** Keep generic internal keys; detect conflicts at runtime.
- **Consequences:** Safer coexistence. If implementation already exists with generic keys, a versioned migration and compatibility alias plan is required.

## ADR-0011 — Elementor is optional; Pro-specific APIs are guarded

- **Status:** Accepted — 2026-07-27
- **Context:** Core Elementor widgets can be provided by an add-on, but active dynamic tags and Theme Builder conditions depend on Elementor Pro.
- **Decision:** DoctorPilot works through native templates/shortcodes without Elementor. Core widgets load with compatible Elementor. Complete dynamic tags and custom Theme Builder conditions load only with compatible Elementor Pro.
- **Alternatives:** Require Elementor; advertise Theme Builder without dependency guards.
- **Consequences:** Wider compatibility and no fatal errors. UI/documentation must clearly label Elementor Pro-dependent features.

## ADR-0012 — Native canonical storage with rebuildable search derivatives

- **Status:** Accepted — 2026-07-27 (§5.2 repeater treatment of procedures/education/publications/FAQs superseded by ADR-0017)
- **Context:** WordPress-native storage is portable, while complex discovery requires normalized values and caching.
- **Decision:** Posts/terms/meta remain canonical. Completeness, normalized search documents, sort values, and response caches are derived and rebuildable. Initial search uses optimized native queries plus bounded application scoring.
- **Alternatives:** Custom search table from day one; external search service; raw unbounded `WP_Query`.
- **Consequences:** Lower initial complexity and good portability. A dedicated index requires benchmark evidence and a new ADR.

## ADR-0013 — Publication status and review status are separate

- **Status:** Accepted — 2026-07-27
- **Context:** An editable `profile_status=published` duplicates WordPress post status and can drift.
- **Decision:** WordPress `post_status` controls public visibility. A prefixed review-status meta value and computed completeness score support editorial workflow.
- **Alternatives:** Custom publication field; custom post statuses for every review state.
- **Consequences:** Public queries reliably use `post_status=publish`; admin workflow remains expressive without conflicting truth.

## ADR-0014 — Capabilities are primary; custom roles are optional presets

- **Status:** Accepted — 2026-07-27
- **Context:** Sites already have different role models, and role-name checks are brittle.
- **Decision:** Authorize through custom mapped capabilities and object/department scope. Optional Pro role presets grant those capabilities but business logic never checks a role name.
- **Alternatives:** Hard-code Administrator/Editor; create roles unconditionally.
- **Consequences:** Better interoperability. Installation, upgrade, and uninstall must manage capabilities idempotently.

## ADR-0015 — One owner per SEO artifact

- **Status:** Accepted — 2026-07-27
- **Context:** DoctorPilot, WordPress core, and SEO plugins can emit duplicate canonicals, schema, breadcrumbs, or sitemap entries.
- **Decision:** Resolve an SEO owner for each artifact. DoctorPilot supplies reviewed data and emits only what the active owner does not already handle.
- **Alternatives:** Always output DoctorPilot metadata; disable SEO integrations.
- **Consequences:** Cleaner markup and fewer conflicts. Compatibility tests are required for WordPress core plus the supported SEO plugins.

## ADR-0016 — Analytics are aggregate, opt-in, and local by default

- **Status:** Accepted — 2026-07-27
- **Context:** WordPress.org requires consent for tracking, and medical searches deserve stricter minimization.
- **Decision:** Pro analytics are disabled by default, stored locally as daily aggregates, and never include identity/session fields. External telemetry requires a future separate consent decision.
- **Alternatives:** Raw event logs; third-party analytics by default; no analytics.
- **Consequences:** Limited journey analysis but substantially lower privacy risk and clearer retention/deletion behavior.

## ADR-0017 — Reusable global content library (hybrid CPT + local repeater)

- **Status:** Accepted — 2026-07-28
- **Implementation status:** Implemented — all five types shipped. Experience & Education landed 2026-07-29 (schema version 2); Procedures, Publications, and FAQs landed 2026-07-30 via a data-driven `LibraryPostTypes::schema()`, reusing each type's existing doctor repeater as its local store (no migration).
- **Amendment (2026-07-29):** Experience & Education is implemented as **two** separate CPTs — `doctorpilot_exp` (Experience) and `doctorpilot_edu` (Education) — instead of the single combined `doctorpilot_edu_exp` originally proposed. Rationale: the two lists are surfaced under distinct headings and carry different label semantics (Experience: position/organization; Education: qualification/institution), so a shared record type forced an ambiguous `type` discriminator. The two CPTs share one field schema — `primary` (position or qualification), `secondary` (organization or institution), `start_year`, `end_year`, `country` (all prefixed `_doctorpilot_lib_*`), plus `description` in `post_content`. Doctors reference them through ordered ID lists `_doctorpilot_exp_ids` / `_doctorpilot_edu_ids` and keep doctor-specific rows in `_doctorpilot_exp_local` / `_doctorpilot_edu_local`. The reverse-reference index stores assigned doctor IDs in `_doctorpilot_lib_doctors` on each record. Because Experience/Education are **not** part of the search document, global edits reflect live through the resolver with no derived-data rebuild; only a small `before_delete_post` cleanup is needed. Schema-version-2 migration splits any legacy `_doctorpilot_education_experience` rows into the two local repeaters (rows typed education/fellowship/residency/training/degree/qualification → Education; all others → Experience). Procedures, Publications, and FAQs will follow the original single-CPT-per-type plan when implemented.
- **Context:** Procedures, Experience & Education, Publications, and FAQs were stored only as per-doctor array-meta repeaters (ADR-0001/ADR-0012 §5.2). Hospitals need these items to be reusable across many doctors and editable centrally, while still allowing independent doctor-specific entries. A repeater-only model cannot share or centrally update a record, and duplicating identical repeater rows across doctors is unmaintainable.
- **Decision:** Introduce a **hybrid** model for these four content types. Each becomes a reusable, prefixed **Free custom post type** — `doctorpilot_proc`, `doctorpilot_edu_exp`, `doctorpilot_pub`, `doctorpilot_faq` (all ≤ 20 characters, the WordPress post-type limit) — whose `post_status` is the publication authority. Doctors reference global records through ordered, unique ID lists in prefixed meta (`_doctorpilot_{proc|edu_exp|pub|faq}_ids`) **and** retain the existing repeater meta for doctor-specific ("local") items. A single shared `DoctorContentResolver` merges globals (published only, in public context) with local items into one ordered list (globals first, then locals), tagging each item's origin in admin context only. All consumers — native templates, search-document builder, single REST response, and the Elementor Doctor Profile widget — read through this one resolver. Global records are not publicly queryable on their own; they surface only through a doctor. A rebuildable reverse-reference index (`_doctorpilot_assigned_doctor_ids` on each global) enables O(1) impact lookup; a global edit/unpublish/delete/merge increments cache generation immediately and enqueues bounded rebuilds of affected doctors' derived search documents. Assignment lists de-duplicate to unique IDs; global creation blocks exact normalized-key duplicates (override requires `manage_doctorpilot_library`, audit-logged).
- **Scope of supersession:** This supersedes the treatment of Procedures, Experience & Education, Publications, and FAQs as doctor-only repeaters in ADR-0001 and ADR-0012 §5.2. All other doctor fields remain canonical doctor metadata. ADR-0013 (post_status is publication authority) and ADR-0010 (prefixed identifiers) continue to apply, now also to the library CPTs. Because this decision is Accepted, the superseded portions of ADR-0001/ADR-0012 are marked accordingly; the remainder of those ADRs stands.
- **Alternatives:** (a) Keep repeater-only — rejected: no reuse or central update. (b) Taxonomy terms + term meta — rejected: thin, awkward for rich multi-field records and ordering. (c) All-CPT (local items also become posts) — rejected: heavy, and complicates independence/cleanup of one-off local notes.
- **Consequences:** Reusable, centrally-updatable records with preserved doctor-specific independence and full backward compatibility (existing repeaters become local items with no data loss). Adds four managed CPTs, reference storage, a resolver, a reverse-reference index with cache-generation/rebuild invalidation, an assignment UI, and one authenticated internal search route. Requires defined lifecycle behavior for edit/unpublish/trash/delete/merge and duplicate prevention on both assignment and creation. Full design in `11-global-content-library.md`; requirements FR-058–FR-063 and AC-025–AC-035; tech-spec §5.4/§7/§10–11.
