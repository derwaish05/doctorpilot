# Task 4 — SEO, Pro Migration & Release

> Legacy planning reference. The canonical implementation sequence is indexed in `tasks/README.md`; use `milestone-5-migration-and-operations.md` and `milestone-6-pro-features.md` for executable work.

**Covers development phases:** SEO, Pro import/redirects/analytics, Aman migration, QA, and release
**Tier:** DoctorPilot Free MVP release plus separate DoctorPilot Pro v1 add-on
**Goal:** Complete the Free SEO/release contract, migrate Aman Hospital safely with Pro tooling, and ship separately packaged compatible releases.
**Depends on:** Tasks 1–3
**Blocks:** launch
**Requirements:** FR-041–FR-056, NFR-001–NFR-010
**Acceptance criteria:** AC-015–AC-024

## Scope

### Free SEO and lifecycle

- Resolve a single owner for canonical, Open Graph, breadcrumbs, schema, and sitemap behavior across DoctorPilot, WordPress core, and supported SEO plugins.
- Emit or integrate Physician and MedicalOrganization structured data using reviewed public fields only; never duplicate an artifact owned by another provider.
- Keep search/filter URLs and REST endpoints out of XML sitemaps and apply appropriate noindex/canonical behavior. Enable taxonomy archives only when templated and useful.
- Derive public URLs and canonical host from site configuration/current permalink APIs; never hardcode Aman Hospital into reusable plugin code.
- Complete WordPress.org packaging, privacy text, translations, accessibility/security/performance verification, upgrade/deactivation/uninstall testing, screenshots/assets, documentation, demo fixtures, and changelog/version consistency.

### DoctorPilot Pro import, export, redirects, bulk tools, and analytics

- Implement the Pro tables and versioned migrations in `06-technical-spec.md` §14.
- Implement the resumable pipeline: upload → validate → map → preview → dry run → enqueue chunks → import draft/pending → review → approve/publish → redirects → launch verification.
- Validate file capability, nonce, size, extension/MIME, encoding, headers, structure, and storage. Parse with bounded memory and delete temporary uploads after retention.
- Support create-only, update-by-source-key, and upsert. Use source key plus checksum for idempotency; checkpoint each chunk; report row-level safe errors without storing full private rows.
- Permit rollback only for unpublished records and mappings created by that job. Imports never auto-publish.
- Protect exports from CSV formula injection and expose only fields the current user may access.
- Add explicit one-to-one redirect management. Normalize same-site source paths and reject duplicates, chains, loops, guessed mappings, home-page fallbacks, query-only sources, staging destinations, and external destinations.
- Add Pro bulk taxonomy assignment, review-state changes, capability-checked publish/unpublish, duplicate doctor, and completeness reporting.
- Add opt-in local daily aggregate analytics, default off, with disclosure, configurable retention, bounded deletion, and no IP, user/session/cookie ID, raw user agent, or person-query association.

### Aman Hospital migration and editorial review

- Use the reviewed inventory: `doctor_id, name, old_url, new_url, department, specialty, profile_status, review_status, redirect_status`.
- Map legacy content only through `06-technical-spec.md` §15.3 and human-reviewed source material.
- Flag/remove wrong-doctor “Dr. Kanso” content, placeholder `1+` counters, copied or unsupported testimonials, staging URLs, and unverified credentials/pronouns/specialties/contact/booking data.
- Import every profile as draft/pending review. Require authorized human approval before publication and redirect activation.
- Map `/doctor/{slug}/` to an explicit reviewed current DoctorPilot permalink. Imports may preserve a reviewed `dr-...` slug; the reusable plugin does not force it.
- For production, verify `amanhospital.org` canonicals and reject `amanhospital.tempurl.host` from canonicals and redirect destinations.

## Aman reference acceptance criteria — Dr. Ali A. Haydar

- The new draft contains all and only approved information from the legacy source, in canonical structured fields.
- Wrong Dr. Kanso/testimonial content and placeholder counters are absent; identity, credentials, pronouns, specialty, contact, and booking receive human approval.
- Once published, search finds the doctor by approved name, specialty, procedure, and language fixtures.
- Cards and profiles resolve the correct current permalink and the configured safe booking action works.
- The exact reviewed legacy path redirects with 301 to the current production doctor permalink; no staging-domain link remains.
- Exactly one production canonical is emitted, the profile appears in the XML sitemap, and native/Elementor desktop and mobile views use canonical DoctorPilot data.

## Release acceptance criteria

- Free remains independently useful and contains no remotely unlocked Pro implementation; Pro is a separate compatible add-on.
- Free continues without Pro. Pro reports missing/incompatible base versions safely. Disabling Pro preserves Pro-only data while Free canonical data/output remain valid.
- All approved legacy published URLs have explicit reviewed destinations; no migrated profile auto-publishes.
- The approved 1,000-doctor search fixture meets p95 ≤ 500 ms excluding network time and the report records environment/query count.
- Core patient workflows have no known serious/critical accessibility or security defect.
- Versions, schema migrations, rollback limitations, readmes, and changelogs agree.
- Sitemap/canonicals/internal links/redirects/caches/search/booking are verified at launch; 404s and redirect loops are monitored afterward.

## Verification

- Unit/integration/contract tests covering SEO ownership, structured data, public privacy, schema upgrades, import dry run/idempotency/resume/draft-only/rollback, formula safety, redirects, analytics default-off/aggregation/retention, and lifecycle.
- Compatibility matrix for supported WordPress/PHP, themes, Elementor absent/Free/Pro, DoctorPilot Pro absent/compatible/incompatible, and supported SEO owners.
- Search accuracy, performance, frontend URL/history/no-JS, accessibility automation, and manual keyboard/screen-reader tests.
- Fresh install, upgrade with existing data, deactivation, both uninstall settings, multisite bounds, release-package Plugin Check, PHPCS, PHP/JS checks, translations, and asset/license review.
- Manual launch checklist: replace staging URLs, verify one canonical/schema owner, activate only reviewed redirects, regenerate/submit sitemap, update internal links, clear relevant WordPress/CDN caches, test search/profile/booking, and monitor 404s/loops.
- State exactly what ran, what was not run, blockers, and residual risk.

## Docs to sync

`01-product-vision.md`, `02-requirements.md`, `03-architecture.md`, `04-decisions.md` for durable changes, `05-ai-development-rules.md` if workflow rules change, `06-technical-spec.md`, Free and Pro `README.md`/`readme.txt`/`CHANGELOG.md`, and any roadmap, design-system, or release documents that actually exist.
