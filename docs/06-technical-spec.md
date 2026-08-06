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
- Changing an existing doctor slug must offer an explicit old-path redirect in Pro; Free must at least warn about URL impact.
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
	'key'           => '_doctorpilot_credentials',
	'group'         => 'profile',
	'type'          => 'string',
	'default'       => '',
	'tier'          => 'free',
	'public'        => true,
	'sanitize'      => 'sanitize_text_field',
	'validate'      => CredentialsRule::class,
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
| Credentials | Post meta | `_doctorpilot_credentials` | Free |
| Gender | Post meta | `_doctorpilot_gender` | Free |
| Featured | Post meta | `_doctorpilot_is_featured` bool | Free |
| Accepting new patients | Post meta | `_doctorpilot_accepting_new_patients` bool | Free |
| International doctor | Post meta | `_doctorpilot_is_international` bool | Free |
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
| Location/contact records | Array meta | `_doctorpilot_locations`; max 1 in Free | Free/Pro |
| Consultation duration | Post meta | `_doctorpilot_consultation_duration_minutes` positive int | Free |
| Consultation price | Post meta | `_doctorpilot_consultation_price_minor` int plus `_doctorpilot_consultation_currency` ISO code | Free |
| Booking method/config | Post meta | `_doctorpilot_booking_*` | Free/Pro |
| SEO overrides | Post meta | `_doctorpilot_seo_*` | Free |
| Review state | Post meta | `_doctorpilot_review_status` | Free |
| Completeness | Derived post meta | `_doctorpilot_completeness_score` | Free |
| External source ID | Private post meta | `_doctorpilot_external_source_id` | Pro |
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
- `manage_doctorpilot_migrations` (Pro)
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
| Boolean filters | `0\|1` | accepting-new, online |
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
      "credentials": "MD, MRCP, FRCR, EMBA",
      "primary_department": {"id": 7, "name": "Radiology", "slug": "radiology"},
      "primary_specialty": {"id": 12, "name": "Interventional Radiology", "slug": "interventional-radiology"},
      "years_experience": 15,
      "languages": [{"id": 20, "name": "English", "slug": "english"}],
      "consultation_type": "both",
      "accepting_new_patients": true,
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
| Credentials/clinical-interest/search-document match | 250 |
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

### 10.2 Elementor contracts

Free core widgets:

- Doctor Search
- Doctor Grid/List — card grid or compact list, with an optional search bar,
  search-mode tabs, collapsible filter row (sort / gender / language /
  department / specialty / symptom / accepting-new / online), A–Z index, result
  count, and pagination. Server-renders from the query string (so the no-JS GET
  form and shareable URLs work) and progressively enhances to the public
  `/doctors` route. Templates: `directory.php`, `row-doctor.php`,
  `card-doctor.php`.
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
2. Hero: photo, name, credentials, primary department/specialty, main booking CTA
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
    "single_doctor_template": 0
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

Unknown keys are discarded. Secrets are stored separately from exportable design/general settings and never autoloaded when large. Settings validation enforces ranges and safe enums.

## 14. Pro database tables

Table names use `$wpdb->prefix`.

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
| Credentials text | `_doctorpilot_credentials` |
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

Review must catch wrong-doctor content (including “Dr. Kanso” references), placeholder counters, credentials, pronouns, specialties, contact, booking, copied testimonials, and staging URLs.

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
