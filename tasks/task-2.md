# Task 2 — Free Frontend, REST Search & Booking

> Legacy planning reference. The canonical implementation sequence is indexed in `tasks/README.md`; use `milestone-2-doctor-directory.md` and `milestone-3-search-and-frontend.md` for executable work.

**Covers development phases:** Native frontend, public discovery, and core booking actions
**Tier:** DoctorPilot Free MVP
**Goal:** Deliver accessible native doctor views, deterministic public search, and safe core booking actions without requiring Elementor.
**Depends on:** Task 1
**Blocks:** Tasks 3 and 4
**Requirements:** FR-015–FR-030, FR-037–FR-038, FR-040, NFR-001–NFR-006
**Acceptance criteria:** AC-002, AC-004–AC-010, AC-013–AC-014, AC-018

## Scope

- Implement the shared doctor view-model, public serializer, search request, search service, and booking CTA services consumed by native rendering, REST, and later Elementor integration.
- Add overrideable templates and native shortcodes: `[doctorpilot_search]`, `[doctorpilot_directory]`, and `[doctorpilot_profile id=""]`. Use the documented theme `doctorpilot/` override lookup.
- Render doctor cards, grid/list results, department/specialty views where supported, and the single profile in the non-empty section order in `06-technical-spec.md` §11.
- Provide stable image ratios/placeholders, result count, pagination, loading/error/empty states, grid/list switch, removable filter chips, “Clear all,” and an accessible mobile filter drawer.
- Implement public `doctorpilot/v1` GET routes: `/doctors`, `/doctors/suggest`, `/doctors/{id}`, and `/facets`, with explicit public permission callbacks, bounded schemas, stable errors, published-only candidates, and allowlisted fields.
- Support Free search modes: name, department, specialty, symptom, and universal.
- Support Free filters: department, specialty, symptom, gender, language, accepting new patients, and online consultation.
- Support deterministic ranking and tie-breaking from `06-technical-spec.md` §8, including curated synonyms/relationships only. Preserve Unicode.
- Support relevance, name, featured, experience, and recent sorting plus mandatory pagination.
- Encode normalized filter, sort, view, and page state canonically in the URL. Restore state on reload and browser history. Provide a server-rendered GET fallback using the same service.
- Implement debounced, bounded suggestions with stale-request cancellation/ignoring.
- Implement the non-diagnostic no-result contract and the A–Z symptom directory with an approved medical disclaimer. Do not log searches in Free when Pro analytics is absent or disabled.
- Implement cache keys/generation and invalidation from `06-technical-spec.md` §9. Cache only published public-safe view models.
- Implement Free booking actions for external URL/hospital booking page, phone, and enquiry. Validate schemes/destinations, targets, and configured safe fallbacks; never fabricate availability.

## Pro boundaries

- Location, insurance, condition, treatment, consultation-type, date, schedule, and availability filters are Pro.
- Multiple locations, location-aware booking, BookPilot, aggregate analytics, earliest-availability sorting, import/export, and redirect management are Pro.
- Privacy, security, accessibility, public allowlisting, and safe booking validation remain Free requirements.

## Change-impact constraints

- **Storage/schema:** consume `FieldRegistry` and canonical services; do not query raw meta from templates.
- **Public API:** follow the exact parameter, response, and error contracts in `06-technical-spec.md` §7.
- **Permissions/privacy:** public GET routes use no nonce and expose published allowlisted data only; analytics remain off and absent without Pro opt-in.
- **Elementor/theme/SEO:** native output must work without Elementor; filtered/internal combinations must not become indexable; template overrides remain documented.
- **Performance:** bound candidate retrieval, facets, suggestions, page size, and offsets; avoid hydrating all records before pagination.

## Acceptance criteria

- Visitors can find a published doctor by name, department, specialty, or assigned symptom.
- Filter changes update results, count, chips, and canonical URL without reload; reload and back/forward restore equivalent state.
- The same query works through a standard GET form when JavaScript/REST is unavailable.
- Only published, allowlisted doctor data is returned publicly; private fields and provider internals are absent.
- Pagination is keyboard accessible and preserves a crawlable page state.
- No-result output uses only approved curated/configured suggestions and makes no diagnostic claim.
- Native directory/profile output remains functional with Elementor inactive.
- Unsafe booking destinations are rejected; provider/action failure uses only an explicitly configured safe fallback.
- With analytics disabled or Pro absent, searches create no analytics rows or tracking requests.

## Verification

- Unit/contract tests for normalization, bounded inputs, ranking/ties, Unicode, serializers, booking CTA validation, and stable errors.
- Integration tests for every REST route, public/private fixtures, filter combinations, pagination, cache invalidation, and unpublished exclusion.
- Frontend tests for URL/history restoration, stale requests, filter chips, pagination, and no-JS fallback.
- Accessibility checks plus manual keyboard and screen-reader smoke tests for search, drawer, chips, pagination, result announcements, profile, and booking.
- Benchmark the approved 1,000-doctor fixture and record p50/p95 and query count; target REST p95 ≤ 500 ms excluding network time.
- Manual desktop/tablet/mobile and theme-override smoke tests. Report exactly what ran.

## Docs to sync

`02-requirements.md`, `03-architecture.md`, `04-decisions.md` when required, `06-technical-spec.md`, `readme.txt`, and `CHANGELOG.md`.
