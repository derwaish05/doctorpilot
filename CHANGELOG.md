# Changelog

All notable changes to DoctorPilot are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/) and the project uses semantic
versioning.

## [0.2.0] — 2026-08-15

### Added
- **The optional widget heading is now on every widget that can carry one.** It
  was already on Doctor Grid/List, Doctor Carousel/Grid, About Doctor,
  Experience Timeline and Specialties; it is now also on Academic Highlights,
  Booking Button, Booking Card, Certifications, Certifications & Publications,
  Department Grid, Doctor Facts, Doctor Profile, Doctor Search, Page header,
  Symptom Directory and Related Doctors.

  Each gets the same four content controls (show, eyebrow, heading, heading tag
  h1–h6/div) and the same Heading style section (eyebrow and heading colour and
  typography, spacing below). **Off by default**, so no existing widget changes
  appearance until an editor switches it on.

  The heading prints only after a widget has confirmed it has something to show.
  A Certifications widget on a doctor with no certifications, a Booking Button
  with no booking destination, a Related Doctors block with no matches — each
  still renders nothing at all rather than a heading standing over an empty
  space. In several widgets that meant moving the call below the point where the
  content is assembled rather than putting it first.

  Heading Block is the one widget left out: it *is* the heading widget, and it
  already registers its own `heading_show` and `heading_text`, so adding the
  shared set would have collided on those control IDs.

  Doctor Profile's style section named "Headings" is renamed "Profile sections",
  because it styles the `h2` that opens each section inside the profile — a
  different thing from the widget heading, and two panel sections called
  "Heading" would have been a guessing game.
- **Doctor Carousel / Grid widget.** A second way to place doctors: the same
  card, the same six design presets, the same style panel and the same Card
  elements switches as Doctor Grid / List, with the directory apparatus removed
  — no search bar, no filter row, no A–Z index, no pagination. The editor
  chooses who appears; the visitor scrolls or reads.

  **Choosing doctors.** One multi-select per registered filter taxonomy —
  Department, Specialty, Symptom and Language always, plus Condition, Treatment,
  Location and Insurance Providers when Pro is active. The controls are built
  from `doctorpilot/search/filter_taxonomies`, so a taxonomy an add-on registers
  gains a control with no change here, and a taxonomy that is not registered
  never produces a control that would silently match nothing. Several terms in
  one taxonomy widen the selection; terms in more than one narrow it. Leave
  everything empty for all published doctors. **Follow the current archive**
  makes one widget serve every department, specialty or location archive by
  taking the term from the page instead of the panel.

  Selection runs through the same `SearchRequestFactory` and `SearchService` as
  the directory, so ranking, ordering, the request cache and the card payload
  are shared rather than reimplemented.

  **Carousel.** Built on CSS scroll snapping, not a slider library — nothing is
  loaded from a CDN and no third-party asset handle is depended on. The browser
  owns the physics, so touch flicks, trackpad swipes and momentum behave the way
  the platform behaves. Because the track is a real scroll container, the strip
  is scrollable and keyboard-reachable **before the script runs and without it**;
  the arrows, dots and autoplay ship `hidden` and are revealed only once the
  script is there, so a control that does nothing is never shown.

  Arrows can sit over the cards, outside them, or on the control row beside the
  dots, with colour, hover and size controls. Wrap-around is optional; with it
  off the arrows disable at each end. Autoplay is off by default and, when on,
  adds a real pause button and stops on hover, on keyboard focus, while the tab
  is in the background, and for anyone whose system asks for reduced motion.
  Arrow keys, Home and End page the track. A carousel whose cards already fit
  hides its own controls.
- **Per-element show/hide switches for the doctor card.** A new **Card
  elements** section in the Doctor Grid / List and Related Doctors (Pro)
  widgets turns any part of the card off: photo, "Featured" badge,
  "International" badge, credentials, specialty or department, short bio, years
  of experience, languages, the "Accepting new patients" row, the View Profile
  button and the booking button. The doctor name always renders — it is the
  card's link to the profile.

  A hidden part is omitted from the markup rather than hidden with CSS, so it
  never reaches a screen reader, a crawler or the wire. The switches are honoured
  identically by `templates/card-doctor.php` and by the JavaScript card renderer,
  so cards rebuilt after a filter change match the ones rendered on load. Turning
  both buttons off removes the actions row and its divider instead of leaving an
  empty strip; turning the photo off adds `dp-card--no-media`, which drops the
  Image overlay preset back to its light surface so white type never lands on
  white. The "Specialty or department" switch also governs the single metadata
  line of the compact list row.

  Every switch defaults to on, so existing widgets render exactly as before.
- **The doctor card has a premium default design.** White cards on a soft
  blue-grey surface, deep medical teal accents, dark navy text, a 16px radius
  and a soft professional shadow, with a 240ms hover lift. It looks finished
  the moment the widget is dropped on a page; every colour and metric remains
  editable.
- **Six card design presets** — Classic, Modern, Elevated, Minimal, Image
  overlay and Compact. Modern is the default; **Classic reproduces the previous
  card exactly** for anyone who wants the old look back.
- **A full card style panel**, replacing the three controls the card used to
  have. New sections cover the card box (background, border, radius, padding,
  content gap, divider, shadow, hover shadow, lift, scale, transition), the
  doctor image (aspect ratio, object fit and position, radius, placeholder
  colour, overlay, hover overlay, hover zoom), per-element text colour and
  typography for name, headline, credentials, specialty and metadata, three
  badge types, and independent primary and secondary buttons with normal and
  hover states.
- **Initials fallback for doctors without a photo.** A doctor with no image now
  shows their initials on a soft gradient instead of a blank grey block. The
  honorific is stripped first, so "Dr. Aisha Rahman" reads AR rather than DA.
- **Metadata icons.** Experience, languages and availability each get an inline
  icon, replacing the unstyled bulleted list.
- **Job title on the card.** The doctor's Headline (`post_excerpt`) is now part
  of the card, with an optional line clamp.
- **Optional availability badge** over the image for doctors accepting new
  patients. Off by default, since the same fact already appears in the metadata.
- **Whole-card click option.** The cover link is hidden from the keyboard path,
  so the name and buttons stay the only tab stops.
- **Image-card list style.** The list layout can now render full doctor cards
  laid out horizontally, with the image left, right or on top and a settable
  width. The original compact rows remain the default.
- **Skeleton loading cards** while an AJAX request is in flight, sized from the
  same tokens as the real card so nothing moves when results arrive.
- **National / International filter.** A tri-state filter — All, National,
  International — driven by the existing "International doctor" flag, so no new
  field or taxonomy was added. Available in the Doctor Grid / List Filters
  control, in the public `/doctors` REST route as `international`, and in the
  shareable URL. Doctors with no stored flag count as national, so imported
  profiles are not dropped from that result. It is off by default: existing
  widgets are unchanged until the filter is selected.
- The **Doctor Grid / List** search tabs now open a **browse index above the
  doctor results, with the results still on screen**. **By Doctor Name** lists
  every published doctor (name plus specialty); **By Department**, **By
  Specialty**, and **By Symptom** list that taxonomy's entries. Only terms at
  least one published doctor uses are listed, so no entry leads to an empty
  result. The widget renders every enabled index at once, so switching tabs is
  instant and needs no request.
- **Picking an entry filters the doctors below it.** The chosen name, department
  or symptom is outlined and highlighted, the results underneath narrow to it,
  and clicking it again clears it. Switching tabs clears the previous tab's
  selection, so a filter can never stay active with no visible control for it.
  The selection is an ordinary search parameter, so it appears in the URL, the
  active-filter count, and the **Clear** button, and it works as a plain link
  with JavaScript disabled.
- New **`doctor` search parameter** (comma-separated doctor slugs, max 10),
  accepted by `GET /doctors` and the widget's GET form, restricting results to
  those doctors before ranking.
- Every filter now stays usable while browsing, because the results never go
  away. A filter a visible index already controls (for example Department while
  the Department tab is open) is no longer duplicated as a select in the **More
  Filters** row.
- The **A–Z index** is a filter rather than a set of headings, and works without
  JavaScript: each letter is a real `?letter=` link, the active letter is
  highlighted, and letters with no entries are dimmed and non-clickable. It now
  applies to the browse index on every tab, updates as you type, and is **on by
  default**.
- New theme-overridable template `doctorpilot/row-term.php` for a browse-index
  entry (doctor name or taxonomy term).
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

### Changed
- **DoctorPilot Pro is now part of this plugin.** One artifact contains the
  directory, search, the Elementor widget set, the advanced taxonomies and
  filters, CSV migration, legacy-URL redirects, reconciliation, scoped roles and
  opt-in analytics. The add-on's bootstrap, its own autoloader, its `Plugin`
  singleton and the `maybe_boot()` registration handshake are gone; its modules
  are booted directly from `Infrastructure\Plugin::boot()`.

  **A leftover `doctorpilot-pro` is deactivated for you** on the first admin
  page load, through the silent form of `deactivate_plugins()`. That detail is
  the point: the add-on's own deactivation handler clears
  `bstdz_doctorpilot_pro_retention` and `bstdz_doctorpilot_pro_run_import`,
  which are the hook names this plugin now uses. Deactivating it the ordinary
  way — which is what the upgrade instructions would have asked for — would have
  stopped retention cleanup for good and killed any import waiting on its next
  chunk. The silent form never runs that handler.

  An admin notice then explains how to remove the old folder safely. Its
  `uninstall.php` reads *this* plugin's "delete data on uninstall" setting and
  drops the redirect and import tables, so on a site with that setting on,
  deleting it from the Plugins screen would take every legacy-URL redirect with
  it. Switch the setting off first, or remove the folder over FTP.

  Recurring schedules are also re-created on any admin request that finds them
  missing, so a schedule cleared by anything at all repairs itself on the next
  page load rather than staying gone until someone reactivates the plugin.

  Nothing is moved, recreated or lost. Every table name, option key, capability,
  role slug and cron hook keeps its existing string — including the ones with a
  `_pro_` segment, which identify rows already sitting in your database. Schema
  migration 3 adopts them on the first admin page load after the update; it is
  idempotent, so it is a no-op on a site that already had them and a first
  install on one that did not. No re-activation, no re-import, no re-mapping.

  Public contracts are unchanged: same REST routes, shortcodes, template names
  and override paths, Elementor widget names, table schema, capabilities and
  cron hooks. Saved widget settings survive.

  `ExtensionRegistry` and the `doctorpilot/registered` action are kept even
  though nothing here consumes them now — they are the published contract a
  third-party add-on would be built against.

  The reasoning, including what this gives up, is recorded in ADR-0020; the
  commercial detail is in `docs/12-packaging-and-tiers.md`.

- **DoctorPilot Pro no longer advertises bulk operations.** The Pro plugin
  description and `readme.txt` listed bulk operations as a shipped feature, but
  no bulk-action code exists (FR-046 is unimplemented). The description now
  names what Pro actually ships: CSV migration, legacy-URL redirects,
  reconciliation, advanced filters, and opt-in analytics.
- **Commercial packaging recorded (ADR-0019).** DoctorPilot now has a documented
  three-tier model — Free on WordPress.org, a one-time **Migration** entitlement,
  and an annual **Pro** subscription — shipped across the existing two plugins
  rather than merged into one paid module. No runtime, schema, or hook change;
  see `docs/12-packaging-and-tiers.md`.
- The compact list layout's **A–Z doctor list** — which paged through up to 480
  matches to group them by letter — is replaced by the **By Doctor Name** index,
  which is available in both layouts and can be clicked to filter. The doctor
  results now always paginate, so **Doctors per page** applies at all times.
- **Doctor Grid / List**: the A–Z index defaults to on, and the compact doctor
  list no longer prints a heading per letter — it is one flat, multi-column list
  that the A–Z bar filters. With the A–Z index on, the compact doctor list shows
  every match instead of paginating (bounded to 10 pages, with a notice when a
  larger directory is truncated); switch **Show A–Z index** off to paginate.
- **Doctor Grid / List** now defaults to showing its search bar and the Sort By /
  Gender / Languages Spoken filters. Existing placements of the widget will start
  showing the search toolbar; switch **Show search bar** off and clear the
  **Filters** control to restore the previous plain-grid output.
- The widget's **List** layout now renders compact name + specialty rows instead
  of one-column cards. Use the **Card grid** layout with Columns set to 1 for the
  previous appearance.
- `GET /doctors` now honours the documented `per_page` parameter instead of
  always using the site's per-page setting. The value is still clamped to 1–48
  and is part of the cache key, so cached responses stay correct.
- Shared (global) **Publications** now use the same field set and labels as the
  doctor-only Publications repeater — the last field is labelled **Description**
  (was "Summary") so both forms match. Fields: Title, Journal, Published date,
  URL, DOI, Description.
- Icon style sections in **Doctor Facts**, **Specialties**, **Certifications**, and
  the **Experience & Education** timeline node now include full box controls:
  padding, border radius, transition, and Background / Border / Box-shadow groups
  with Normal + Hover tabs.
- Content widgets (**About Doctor**, **Specialties**, **Experience & Education**)
  gained an optional built-in **eyebrow + heading** that renders only when the
  widget has data — so empty sections (and their headings) disappear for doctors
  without that content, instead of leaving a stray heading. Use it in place of a
  separate Heading Block.
- **Page Header** now has a **Placeholder image** control shown when a doctor has
  no featured photo (defaults to the Elementor placeholder).
- Frontend theme primary color set to `#136888` (via a `--dp-primary` CSS
  variable), applied to primary buttons, the single-doctor hero background/accent,
  profile headings (h1–h6), and links.
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

### Removed
- The **Tabs browse an index** widget switch. Its off state claimed the tabs
  would "only narrow what the doctor search matches", but the search mode was
  never used for ranking, so a widget with it off showed tabs that did nothing.
  Every configured tab now browses; to have no browse tabs, clear the
  **Search-mode tabs** control. Widgets that had the switch off gain working
  tabs on upgrade.

### Fixed
- **Uninstall now removes what the setting says it removes.** With "delete data
  on uninstall" enabled, the handler previously dropped three tables and two
  options and stopped there — leaving every doctor, all prefixed meta, the
  taxonomy terms, the analytics table, the cached search responses, both roles
  and all fifteen capabilities on the site, while the checkbox promised
  "permanently remove all DoctorPilot data".

  It now removes content, terms, orphan meta, all four tables, the options, the
  transient cache, the cron events, the capabilities and the roles, in
  dependency order so an interruption leaves the site consistent. The table,
  role and capability lists are read from `Tables`, `Roles` and `Capabilities`
  rather than restated, which is what let the analytics table be created but
  never dropped: the old handler named `bstdz_doctorpilot_analytics` while the
  table has always been `bstdz_doctorpilot_analytics_daily`, so its `DROP TABLE
  IF EXISTS` matched nothing and reported success.

- **Four-column grids no longer produce five or six columns.** The column
  classes used `repeat(auto-fill, minmax(Npx, 1fr))`, which ignores the chosen
  count and fits as many tracks as the canvas allows. Track counts are now
  explicit.
- **Buttons no longer sit against the card edge**, and cards in a row line their
  CTAs up regardless of how much metadata each doctor has.
- **Empty metadata rows and empty button rows are no longer rendered.** The card
  builds both lists before printing them and omits the container entirely when
  nothing qualifies, so no orphan bullet, stray divider or blank line appears.
- **Missing or slow-loading photos no longer shift the layout.** The media box
  reserves its height through `aspect-ratio`, and images carry intrinsic
  dimensions.
- **`tel:` and `mailto:` booking buttons survive filtering.** The client-side
  URL allowlist accepted only `http(s)`, so a Call button rendered on first
  paint and then disappeared on the first keystroke.
- Clicking a pagination link on a freshly loaded page no longer silently
  re-fetched page 1 on sites using pretty permalinks.
- A `?letter=` in the URL no longer filtered the index on widgets with the A–Z
  bar switched off, where nothing could clear it.
- Letters emptied by a text search are now dimmed and non-clickable instead of
  leading to an empty index, and an index with no entries at all keeps its
  explanation instead of showing a bare count.
- The active-filter badge no longer jumped by one the first time a control was
  touched on a widget with a non-default sort.
- Array-shaped query parameters (`?mode[]=x`) no longer raise a PHP warning.
- The **Doctor Grid / List** Elementor widget now has a built-in **search
  option**. Alongside the existing card grid it can render a search bar with
  search-mode tabs (**By Doctor Name / By Department / By Specialty / By
  Symptom**), a collapsible **More Filters** row (**Sort By**, **Gender**,
  **Languages Spoken**, plus Department, Specialty, Symptom, Accepting new
  patients, and Online consultation), an active-filter badge, a **Clear**
  button, a live result count, and pagination. Everything is toggleable per
  widget, and taxonomy filters only appear when the site has matching terms and
  the filter is enabled in DoctorPilot settings.
- The widget's **Compact list** layout renders name + specialty rows in
  responsive columns and gains an optional **A–Z index**: results are grouped by
  first letter (honorifics ignored), unavailable letters are dimmed, and picking
  a letter narrows the view. The A–Z view lists every match rather than a single
  page, bounded to 10 pages of results with an honest notice when a larger
  directory is truncated.
- New style sections for the widget: **Search bar** (bar background, padding,
  radius, input colors/border, mode-tab typography and active colors, filter
  label and button colors), **A–Z index**, and **List row** (name and specialty
  typography/color, row gap). The Columns control now drives the list rows as
  well as the card grid.
- New theme-overridable templates `doctorpilot/directory.php` and
  `doctorpilot/row-doctor.php`, and a new `doctorpilot-directory` script that is
  enqueued only on pages where the widget is present.
- New **`name_desc` (Z → A)** sort value, available to the widget, the
  `[doctorpilot_search]` shortcode, and `GET /doctors`.
- **Booking Card** buttons gained the full button style treatment (matching the
  Page header): shared Typography, Icon size, Icon spacing, Padding, and Border
  radius, plus per-button (Book / Call) **Normal + Hover** tabs with Text color,
  Background color, and Icon color, and Border and Box Shadow group controls.
- New **shared/global Certifications** library type (`doctorpilot_cert`), mirroring
  Publications: a central, reusable Certifications post type (Name, Organization,
  Issued date, Expiry date, Credential URL) managed under the DoctorPilot menu and
  assignable to many doctors via a new **Shared certifications** field on the doctor
  editor. The doctor's certifications now merge assigned shared records first, then
  the doctor-only repeater (renamed **Certifications (this doctor only)**). Wired
  through the content resolver, REST assignment picker, and reverse index.
- Certification widgets now surface **all fields** on the frontend: the standalone
  **Certifications** widget and the **Certifications & Publications** widget can
  show the **issued / expiry dates** (behind a toggle) in addition to name,
  organization, and the credential link.
- **Page header** credential chips now have a configurable **Certification icon**
  (content tab, defaults to the award icon) and a **Certifications** style section
  with icon color (default `#136888`), icon size, chip text/background color,
  typography, gap, padding, and radius — plus a Show/Hide toggle. The icons render
  from the doctor's dynamic certification data. Icon color scopes `fill` to Font
  Awesome SVGs so the built-in line icon isn't filled solid.
- New per-doctor **Academic highlights** field (repeater of value + label) in the
  doctor editor; the Academic Highlights widget gains a **Data source** option
  (doctor field / manual) so the numbers can be dynamic per doctor.
- Importable Elementor page template (`tools/single-doctor.elementor.json`)
  assembling the header plus a two-column layout with a `dp-sticky` sidebar.
- New sidebar Elementor widgets (Single DoctorPilot category): **Booking Card**
  (dark CTA with title, `{name}` subtitle, Book + Call), **Doctor Facts**
  (icon-led Languages/Availability/Department/Email/Phone), **Academic Highlights**
  (manual stat grid via a repeater), and **Certifications** (from the doctor's
  certifications, optionally linked to the credential URL). All dynamic and fully
  styleable. Added a **`dp-sticky`** utility CSS class (with `dp-sticky--scroll`)
  so any Elementor column can be made sticky without Elementor Pro.
- New **Heading Block** Elementor widget (DoctorPilot category): a main heading
  (optionally linked), subheading, and description, each with its own HTML tag,
  show/hide toggle, and display order. Full Style controls per element —
  typography, text color (normal/hover), gradient text, text shadow, spacing,
  padding — plus a Box section (background/gradient/image, overlay, padding,
  radius, border and box-shadow with normal/hover tabs, transition) and an
  optional decorative line (above/below/left/right). Uses core Elementor control
  APIs, so it works with or without Pro; all output escaped and translation-ready.
- New **Experience & Education** timeline Elementor widget (Single DoctorPilot
  category): a vertical timeline of the doctor's experience and/or education
  entries — icon nodes on a connecting rail with a card per entry (role/
  qualification + organization/institution, optional year range). Configurable
  node/meta icons and full rail, card, and text style controls.
- New **Certifications & Publications** Elementor widget (Single DoctorPilot
  category): renders both the doctor's certifications (name, organization,
  credential link) and publications (title, journal · date, URL/DOI link) as two
  titled, icon-led lists in one widget. Each group toggles independently and hides
  when empty; full layout, icon (with box options), and text style controls.
  Publications can now show the **description/summary** and certifications the
  **issued/expiry dates** (each behind its own toggle), with a Description text
  style control — so every stored field can be surfaced on the frontend.
- New **Specialties** Elementor widget (Single DoctorPilot category): a
  check-marked, multi-column "scope of practice" list built from the doctor's
  procedures, treatments, specialties, conditions, symptoms, or clinical
  interests. Selectable icon and columns, with item/label/icon style controls
  (background, border, radius, hover, typography, colors).
- New **About Doctor** Elementor widget (Single DoctorPilot category): outputs the
  doctor's biography (or headline) for the selected/current doctor, with body-text
  typography/color/link/spacing and box style controls. Pair it with a Heading
  Block for the eyebrow/heading above it.
- New Elementor widget category **Single DoctorPilot** with a **Page header with
  dynamic data** widget. It renders a single-doctor hero from canonical data —
  breadcrumb, name, role, primary specialty/department/languages/email/phone,
  credential chips, photo with a specialty badge, and Book / Call actions — for
  the selected or currently viewed doctor. Style controls include Elementor's full
  **Background** group (Classic color/image with position, repeat, size,
  attachment; Gradient; and Video/Slideshow when Pro is active), an overlay color,
  and text/name/accent colors. An **Image** section provides width, aspect ratio,
  object-fit, border radius, border, box shadow, opacity, CSS filters (normal +
  hover), a hover animation, and transition. A **Photo badge** section styles the
  badge (typography, colors, padding, radius, border). An optional Availability
  text field is provided; Availability otherwise falls back to the doctor's
  location office hours.
- New **Single doctor layout** setting (DoctorPilot → Settings). Pick an Elementor
  template or page to render on every single-doctor view. Because the Doctor
  Profile widget resolves the currently viewed doctor, you can design the layout
  once with **free** Elementor — no Elementor Pro / Theme Builder needed. Defaults
  to the built-in native template, and falls back to it if the chosen template is
  empty or Elementor is inactive.
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
- Department and specialty names containing an ampersand rendered as
  `Hepatology &amp; Endoscopy` on cards, list rows, and in the `/doctors` REST
  payload. WordPress stores term names HTML-encoded, and every consumer escapes
  again for its output context, so the entity survived to the screen. Term names
  are now decoded once in the doctor view model, which fixes the templates and
  the frontend script together.
- A stray radio button appeared next to the **Doctor Grid / List** search bar
  when a search-mode tab (By Doctor Name / By Department / By Symptom) was
  selected. The tabs are a real radio group styled through their labels, and the
  inputs were hidden with `.screen-reader-text` plus `clip` — but themes commonly
  un-hide that class on `:focus` (the skip-link pattern) and clicking a tab
  focuses its radio, while `clip` does not reliably clip a native control's own
  rendering in every browser. The inputs now carry a self-contained
  visually-hidden rule built on `appearance: none`, so no theme or browser can
  paint them. They remain keyboard-focusable, and the focus ring still shows on
  the tab label.
- The **Book** call-to-action on doctor cards was invisible: `.dp-directory a`
  out-specified `.dp-btn--primary`, painting the label the same colour as the
  button's own background. Link colouring now skips `.dp-btn`.
- **Elementor single-doctor templates now load Elementor's base frontend CSS** on
  the doctor page. Because the queried object is a doctor (not an Elementor
  document), Elementor previously skipped its own asset loading, so flexbox
  container styles (`.e-con`) never loaded and multi-column template layouts
  collapsed/overlapped on the frontend while looking correct in the editor. The
  plugin now registers (early, priority 1) and enqueues Elementor's frontend
  styles/scripts and the template's generated CSS on single-doctor pages that use
  a template. Registering the `elementor-frontend` handles early also resolves the
  WordPress 6.9 "dependencies that are not registered: elementor-frontend" notices
  raised by the theme's Elementor mega-menu templates on doctor pages.
- Doctor-specific ("this doctor only") **publications now display** alongside
  shared library publications. Library types whose title is a dedicated field
  (publications) have no composed `title_parts`, so the local-entry resolver was
  overwriting each row's real title with an empty string — blanking it and causing
  the item to be filtered out. The composed title is now only applied when it is
  non-empty, otherwise the field-derived title is kept. Affects any library type
  without `title_parts`.
- Single-doctor Elementor widgets no longer show the same doctor on every doctor
  page. The **current queried doctor now takes precedence** over a widget's
  Doctor ID, so one template renders each doctor correctly. The Doctor ID is now
  a fallback only — for editor preview or placing a widget off a doctor page.
- Doctor editor: **Primary specialty** (and Primary department) would silently
  revert on save when the chosen term wasn't one of the doctor's assigned terms —
  the dropdown listed every term, but a primary must be an assigned term, so it
  was cleared. The dropdowns now list only the doctor's assigned specialties /
  departments, and update live as you check/uncheck the term chips, so the
  selection always saves.
- Doctor editor: the location repeater's **Location** and **Department at
  location** fields are now taxonomy dropdowns instead of text boxes. They store
  term IDs, so a typed name was previously coerced to `0` on save; you now pick a
  term from the Location / Department taxonomy.
- Translation loading no longer runs before `init` (WordPress 6.7
  `_load_textdomain_just_in_time` notice). Both plugins load their text domains on
  `init`, and the Pro add-on registers with the base using an untranslated brand
  name so no `__()` call fires during the early `plugins_loaded` boot.
