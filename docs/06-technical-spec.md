# 06 — Technical Specification

Implementation contracts for DoctorPilot. Requirement IDs refer to `02-requirements.md`; durable choices refer to `04-decisions.md`.

## 1. Runtime and compatibility contract

Initial baseline:

| Dependency | Minimum | Behavior when unavailable/incompatible |
|---|---:|---|
| WordPress | 6.4 | Prevent activation with an actionable notice |
| PHP | 8.1 | Prevent activation through plugin header/runtime guard |
| MySQL/MariaDB | WordPress-supported | Avoid engine-specific features unless guarded |
| Elementor | Optional; tested versions declared per release | Native templates/shortcodes continue |
| Elementor Pro | Optional; tested versions declared per release | Dynamic tags/Theme Builder features remain disabled |
| DoctorPilot Pro | Compatible base API required | Safe notice; no fatal; Free continues |

The release CI matrix and plugin headers/readme are authoritative for tested versions. Changing minimum versions requires an ADR or release decision plus compatibility documentation.

## 2. Plugin layout

```text
doctorpilot/
├── doctorpilot.php
├── uninstall.php
├── readme.txt
├── includes/
│   ├── Domain/
│   │   ├── Doctor/
│   │   └── Search/
│   ├── Application/
│   │   ├── Doctors/
│   │   └── Search/
│   ├── Infrastructure/
│   ├── PostTypes/
│   ├── Taxonomies/
│   ├── Fields/
│   ├── Rest/
│   ├── Admin/
│   ├── Frontend/
│   ├── Elementor/
│   ├── Booking/
│   ├── ImportExport/
│   ├── Redirects/
│   ├── Analytics/
│   ├── Seo/
│   └── Support/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── templates/
├── languages/
└── tests/
```

Identifiers:

- Namespace: `BrainStudioz\DoctorPilot`
- Procedural/hook/handle prefix: `bstdz_doctorpilot_`
- Text domain: `doctorpilot`
- REST namespace: `doctorpilot/v1`
- Option prefix: `bstdz_doctorpilot_`
- Private post/term meta prefix: `_doctorpilot_`
- Database table suffix prefix: `bstdz_doctorpilot_`

## 3. Post type and public URLs

Internal post type: `doctorpilot_doctor` (prefixed to prevent collision).

```php
register_post_type(
	'doctorpilot_doctor',
	[
		'public'              => true,
		'has_archive'         => true,
		'rewrite'             => [
			'slug'       => 'find-a-doctor',
			'with_front' => false,
		],
		'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author' ],
		'show_in_rest'        => false,
		'capability_type'     => [ 'doctorpilot_doctor', 'doctorpilot_doctors' ],
		'map_meta_cap'        => true,
		'exclude_from_search' => false,
		'menu_icon'           => 'dashicons-businessperson',
	]
);
```

Notes:

- `show_in_rest` is false for the core post controller in v1. DoctorPilot’s custom public controller owns the public allowlist. A future block-editor requirement may change this through an ADR/security review.
- Default URL: `/find-a-doctor/{post_name}/`.
- The plugin does not force `dr-`. Imports may preserve an explicit `dr-...` post slug.
- Directory-base changes validate reserved slugs and flush rewrites once after saving.
- Changing an existing doctor slug must offer an explicit old-path redirect (Migration tier); Free must at least warn about URL impact.
- WordPress `post_status=publish` is the public-visibility authority.

## 4. Taxonomy contract

| Label | Internal key | Default public rewrite | Hierarchical | Tier |
|---|---|---|:---:|:---:|
| Department | `doctorpilot_department` | `doctor-department` | ✓ | Free |
| Specialty | `doctorpilot_specialty` | `doctor-specialty` | ✓ | Free |
| Symptom | `doctorpilot_symptom` | `symptom` | ✓ | Free |
| Condition | `doctorpilot_condition` | `condition` | ✓ | Free |
| Treatment | `doctorpilot_treatment` | `treatment` | ✓ | Free |
| Language | `doctorpilot_language` | disabled by default | ✓ | Free |
| Location | `doctorpilot_location` | disabled by default | ✓ | Free/Pro |
| Insurance | `doctorpilot_insurance` | disabled by default | ✓ | Pro |

All directory taxonomies are hierarchical (category-style, checkbox UI) so terms are managed as a curated, structured vocabulary rather than free-text tags.

### 4.0 Term creation from the editor

The doctor editor form submits, per taxonomy field, `doctorpilot[new_terms_{taxonomy}][]` — an array of term **names** — alongside the usual `doctorpilot[terms_{taxonomy}][]` ID list. `SaveDoctor::save_terms()` resolves it before assigning terms:

- Skipped entirely unless `current_user_can( 'manage_doctorpilot_terms' )`; the taxonomy's `assign_terms` capability alone does not allow creating terms.
- Each name is run through `sanitize_text_field()` and trimmed; empty names, names longer than 200 characters, and duplicates within the request (case-insensitive) are dropped; at most 100 names per field per save.
- An existing term with that name (`get_term_by( 'name' )`) is reused; otherwise `wp_insert_term()` creates it at the top level. A `term_exists` error from a concurrent save resolves to the existing ID, so a retried save is idempotent.
- The resulting IDs are merged with the submitted IDs before the existing validation (`term_exists`) and `wp_set_object_terms()`.
- Only taxonomy terms are created this way. Global library records are separate posts and are never created from this field.

All attach to `doctorpilot_doctor`. Public archives are enabled only when a supported native/Elementor template exists; otherwise rewrite is false to avoid thin pages.

### 4.1 Symptom term metadata

| Key | Type | Visibility | Rule |
|---|---|---|---|
| `_doctorpilot_alt_names` | array of strings | Public | Bounded, sanitized synonyms |
| `_doctorpilot_short_explanation` | string | Public | Informational, non-diagnostic |
| `_doctorpilot_related_department_ids` | array of ints | Public relationship | Must reference existing Department terms |
| `_doctorpilot_related_specialty_ids` | array of ints | Public relationship | Must reference existing Specialty terms |
| `_doctorpilot_priority` | int | Internal | Bounded sort value |
| `_doctorpilot_az_listed` | bool | Public behavior | Include in A–Z directory |
| `_doctorpilot_seo_title` | string | Public when SEO owner uses it | Plain text |
| `_doctorpilot_seo_description` | string | Public when SEO owner uses it | Plain text, bounded |
| `_doctorpilot_reviewed_by` | user ID | Private | Audit reference only |
| `_doctorpilot_reviewed_at` | UTC datetime | Private | Set on approved relationship edit |

Term metadata for other clinical taxonomies follows the same prefix, schema, and review rules.

## 5. Field registry

Each field definition contains:

```php
[
	'key'           => '_doctorpilot_last_name',
	'group'         => 'profile',
	'type'          => 'string',
	'default'       => '',
	'tier'          => 'free',
	'public'        => true,
	'sanitize'      => 'sanitize_text_field',
	'validate'      => NameRule::class,
	'rest_schema'   => [ 'type' => 'string', 'maxLength' => 200 ],
	'searchable'    => true,
	'elementor_tag' => true,
]
```

The registry powers admin fields, meta registration, validation, public serializer decisions, migration mapping, search derivation, Elementor tags, and documentation tests. Storage-specific exceptions require an explicit adapter, not a second schema.

### 5.1 Canonical field/storage map

| Domain value | Storage | Key/contract | Tier |
|---|---|---|:---:|
| Display name | Post title | `post_title` | Free |
| Biography | Post content | `post_content` | Free |
| Short introduction | Post excerpt | `post_excerpt` | Free |
| Photo | Featured image | thumbnail ID | Free |
| Prefix | Post meta | `_doctorpilot_prefix` | Free |
| Gender | Post meta | `_doctorpilot_gender` | Free |
| Featured | Post meta | `_doctorpilot_is_featured` bool | Free |
| International doctor | Post meta | `_doctorpilot_is_international` bool | Free |
| Upcoming visit dates | Post meta | `_doctorpilot_visit_dates` free text; on the full view only, rendered by the Page Header for international doctors | Free |
| Years of experience | Post meta | `_doctorpilot_years_experience` non-negative int | Free |
| Manual order | Post meta | `_doctorpilot_manual_order` bounded non-negative int | Free |
| License number/authority | Post meta | `_doctorpilot_license_*`; private by default | Free |
| Departments/specialties/clinical terms/languages | Term relationships | Prefixed taxonomies | Free |
| Primary department | Post meta term ID | `_doctorpilot_primary_department_id` | Free |
| Primary specialty | Post meta term ID | `_doctorpilot_primary_specialty_id` | Free |
| Leadership | Array meta | `_doctorpilot_leadership_positions` | Free |
| Experience (local rows) | Array meta | `_doctorpilot_exp_local` | Free |
| Experience (global refs) | Array meta (int IDs) | `_doctorpilot_exp_ids` → `doctorpilot_exp` CPT | Free |
| Education (local rows) | Array meta | `_doctorpilot_edu_local` | Free |
| Education (global refs) | Array meta (int IDs) | `_doctorpilot_edu_ids` → `doctorpilot_edu` CPT | Free |
| Certifications/memberships | Array meta | `_doctorpilot_certifications` | Free |
| Publications | Array meta | `_doctorpilot_publications` | Free |
| Procedures | Array meta | `_doctorpilot_procedures` | Free |
| Clinical interests | Array meta | `_doctorpilot_clinical_interests` | Free |
| FAQs | Array meta | `_doctorpilot_faqs` | Free |
| Location/contact records | Array meta | `_doctorpilot_locations`; the editor edits the first record only, further records are preserved on save; `doctorpilot/locations/max` caps storage | Free/Pro |
| Consultation duration | Post meta | `_doctorpilot_consultation_duration_minutes` positive int | Free |
| Consultation price | Post meta | `_doctorpilot_consultation_price_minor` int plus `_doctorpilot_consultation_currency` ISO code | Free |
| Booking method/config | Post meta | `_doctorpilot_booking_*` | Free/Pro |
| SEO overrides | Post meta | `_doctorpilot_seo_*` | Free |
| Review state | Post meta | `_doctorpilot_review_status` | Free |
| Completeness | Derived post meta | `_doctorpilot_completeness_score` | Free |
| External source ID | Private post meta | `_doctorpilot_external_source_id` | Migration tier |
| Search document | Derived private post meta | `_doctorpilot_search_document` | Free |
| Normalized sort name | Derived private post meta | `_doctorpilot_sort_name` | Free |

### 5.2 Repeater shapes

All arrays have item and total-item limits defined in the field registry.

- Leadership: `{title, organization_or_scope, start_year?, end_year?}`
- Education/experience: `{type, institution, qualification_or_position, start_year?, end_year?, country?, description?}`
- Certification: `{name, organization?, issued_date?, expiry_date?, credential_url?}`
- Publication: `{title, journal?, published_date?, url?, doi?, description?}`
- Procedure: `{name, description?}`
- FAQ: `{question, answer}`
- Location: `{location_term_id, department_term_id?, phone?, public_email?, email_is_public, room_floor?, consultation_type, office_hours_text?, booking_override?}`

Rich text is allowlisted through an approved `wp_kses` policy. Nested values are sanitized recursively; unknown keys are discarded.

### 5.3 Invariants

- Primary department/specialty must be among assigned taxonomy terms.
- Free stores at most one location; Pro may increase the registry limit without changing the canonical key.
- A public email requires `email_is_public=true`.
- Empty repeaters are stored consistently as absent/empty according to repository policy.
- Completeness is computed, never accepted from clients.
- A profile cannot publish until required fields and review rules pass, unless an administrator uses a logged explicit override.
- Prefix rendering detects whether the display title already contains that prefix.

### 5.4 Global content library (ADR-0017)

Procedures, Experience & Education, Publications, and FAQs use a hybrid model,
specified in full in `11-global-content-library.md`:

- Global records are five prefixed Free custom post types: `doctorpilot_exp`,
  `doctorpilot_edu`, `doctorpilot_proc`, `doctorpilot_pub`, `doctorpilot_faq`
  (all ≤ 20 chars). Fields are described by `LibraryPostTypes::schema()` — one
  `store => content` field per type in `post_content`, the rest as prefixed meta
  `_doctorpilot_lib_{key}`; the title is computed read-only from the type's
  `title_parts`. All are `show_in_rest=false`, no public archive; `post_status`
  is the publication authority; managed via `manage_doctorpilot_library`.
- Doctors reference globals through ordered, unique ID lists registered in the
  one `FieldRegistry`: `_doctorpilot_{exp|edu|proc|pub|faq}_ids`. Local
  (doctor-specific) items live in repeaters: Experience/Education use the split
  `_doctorpilot_exp_local` / `_doctorpilot_edu_local`; Procedures, Publications,
  and FAQs reuse the existing `_doctorpilot_procedures`,
  `_doctorpilot_publications`, `_doctorpilot_faqs` (no data migration). Each
  global stores a rebuildable reverse-reference index in `_doctorpilot_lib_doctors`.
  Library records are not part of the doctor search document, so a global edit
  reflects live through `DoctorContentResolver` with no rebuild; only
  `before_delete_post` cleanup runs. (The originally-planned
  `_doctorpilot_dedupe_key` normalized-duplicate key is not yet implemented.)
- A single `DoctorContentResolver` returns the merged, ordered list per type
  (published globals first, then local items in public context), tagging each
  item's origin in admin context only. All consumers (native templates, search
  document, `GET /doctors/{id}`, Elementor Doctor Profile) read through it. §7
  adds the authenticated internal route `GET doctorpilot/v1/library/search`.
- A global edit/unpublish/trash/delete/merge increments
  `bstdz_doctorpilot_cache_generation` immediately and enqueues bounded rebuilds
  of affected doctors' search documents via the reverse index. Lifecycle,
  invalidation, output schemas, and duplicate-prevention rules are defined in
  `11-global-content-library.md` §3–§9.

## 6. Capabilities

Object capabilities use the post type’s mapped capability model. Required primitives include:

- `read_doctorpilot_doctor`
- `read_private_doctorpilot_doctors`
- `edit_doctorpilot_doctor`
- `edit_doctorpilot_doctors`
- `edit_others_doctorpilot_doctors`
- `edit_published_doctorpilot_doctors`
- `publish_doctorpilot_doctors`
- `delete_doctorpilot_doctor`
- `delete_doctorpilot_doctors`
- `delete_others_doctorpilot_doctors`
- `manage_doctorpilot`
- `manage_doctorpilot_terms`
- `manage_doctorpilot_library`
- `manage_doctorpilot_migrations` (Migration tier)
- `view_doctorpilot_analytics` (Pro)

Installation grants appropriate capabilities to administrators idempotently. Optional role presets are separate from authorization. Department/self-edit scope is checked after meta-cap mapping and cannot be bypassed by sending a different department in the request.

## 7. Public REST API

Namespace: `doctorpilot/v1`.

| Method | Route | Purpose | Cache |
|---|---|---|---|
| GET | `/doctors` | Search, filter, sort, paginate, facets | Public cacheable |
| GET | `/doctors/suggest` | Bounded suggestions | Short public cache |
| GET | `/doctors/(?P<id>\d+)` | Public doctor view model | Public cacheable |
| GET | `/facets` | Available public filter options/counts | Public cacheable |
| POST | `/analytics/search` | Optional aggregate event | No-store; Pro/opt-in |
| GET | `/library/search` | **Authenticated** library-item lookup for the doctor editor | No-store (authenticated) |

Every route supplies an explicit `permission_callback`. Public routes use a deliberate public callback, not a missing permission handler. `/library/search` is authenticated and is the only non-public route in this namespace.

### 7.0 Library search route contract (`GET /library/search`)

Used by the doctor editor's assignment UI. Authenticated, never cached.

**Parameters**

| Parameter | Type/default | Limits |
|---|---|---|
| `type` | enum, required | `exp`, `edu`, `proc`, `pub`, `faq` |
| `search` | string/empty | 0–100 Unicode characters |
| `doctor_id` | int/optional | If present, the doctor being edited |
| `page` | int `1` | min 1; reject unreasonable offsets |
| `per_page` | int `20` | min 1, max 50 |
| `exclude[]` | int[]/empty | already-assigned IDs to omit; max 100 |

**Permission callback**

- If `doctor_id` is supplied: `current_user_can( 'edit_doctorpilot_doctor', doctor_id )` — the user must be able to edit *that* doctor (object check), not merely hold the broad collection capability.
- If `doctor_id` is absent: `current_user_can( 'edit_doctorpilot_doctors' )`.
- Never a missing/`__return_true` handler.

**Visibility**

- Returns `publish` and `draft` library items (editors assign draft items knowingly), each with a `status` field. `private`/`trash` are excluded. No private/internal fields are returned — only display fields needed to choose an item.

**Success response**

```json
{
  "data": [
    { "id": 501, "type": "pub", "title": "…", "subtitle": "Journal, 2024", "status": "publish" }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 42, "total_pages": 3 }
}
```

**Errors**: stable `WP_Error` codes reusing the §7.3 contract —
`doctorpilot_invalid_parameter` (bad `type`/bounds), `doctorpilot_forbidden`
(permission), `doctorpilot_internal_error`. No SQL/paths/secrets exposed.

**Cache**: `no-store`; results are per-user/editor context and must not be
publicly cached. Bounded `per_page` and `exclude[]` prevent abuse.

### 7.1 Canonical search parameters

| Parameter | Type/default | Limits |
|---|---|---|
| `q` | string/empty | 0–100 Unicode characters |
| `mode` | enum `universal` | `name`, `department`, `specialty`, `symptom`, `universal` |
| `department`, `specialty`, `symptom`, `condition`, `treatment`, `language`, `location`, `insurance` | comma-separated slugs | max 10 values per filter; normalized/sorted; tier rules apply |
| `doctor` | comma-separated doctor post slugs | max 10; normalized/sorted; restricts candidates to those doctors before scoring |
| Boolean filters | `0\|1` | online |
| `international` | `0\|1` | Tri-state: `1` international only, `0` national only, absent for either. Unlike the other booleans, `0` is a filter rather than a clear, and matches a stored `0` **or** a missing meta row |
| `gender` | allowed enum | registry-defined |
| `date` | ISO `YYYY-MM-DD` | Pro; valid site-local date |
| `sort` | enum `relevance` | `relevance`, `name` (A→Z), `name_desc` (Z→A), `featured`, `experience`, `recent`, `availability` (Pro) |
| `view` | enum `grid` | `grid`, `list` |
| `page` | int `1` | min 1; reject unreasonable offsets |
| `per_page` | int `12` | min 1, max 48; falls back to the site's General → per-page setting when omitted |

`per_page` is honoured on `GET /doctors` so a caller with its own page size (for
example an Elementor Doctor Grid/List widget) returns the same number of results
from its server-rendered first paint and its later no-reload requests. The value
is clamped by the request factory and forms part of the request signature, so the
response cache stays correct per page size.

Unknown parameters are ignored or rejected consistently according to controller policy. The frontend serializer orders filter names and values so equivalent states produce one canonical URL/cache key.

### 7.2 Success response

```json
{
  "data": [
    {
      "id": 101,
      "slug": "dr-ali-a-haydar",
      "name": "Dr. Ali A. Haydar",
      "url": "https://example.org/find-a-doctor/dr-ali-a-haydar/",
      "photo": {
        "url": "https://example.org/uploads/doctor.jpg",
        "alt": "",
        "width": 640,
        "height": 800
      },
      "primary_department": {"id": 7, "name": "Radiology", "slug": "radiology"},
      "primary_specialty": {"id": 12, "name": "Interventional Radiology", "slug": "interventional-radiology"},
      "years_experience": 15,
      "languages": [{"id": 20, "name": "English", "slug": "english"}],
      "consultation_type": "both",
      "booking": {
        "type": "external_url",
        "label": "Book Appointment",
        "url": "https://booking.example.org/doctor/101",
        "target": "_blank",
        "availability_verified": false
      }
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 12,
    "total": 24,
    "total_pages": 2,
    "sort": "relevance",
    "request_id": "dp_01H..."
  },
  "facets": {
    "department": [{"slug": "radiology", "label": "Radiology", "count": 6}]
  },
  "active_filters": [
    {"type": "department", "value": "radiology", "label": "Radiology"}
  ],
  "suggestions": []
}
```

Rules:

- Only `post_status=publish` enters public responses.
- Serializer allowlists fields and never forwards raw meta.
- Absolute URLs use site URL helpers, not hardcoded hosts.
- `next_available` is omitted unless a compatible provider returns verified data.
- Standard `X-WP-Total` and `X-WP-TotalPages` headers may mirror envelope totals.

### 7.3 Error contract

Use `WP_Error`/REST conventions:

```json
{
  "code": "doctorpilot_invalid_filter",
  "message": "One or more filters are invalid.",
  "data": {
    "status": 400,
    "details": {
      "department": "Unknown term slug."
    },
    "request_id": "dp_01H..."
  }
}
```

Stable codes include `doctorpilot_invalid_parameter`, `doctorpilot_invalid_filter`, `doctorpilot_not_found`, `doctorpilot_forbidden`, `doctorpilot_rate_limited`, `doctorpilot_provider_unavailable`, and `doctorpilot_internal_error`. Internal errors do not expose SQL, paths, secrets, or stack traces.

## 8. Search normalization and ranking

### 8.1 Normalization

- Trim/collapse whitespace.
- Apply WordPress accent removal where supported while retaining original Unicode.
- Case-fold with multibyte-safe behavior.
- Strip an approved set of common title prefixes for comparison while preserving display name.
- Match curated synonyms/alternative names only; no auto-generated clinical equivalence.
- Ignore tokens below configured meaningful length except exact names/initials.

### 8.2 Candidate and scoring contract

Visibility/taxonomy filters are applied before scoring. Candidate retrieval is bounded; pagination occurs after deterministic scoring.

Default scoring:

| Match | Score |
|---|---:|
| Exact normalized display name | 1000 |
| Display name begins with complete query | 850 |
| All query tokens match display name | 750 |
| Exact primary specialty/department | 650 |
| Exact assigned specialty/department | 600 |
| Direct symptom assignment | 550 |
| Symptom-curated specialty/department relationship | 450 |
| Exact condition/treatment assignment | 400 |
| Clinical-interest/search-document match | 250 |
| Featured tie boost | +30 |

Tie order: score descending, featured descending, manual order ascending, normalized name ascending, post ID ascending. Ranking weights may be filtered by code but are not an arbitrary public request parameter.

### 8.3 No-result contract

Suggestions come only from:

- close doctor-name or curated synonym matches;
- curated related departments/specialties/symptoms;
- configured hospital contact/emergency content; and
- configured featured doctors.

No-result content cannot infer diagnosis, urgency, or treatment.

## 9. Caching and invalidation

- Option `bstdz_doctorpilot_cache_generation` is a monotonic integer.
- Public cache key includes API schema version, generation, locale, normalized parameters, and relevant tier/provider capability state.
- Transient/object-cache TTLs are configurable within safe bounds; correctness does not rely on expiration.
- Saving/deleting/publishing a doctor, changing relevant term/meta, changing public settings, completing migration, or changing provider availability integration increments generation.
- Derived doctor search meta rebuilds synchronously for a single edit and via bounded background jobs for bulk changes.
- Authenticated previews, import state, secrets, and private fields are never written to public caches.

## 10. Frontend and Elementor

### 10.1 Native contracts

- Shortcodes: `[doctorpilot_search]`, `[doctorpilot_directory]`, `[doctorpilot_profile id=""]`.
- Shortcode attributes use a declared schema and the same query/view services as REST.
- Template lookup order: theme `doctorpilot/{template}.php` → child-theme equivalent as resolved by WordPress → plugin default.
- Server-rendered GET forms use the same canonical parameter names as REST.

### 10.1a Packaging

One artifact, `doctorpilot`, contains everything. The former `doctorpilot-pro`
add-on was merged in at 0.2.0 (ADR-0020): its 34 runtime classes moved to the
base namespace at their existing relative paths, and its modules are booted
directly from `Infrastructure\\Plugin::boot()` rather than through the
`doctorpilot/registered` handshake.

`ExtensionRegistry`, the `doctorpilot/registered` action and
`DOCTORPILOT_BASE_API_VERSION` are retained as a published contract for
third-party add-ons; nothing in this repository consumes them.

Identifiers kept from the add-on, unchanged because they name live rows:

| Kind | Name |
| --- | --- |
| Tables | `{prefix}bstdz_doctorpilot_redirects`, `_import_jobs`, `_import_items`, `_analytics` |
| Options | `bstdz_doctorpilot_pro_version`, `bstdz_doctorpilot_pro_db_version` |
| Cron | `bstdz_doctorpilot_pro_retention`, `bstdz_doctorpilot_pro_run_import` |
| Roles | `doctorpilot_department_manager`, `doctorpilot_doctor` |
| Capabilities | `manage_doctorpilot_migrations`, `view_doctorpilot_analytics` |

`Infrastructure\\LegacyAddon` retires a leftover `doctorpilot-pro` on
`admin_init`: it deactivates it through the silent form of
`deactivate_plugins()`, so the add-on's deactivation handler — which clears both
cron hooks above — never runs. The same class re-creates any missing recurring
schedule on every admin request, so a cleared schedule repairs itself rather
than waiting for a reactivation.

Schema version 3 adopts all of the above on upgrade, since merging code does not
re-run an activation hook. Every step is idempotent: `Tables::create()` goes
through `dbDelta`, `Roles::install()` and `Capabilities::grant_default_roles()`
are additive grants, and the retention schedule is only created when nothing is
scheduled. The result is a no-op on a site that ran both plugins and a first
install on one that never did.

### 10.2 Elementor contracts

**`the_content` is not a filter, where Elementor is concerned.** `Elementor\Frontend::apply_builder_in_content()`
is hooked to `the_content` at priority 9 and replaces the string it is given with
the whole rendered document for `get_the_ID()`. Any plugin code that calls
`apply_filters( 'the_content', … )` from inside a widget therefore renders the
containing document inside that widget. `DoctorView::rendered_biography()` is the
only place DoctorPilot calls it, and it suspends that one filter for the duration
via Elementor's public `remove_content_filter()` / `add_content_filter()`, restoring
it only when it was registered on entry — Elementor drops it during its own nested
renders, and re-adding it there would builder-inject the outer content. A static
re-entrancy guard backs this up. New code must not call `the_content` without the
same protection.

**Button link overrides.** `AbstractWidget::link_override( $settings, $key )`
turns an Elementor URL control into `{ url, target, rel, attrs }`. The URL is
passed through `esc_url_raw()`, so a disallowed scheme resolves to an empty
`url` (no override) rather than a link; `is_external` becomes `target="_blank"`
plus `rel="noopener"`, and `nofollow` is appended to `rel`. Page Header uses it
for `book_link` and `call_link`; Doctor Grid / List uses it for
`booking_button_link`, which `CardElementControls::register_card_elements_section( true )`
registers under the Booking button switch (the carousel and related-doctors card
pass nothing and get no control). The grid passes the result to
`templates/directory.php`, which emits `data-booking-url`, `data-booking-target`
and `data-booking-rel` on the directory root; `templates/card-doctor.php` reads
`$data['booking_link']` and `assets/js/directory.js` reads the three attributes,
so a card rebuilt after a filter change points where the server-rendered one
did. Both renderers fall back to the same label — the doctor's booking label,
else *Book an Appointment* (`strings.bookNow`) — because a set link renders the
button for a doctor with no booking CTA of their own.

**Built-in widget heading.** `AbstractWidget` provides an optional eyebrow +
heading block through three methods: `register_heading_controls()` (call inside
an open content section), `register_heading_style()` (call at the top level of
`register_controls()`; it opens its own section) and `render_heading( $settings )`
(call in `render()` *after* the widget has confirmed it has content, so an empty
widget never leaves an orphan heading). Content control IDs: `heading_show`,
`eyebrow_text`, `heading_text`, `heading_tag`. `heading_show` defaults to off, so
adding the set to a widget changes nothing until an editor enables it. Markup is
`.dp-wh` > `.dp-wh__eyebrow` + `.dp-wh__title`; the tag is validated against
`heading_tags()` and falls back to `h2`.

`register_heading_style()` opens **two** Style-tab sections — `section_eyebrow_style`
and `section_heading_style` — both conditioned on `heading_show`. Each is built by
the private `register_wh_part_style()` from one prefix and one selector, so the two
lines cannot drift apart control by control:

| Control | Eyebrow ID | Heading ID | Applies to |
|---|---|---|---|
| Typography (group) | `eyebrow_typography` | `wh_title_typography` | base |
| Padding | `eyebrow_padding` | `wh_title_padding` | base |
| Margin | `eyebrow_margin` | `wh_title_margin` | base |
| Border radius | `eyebrow_radius` | `wh_title_radius` | base |
| Fit background to text | `eyebrow_inline` | `wh_title_inline` | base (`width: fit-content`) |
| Transition duration | `eyebrow_transition` | `wh_title_transition` | base |
| Text color | `eyebrow_color` | `wh_title_color` | Normal tab |
| Background (group) | `eyebrow_bg` | `wh_title_bg` | Normal tab |
| Box shadow (group) | `eyebrow_shadow` | `wh_title_shadow` | Normal tab |
| Text color | `eyebrow_color_hover` | `wh_title_color_hover` | Hover tab (`:hover`) |
| Background (group) | `eyebrow_bg_hover` | `wh_title_bg_hover` | Hover tab (`:hover`) |
| Box shadow (group) | `eyebrow_shadow_hover` | `wh_title_shadow_hover` | Hover tab (`:hover`) |

`wh_spacing` stays in the Heading section: it is the margin below the whole
`.dp-wh` block, not below the heading line.

Both lines are block-level, so a background paints the full column width. "Fit
background to text" is the opt-out, off by default — no widget carries a
background yet, so neither default changes an existing page.

`eyebrow_color`, `eyebrow_typography`, `wh_title_color`, `wh_title_typography` and
`wh_spacing` keep the IDs they were introduced with, and moving a control between
sections or into a tab preserves its saved value: Elementor stores settings flat,
by control ID. Renaming one would resolve it to the control default instead and
silently restyle every page already built.

Every widget registers it except `HeadingBlock`, which is the heading widget and
already owns `heading_show` / `heading_text`.

Widgets:

- Doctor Search
- Doctor Carousel / Grid — a curated set of doctors as a scroll-snap carousel or
  a static grid. Same card, presets, style panel and Card elements switches as
  Doctor Grid / List via `Concerns\CardDesignControls` + `Concerns\
  CardElementControls`; no search, filters, A–Z or pagination. Template:
  `carousel-doctor.php` (which includes `card-doctor.php`). Assets:
  `assets/css/carousel.css` (depends on the premium card layer),
  `assets/js/carousel.js` (no dependencies).

  Selection: one `tax_{param}` SELECT2 per entry in
  `SearchRequestFactory::filter_taxonomies()`, storing **term slugs** — the same
  vocabulary the search request, the REST route and a shareable directory URL
  use. Values are handed to `SearchRequestFactory::from_array()` and
  `SearchService::search()`; there is no separate query path. `use_current_term`
  replaces the panel's picks for whichever taxonomy the current term archive
  belongs to, and only that one. `SearchRequestFactory` caps each taxonomy at 10
  slugs and the request at `MAX_PER_PAGE` (48).

  Term lists are fetched only for the editor panel (`is_admin()` or edit mode)
  and memoised per request; the control itself is registered in both contexts, so
  the control set never differs between panel and page.

  Carousel mechanics live in the browser: the viewport is
  `overflow-x: auto` + `scroll-snap-type: x mandatory`, and the script moves it
  with `scrollTo`. A "page" is one viewport width. The script reads the current
  page back out of `scrollLeft`, so a swipe and a button press converge on the
  same state. Controls are emitted `hidden` and revealed on boot; a track that
  needs no paging hides them again. Autoplay is suspended by a named set of
  holds (hover / focus / pointer / hidden tab), so releasing one does not resume
  while another stands, and an explicit pause outranks all of them.

- Doctor Grid/List — card grid or compact list, with an optional search bar,
  browse tabs, collapsible filter row (sort / gender / language / department /
  specialty / symptom / online), A–Z index, result count, and
  pagination. Templates: `directory.php`, `row-doctor.php`, `row-term.php`,
  `card-doctor.php`.

  **Card elements.** `Concerns\CardElementControls` owns the per-part show/hide
  switches and is the single source of truth for part names. It is used by
  `DoctorGridList`, `DoctorCarousel` and `Elementor\RelatedDoctors`.

  | Part | Setting key |
  | --- | --- |
  | `image` | `image_show` |
  | `badge_featured` | `badge_featured_show` |
  | `badge_international` | `badge_international_show` |
  | `specialty` | `specialty_show` |
  | `bio` (the Headline; the part name predates the label) | `bio_show` |
  | `experience` | `experience_show` |
  | `languages` | `languages_show` |
  | `profile_button` | `profile_button_show` |
  | `booking_button` | `booking_button_show` |

  `card_parts()` resolves the switches to `array<string, bool>` and is passed to
  the template as `$data['parts']`; an absent part resolves to visible, which is
  what lets the shortcode, the archive template and theme overrides render the
  complete card without passing anything. `card_hidden_parts()` inverts the same
  array into the `data-card-hide` attribute on the directory root, which
  `Directory.prototype.showPart()` reads so client-rendered cards match
  server-rendered ones. Hidden parts are not emitted, never merely
  `display: none`. Hiding `image` also adds the `dp-card--no-media` class, in
  both renderers.

  The widget renders two regions that are visible together. The **results**
  region always holds the doctor output of the shared search services and
  refreshes from the public `/doctors` route. The **index** region sits above it
  and holds one hidden panel per browse tab; the `mode` parameter decides which
  one is shown.

  | Tab | Index source | Bound | Parameter an entry writes |
  | --- | --- | --- | --- |
  | By Doctor Name | `WP_Query` over published doctors; ordered in PHP by honorific-stripped name | 500 posts | `doctor` (post slug) |
  | By Department | `get_terms( hide_empty )` | 500 terms | `department` |
  | By Specialty | `get_terms( hide_empty )` | 500 terms | `specialty` |
  | By Symptom | `get_terms( hide_empty )` | 500 terms | `symptom` |

  Choosing an entry writes its parameter into the shared search state, so it
  reaches `SearchRequest`, the request signature, the REST cache key, and the
  active-filter chips exactly like a filter-row selection. Choosing the current
  selection again clears it; switching tabs clears the tab being left, so no
  filter survives without a visible control. A parameter owned by a rendered
  index is not also offered as a select in the filter row.

  One widget-local GET parameter extends the shared search state: `letter`
  (`A`–`Z`; anything else, including a stale `#`, means "all"). It filters the index only, never the results, and is ignored
  when the A–Z bar is switched off, since nothing would be able to clear it. `q`
  does double duty — it filters the index client-side by name *and* runs as the
  ordinary search query against the results. Index filtering is applied by
  marking non-matching rows `hidden` rather than omitting them, so the same
  markup serves the no-JavaScript `?letter=` / `?q=` links and instant
  client-side filtering, and clearing a filter restores the full list without a
  round trip. A–Z letters left with no matching entry are marked `is-empty` and
  are not clickable.

  Doctor-name index sub-labels use the doctor's primary specialty, then primary
  department, then the first assigned term of either — a label, unlike the card,
  should not be blank just because no primary was chosen.
- Doctor Profile
- Department Grid
- Symptom Directory
- Booking Button

Pro widgets:

- Advanced Doctor Search
- Doctor Carousel
- Doctor Information/section widgets
- Doctor Locations
- Doctor Schedule
- Related Doctors
- Results Count
- Active Filters
- Pagination

Widgets declare unique prefixed names, style/script dependencies, and semantic render output. Dynamic tags use current Elementor category constants and load only when active APIs support them. Theme conditions extend Elementor Pro and are never referenced before compatibility checks.

## 11. Single-doctor view order

Render non-empty sections only:

1. Breadcrumbs
2. Hero: photo, name, primary department/specialty, main booking CTA
3. Short introduction / About
4. Scope of practice and clinical interests
5. Procedures and treatments
6. Experience and education
7. Certifications and memberships
8. Publications
9. FAQs
10. Locations and verified schedule/availability
11. Related doctors
12. Final booking CTA
13. Medical disclaimer where clinical discovery terms are shown

Heading hierarchy must be valid even when sections are omitted.

## 12. Booking adapter

```php
interface BookingProviderInterface {
	public function id(): string;
	public function capabilities(): BookingCapabilities;
	public function resolve(DoctorId $doctor_id, ?LocationId $location_id = null): BookingCta|\WP_Error;
}
```

`BookingCapabilities` declares location, schedule, and verified-availability support. `BookingCta` contains only:

- `type`: `external_url|phone|enquiry|bookpilot`
- `label`
- `url` or normalized phone destination
- `target`: `_self|_blank`
- `availability_verified`: bool
- optional safe analytics action name, if analytics are enabled

Implementations: `ExternalUrlProvider`, `PhoneProvider`, `EnquiryProvider`, and Pro `BookPilotProvider`. Output is revalidated after provider resolution. A configured fallback is explicit; failures never fabricate an appointment time.

## 13. Settings

Use one versioned option object, `bstdz_doctorpilot_settings`, with schema:

```json
{
  "version": 1,
  "general": {
    "directory_base": "find-a-doctor",
    "per_page": 12,
    "default_sort": "relevance",
    "placeholder_attachment_id": 0,
    "single_doctor_template": 0,
    "single_doctor_template_international": 0
  },
  "search": {
    "minimum_characters": 2,
    "suggestion_limit": 8,
    "enabled_filters": ["department", "specialty", "symptom", "gender", "language"],
    "cache_ttl_seconds": 300
  },
  "booking": {
    "default_type": "external_url",
    "default_label": "Book Appointment",
    "default_target": "_self"
  },
  "design": {
    "card_radius": "8px",
    "image_ratio": "4/5"
  },
  "privacy": {
    "analytics_enabled": false,
    "analytics_retention_days": 90
  },
  "data": {
    "delete_on_uninstall": false
  }
}
```

`general.single_doctor_template` is the layout for doctors without the International flag; `general.single_doctor_template_international` is the layout for doctors with `_doctorpilot_is_international` set. Both are an Elementor library template or page ID, `0` meaning "not set". `Settings::single_doctor_template_for( $doctor_id )` resolves a doctor to one ID: the International ID when it is > 0, the doctor is flagged, and the post is `publish` or `private`; otherwise the standard ID (which may itself be 0 for the native template). The key is merged in by `merge_defaults()`; no schema version bump or migration is needed.

Unknown keys are discarded. Secrets are stored separately from exportable design/general settings and never autoloaded when large. Settings validation enforces ranges and safe enums.

## 14. Database tables

All tables below are owned by the single `doctorpilot` artifact and versioned with it. They were created by the `doctorpilot-pro` add-on before the merge (ADR-0020) and keep their original names, so an upgraded site keeps every row. `Support\Tables` is the single source of truth for the names; `Tables::create()` builds them through `dbDelta` and `Tables::drop()` removes them on an opt-in uninstall. Table names use `$wpdb->prefix`.

### 14.1 Redirects: `bstdz_doctorpilot_redirects`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | Auto increment |
| `source_path` | varchar(255) | Normalized same-site path; unique |
| `doctor_id` | bigint unsigned | Destination post ID |
| `destination_path` | varchar(255) | Reviewed resolved path |
| `status_code` | smallint | 301 in v1 |
| `is_active` | tinyint(1) | Indexed |
| `created_at`, `updated_at` | datetime UTC | Audit |

Indexes: unique `source_path`; `doctor_id`; composite `(is_active, source_path)`. Validation rejects self-redirects, chains/loops, query-only sources, directory-home fallbacks, and staging/external destinations.

### 14.2 Import jobs: `bstdz_doctorpilot_import_jobs`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `job_uuid` | char(36) | Unique public-safe job reference |
| `status` | varchar(24) | uploaded/mapped/validated/importing/paused/completed/failed |
| `source_name` | varchar(190) | Sanitized display name; not raw path |
| `mapping_json` | longtext | Validated mapping |
| `cursor` | bigint unsigned | Resume checkpoint |
| `created_by` | bigint unsigned | Admin user ID |
| `total_rows` | bigint unsigned | Total accepted rows |
| `created_rows` | bigint unsigned | Created count |
| `updated_rows` | bigint unsigned | Updated count |
| `skipped_rows` | bigint unsigned | Skipped count |
| `failed_rows` | bigint unsigned | Failed count |
| `created_at`, `updated_at`, `expires_at` | datetime UTC | Retention |

### 14.3 Import items: `bstdz_doctorpilot_import_items`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `job_id` | bigint unsigned | Indexed |
| `row_number` | bigint unsigned | Unique with job |
| `source_key` | varchar(190) | Indexed; idempotency |
| `row_checksum` | char(64) | SHA-256 |
| `doctor_id` | bigint unsigned nullable | Result |
| `status` | varchar(20) | pending/created/updated/skipped/failed |
| `error_code` | varchar(100) nullable | Stable safe code |
| `error_message` | text nullable | No full row/private data |

Indexes: unique `(job_id,row_number)`; `(job_id,status)`; `source_key`.

### 14.4 Analytics: `bstdz_doctorpilot_analytics_daily`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `event_date` | date | Site-local aggregate date |
| `event_type` | varchar(32) | search/doctor_view/symptom/no_result |
| `dimension_hash` | char(64) | Hash of normalized approved dimension |
| `dimension_label` | varchar(190) | Optional normalized non-identifying label |
| `count` | bigint unsigned | Incremented aggregate |

Unique index `(event_date,event_type,dimension_hash)`; retention index `event_date`. No IP, user/session/cookie ID, or raw user-agent column is permitted.

## 15. CSV import/export and migration

### 15.1 Import pipeline

`upload → validate file → map → preview → dry run → enqueue chunks → import draft/pending → review → approve/publish → redirects → launch verification`

Rules:

- Require migration capability and nonce.
- Validate extension/MIME/size and parse using bounded memory.
- Store temporary uploads outside public execution and delete after retention.
- Normalize encoding and headers; reject ambiguous duplicate mapped columns.
- Use explicit import mode: create-only, update-by-source-key, or upsert.
- Source key plus checksum controls idempotency.
- Unknown clinical terms require explicit “create terms” permission and remain subject to review.
- Each chunk stores its checkpoint before scheduling the next.
- A job rollback may remove only unpublished records created by that job and mappings created by that job.

### 15.2 Export safety

Prefix cells beginning with `=`, `+`, `-`, or `@` according to the export policy so spreadsheet software does not execute formulas. Export only fields the current user can access.

### 15.3 Aman reference mapping

| Legacy value | Canonical destination |
|---|---|
| Page title/name | `post_title` after identity review |
| Credentials text | Not imported — the field was removed (ADR-0024) |
| Primary department/specialty | Taxonomy assignment plus validated primary term ID |
| Languages | Language terms |
| “15+ years” | Reviewed integer `_doctorpilot_years_experience` |
| Intro/biography | Post excerpt/content |
| Procedures | Procedures array |
| Scope of practice | Reviewed clinical taxonomy/interests; never blindly reclassified |
| Experience/education | Structured array |
| Certifications/publications/FAQs | Corresponding structured arrays |
| Phone/email | Location record with visibility approval |
| Book Consultation | Booking configuration |
| Old URL | Redirect source path |
| New reviewed URL | Doctor post slug/destination path |

Review must catch wrong-doctor content (including “Dr. Kanso” references), placeholder counters, pronouns, specialties, contact, booking, copied testimonials, and staging URLs.

## 16. SEO contract

- Derive canonical host from the production site configuration/SEO owner, never a hardcoded hospital domain in reusable code.
- Detect supported SEO owners before emitting canonical, Open Graph, breadcrumbs, schema, or sitemap behavior.
- Structured data uses reviewed public fields only and is validated in release QA.
- Search/filter URLs and REST endpoints are not included in XML sitemaps and receive appropriate noindex/canonical treatment when rendered.
- Taxonomy archives are indexable only when enabled, templated, and sufficiently useful.
- Redirect sources are normalized paths; destinations resolve from current doctor permalinks and are revalidated after slug/base changes.
- Launch checklist: replace staging URLs, verify canonicals, activate reviewed redirects, regenerate sitemap, update internal links, clear relevant caches, test search/booking, submit sitemap, and monitor 404/redirect loops.

## 17. Analytics contract

- Feature is Pro and disabled by default.
- Enabling presents what is collected, storage location, retention, and deletion controls.
- Events are normalized into approved aggregate dimensions before persistence.
- Raw event bodies are not retained.
- Repeated increments use an atomic upsert on the unique daily aggregate key.
- Scheduled retention deletes expired aggregate/import-log rows in bounded batches.
- Analytics endpoint returns `204` for an accepted event; it never returns stored aggregate details publicly.

## 18. Lifecycle and schema versions

Options:

- `bstdz_doctorpilot_plugin_version`
- `bstdz_doctorpilot_schema_version`
- `bstdz_doctorpilot_cache_generation`

Activation/update:

1. verify runtime;
2. register post type/taxonomies;
3. run ordered idempotent schema migrations;
4. grant capabilities;
5. schedule maintenance;
6. flush rewrites once if needed;
7. record versions.

Deactivation unschedules jobs but preserves data. Uninstall checks `WP_UNINSTALL_PLUGIN` and the explicit delete setting, then removes only DoctorPilot-owned posts, relationships/terms, prefixed meta, options, transients, tables, cron events, and capabilities. Shared user/WordPress data is not deleted.

## 19. Hooks and versioned extension API

Initial public extension points:

- `doctorpilot/fields/register`
- `doctorpilot/search/request`
- `doctorpilot/search/weights`
- `doctorpilot/search/result`
- `doctorpilot/booking/providers`
- `doctorpilot/templates/locations`
- `doctorpilot/public/doctor_view`

Document argument/return contracts and version them through a base API constant. Hooks may extend but cannot expose private fields, bypass validation, or change publication authorization.

## 20. Verification matrix

| Contract | Minimum verification |
|---|---|
| Field registry | Schema snapshot + sanitizer/validator tests |
| Publication/privacy | Public REST allowlist and unpublished/private fixtures |
| Search | Ranking fixtures, filter combinations, Unicode, pagination, URL/history, no-JS |
| Performance | 1,000-doctor p50/p95 benchmark and query count |
| Elementor | Absent, Free, Pro; widget assets; dynamic tags/conditions |
| Booking | Provider contract tests, unsafe URL, provider failure/fallback |
| Import | Dry run, malformed CSV, idempotency, resume, draft-only, rollback |
| Redirects | Exact mapping, staging rejection, duplicate, chain, loop, 404 |
| SEO | Core plus supported SEO owners; one canonical/schema owner |
| Analytics | Default off, aggregate-only schema, retention deletion |
| Lifecycle | Fresh activate, update with data, deactivate, both uninstall settings, multisite scope |
| Accessibility | Automated plus keyboard/screen-reader smoke tests |
