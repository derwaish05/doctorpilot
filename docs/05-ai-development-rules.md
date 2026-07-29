# 05 — AI Development Rules

Repository-specific rules for AI-assisted development of DoctorPilot. These extend the repository’s global `CLAUDE.md`/agent instructions. When instructions conflict, follow the more specific safety, WordPress, or task requirement and record the conflict.

## 1. Mandatory workflow before coding

1. Read the repository instructions and the relevant DoctorPilot documents.
2. Inspect the existing implementation, tests, dependency versions, and working-tree changes.
3. Search for an established pattern before introducing another abstraction.
4. State the requested outcome, affected requirements/acceptance criteria, Free/Pro tier, and assumptions.
5. Complete a change-impact note:
   - storage/schema impact;
   - public REST/API impact;
   - permissions/privacy impact;
   - Elementor/theme/SEO compatibility impact;
   - migration/backward-compatibility impact;
   - documentation and test impact.
6. If a proposed change contradicts an accepted ADR, stop and add/suggest a superseding ADR before implementation.

Do not begin by copying legacy Elementor content. Build against the canonical field registry and approved migration mappings.

## 2. WordPress and packaging standards

- Follow WordPress Coding Standards, WordPress.org Plugin Guidelines, and the declared supported-version matrix.
- Use `BrainStudioz\DoctorPilot` for PHP classes.
- Prefix internal post types, taxonomies, meta keys, options, transients, tables, hooks, cron events, script/style handles, and procedural symbols as specified in `06-technical-spec.md`.
- Keep public rewrite slugs independent from internal identifiers.
- Use WordPress APIs for posts, terms, metadata, REST, HTTP, filesystem, cron, settings, privacy, and database upgrades.
- All user-facing strings must be translatable with text domain `doctorpilot`.
- Do not include incompatible-licensed assets/dependencies, obfuscated code, remote executable code, or undisclosed tracking.
- The WordPress.org Free package must be complete and useful. Pro implementation belongs in a separate add-on; do not ship disabled Pro code as trialware.
- Increment plugin and schema versions deliberately and update `readme.txt`/changelog for releases.

## 3. Architecture and data integrity

- `FieldRegistry` is the only authority for field keys, types, defaults, validation, sanitization, public visibility, REST shape, and tier.
- WordPress post status is the only publication authority. Do not reintroduce editable `profile_status=published` meta.
- Taxonomy relationships are canonical. Primary department/specialty meta must reference an assigned term.
- Admin, REST, native templates, Elementor, search indexing, schema, and migration must consume shared domain/view-model services.
- Derived data—completeness, search document, sort fields, facets, caches—must be rebuildable.
- Do not query post meta directly from widgets or templates when a doctor view model/service exists.
- Free owns canonical services. Pro extends registered interfaces; no duplicate controller, menu, schema, route, or search implementation.
- Domain code must not depend on request globals, Elementor classes, HTML rendering, or provider SDKs.

### 3.1 Global content library (ADR-0017)

- Global procedures/education/publications/FAQs are Free CPTs; their `post_status` is the only publication authority. Never expose draft/pending/private/trashed globals in public templates, search, REST, or Elementor.
- All consumers read combined content through the single `DoctorContentResolver`; do not read the reference-ID meta or repeaters directly in templates/widgets, and do not build a second data source. The resolver and search-document builder must defensively ignore missing/non-published records.
- Never resolve global→doctor impact with a serialized-array meta `LIKE`. Use the rebuildable reverse-reference index; on any global create/edit/status-change/delete/merge, bump cache generation immediately and enqueue bounded search-document rebuilds for affected doctors.
- The education CPT title is computed read-only from qualification + institution. Assignment lists are unique; exact-key duplicate globals are blocked unless overridden with `manage_doctorpilot_library`, and overrides are audit-logged.
- Existing doctor repeater data must be preserved as local items; migrations are additive and idempotent.

## 4. Security rules

Security is mandatory and never Pro-gated.

### 4.1 Authorization and request intent

- Enforce capabilities and object/department scope server-side for every protected action.
- Nonces protect authenticated request intent; they do not grant permission.
- Never authorize by role name, hidden UI, ownership meta alone, or a client-supplied department.

### 4.2 Public and authenticated REST

- Public, cacheable GET endpoints do not require nonces. Use explicit public permission callbacks, strict argument schemas, bounded inputs, and allowlisted response fields.
- Authenticated state-changing REST requests require the WordPress REST nonce plus capability/object checks.
- Every route declares methods, permission callback, argument schema, validation, sanitization, and stable errors.
- Never expose private email, professional license identifiers, secrets, internal notes, unpublished data, or raw provider responses publicly.
- Rate limiting is an abuse control, not authentication. It must not permanently block legitimate users behind a shared hospital/network address.

### 4.3 Input, output, storage, and files

- Sanitize and validate at system boundaries; preserve raw values only when explicitly needed and protected.
- Escape late for the exact context: HTML, attribute, URL, JavaScript, JSON, or SQL.
- Use core query APIs and `$wpdb->prepare()` for unavoidable custom SQL.
- Validate upload capability, nonce, file size, extension, MIME/type, CSV structure, and storage location.
- Protect CSV exports against formula injection.
- Validate redirect source/destination, URL schemes, phone formats, provider output, and Elementor control values.
- Never log credentials, nonces, private field values, uploaded CSV rows, or full request bodies.

## 5. Privacy and healthcare-content safety

- Do not add patient records, patient accounts, identifiable symptom history, session-linked analytics, or external telemetry without an approved scope and new ADR.
- Analytics remain disabled until explicit administrator opt-in.
- Store no IP, user ID, cookie ID, session ID, raw user-agent, or query-to-person association.
- Add/update WordPress privacy-policy text and erasure behavior when a feature changes collected data.
- Symptom search supports discovery only. UI, code comments, seed data, and marketing must not describe results as diagnosis.
- Never invent doctor credentials, clinical relationships, treatment claims, testimonials, availability, or migration mappings.
- AI-generated or transformed medical content must remain unpublished until human review; AI medical features require a separate ADR and evaluation plan.
- Emergency messaging is configurable static content approved by the hospital; the plugin does not assess urgency.

## 6. Search and REST implementation rules

- Normalize request parameters once into an immutable search request.
- Enforce minimum/maximum lengths, list counts, page size, and allowed sort/filter values.
- Debounce suggestions and cancel/ignore stale frontend requests.
- Follow the ranking contract in `06-technical-spec.md`; add ranking fixtures for every rule change.
- Apply visibility before ranking: only WordPress `publish` records enter public candidates.
- Preserve Unicode names/terms; do not force transliteration.
- Encode canonical filter/sort/view/page state in the URL and preserve reload/back/forward behavior.
- Provide a server-rendered GET fallback that uses the same request/service.
- Cache only public-safe view models. Include locale, schema version, and cache generation in keys.
- Invalidate derived search values and cache generation after relevant doctor, taxonomy, settings, or visibility changes.
- Filtered/internal search combinations must not become indexable duplicate pages.

## 7. Elementor and presentation rules

- DoctorPilot must boot and render native output when Elementor is absent.
- Check compatible Elementor/Elementor Pro versions before referencing their classes.
- Core widgets may load with Elementor; dynamic tags and Theme Builder conditions load only when the required Elementor Pro APIs are active.
- Register widget dependencies using Elementor APIs and load assets only where used.
- Use current Elementor widget/control/rendering APIs; do not copy deprecated examples without checking the installed version.
- Minimize markup and DOM wrappers; render semantic HTML before adding ARIA.
- Keep selectors scoped to DoctorPilot to avoid theme/admin CSS bleed.
- Use design tokens for shared defaults; widget settings override global DoctorPilot tokens.
- Omit empty sections and inaccessible placeholder controls.
- Never store canonical doctor content in Elementor widget settings/document JSON.

## 8. Accessibility requirements

- Target WCAG 2.2 AA for core workflows.
- Native semantic controls are preferred to simulated buttons/links.
- All filters, drawers, chips, pagination, view switches, suggestions, dialogs, and booking actions must work with keyboard.
- Manage focus when drawers/dialogs open and close and after route-like result changes when appropriate.
- Announce result-count/status changes without repeatedly interrupting screen readers.
- Provide visible focus, sufficient contrast, meaningful labels, error associations, reduced-motion behavior, RTL support, and non-color status cues.
- Placeholder doctor images use correct alt behavior: decorative when the adjacent name supplies identity, meaningful only when the image adds content.
- Accessibility failures block release regardless of tier.

## 9. Performance and caching

- Treat the performance budget in `02-requirements.md` as a release requirement, not an aspiration.
- Avoid unbounded `posts_per_page`, term/meta queries, suggestions, or facet scans.
- Select only required fields/IDs during candidate retrieval; do not hydrate every profile before pagination.
- Do not autoload large settings, caches, logs, or import state.
- Avoid N+1 queries in result serialization and Elementor loops.
- Cache safely and measure before adding a custom index.
- Do not flush rewrite rules, rebuild all search data, or clear all caches on normal requests.
- Run the fixture benchmark after changes to search, fields, facets, serializers, caching, or ranking.

## 10. Database, migration, and lifecycle rules

- Every custom-table change has a schema version, forward migration, indexes, and upgrade test.
- Use `dbDelta()` only with compatible schema SQL; verify the resulting schema.
- Migrations are idempotent, resumable where long-running, and safe on non-empty databases.
- CSV rows use an external source key and checksum; retrying cannot create duplicates.
- Imports never auto-publish. Redirects are explicit and loop-checked.
- Activation may register/upgrade and flush rewrites once. Deactivation deletes no content.
- Uninstall runs only in WordPress uninstall context and follows the explicit delete-data setting.
- Multisite operations must be bounded and must not silently iterate an unbounded network.

## 11. Free/Pro and dependency discipline

- Use the capability matrix in `02-requirements.md`; do not move features between tiers incidentally.
- Free and Pro negotiate a versioned base API. Incompatibility produces a safe admin notice, never a fatal error.
- Disabling Pro must leave Free data and output valid.
- Do not make public data unreadable merely because a license expires.
- Accessibility, security, privacy controls, migration safety, and uninstall safety are never licensed features.
- Elementor, Elementor Pro, SEO plugins, and BookPilot integrations must each be isolated behind compatibility checks.

## 12. Testing and verification

Select the highest-value checks for the change and report exactly what ran.

### Required automated coverage

- Unit tests: normalization, validation, invariants, completeness, ranking, booking CTA, redirect validation.
- Integration tests: CPT/tax/meta registration, capabilities, public/private REST fields, cache invalidation, lifecycle, schema upgrades.
- Contract tests: public REST request/response/error schema and each booking adapter.
- Migration tests: dry run, idempotency, resume, duplicate source key, draft-only behavior, CSV formula safety, redirect loop/staging rejection.
- Compatibility tests: supported WordPress/PHP versions, Elementor absent/Free/Pro, DoctorPilot Pro absent/incompatible, supported SEO owners.
- Frontend tests: URL state, history, stale-request handling, pagination, no-JS fallback, empty/loading/error states.
- Accessibility tests: automated scans plus manual keyboard and screen-reader smoke paths.
- Performance tests: approved 1,000-doctor fixture and recorded p50/p95.

### Required static/release checks

- PHP syntax, PHPCS WordPress ruleset, Plugin Check
- JS lint/typecheck/tests/build where applicable
- Translation/string and asset checks
- No secrets, build-only dependencies, or unintended generated artifacts
- Manual desktop/tablet/mobile smoke test for search, profile, filters, and booking

“Not run” is not “passed.” Record blockers and residual risk.

## 13. Documentation and decision sync

- Update affected `01`–`06` documents, `CHANGELOG.md`, and `readme.txt` in the same change as behavior.
- Requirement/tier changes update `02-requirements.md`.
- Architecture or durable policy changes add/supersede an ADR in `04-decisions.md`.
- Storage/API changes update `06-technical-spec.md` and migration/version notes.
- Keep examples and seed fixtures free of unsupported medical claims or copied legacy errors.
- Do not mark work complete until docs, code, tests, and migration impact agree.

## 14. Prohibited shortcuts

- No implementation before inspecting existing code and requirements.
- No generic/unprefixed internal WordPress identifiers.
- No second field schema, menu, route, serializer, or search engine.
- No public `show_in_rest` metadata exposure without an allowlist review.
- No auto-publishing imports or generated medical content.
- No guessed redirects, credentials, clinical relationships, or availability.
- No raw SQL interpolation, unsafe URL schemes, global CSS leakage, or remote executable code.
- No destructive reset of existing site data to make a migration pass.
- No hiding skipped/failing checks or unrelated user changes.

## 15. Definition of done

A task is complete only when:

- linked requirements and acceptance criteria are satisfied;
- security, privacy, tier, compatibility, and migration impact are addressed;
- code uses the canonical services and identifiers;
- appropriate automated/manual checks pass;
- documentation and changelog are synchronized;
- no known serious accessibility/security defect remains; and
- the final report states changed behavior, verification, and any remaining limitation.

## 16. Primary references

- [WordPress Plugin Developer Handbook](https://developer.wordpress.org/plugins/)
- [WordPress.org Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [WordPress REST API: Routes and Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/)
- [WordPress REST API: Schema](https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/)
- [Elementor Widget Documentation](https://developers.elementor.com/docs/widgets/)
- [Elementor Dynamic Tags](https://developers.elementor.com/docs/dynamic-tags/)
- [Elementor Theme Conditions](https://developers.elementor.com/docs/theme-conditions/)
