# Changelog

All notable changes to DoctorPilot are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/) and the project uses semantic
versioning.

## [Unreleased]

### Added
- New Elementor widget category **Single DoctorPilot** with a **Page header with
  dynamic data** widget. It renders a single-doctor hero from canonical data —
  breadcrumb, name, role, primary specialty/department/languages/email/phone,
  credential chips, photo with a specialty badge, and Book / Call actions — for
  the selected or currently viewed doctor. Background and accent colors are
  themeable via the widget's style controls; an optional Availability text field
  is provided.
- New **Single doctor layout** setting (DoctorPilot → Settings). Pick an Elementor
  template or page to render on every single-doctor view. Because the Doctor
  Profile widget resolves the currently viewed doctor, you can design the layout
  once with **free** Elementor — no Elementor Pro / Theme Builder needed. Defaults
  to the built-in native template, and falls back to it if the chosen template is
  empty or Elementor is inactive.
- **Locations** are now a global content type too (`doctorpilot_loc`). Create a
  reusable hospital location once (name, address/area, city, phone, email, map
  link, opening hours) and assign it to many doctors via the new "Shared locations"
  selector. Shared locations render first in the profile's Locations section,
  followed by the doctor's own location & contact records (which are unchanged and
  still drive booking). A "Get directions" link shows when a map URL is set.
- New **International doctor** listing flag (checkbox in the doctor editor's
  Listing flags box). When enabled, an "International" tag shows on the doctor's
  card (top-right badge) and profile header.
- Extended the global content library to **Procedures, Publications, and FAQs**
  (in addition to Experience and Education). Each is now an admin-managed record
  type that can be created once and assigned to many doctors from the doctor
  editor's shared-record selectors, and each renders on the public profile merged
  with the doctor's own entries. `LibraryPostTypes` is now schema-driven, so all
  five types share one registration, editor, save, resolver, and REST path.
  Procedures/Publications/FAQs reuse their existing per-doctor repeaters as the
  "this doctor only" store, so no data migration is required. All five types are
  grouped under the "Global content" admin menu heading.
- Global reusable content library for Experience and Education (ADR-0017). Two new
  admin-managed record types — Experience (`doctorpilot_exp`) and Education
  (`doctorpilot_edu`) — let a hospital create a record once and assign it to many
  doctors; editing the record updates it everywhere it is used. Each record has a
  position/qualification, organization/institution, start/end year, country, and an
  optional description, with a read-only title composed from the first two. The
  doctor editor's Experience and Education boxes now offer shared-record selectors
  (chips) alongside the existing doctor-specific rows. A new authenticated
  `GET doctorpilot/v1/library/search` route backs the picker, a `DoctorContentResolver`
  merges shared and doctor-specific entries for display, and a reverse-reference
  index cleans up assignments when a shared record is deleted. Managed by a new
  `manage_doctorpilot_library` capability (granted to administrators).

### Changed
- SEO defaults now derive from the doctor's own post: the **SEO title** defaults to
  the post title (previously name + specialty), and the **meta description**
  defaults to the doctor's description — the headline (excerpt) when present,
  otherwise a trimmed excerpt of the biography. The editor placeholders and search
  preview, and the front-end `<title>`, meta description, and Open Graph tags all
  reflect these defaults; explicit SEO overrides still win.
- Global content records (Experience, Education, Procedures, Publications, FAQs)
  now show an editable **title** field. Leaving it blank auto-composes the title
  from the record's fields (as before); a typed title is preserved.
- Global **FAQ**, **Procedure**, and **Publication** records now use the title
  field as their single identifier (question / procedure name / publication
  title), removing the duplicate field. FAQ and Procedure have no other fields, so
  their Details box is removed entirely; Publication's box keeps journal, date,
  URL, and DOI. Experience and Education are unchanged (their title is composed
  from two distinct fields).
- Doctor profiles now show separate **Experience** and **Education** sections
  (previously one combined "Experience & education" list). Schema version 2 migrates
  any existing combined entries into the two new lists automatically.
- Admin menu: the DoctorPilot submenu is sorted into a fixed order — All Doctors,
  Education, Experience, Locations, Procedures, Publications, FAQs, Health,
  Analytics, Migration, Settings. (The earlier "Global content" heading was
  removed.)

### Fixed
- Doctor editor: the location repeater's **Location** and **Department at
  location** fields are now taxonomy dropdowns instead of text boxes. They store
  term IDs, so a typed name was previously coerced to `0` on save; you now pick a
  term from the Location / Department taxonomy.
- Translation loading no longer runs before `init` (WordPress 6.7
  `_load_textdomain_just_in_time` notice). Both plugins load their text domains on
  `init`, and the Pro add-on registers with the base using an untranslated brand
  name so no `__()` call fires during the early `plugins_loaded` boot.

### Changed
- SEO tab now derives from the post's own data when overrides are blank: the SEO
  title placeholder/preview shows the doctor's display name plus primary specialty
  (and credentials in the preview), the meta description falls back to the
  headline/excerpt, and the canonical URL placeholder shows the actual permalink.
  The live search preview reflects these defaults, and the front-end output
  already inherits them (canonical → permalink, description → excerpt).
- Doctor editor: the main-editor term chips and the default sidebar taxonomy
  metaboxes now stay in sync — selecting or deselecting a term in either updates
  the other, and the save merges both sources so a term added from the sidebar's
  "Add New" control is never dropped.
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
- Doctor editor aligned to the approved mockups across every tab: sentence-case
  pane titles with exact help text and a right-aligned "N rows" pane meta;
  per-term-field counts with the correct noun ("3 symptoms selected"); an
  "Add <taxonomy>" empty-state link; side-by-side rows (Prefix · First · Last,
  Credentials · Gender, Primary department · Primary specialty, Years · Manual
  order, Canonical · Legacy URL); a bordered Licensing box with a PRIVATE badge
  and note; repeater rows with per-column widths and placeholders; and
  placeholder text on SEO and other inputs. Layout is driven by generic
  row/width/box/placeholder/count_noun hints in the field registry.
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
