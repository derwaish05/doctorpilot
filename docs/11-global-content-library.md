# 11 — Global Content Library (design spec)

**Status:** Accepted — 2026-07-28 (ADR-0017). **Implementation status:** Not Started; gated on implementation approval.
**Related:** ADR-0017 (`04-decisions.md`); requirements FR-058–FR-063 and AC-025–AC-035; tech-spec §5.4/§7/§10–11.

Reusable, centrally-managed records for **Procedures**, **Experience & Education**,
**Publications**, and **Frequently Asked Questions**, assignable to many doctors,
alongside independent doctor-specific ("local") items.

## 1. Data model and storage

### 1.1 Global records — Free custom post types

Prefixed, `show_in_rest=false`, no public archive/single. Managed under the
DoctorPilot admin menu. The `Menu` owner sorts the submenu late on `admin_menu`
into a fixed order — All Doctors, Education, Experience, Locations, Procedures,
Publications, FAQs, Health, Analytics, Migration, Settings — and the individual
CPTs keep `show_in_menu => 'doctorpilot'` so screen highlighting stays correct.
`post_status` is the publication authority (ADR-0013). All CPT keys are ≤ 20
characters (WordPress limit).

> **Implementation note (2026-07-30, schema-driven).** All five library types
> are built: `doctorpilot_exp`, `doctorpilot_edu`, `doctorpilot_proc`,
> `doctorpilot_pub`, `doctorpilot_faq`. `LibraryPostTypes` holds one data-driven
> `schema()` per type (labels, `title_parts`, `list_subtitle`, and a `fields`
> map). Every field is stored as prefixed post meta at `_doctorpilot_lib_{key}`
> **except** exactly one `store => content` field per type, which lives in
> `post_content` and is edited via the standard editor. The `post_title` is
> computed read-only from `title_parts`. Experience & Education replaced the
> originally-proposed single `doctorpilot_edu_exp` (ADR-0017 amendment). The
> reverse-index for every type is `_doctorpilot_lib_doctors`.

Title column: the editor may type a title; if left blank it is auto-composed from
the listed parts (see §12). A single-identifier type instead marks that field
`store => title`, so it *is* the post title with no duplicate field — FAQ
(`question`), Procedure (`name`), and Publication (`title`). Experience &
Education keep `primary`/`secondary` fields because their title is composed from
two distinct parts. A type whose only remaining fields are the title and content
(FAQ, Procedure) renders no Details meta box.

| Content | CPT key (len) | `post_title` | `post_content` | Prefixed meta (`_doctorpilot_lib_*`) |
|---|---|---|---|---|
| Experience | `doctorpilot_exp` (15) | editable; auto from primary + secondary | description | `primary` (position), `secondary` (organization), `start_year`, `end_year`, `country`, `doctors` |
| Education | `doctorpilot_edu` (15) | editable; auto from primary + secondary | description | `primary` (qualification), `secondary` (institution), `start_year`, `end_year`, `country`, `doctors` |
| Procedure | `doctorpilot_proc` (16) | **is** the name (typed directly) | description | `doctors` |
| Publication | `doctorpilot_pub` (15) | **is** the title (typed directly) | summary | `journal`, `published_date`, `url`, `doi`, `doctors` |
| FAQ | `doctorpilot_faq` (15) | **is** the question (typed directly) | answer | `doctors` |
| Location | `doctorpilot_loc` (15) | **is** the name (typed directly) | opening hours | `area`, `city`, `phone`, `email`, `map_url`, `doctors` |

Capabilities: a mapped set (`doctorpilot_library_item` /
`doctorpilot_library_items`) plus a `manage_doctorpilot_library` primitive granted
to administrators (and, optionally, Pro-scoped managers). Assigning items on a
doctor requires the ability to edit that specific doctor (`edit_doctorpilot_doctor`
with the doctor as the object).

Two derived meta values live on each global record, both rebuildable:
- `_doctorpilot_assigned_doctor_ids` — the reverse-reference index (§3).
- `_doctorpilot_dedupe_key` — the normalized duplicate-detection key (§5).

### 1.2 Doctor references and local items

Per doctor, ordered **unique** reference lists (prefixed meta, arrays of ints):
`_doctorpilot_exp_ids`, `_doctorpilot_edu_ids`, `_doctorpilot_proc_ids`,
`_doctorpilot_pub_ids`, `_doctorpilot_faq_ids`.

Doctor-specific items live in local repeater meta. Experience & Education use
dedicated split repeaters `_doctorpilot_exp_local` / `_doctorpilot_edu_local`
(replacing the legacy combined `_doctorpilot_education_experience`, migrated at
schema version 2). Procedures, Publications, and FAQs **reuse their existing
doctor repeaters** as the local store — `_doctorpilot_procedures`,
`_doctorpilot_publications`,
`_doctorpilot_faqs`. Each local repeater row is
assigned a stable `_key` (a short uid) on save so the editor can reorder, edit,
and remove local items reliably.

**Locations** are a special case of the hybrid model. Shared locations are
assigned via `_doctorpilot_loc_ids`, but the doctor's own contact details keep
the pre-existing term-based `_doctorpilot_locations` repeater (room/floor, direct
phone, consent-gated email, consultation type) — which also feeds the booking
resolver. Because those two shapes differ, `DoctorView::locations()` maps and
merges them itself (shared first, then local) rather than using the generic
resolver's local pass; the resolver's `loc` local key is an unused placeholder.

### 1.3 One canonical schema

The single `FieldRegistry` remains the authority. It gains: (a) the four
reference fields (type `array`, input `library`), and (b) library-item field
definitions per CPT (reusing the shared `Sanitizer`). No second schema.

## 2. Free vs Pro

- **Free:** CPTs, schema, reference storage, resolver, assignment UI, native
  rendering, edit propagation, reverse index + invalidation, unpublish/delete
  handling, dedupe-on-create, and **basic blast-radius visibility** (§14).
- **Pro:** bulk assign to many doctors; merge duplicate globals + repoint
  references; usage/impact reporting; bulk "promote local → global" with
  de-duplication; CSV import/export of library items. All via Free contracts.

## 3. Reverse-reference index, cache, and invalidation

Serialized ID arrays cannot be queried reliably with a meta `LIKE`. A global edit
must reach every assigned doctor's public view, REST response, search document,
cache, and Elementor output. Design:

- **Reverse index (rebuildable).** Each global record stores
  `_doctorpilot_assigned_doctor_ids` (array of doctor IDs). It is maintained
  transactionally in `SaveDoctor`: when a doctor's reference list changes, the
  diff (added/removed IDs) updates the affected globals' reverse index. The whole
  index is rebuildable by scanning doctors in bounded batches (a maintenance
  action / migration step), so it is derived data, never a source of truth.
- **On global create/edit/status-change/delete/merge:**
  1. Increment `bstdz_doctorpilot_cache_generation` **immediately** (invalidates
     all cached public search responses at once).
  2. Look up affected doctors via the reverse index (O(1) per global) and
     **enqueue bounded background rebuilds** of each affected doctor's derived
     search document (`SearchIndexBuilder::rebuild`), using the M1 job/cron
     primitive in bounded batches with checkpoints.
- **Live reads.** Native templates, `GET /doctors/{id}`, and Elementor read
  through the resolver at request time, so they reflect a global edit immediately;
  only the cached search layer and the derived search document require the
  generation bump + rebuild above.
- **Search document.** The search-document builder includes resolved global +
  local text for these types, which is why a global edit must rebuild assigned
  doctors' documents.
- **Failure/edge:** rebuild jobs are idempotent and resumable; a missing or
  non-published global is ignored defensively by both the resolver and the
  document builder even before the reverse index catches up.

## 4. Resolution service

`DoctorContentResolver::resolve( int $doctor_id, string $type, string $context )`
returns an ordered list.

- `context = public` → assigned globals filtered to `post_status = publish`, in
  assigned order, then local items. Output items carry **no** `origin`, `status`,
  or internal `id` (see §13).
- `context = admin` → all assigned globals (any status) then local items, each
  carrying `origin`, `status`, global `id`, and local `_key` for the editor.

**Ordering (chosen): separate ordering** — globals first (in assigned order),
then locals (in repeater order). This is predictable and simple; the editor
communicates it explicitly ("Assigned library items appear first, followed by
this doctor's own items"). Unified interleaved ordering is out of scope for this
milestone.

Consumers (single source, no duplicate data source): `DoctorView::full()`,
`profile-content.php`, the search-document builder, the Elementor Doctor Profile
widget, and `GET /doctors/{id}`.

## 5. Duplicate handling

- **Assignment:** reference lists are sanitized to unique ints on save; the UI
  disables already-assigned items.
- **Global creation dedupe key** (`_doctorpilot_dedupe_key`), per type:
  - procedure = normalized title;
  - education = normalized(institution) + "|" + normalized(qualification);
  - publication = normalized DOI if present, else normalized title;
  - FAQ = normalized question.
- **Normalization:** Unicode NFC, case-fold (multibyte), trim, collapse internal
  whitespace, strip punctuation for key purposes. **DOI** additionally lowercased
  with any `https://doi.org/` / `doi:` prefix removed.
- **Scope:** duplicate detection considers `publish` and `draft` records; `trash`
  is excluded so restoring a trashed item is not blocked.
- **Override:** an exact-key collision blocks by default; overriding to create a
  deliberate duplicate requires `manage_doctorpilot_library`, and the override is
  written to an audit log entry (who/when/key).
- **Race protection:** slugs are unique; on create the dedupe key is written and
  then re-queried post-insert — if a concurrent create produced the same key, the
  later record is flagged for review rather than silently duplicated.

## 6. Identifiers, security, and publication

- CPTs `doctorpilot_{exp,edu}` (built) and `doctorpilot_{proc,pub,faq}` (planned); meta `_doctorpilot_*`; option/table
  prefix `bstdz_doctorpilot_`; capability `manage_doctorpilot_library`; one
  authenticated internal route `GET doctorpilot/v1/library/search` (§7 of the
  tech spec).
- `post_status` is the only publication authority; no editable published-state meta.
- Every mutation: capability + nonce; sanitize on input; validate; escape late by
  context. Assignment IDs validated to existing posts of the correct CPT.
- Public resolver returns only published globals; draft/pending/private/trashed
  globals never appear in public templates, search, REST, or Elementor.

## 7. Lifecycle behavior

| Event | References | Public output | Rebuild/invalidation |
|---|---|---|---|
| Global **edited** | unchanged | updates everywhere via resolver | bump generation; rebuild affected docs' search documents |
| Global **draft / pending / private** | retained | hidden | bump generation; rebuild affected docs |
| Global **trashed** | **retained** (so restore re-links) | hidden | bump generation; rebuild affected docs |
| Global **restored from trash** | intact | reappears | bump generation; rebuild affected docs |
| Global **permanently deleted** (`before_delete_post`) | **removed** from all doctors in bounded batches | gone | bump generation; rebuild affected docs |
| Global **merged** (Pro) | repointed from duplicates to canonical, then duplicates trashed | canonical shown | bump generation; rebuild affected docs; log |
| **Local** item edit/remove | n/a | only that doctor | rebuild that doctor only |

The resolver and search-document builder **always** ignore missing or
non-published records defensively, so a stale reference never causes a fatal or a
leak, even before background cleanup completes.

## 8. Migration and backward compatibility

- **Additive.** Existing repeater data is preserved and renders as **local**
  items; no forced migration, no data loss.
- Idempotent schema migration registers CPTs, reference meta, and caps; bumps the
  schema version; deactivation preserves data; uninstall removal stays opt-in.
- A maintenance action rebuilds the reverse index by scanning doctors in bounded
  batches (also runnable after a bulk import).
- **Opt-in "Promote to global"**: converts a chosen local item into a global
  record, assigns it, and removes only that doctor's local copy. Pro adds bulk
  promotion with de-duplication.
- **Migration tests:** (a) pre-upgrade doctor renders identically after upgrade;
  (b) promote produces a global + reference and removes only the local copy;
  (c) other doctors are unaffected; (d) trash retains references and restore
  re-links; (e) permanent delete removes references with no dangling data.

## 9. Resolved item output schemas (§13)

Public output shapes (admin context additionally includes `origin`, `status`,
global `id`, and local `_key`; public output omits all three):

- **Procedure:** `{ name: string, description: string(rich, wp_kses_post) }`
- **Education/experience:** `{ title: string(computed), type: enum(degree|fellowship|residency|position|other), institution: string, qualification: string, start_year: int|null (1900–2100), end_year: int|null, country: string }`
- **Publication:** `{ title: string, journal: string, date: string(YYYY or YYYY-MM-DD), url: string(esc_url, http/https only), doi: string(normalized), description: string }`
- **FAQ:** `{ question: string, answer: string(rich, wp_kses_post) }`

Rules: years validated to 1900–2100; dates accepted as `YYYY` or `YYYY-MM-DD`;
URLs validated to http/https via `esc_url_raw`; DOI normalized per §5; rich text
(`description`, `answer`) sanitized with the approved `wp_kses_post` policy; all
plain fields via `sanitize_text_field`.

## 10. Blast-radius visibility (Free, §14)

Because a single global edit can change many published profiles, the Free admin
UI shows, without requiring Pro:

- **"Used by N doctors"** on each global record (from the reverse index).
- A link to the list of assigned doctors.
- A **confirmation warning before unpublishing or deleting** a global that is in
  use ("This will affect N published doctors").
- Basic audit info for material edits (who/when) and for dedupe overrides.

Advanced impact reporting and analytics remain Pro.

## 11. Development tasks and acceptance criteria

Tasks are specified in `../tasks/milestone-7-global-content-library.md`
(modules M7-MOD-01…M7-MOD-06). Acceptance criteria are canonical in
`02-requirements.md` as **AC-025–AC-035** (mirrored here as AC-L1–AC-L11):

- **AC-025 / AC-L1** Admin can create/edit/publish/unpublish/delete global items of each type from the DoctorPilot admin area.
- **AC-026 / AC-L2** In the doctor editor, a user can search for and select existing globals and assign multiple of each type.
- **AC-027 / AC-L3** A user can add doctor-specific (local) items and edit/remove them without changing any global record.
- **AC-028 / AC-L4** Global and local items are clearly distinguished in the editor.
- **AC-029 / AC-L5** Duplicate assignments are prevented; near-duplicate global records are flagged on creation.
- **AC-030 / AC-L6** Editing a global updates it for every assigned doctor; local items remain independent.
- **AC-031 / AC-L7** Draft/pending/private/trashed globals never appear in public templates, search, REST, or Elementor.
- **AC-032 / AC-L8** Deleting or unpublishing a global leaves no dangling references and no fatal errors; trashing retains references and restore re-links.
- **AC-033 / AC-L9** Existing doctors render identically after upgrade (repeater data intact as local items).
- **AC-034 / AC-L10** Native templates, REST `/doctors/{id}`, the search document, and the Elementor Doctor Profile widget present the same resolved, combined content.
- **AC-035 / AC-L11** All mutations enforce capability + nonce + sanitization + validation + context-specific escaping; a global edit bumps cache generation and enqueues bounded rebuilds of affected doctors.

## 12. Record title (editable, auto-composed fallback)

Every library type supports the standard WordPress title field, which the editor
may fill in directly. When the title is **left blank on save**, it is composed
automatically from the type's `title_parts` (joined with " — ", using whichever
parts are present) — e.g. Experience/Education from `primary` + `secondary`,
Procedures from `name`, Publications from `title`, FAQs from `question`. A title
the editor typed is always preserved. This keeps a sensible default while still
allowing a custom label, and the compose-on-save uses a remove/re-add-action
guard around `wp_update_post` to avoid save recursion.

## 13. Verification

Unit (schema, sanitizers, dedupe key/normalization/DOI, resolver ordering/origin,
computed title), reverse-index (diff maintenance, rebuild, O(1) lookup),
invalidation (generation bump + bounded rebuild on edit/unpublish/delete/merge),
migration (pre/post parity, promote isolation, trash-vs-delete reference
behavior), security (capability/nonce/object-scope/public-visibility), REST
contract (`/library/search` params/bounds/errors/permission/no-store),
accessibility (keyboard/screen-reader assignment UI), and manual (create global →
assign to two doctors → edit → both update → add local to one → other unaffected →
trash → restore → delete → safe, no dangling). Report exactly what ran.
