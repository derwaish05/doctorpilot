# 02 — Requirements

**Scope:** DoctorPilot doctor directory and search plugin.  
**Normative language:** **Must** is release-blocking; **should** is strongly recommended; **may** is optional.  
**Traceability:** Every implementation task must reference at least one requirement or acceptance criterion. GitHub Issues may decompose work but must not silently change this document.

## 1. Actors, roles, and authorization

DoctorPilot defines capabilities, not assumptions about role names. Administrators may assign capabilities to existing roles. Pro may provide optional role presets and department-scoped controls.

| Actor | Responsibilities | Minimum authorization |
|---|---|---|
| Administrator | Configure settings, capabilities, migrations, and deletion policy | `manage_doctorpilot` |
| Doctor editor | Create and edit doctor profiles | `edit_doctors` |
| Doctor publisher | Review and publish profiles | `publish_doctors` |
| Department manager (Pro) | Manage approved departments only | Base edit capabilities plus server-side department scope |
| Doctor self-editor (Pro) | Edit an explicitly linked profile, without publishing | Object-level `edit_doctor` mapping |
| Patient / visitor | Search and view published public data | No authentication |

Authorization must be enforced server-side. Nonces verify request intent for authenticated actions but never replace capability checks. Public, cacheable search endpoints must not require a WordPress nonce.

## 2. Core user stories

- US-01 As a patient, I can search by doctor name and see matching published profiles.
- US-02 As a patient, I can choose a symptom and see relevant doctors without being told I have a diagnosis.
- US-03 As a patient, I can share a filtered URL and return to the same result state.
- US-04 As a keyboard user, I can open filters, change them, review results, and reach a booking action.
- US-05 As an editor, I can maintain one structured doctor record and reuse it across all displays.
- US-06 As a publisher, I can see completeness and review state before publishing.
- US-07 As a designer, I can style core doctor views in Elementor and use a native fallback when Elementor is unavailable.
- US-08 As a migration owner, I can import legacy data as drafts, resume failed work, and map every redirect explicitly.
- US-09 As a privacy owner, I can disable analytics and verify that enabled analytics remain aggregate and non-identifying.
- US-10 As a developer, I can integrate a booking provider through a stable adapter without changing doctor storage.

## 3. Release capability matrix

Three commercial tiers ship across two artifacts (ADR-0019). **Free** is the WordPress.org build. **Migration** and **Pro** are separately priced entitlements inside the `doctorpilot-pro` artifact — Migration one-time, Pro annual.

| Capability | Free MVP | Migration | Pro v1 | Later |
|---|:---:|:---:|:---:|:---:|
| Structured doctor profiles and review state | ✓ | — | Extends | — |
| Department, Specialty, Symptom, Condition, Treatment, Language | ✓ | — | ✓ | — |
| Single location | ✓ | — | ✓ | — |
| Multiple locations | — | — | ✓ | — |
| Name/department/specialty/symptom search | ✓ | — | ✓ | — |
| Gender/language/online filters | ✓ | — | ✓ | — |
| Location, insurance, schedule, availability filters | — | — | ✓ | — |
| Native templates and shortcodes | ✓ | — | ✓ | — |
| Core Elementor widgets | ✓ | — | ✓ | — |
| Complete dynamic tags and Theme Builder conditions | — | — | ✓; Elementor Pro required | — |
| External URL, phone, enquiry booking | ✓ | — | ✓ | — |
| BookPilot provider | — | — | ✓ | — |
| CSV doctor import with dry run, resume, rollback | — | ✓ | — | — |
| Legacy field mapping and review-flag heuristics | — | ✓ | — | — |
| Legacy-URL redirect manager | — | ✓ | — | — |
| Reconciliation reporting and formula-safe CSV export | — | ✓ | — | — |
| Bulk operations (taxonomy, review state, publish, duplicate) | — | — | ✓ | — |
| Opt-in aggregate analytics | — | — | ✓ | — |
| Global content library and assignment (procedures, education, publications, FAQs) | ✓ | — | Extends | — |
| Doctor-specific local items | ✓ | — | ✓ | — |
| Bulk assignment / promotion | — | — | ✓ | — |
| Merge/repoint duplicate globals | — | — | ✓ | — |
| Library CSV import/export | — | — | ✓ | — |
| Native appointments/patient records | — | — | — | Separate product/ADR |
| AI-assisted medical content | — | — | — | Separate safety review/ADR |

### 3.1 Build status against the paid tiers

The tier column above states where a capability *belongs*, not whether it exists. As of 2026-08-11 the paid artifact is unevenly built, and the gap falls almost entirely on the subscription tier:

| Tier | State |
|---|---|
| Migration | **Substantially complete.** Import/export, mapping, review flags, redirects, reconciliation, retention, and the Migration admin screens are implemented and were exercised on the Aman deployment. |
| Pro | **Incomplete — not saleable as a subscription yet.** Implemented: insurance taxonomy, condition/treatment/location/insurance filters, Pro fields, BookPilot provider, ScopeGuard department/self scoping, opt-in analytics, and the Related Doctors widget with its dynamic tag. Not implemented: **FR-009** multi-location records (only a locations-max limit raise exists), **FR-017** schedule and availability filters, **FR-024** sort by earliest verified availability, **FR-032** advanced widgets (only Related Doctors is in Pro), **FR-035** Theme Builder conditions, **FR-046** bulk operations, and the Pro library operations (bulk assignment/promotion, duplicate merge with repointing, library CSV, impact reporting). Pro ships zero front-end assets, consistent with the missing widget set. |

Closing the Pro column is a precondition for charging a renewal, not a roadmap preference (ADR-0019). Licence/activation for both paid tiers is likewise unbuilt.

**Unreconciled: the Free/Pro widget boundary.** FR-031 documents six Free widgets, but `Integration::register_widgets()` registers sixteen — the six named plus PageHeader, HeadingBlock, AboutDoctor, Specialties, ExperienceTimeline, BookingCard, DoctorFacts, AcademicHighlights, Certifications, and CertsPublications. Several of those are the "Doctor Information sections" that FR-032 assigns to **Pro**. Either FR-031/FR-032 are stale or Pro widget value has already shipped in Free. This is deliberately not resolved by editing a tier tag, because the answer changes what Pro can be priced on; it is tracked in `12-packaging-and-tiers.md` §9 and is Phase 3 work.

## 4. Functional requirements

### 4.1 Doctor profiles

- **FR-001 [Free]** Store each doctor in the prefixed internal post type defined by `06-technical-spec.md`; public URLs remain human-readable.
- **FR-002 [Free]** Provide a guided editor with these groups: Profile, Professional Information, Highlights, Clinical Services, Experience & Education, Certifications & Memberships, Publications, Booking, SEO, and FAQs. Location & contact is edited inside Profile as a single record, not a group of its own.
- **FR-003 [Free]** Use one field registry as the source for storage type, default, sanitizer, validation, public visibility, REST shape, and tier.
- **FR-004 [Free]** Support structured records for the doctor's professional history. Leadership positions and certifications/memberships are stored as doctor repeaters. Procedures, Experience & Education, Publications, and FAQs use the hybrid global-library model (reusable global records assignable to many doctors) plus per-doctor local items, per ADR-0017 and §4.10.
- **FR-005 [Free]** Support name, prefix, photo, optional gender, short introduction, biography, featured state, departments, specialties, experience, languages, professional license, clinical terms, contact data, consultation type, optional consultation duration/price, and booking configuration.
- **FR-006 [Free]** Use WordPress post status for publication (`draft`, `pending`, `publish`) and a separate computed review/completeness state; do not duplicate publication state in editable meta.
- **FR-007 [Free]** Hide all private/internal fields from public output unless an administrator explicitly changes the field's supported visibility setting.
- **FR-008 [Free]** Validate that each primary department/specialty is also assigned in the corresponding taxonomy.
- **FR-009 [Pro]** Support multiple location records and location-specific booking/contact/schedule data.

### 4.2 Taxonomies and clinical relationships

- **FR-010 [Free]** Keep Department, Specialty, Symptom, Condition, Treatment, Language, and Location separate. Insurance is Pro.
- **FR-011 [Free]** Internal taxonomy keys must be prefixed; clean public rewrite slugs may be configured independently.
- **FR-012 [Free]** Symptom terms support alternative names, a short explanation, curated related departments/specialties, priority, A–Z visibility, and SEO fields.
- **FR-013 [Free]** Clinical term relationships must be explicitly curated and attributable to an editor; the plugin must not auto-invent diagnoses or treatment advice.
- **FR-014 [Free]** Deleting or merging a term must surface affected doctors and prevent silent orphaned primary-term references.

### 4.3 Search and filters

- **FR-015 [Free]** Search modes: doctor name, department, specialty, symptom, and universal keyword.
- **FR-016 [Free]** Core filters: department, specialty, symptom, gender, language, online consultation, and national/international.
- **FR-016b** There is no accepting-new-patients field, filter, card row or badge. The whole feature was removed in 0.2.1 at the product owner's request; see ADR-0021 for what was removed and what was deliberately left in the database.
- **FR-016a [Free]** The national/international filter is tri-state — All, National, International — because "national" is a selection a visitor makes, not merely the absence of a filter. It reads the existing `is_international` flag; no new field or taxonomy is introduced. A doctor with no stored flag counts as national, so profiles created by import or by code are not silently dropped from that result.
- **FR-017 [Pro]** Advanced filters: location, insurance, condition, treatment, consultation type, available today, and available date.
- **FR-018 [Free]** Debounced suggestions begin only after the configured minimum length and return bounded, public-safe results.
- **FR-019 [Free]** Results update without a full reload when JavaScript/REST is available and degrade to a standard GET form when it is not.
- **FR-020 [Free]** Filter, sort, view, and page state are encoded canonically in the URL; reload and browser history restore the same state.
- **FR-021 [Free]** Active filters appear as removable chips with a visible “Clear all” action.
- **FR-022 [Free]** Default ranking is deterministic: exact doctor name, name prefix/token match, exact department/specialty, direct symptom, curated related department/specialty, keyword match, featured flag, manual order, then title.
- **FR-023 [Free]** No-result UI may offer spelling/synonym suggestions, related departments or symptoms, a hospital contact action, and featured doctors. It must not provide a diagnosis.
- **FR-024 [Free/Pro]** Sorting supports relevance, name, featured, experience, and recently added (Free); Pro adds earliest verified availability. *The Pro half is not implemented — it depends on FR-017.*
- **FR-025 [Free]** Pagination is mandatory. “Load more” may enhance it but must preserve a crawlable and keyboard-accessible page state.

### 4.4 Frontend display

- **FR-026 [Free]** A core doctor card supports photo/placeholder, name, headline, primary department/specialty, experience, languages, consultation type, View Profile, and booking action. Empty optional values are omitted without blank labels.
- **FR-027 [Free]** Grid and list layouts support consistent media ratios, result count, pagination, loading state, empty state, and a mobile filter drawer.
- **FR-028 [Free]** The single profile renders only non-empty structured sections in the order defined by the technical specification.
- **FR-029 [Free]** Native templates and shortcodes provide functional output when Elementor is absent or inactive.
- **FR-030 [Free]** Themes may override templates through a documented `doctorpilot/` override directory.

### 4.5 Elementor compatibility

- **FR-031 [Free]** Core widgets: Doctor Search, Doctor Grid/List, Doctor Profile, Department Grid, Symptom Directory, and Booking Button.
- **FR-031a [Free]** The Doctor Grid/List widget renders either a card grid or a compact list and can expose search in place: a query field, browse tabs, a collapsible filter row, an A–Z index, a result count, and pagination. Filters, sort, query, tab, and letter are encoded in the URL per FR-020, the widget works as a plain GET form without JavaScript, and every filter it offers is limited to those enabled in DoctorPilot settings and backed by existing terms.
- **FR-031b [Free]** The widget's browse tabs open an index *above* the doctor results, and the results stay on screen with it. **By Doctor Name** indexes every published doctor (name plus primary specialty or department); **By Department**, **By Specialty**, and **By Symptom** index that taxonomy's terms, limited to terms at least one published doctor uses. Each index is browsable A–Z and by free text. Every configured tab browses — there is no tab that only scopes the query, because `mode` is not a ranking input and such a tab would change nothing. A site that does not want browse tabs configures none.
- **FR-031c [Free]** Choosing an entry in a browse index narrows the doctor results below it rather than navigating away: the entry writes that index's search parameter (`doctor`, `department`, `specialty`, `symptom`), is marked as the current selection, and choosing it again clears it. Switching tabs clears the previous tab's selection, so no filter can stay active without a visible control for it. Because the selection travels as an ordinary search parameter it is shared by the URL, the REST route, the cache key, and the active-filter count, and it works as a plain GET link without JavaScript.
- **FR-031d [Free]** Every filter the widget offers stays applicable at all times, because the results are always present. A parameter owned by a visible browse index is not also offered as a select in the filter row, so one value is never settable from two controls that could disagree.
- **FR-031f [Free]** The Doctor Index block opens with no list showing — a search bar and its browse buttons, nothing more. A browse list is what a visitor gets after asking for one, not what greets them. Pressing the open button closes it again, so the starting state stays reachable. Because no list is selected at rest, the buttons are disclosure controls carrying `aria-expanded`, not a tablist: a tablist must always have exactly one selected tab, and its roving tabindex would leave no focusable button at all while everything is closed.
- **FR-031e [Free]** The Doctor Index block — a browse index with no results grid, whose entries link out — offers a collapsible **Sort By / Gender / Languages Spoken** row. Gender and Languages describe doctors, so they narrow the **By Doctor Name** list. On a Department, Specialty or Symptom tab they have nothing to match, and the whole row is therefore unavailable: the **More Filters** button is disabled and dimmed rather than hidden, so the bar does not reflow and the control's absence reads as belonging to another tab rather than as a bug. Values are kept, not reset, so returning to **By Doctor Name** restores the visitor's choice. A term entry's link carries only its term, and a doctor entry's link is a profile permalink; neither gains a filter the visitor cannot see at the moment they click. The filters are read back from the query string on the server, so the block sorts and narrows identically without JavaScript, where the disabled state is correct from the first paint; a filter whose options would be empty is not rendered at all.
- **FR-032 [Pro]** Advanced widgets: Advanced Search, Carousel, Doctor Information sections, Locations, Schedule, Related Doctors, Results Count, Active Filters, and pagination controls.
- **FR-033 [Free/Pro]** Widgets expose controls appropriate to their output, including typography, color, spacing, border/radius, image ratio, columns, responsive behavior, focus/hover states, empty state, and loading state. Irrelevant controls must not be added merely to inflate feature count.
- **FR-034 [Free/Pro]** Assets load only for widgets present on the page and use Elementor's dependency APIs.
- **FR-035 [Pro]** Complete dynamic tags and custom Theme Builder conditions load only when a compatible Elementor Pro version is active. The plugin must remain functional without Elementor Pro.
- **FR-036 [Free/Pro]** Widget-level style settings override DoctorPilot global design tokens.

### 4.6 Booking

- **FR-037 [Free]** Booking actions support an external URL, hospital booking page, telephone link, or enquiry destination.
- **FR-038 [Free]** Booking configuration is per doctor; unsafe schemes or malformed destinations are rejected.
- **FR-039 [Pro]** BookPilot and location-aware actions use the booking adapter contract.
- **FR-040 [Free/Pro]** A provider failure falls back only to a configured safe action; it must not invent availability or silently redirect elsewhere.
- **FR-040a [Free]** The Page Header's Book and Call buttons and the Doctor Grid / List's Booking button each accept an optional per-widget link that replaces the resolved destination for that widget. A set link renders the button even when the doctor has no booking method or phone. The link is validated with the same scheme rules as booking destinations; an unsafe link yields no override, never a live link.

### 4.7 Import, export, and migration

Tier note (ADR-0019): FR-041 through FR-045 and FR-047 belong to the **Migration** one-time entitlement, because they are launch tooling used once per deployment. FR-046 bulk operations stay in **Pro** — bulk editing is day-to-day directory administration, not migration.

- **FR-041 [Migration]** CSV import provides upload validation, column mapping, preview, dry run, row-level errors, optional term creation, and chunked processing.
- **FR-042 [Migration]** Imported doctors default to draft/pending review and never auto-publish.
- **FR-043 [Migration]** An external source key makes imports idempotent. Retrying a job must not duplicate successfully imported doctors.
- **FR-044 [Migration]** Redirects require an explicit normalized source path and destination doctor. Reject redirect loops, duplicate sources, staging destinations, and guessed mappings.
- **FR-045 [Migration]** Export protects spreadsheet consumers against CSV formula injection.
- **FR-046 [Pro]** Bulk actions include assign taxonomy, set review state, publish/unpublish with capability checks, duplicate doctor, and completeness report. *Not implemented.*
- **FR-047 [Migration]** A failed or interrupted import is resumable and retains an auditable summary without storing uploaded files indefinitely.

### 4.8 SEO

- **FR-048 [Free]** Default profile URLs use the configured directory base and WordPress post slug. DoctorPilot must not force a `dr-` prefix globally.
- **FR-049 [Free]** A deployment may preserve/import `dr-` slugs when explicitly mapped, as required for Aman Hospital.
- **FR-050 [Free]** Integrate with the active SEO owner to avoid duplicate canonical, Open Graph, breadcrumb, schema, and sitemap output.
- **FR-051 [Free]** Public profiles may emit valid Physician and organization-related structured data from reviewed fields, without promising a search-engine rich result.
- **FR-052 [Free]** Filtered combinations, internal search results, and AJAX endpoints must not create indexable duplicate pages.
- **FR-053 [Migration]** Legacy redirects are one-to-one 301 mappings and are monitored for loops and 404s. Resolution must continue under any licence state, including expired or absent: a lapsed licence must never 404 live inbound traffic (ADR-0019). This is a licence-state guarantee, not a Free-artifact capability — Free ships no redirect code.

### 4.9 Analytics and privacy

- **FR-054 [Pro]** Analytics are disabled by default and require explicit administrator opt-in with clear disclosure.
- **FR-055 [Pro]** Store daily aggregate counts only. Do not store IP addresses, user IDs, cookie identifiers, raw user-agent strings, or query-to-person/session associations.
- **FR-056 [Pro/Migration]** Retention and deletion controls apply to analytics (Pro) and import logs (Migration).
- **FR-057 [Free/Pro]** Symptom search displays a configurable medical disclaimer stating that results support doctor discovery and are not diagnosis or emergency advice.

### 4.10 Global content library (ADR-0017; see `11-global-content-library.md`)

- **FR-058 [Free]** Provide reusable global records for Procedures, Experience & Education, Publications, and FAQs as prefixed custom post types managed from the DoctorPilot admin area, with WordPress `post_status` as the publication authority and a `manage_doctorpilot_library` capability. As built (schema version 2), Experience and Education ship as two separate CPTs `doctorpilot_exp` and `doctorpilot_edu` (per the ADR-0017 amendment); Procedures (`doctorpilot_proc`), Publications (`doctorpilot_pub`), and FAQs (`doctorpilot_faq`) remain planned. All CPT keys are ≤ 20 characters.
- **FR-059 [Free]** In the doctor editor, allow searching for and selecting existing global items (via an authenticated `GET doctorpilot/v1/library/search`) and assigning multiple items of each type, with a stable display order and duplicate-assignment prevention.
- **FR-060 [Free]** Continue to allow doctor-specific ("local") items for each of these types; each local item has a stable key and can be added, edited, and removed without changing any global record and never affects other doctors.
- **FR-061 [Free]** Resolve each type as a single ordered list — assigned global records (published only, in public contexts) first, then the doctor's local items — through one shared `DoctorContentResolver`, distinguishing global from local in admin context. Native templates, search document, REST `/doctors/{id}`, and Elementor consume the same resolved data.
- **FR-062 [Free]** Prevent duplicate assignments (unique reference lists) and block exact-key duplicate global records on creation using a normalized per-type key (Unicode NFC, case-fold, whitespace/punctuation normalization, DOI normalization); an override requires `manage_doctorpilot_library` and is audit-logged.
- **FR-063 [Free/Pro]** Define lifecycle behavior: editing a global propagates to all assigned doctors and, via a rebuildable reverse-reference index, immediately increments cache generation and enqueues bounded rebuilds of affected doctors' search documents; unpublished/trashed globals are hidden from public output but keep their references (restore re-links); permanent deletion removes references from all doctors in bounded batches with no dangling data; Pro may merge duplicate globals and repoint references. Draft, pending, private, or trashed globals must never appear in public templates, search, REST, or Elementor. Free surfaces basic blast-radius visibility ("used by N doctors", affected list, and a warning before unpublish/delete).
- **FR-064 [Free]** Support WPML on a multilingual site. Doctors, the six library post types and all eight taxonomies are declared translatable through `wpml-config.xml`; term slugs saved in Elementor widgets or arriving in a URL resolve to the current language at render; stored primary-term IDs, library post IDs and the single-doctor template ID convert when read; the derived search index is built per language and rebuilt for a doctor created by any route, not only the editor form; the search cache is keyed per language; and both public REST routes accept a `lang` parameter and switch language before reading content. A single-language site behaves identically to before. Polylang is not supported; `Support\Multilingual` is the only place that would change. See ADR-0023.
- **FR-065 [Free]** Derived search values are rebuilt once per doctor per request, at the end of the request, for every write path — the editor form, CSV import, WP-CLI, REST, Quick Edit and a translation plugin. A doctor with no search index is excluded from every result rather than ranked low, so "indexed" is a correctness property, not an optimisation.

## 5. Non-functional requirements

- **NFR-001 Security:** sanitize and validate at boundaries, escape late by context, use explicit REST permission callbacks, prepared SQL, safe upload handling, and server-side capability checks.
- **NFR-002 Privacy:** no identifiable patient data; no analytics or external transmission without explicit consent; include privacy-policy text when applicable.
- **NFR-003 Accessibility:** target WCAG 2.2 AA. Core journeys require keyboard operation, visible focus, labelled controls, focus management, screen-reader result announcements, adequate contrast, reduced-motion support, and no color-only status.
- **NFR-004 Performance:** against the approved 1,000-doctor fixture, public search REST p95 must be ≤500 ms excluding network time; default page payload should not include unused profiles or widget assets.
- **NFR-005 Compatibility:** no fatal errors when Elementor, Elementor Pro, DoctorPilot Pro, or a booking provider is absent/incompatible. Supported versions must be declared and tested.
- **NFR-006 Localization:** all user-facing strings are translatable; dates, numbers, currency, and RTL layouts use WordPress locale behavior.
- **NFR-007 Data safety:** deactivation preserves content; uninstall deletes DoctorPilot-owned data only after explicit opt-in; migrations are versioned and restartable.
- **NFR-008 Maintainability:** field definitions, tier rules, public serializers, and capability checks have one authoritative implementation each.
- **NFR-009 Observability:** failures use structured logs that omit secrets and private data; administrators receive actionable, non-technical summaries.
- **NFR-010 Extensibility:** documented hooks may extend fields, result cards, ranking, booking adapters, and template locations without bypassing validation.

## 6. Acceptance criteria

| ID | Given | When | Then |
|---|---|---|---|
| AC-001 | An authorized editor | Creates a valid doctor and saves as draft | The record persists, completeness updates, and nothing is public |
| AC-002 | A doctor has multiple specialties and symptoms | A visitor searches any assigned term | The published doctor is discoverable through each assignment |
| AC-003 | A primary specialty is selected | That term is removed from assigned specialties | Save is blocked or the primary value is cleared with an explicit warning |
| AC-004 | A private email/license field exists | A visitor calls any public endpoint | The private value is absent from the response |
| AC-005 | JavaScript is enabled | A visitor changes a filter | Results, count, active chips, and URL update without a full reload |
| AC-006 | A filtered results URL exists | It is opened in a new session | Equivalent filters, sort, view, page, and results are restored |
| AC-007 | JavaScript/REST is unavailable | A visitor submits the search form | A functional server-rendered result page is returned |
| AC-008 | A keyboard-only visitor | Opens mobile filters and applies them | Focus is managed correctly and the changed result count is announced |
| AC-009 | No doctors match | Search completes | The no-result design appears without diagnostic claims |
| AC-010 | Elementor is inactive | A native DoctorPilot shortcode/template renders | The directory and profile remain functional |
| AC-011 | Elementor Free is active | A core widget renders | Only its declared assets load and output matches native data |
| AC-012 | Elementor Pro is absent | DoctorPilot Pro is active | Pro does not fatal; dynamic-tag/Theme Builder features show a clear dependency notice |
| AC-013 | A booking URL uses an unsafe scheme | An editor saves it | Validation rejects the value |
| AC-014 | A booking provider fails | A fallback is configured | The safe fallback renders; no false availability is shown |
| AC-015 | A CSV job is retried | Previously successful source keys are present | Existing imports are updated/skipped per chosen mode, not duplicated |
| AC-016 | A migrated row is valid | Import completes | The doctor remains draft/pending review until an authorized publisher acts |
| AC-017 | A redirect source loops or targets staging | An administrator saves it | The mapping is rejected with a specific error |
| AC-018 | Analytics are disabled | Searches occur | No analytics rows or external tracking requests are created |
| AC-019 | Analytics are enabled | Searches occur | Only approved daily aggregates are stored |
| AC-020 | The plugin is deactivated | Doctor content exists | Content and settings remain intact |
| AC-021 | Delete-on-uninstall is disabled | The plugin is uninstalled | Doctor content remains |
| AC-022 | Delete-on-uninstall is explicitly enabled | The plugin is uninstalled | Only DoctorPilot-owned posts, terms, options, tables, cron events, and capabilities are removed |
| AC-023 | Yoast or Rank Math owns canonical output | A doctor profile renders | One canonical is emitted and no duplicate DoctorPilot canonical appears |
| AC-024 | The approved performance fixture is loaded | The benchmark suite runs | NFR-004 passes and the report is attached to the release |
| AC-025 | An administrator opens the DoctorPilot admin | They manage global library items | Each type can be created, edited, published/unpublished, and deleted |
| AC-026 | A user edits a doctor | They search the library and select items | Multiple existing globals of each type can be assigned |
| AC-027 | A user edits a doctor | They add or change a local item | The local item changes only that doctor; no global record is altered |
| AC-028 | A doctor has both global and local items | The editor renders the lists | Global and local items are clearly distinguished |
| AC-029 | A user assigns or creates library content | A duplicate is attempted | Duplicate assignments are prevented and exact-key duplicate globals are blocked (override requires the library capability and is logged) |
| AC-030 | A global record is edited | It is used by several doctors | Every assigned doctor reflects the change; cache generation bumps and affected search documents rebuild |
| AC-031 | A global is draft, pending, private, or trashed | A public profile/search/REST/Elementor renders | The non-published global never appears |
| AC-032 | A global is trashed then restored, or permanently deleted | Doctors referenced it | Trash retains references and restore re-links; permanent delete removes references with no dangling data or fatal |
| AC-033 | A pre-upgrade doctor with repeater data exists | The library upgrade runs | The doctor renders identically, its data intact as local items |
| AC-034 | A doctor has resolved global + local content | Native template, REST `/doctors/{id}`, search document, and Elementor render | All present the same combined, ordered content |
| AC-035 | Any library mutation occurs | A request is processed | Capability, nonce, sanitization, validation, and context-specific escaping are enforced |

## 7. Edge cases

- Missing photo: render the configured local placeholder with stable dimensions and appropriate alt behavior.
- Duplicate names: distinguish with specialty/location; slugs remain unique.
- Prefix already included in the title: never render “Dr. Dr. …”.
- Deleted media: preserve layout and show the placeholder.
- Doctor removed from one location: invalidate related search/filter caches.
- Empty optional section: omit the heading and wrapper.
- Non-Latin name or term: preserve Unicode; do not require Latin transliteration.
- Staging host in imported URLs: reject or quarantine for review.
- Legacy page contains another doctor's content: flag for editorial review; never infer ownership.
- Search resembles an emergency: display the configured emergency notice/contact, not medical advice.

## 8. Out of scope

Native appointment storage, patient accounts, EHR/EMR features, prescriptions, insurance claims, payment processing, telemedicine sessions, diagnostic recommendations, and identifiable patient-health data are out of scope. Any later proposal requires a separate privacy, security, legal, and architecture review before implementation.
