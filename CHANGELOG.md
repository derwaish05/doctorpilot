# Changelog

All notable changes to DoctorPilot are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/) and the project uses semantic
versioning.

## [Unreleased]

### Fixed
- Translation loading no longer runs before `init` (WordPress 6.7
  `_load_textdomain_just_in_time` notice). Both plugins load their text domains on
  `init`, and the Pro add-on registers with the base using an untranslated brand
  name so no `__()` call fires during the early `plugins_loaded` boot.

### Changed
- Doctor editor: reworked the tabbed meta box to the native WordPress admin tab
  pattern (active tab merges into the panel, grey inactive tabs with hover),
  added per-tab count badges and a pane header (title, help, and section meta).
  Styling moved to the enqueued stylesheet; markup escaped and internationalized.
- Doctor editor fields redesigned to match the approved mockup: taxonomy terms are
  now selectable chips (blue when selected) with a live "N selected" count and an
  empty-taxonomy "Add terms" link; repeaters gained numbered rows, a drag handle
  (jQuery UI sortable, indices renumber on reorder/add/remove), a red Remove link,
  and an "Add row" action with an inline hint; checkboxes render as title + help
  flags; private fields show a "Private" badge; SEO title/description have live
  character counters and a Google-style search preview. Admin CSS/JS now
  cache-bust by file modification time so edits apply immediately.
- Doctor name is now entered as separate First name / Last name fields; the post
  title is composed from them (via `wp_insert_post_data`, so no recursive save),
  the default title box is removed, and existing doctors pre-fill their name parts
  from the current title. Leadership positions is now a simple one-per-line
  textarea (older structured rows are flattened for display on first edit).

### Design / documentation
- **Global content library (ADR-0017, accepted; implementation gated)** — approved
  design for reusable global Procedures, Experience & Education, Publications, and
  FAQs (Free CPTs `doctorpilot_proc`/`doctorpilot_edu_exp`/`doctorpilot_pub`/`doctorpilot_faq`)
  with per-doctor ordered references, retained doctor-specific local repeaters, a
  single `DoctorContentResolver`, a rebuildable reverse-reference index with
  cache-generation/rebuild invalidation, an authenticated `GET /library/search`
  route, computed read-only education titles, tightened duplicate handling, and
  Free blast-radius visibility. Documented in `docs/11-global-content-library.md`,
  ADR-0017, FR-058–FR-063, AC-025–AC-035, tech-spec §5.4/§7/§10–11, and
  `tasks/milestone-7-global-content-library.md`. No code yet.

### Added
- **Milestone 1 — Foundation** (Free):
  - `doctorpilot.php` bootstrap with version/runtime/path constants and a
    direct-access guard.
  - Runtime guard enforcing WordPress 6.4+ and PHP 8.1+ with actionable admin
    notices; activation is refused safely on unsupported runtimes.
  - Lifecycle: ordered idempotent activation (version seeding, migrations,
    capability grant, maintenance scheduling, one-time rewrite flush),
    data-preserving deactivation, and opt-in `uninstall.php`.
  - PSR-4 autoloader mapping `BrainStudioz\DoctorPilot\` to `includes/`, with the
    approved Domain/Application/Infrastructure/Support package layout.
  - Versioned `ExtensionRegistry` for guarded Pro/add-on registration against a
    base API version.
  - Capability primitives and idempotent administrator grant
    (`Support/Capabilities.php`).
  - Versioned `bstdz_doctorpilot_settings` object with defaults, range/enum
    validation, and unknown-key discarding (`Admin/Settings.php`).
  - REST foundation (`doctorpilot/v1`) with an explicit-permission route
    registrar and an admin-only health route.
  - Single admin navigation owner with Health and Settings screens
    (`Admin/Menu.php`).
  - Observability: redacting logger + interface and a stable `ErrorFactory`.
  - Ordered, idempotent schema `Migrator` with a version-1 baseline.
  - Quality gates: `composer.json`, `phpcs.xml.dist`, `phpunit.xml.dist`, test
    bootstrap, and initial settings/lifecycle tests.

- **Milestone 2 — Doctor Directory** (Free):
  - `doctorpilot_doctor` custom post type (`show_in_rest` false, mapped
    capabilities, `/find-a-doctor/{post_name}/` permalinks, nested under the
    single DoctorPilot admin menu).
  - Seven prefixed Free taxonomies (Department, Specialty, Symptom, Condition,
    Treatment, Language, Location) with Symptom/Condition/Treatment kept
    separate; curated Symptom term metadata with capability + nonce checks.
  - Canonical `FieldRegistry` (single schema authority) with recursive
    `Sanitizer`, private meta registration, bounded repeaters, and primary-term
    invariants; derived completeness, search document, and sort name.
  - Guided tabbed doctor editor with review/completeness side panel, media
    selection, and a nonce + capability-guarded save through the registry
    (`Application/Doctors/SaveDoctor`).
  - Free booking adapters (External URL, Phone, Enquiry) behind
    `BookingProviderInterface` with a safety-revalidated `BookingResolver` and
    immutable `BookingCta`; single Free location record with public-email
    consent.
  - Public allowlisted view model (`DoctorView`), native single/archive/card
    templates with theme overrides, `[doctorpilot_directory]` and
    `[doctorpilot_profile]` shortcodes, and conditionally-loaded frontend CSS.
  - Single SEO owner (`SeoOwner`) that yields to Yoast/Rank Math/AIOSEO/SEOPress
    and otherwise emits canonical, meta description, Open Graph, per-doctor
    indexing, and Physician + MedicalOrganization JSON-LD (`Schema`).

- **Milestone 3 — Search & Frontend** (Free):
  - Immutable, bounded `SearchRequest` with a shared factory/normalizer
    (Unicode-safe, prefix-stripping) used by both REST and the GET fallback.
  - Deterministic `Ranker` implementing the documented score tiers and tie
    order, fed by a rebuildable `SearchIndexBuilder` (normalized name/specialty/
    department/symptom/condition/keyword buckets) so scoring needs no per-request
    term queries; `CandidateRepository` applies visibility + filters before
    scoring.
  - `SearchService` (pagination + active-filter chips) plus `SuggestionService`,
    `FacetService`, and a non-diagnostic `NoResultService`.
  - Public REST under `doctorpilot/v1`: `/doctors`, `/doctors/{id}`,
    `/doctors/suggest`, `/facets` — explicit public permission callbacks,
    bounded args, published-only allowlisted output, stable error envelope,
    pagination headers, and short-window `RateLimiter` abuse control.
  - Cache generation counter with invalidation on doctor/term/settings changes
    and a generation-keyed `SearchCache` (correctness independent of TTL).
  - `[doctorpilot_search]`: server-rendered, shareable GET fallback progressively
    enhanced into no-reload search with History API state, debounced input,
    stale-request cancellation, `aria-live` result count, filters, sort, and
    accessible pagination; filtered query-string combinations are `noindex`.
  - Performance/verification harness: 1,000-doctor fixture generator, service
    benchmark (p50/p95 + query count), ranking query set, and accessibility
    checklist under `tests/perf/`.

- **Milestone 4 — Elementor** (Free):
  - Guarded integration bootstrap: `Compatibility` detects a supported Elementor
    (≥ 3.5) before any Elementor class is referenced; `Integration` registers the
    DoctorPilot widget category, a shared style handle, and the widgets. When
    Elementor is absent or outdated, nothing loads and native output stands
    (with an actionable admin notice for outdated versions).
  - Six Free widgets rendering through the shared view/search/booking services
    (no parallel query engine, no raw meta): Doctor Search, Doctor Grid / List,
    Department Grid, Symptom Directory, Doctor Profile, and Booking Button — with
    responsive column, layout, query, and scoped style controls.
  - Scoped widget styles reuse `assets/css/frontend.css`, loaded only where a
    widget is present via `get_style_depends`; the Doctor Search widget also
    declares the search script dependency.

- **Milestone 6 — Pro extension hooks** (Free base, additive, API v1):
  - `doctorpilot/search/filter_taxonomies` lets add-ons register extra search
    filter taxonomies (condition/treatment/location/insurance) with full parity.
  - `doctorpilot/locations/max` lets Pro raise the single-location limit.
  - `doctorpilot/search/performed` fires after a public search so add-ons can
    record anonymized aggregates (no identifying data passed).
  - These are the only Free changes for M6; all Pro features ship in the separate
    `doctorpilot-pro` add-on (schema/roles/scope, advanced filters + multi-location,
    Pro Elementor tags/widgets, BookPilot provider, and opt-in aggregate analytics).

### Notes
- Milestones 2–4 render accessible, published-only doctor profiles, a directory,
  no-reload search, and Elementor widgets. M4-TASK-004 (complete dynamic tags and
  Theme Builder conditions) and all Milestone 5–6 Pro features live in the
  separate DoctorPilot Pro add-on, which registers against the base extension API.
