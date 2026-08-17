# 04 — Architecture Decision Records

Each ADR records a durable decision. A later ADR supersedes an accepted decision; implementation must not silently rewrite history.

## ADR-0001 — Store doctors as structured data

- **Status:** Accepted — 2026-07-27 (partially superseded by ADR-0017 for reusable library content)
- **Context:** Page-builder-owned profiles make search, reuse, migration, and validation unreliable.
- **Decision:** Store doctors as a dedicated WordPress post type with taxonomy relationships and registered structured metadata. Rendering systems consume this data but do not own it.
- **Alternatives:** Elementor-authored pages; generic pages plus ad hoc custom fields; third-party directory as system of record.
- **Consequences:** One edit can propagate everywhere. DoctorPilot must maintain a field registry, serializers, migration path, and rendering integrations.

## ADR-0002 — Directory and discovery first; booking is an adapter

- **Status:** Accepted — 2026-07-27
- **Context:** Native scheduling would expand v1 into clinic management.
- **Decision:** DoctorPilot resolves safe booking calls to action through providers. It stores no appointments or patient records in v1.
- **Alternatives:** Native appointments; one hard-coded booking integration.
- **Consequences:** Smaller, clearer scope and a stable BookPilot integration boundary. Verified availability cannot be promised without provider support.

## ADR-0003 — Keep Symptom, Condition, and Treatment separate

- **Status:** Accepted — 2026-07-27
- **Context:** Patient experiences, diagnoses, and interventions are medically and editorially distinct.
- **Decision:** Model them as separate prefixed taxonomies. Symptom-to-department/specialty relationships are explicitly curated.
- **Alternatives:** One “medical” taxonomy; free-text tags; automatically generated relationships.
- **Consequences:** Clearer search and SEO; more editorial work. Results require a non-diagnostic disclaimer.

## ADR-0004 — REST search with canonical URL state and server fallback

- **Status:** Accepted — 2026-07-27
- **Context:** Patients need fast filtering, shareable results, browser history, and resilience.
- **Decision:** Use public GET REST endpoints for progressive enhancement, encode normalized state in the URL, and render the same query server-side without JavaScript.
- **Alternatives:** Full-page-only search; `admin-ajax.php`; client-only SPA.
- **Consequences:** Better UX and accessibility. Requires bounded inputs, cache rules, history tests, and noindex control for search/filter combinations.

## ADR-0005 — Product name and code identity

- **Status:** Accepted for product name; directory slug reservation pending — 2026-07-27
- **Context:** The name must fit the BrainStudioz product family without making unsupported AI or Elementor trademark claims.
- **Decision:** Use **DoctorPilot – Doctor Directory & Search**, text domain `doctorpilot`, namespace `BrainStudioz\DoctorPilot`, and procedural prefix `bstdz_doctorpilot_`. Preferred WordPress.org slug is `doctorpilot`; fallback is `doctorpilot-directory`.
- **Alternatives:** Doctor Directory Manager; Find a Doctor Directory; Physician Directory Search.
- **Consequences:** Availability is final only when WordPress.org accepts/reserves the slug. “Elementor” may appear descriptively, not as the leading product identity.

## ADR-0006 — Separate reusable permalink policy from deployment migrations

- **Status:** Accepted — 2026-07-27
- **Context:** Aman Hospital needs `/find-a-doctor/dr-{slug}/`, but a reusable plugin must not hardcode one hospital’s host or force a `dr-` prefix.
- **Decision:** Default public base is `find-a-doctor`; the WordPress post slug remains explicit/editorial. Base changes are controlled settings. Aman preserves its reviewed `dr-` destinations through explicit mappings.
- **Alternatives:** Force `dr-` for every install; keep `/doctor/`; hardcode the Aman domain.
- **Consequences:** Clean reusable behavior and safe reference migration. Rewrite settings require validation, a one-time flush, and redirect planning.

## ADR-0007 — Migration imports as unpublished, review-required content

- **Status:** Accepted — 2026-07-27
- **Context:** Legacy content may contain copied testimonials, placeholder counters, wrong pronouns, or outdated booking data.
- **Decision:** Imports create/update draft or pending profiles and a separate review state. Only a capable human publisher may publish.
- **Alternatives:** Auto-publish; manual re-entry.
- **Consequences:** Safer data quality with an additional workflow step. Import must provide completeness, row errors, and review reporting.

## ADR-0008 — Free/Pro split must not gate safety or duplicate ownership

- **Status:** Accepted — 2026-07-27; **partially superseded by ADR-0020 (2026-08-15)**, which merges the two artifacts into one. The *safety* half of this decision — that security, privacy, accessibility and data-safety are never gated — survives unchanged and is restated in ADR-0020. The *packaging* half (two plugins, extension-registry boundary, graceful refusal without a base) no longer describes the build.
- **Context:** A freemium product needs a clear upgrade path and WordPress.org-compliant Free value.
- **Decision:** Free owns canonical data, core search/rendering, essential SEO, external booking, security, privacy, and accessibility. Pro extends locations, advanced filters, BookPilot, migration tools, aggregate analytics, advanced Elementor integration, and workflow controls.
- **Alternatives:** Paid-only; search locked behind Pro; Pro fork of the Free codebase.
- **Consequences:** Free remains complete. Pro must register extensions, declare compatibility, and fail gracefully without a compatible base.

## ADR-0009 — No identifiable patient or medical-search data

- **Status:** Accepted — 2026-07-27
- **Context:** A directory does not need patient identity, and symptom searches may be sensitive.
- **Decision:** Store no patient records and no person/session-linked search history. Optional analytics use daily aggregates only and are disabled until administrator opt-in.
- **Alternatives:** Full search sessions; cookies for favourites; patient accounts.
- **Consequences:** Lower privacy and compliance risk. Patient features require a separate product and privacy/security ADR.

## ADR-0010 — Prefix all internal WordPress identifiers

- **Status:** Accepted — 2026-07-27
- **Context:** Generic keys such as `doctor`, `department`, and `specialty` can collide with themes or other plugins.
- **Decision:** Use prefixed internal post-type, taxonomy, meta, option, transient, cron, handle, table, capability-group, and hook identifiers. Public rewrite slugs remain clean and configurable.
- **Alternatives:** Keep generic internal keys; detect conflicts at runtime.
- **Consequences:** Safer coexistence. If implementation already exists with generic keys, a versioned migration and compatibility alias plan is required.

## ADR-0011 — Elementor is optional; Pro-specific APIs are guarded

- **Status:** Accepted — 2026-07-27
- **Context:** Core Elementor widgets can be provided by an add-on, but active dynamic tags and Theme Builder conditions depend on Elementor Pro.
- **Decision:** DoctorPilot works through native templates/shortcodes without Elementor. Core widgets load with compatible Elementor. Complete dynamic tags and custom Theme Builder conditions load only with compatible Elementor Pro.
- **Alternatives:** Require Elementor; advertise Theme Builder without dependency guards.
- **Consequences:** Wider compatibility and no fatal errors. UI/documentation must clearly label Elementor Pro-dependent features.

## ADR-0012 — Native canonical storage with rebuildable search derivatives

- **Status:** Accepted — 2026-07-27 (§5.2 repeater treatment of procedures/education/publications/FAQs superseded by ADR-0017)
- **Context:** WordPress-native storage is portable, while complex discovery requires normalized values and caching.
- **Decision:** Posts/terms/meta remain canonical. Completeness, normalized search documents, sort values, and response caches are derived and rebuildable. Initial search uses optimized native queries plus bounded application scoring.
- **Alternatives:** Custom search table from day one; external search service; raw unbounded `WP_Query`.
- **Consequences:** Lower initial complexity and good portability. A dedicated index requires benchmark evidence and a new ADR.

## ADR-0013 — Publication status and review status are separate

- **Status:** Accepted — 2026-07-27
- **Context:** An editable `profile_status=published` duplicates WordPress post status and can drift.
- **Decision:** WordPress `post_status` controls public visibility. A prefixed review-status meta value and computed completeness score support editorial workflow.
- **Alternatives:** Custom publication field; custom post statuses for every review state.
- **Consequences:** Public queries reliably use `post_status=publish`; admin workflow remains expressive without conflicting truth.

## ADR-0014 — Capabilities are primary; custom roles are optional presets

- **Status:** Accepted — 2026-07-27
- **Context:** Sites already have different role models, and role-name checks are brittle.
- **Decision:** Authorize through custom mapped capabilities and object/department scope. Optional Pro role presets grant those capabilities but business logic never checks a role name.
- **Alternatives:** Hard-code Administrator/Editor; create roles unconditionally.
- **Consequences:** Better interoperability. Installation, upgrade, and uninstall must manage capabilities idempotently.

## ADR-0015 — One owner per SEO artifact

- **Status:** Accepted — 2026-07-27
- **Context:** DoctorPilot, WordPress core, and SEO plugins can emit duplicate canonicals, schema, breadcrumbs, or sitemap entries.
- **Decision:** Resolve an SEO owner for each artifact. DoctorPilot supplies reviewed data and emits only what the active owner does not already handle.
- **Alternatives:** Always output DoctorPilot metadata; disable SEO integrations.
- **Consequences:** Cleaner markup and fewer conflicts. Compatibility tests are required for WordPress core plus the supported SEO plugins.

## ADR-0016 — Analytics are aggregate, opt-in, and local by default

- **Status:** Accepted — 2026-07-27
- **Context:** WordPress.org requires consent for tracking, and medical searches deserve stricter minimization.
- **Decision:** Pro analytics are disabled by default, stored locally as daily aggregates, and never include identity/session fields. External telemetry requires a future separate consent decision.
- **Alternatives:** Raw event logs; third-party analytics by default; no analytics.
- **Consequences:** Limited journey analysis but substantially lower privacy risk and clearer retention/deletion behavior.

## ADR-0017 — Reusable global content library (hybrid CPT + local repeater)

- **Status:** Accepted — 2026-07-28
- **Implementation status:** Implemented — all five types shipped. Experience & Education landed 2026-07-29 (schema version 2); Procedures, Publications, and FAQs landed 2026-07-30 via a data-driven `LibraryPostTypes::schema()`, reusing each type's existing doctor repeater as its local store (no migration).
- **Amendment (2026-07-29):** Experience & Education is implemented as **two** separate CPTs — `doctorpilot_exp` (Experience) and `doctorpilot_edu` (Education) — instead of the single combined `doctorpilot_edu_exp` originally proposed. Rationale: the two lists are surfaced under distinct headings and carry different label semantics (Experience: position/organization; Education: qualification/institution), so a shared record type forced an ambiguous `type` discriminator. The two CPTs share one field schema — `primary` (position or qualification), `secondary` (organization or institution), `start_year`, `end_year`, `country` (all prefixed `_doctorpilot_lib_*`), plus `description` in `post_content`. Doctors reference them through ordered ID lists `_doctorpilot_exp_ids` / `_doctorpilot_edu_ids` and keep doctor-specific rows in `_doctorpilot_exp_local` / `_doctorpilot_edu_local`. The reverse-reference index stores assigned doctor IDs in `_doctorpilot_lib_doctors` on each record. Because Experience/Education are **not** part of the search document, global edits reflect live through the resolver with no derived-data rebuild; only a small `before_delete_post` cleanup is needed. Schema-version-2 migration splits any legacy `_doctorpilot_education_experience` rows into the two local repeaters (rows typed education/fellowship/residency/training/degree/qualification → Education; all others → Experience). Procedures, Publications, and FAQs will follow the original single-CPT-per-type plan when implemented.
- **Context:** Procedures, Experience & Education, Publications, and FAQs were stored only as per-doctor array-meta repeaters (ADR-0001/ADR-0012 §5.2). Hospitals need these items to be reusable across many doctors and editable centrally, while still allowing independent doctor-specific entries. A repeater-only model cannot share or centrally update a record, and duplicating identical repeater rows across doctors is unmaintainable.
- **Decision:** Introduce a **hybrid** model for these four content types. Each becomes a reusable, prefixed **Free custom post type** — `doctorpilot_proc`, `doctorpilot_edu_exp`, `doctorpilot_pub`, `doctorpilot_faq` (all ≤ 20 characters, the WordPress post-type limit) — whose `post_status` is the publication authority. Doctors reference global records through ordered, unique ID lists in prefixed meta (`_doctorpilot_{proc|edu_exp|pub|faq}_ids`) **and** retain the existing repeater meta for doctor-specific ("local") items. A single shared `DoctorContentResolver` merges globals (published only, in public context) with local items into one ordered list (globals first, then locals), tagging each item's origin in admin context only. All consumers — native templates, search-document builder, single REST response, and the Elementor Doctor Profile widget — read through this one resolver. Global records are not publicly queryable on their own; they surface only through a doctor. A rebuildable reverse-reference index (`_doctorpilot_assigned_doctor_ids` on each global) enables O(1) impact lookup; a global edit/unpublish/delete/merge increments cache generation immediately and enqueues bounded rebuilds of affected doctors' derived search documents. Assignment lists de-duplicate to unique IDs; global creation blocks exact normalized-key duplicates (override requires `manage_doctorpilot_library`, audit-logged).
- **Scope of supersession:** This supersedes the treatment of Procedures, Experience & Education, Publications, and FAQs as doctor-only repeaters in ADR-0001 and ADR-0012 §5.2. All other doctor fields remain canonical doctor metadata. ADR-0013 (post_status is publication authority) and ADR-0010 (prefixed identifiers) continue to apply, now also to the library CPTs. Because this decision is Accepted, the superseded portions of ADR-0001/ADR-0012 are marked accordingly; the remainder of those ADRs stands.
- **Alternatives:** (a) Keep repeater-only — rejected: no reuse or central update. (b) Taxonomy terms + term meta — rejected: thin, awkward for rich multi-field records and ordering. (c) All-CPT (local items also become posts) — rejected: heavy, and complicates independence/cleanup of one-off local notes.
- **Consequences:** Reusable, centrally-updatable records with preserved doctor-specific independence and full backward compatibility (existing repeaters become local items with no data loss). Adds four managed CPTs, reference storage, a resolver, a reverse-reference index with cache-generation/rebuild invalidation, an assignment UI, and one authenticated internal search route. Requires defined lifecycle behavior for edit/unpublish/trash/delete/merge and duplicate prevention on both assignment and creation. Full design in `11-global-content-library.md`; requirements FR-058–FR-063 and AC-025–AC-035; tech-spec §5.4/§7/§10–11.

## ADR-0018 — Browse indexes filter the results; they do not replace them

- **Status:** Accepted — 2026-08-08
- **Context:** The Doctor Grid/List widget's browse tabs (By Doctor Name / By Department / By Specialty / By Symptom) swapped the doctor results out for an index of that taxonomy, whose entries linked to term archives. Three problems followed. Visitors lost the doctors while browsing and had to leave the widget to get them back, so browsing and searching were two disconnected journeys. "By Doctor Name" had no index at all — it browsed nothing — and the A–Z bar it did offer only appeared in the compact list layout. And because a term entry navigated to an archive, the widget's own filters, ranking, and cache never applied to the thing the visitor had just chosen.
- **Decision:** Render the index and the results as two regions that are visible together. A tab decides which index is shown above the results; the results always stay on screen below. Choosing an index entry writes that index's **ordinary search parameter** — a new `doctor` parameter (doctor post slugs, applied as `post_name__in` before scoring) for names, and the existing `department` / `specialty` / `symptom` parameters for terms — rather than navigating anywhere. Selecting the current entry again clears it; switching tabs clears the tab being left. A parameter owned by a rendered index is not also offered as a select in the filter row. Indexes are catalogues built independently of the active filters, and are rendered server-side for every enabled tab at once, bounded to 500 entries each.
- **Amendment (2026-08-08):** The **Tabs browse an index** switch is removed and every configured tab now browses. Its "off" state promised that the tabs would "only narrow what the doctor search matches", but `mode` was never a ranking input — `Ranker::score()` scores name, specialty, department, and symptom buckets unconditionally — so a widget with the switch off rendered three tabs that did nothing at all. Rather than build a second, unrequested scoping behaviour to make the promise true, the control is dropped: the existing **Search-mode tabs** control already expresses "no browse tabs" by selecting none. Widgets that had the switch off gain working tabs on upgrade, which is the behaviour their tab labels always advertised.
- **Alternatives:** (a) Keep archive links and add a "back to results" affordance — rejected: still two journeys, and the archive cannot honour the widget's filters or sort. (b) Give the index its own client-side filtering over the already-rendered result cards — rejected: results are paginated, so a chosen doctor may not be in the DOM, and the count and pagination would lie. (c) Fetch each index on demand from a new REST route — rejected for now: it adds a public route and a loading state to buy back a bounded first-paint cost, and it would break the no-JavaScript path that currently works by construction.
- **Consequences:** Browsing and filtering share one contract, so the URL, the REST route, the cache key, the active-filter chips, and the no-JavaScript GET fallback all describe a selection identically, and nothing about browsing bypasses ranking or visibility. `SearchRequest` gains a `doctors` list, which changes its constructor signature (appended, defaulted) and its cache signature — a stale cache entry cannot be mistaken for a narrowed one. Tab switching, letter filtering, and index text filtering cost no request. Against that: every enabled index is queried and emitted on each page load even when no tab is open (one bounded query per index, up to ~1500 hidden rows with the default three tabs), and an index cannot be browsed past its 500-entry bound — the visitor is told when that bound is reached. Removed: the doctor A–Z list mode, where the compact list layout paged through up to 480 matches to group them by letter; the By Doctor Name index supersedes it and is available in both layouts.

## ADR-0019 — Three commercial tiers over two code artifacts; migration is one-time, not subscription

- **Status:** Accepted — 2026-08-11; **superseded by ADR-0020 (2026-08-15)**. The premise this ADR reasoned from — that a WordPress.org listing is the acquisition channel worth protecting — was withdrawn by the product owner, which removes the load-bearing argument for two artifacts. Two findings here survive into ADR-0020 and are not re-litigated: that migration tooling is one-time value rather than subscription value, and the licence-lapse safety invariant.
- **Extends:** ADR-0008 (which fixed the *architectural* Free/Pro boundary but assigned no commercial model). ADR-0008 is not superseded — the extension-registry split it mandates is retained here without change.
- **Context:** Pro is built and boots cleanly through `ExtensionRegistry`, but it was never given a commercial shape, and the shape it grew into by accident does not fit the way it was going to be sold. Of Pro's ~4,345 lines, roughly half — import/export, the migration mapping and review-flag heuristics, the Migration admin screens, and reconciliation reporting — is launch tooling. A hospital runs it once, moves 200 doctors off a legacy directory, signs off the reconciliation report, and never opens those screens again. Billing that annually asks a customer to keep paying for a job that finished, which is the classic freemium retention failure: the renewal invoice arrives with nothing to point at. Meanwhile the features that *would* earn a renewal — multi-location records, structured schedules and availability filters, bulk operations, and the wider Elementor widget set — are written into `02-requirements.md` and the vision's Pro v1 boundary but are not implemented (FR-009, FR-017, FR-024, FR-032 beyond Related Doctors, FR-035, FR-046). The proposal that prompted this ADR was to collapse Free and Pro into a single paid plugin and sell one full module. That would trade away the WordPress.org listing, which is the only distribution channel in the plan that does not cost money per customer, to solve a maintenance problem that a shared repository solves more cheaply. Separately, neither plugin contains any licensing, activation, or update code at all: Pro is fully functional the moment its zip is unpacked, so today there is nothing to sell under any model.
- **Decision:** Sell **three tiers across two shipped artifacts**, and keep the artifacts split.
  - **Free — `doctorpilot`**, distributed on WordPress.org, unchanged in scope from ADR-0008. It stays a complete, useful directory on its own.
  - **Pro — `doctorpilot-pro`**, sold as an annual subscription from brainstudioz.com. Its promised value is the *ongoing* directory surface: advanced filters and insurance, multi-location, schedules and availability, bulk operations, the full widget and dynamic-tag set, department-scoped workflow controls, and opt-in aggregate analytics.
  - **Migration — a separately priced, one-time entitlement delivered inside the same `doctorpilot-pro` artifact**, covering CSV import/export, migration mapping and review flags, redirect management, and reconciliation reporting. It is sold as a launch fee or as a done-for-you service engagement, not as recurring value, and it does not require a Pro subscription to have been bought.

  Migration is *not* extracted into a third plugin today. Every migration module already registers through base hooks rather than reaching into base internals, so extraction to a `doctorpilot-migration` artifact stays a mechanical move if migration-only demand ever justifies the third release pipeline; until then a third artifact buys a separation that a licence entitlement already provides.

  Both paid tiers are gated by a licence entitlement layer that does not exist yet and must be built before either can be sold. Gating happens **only inside the paid artifact** — the WordPress.org build contains no locked, encoded, or remotely unlocked Pro code, per vision §11 and the .org guidelines.
- **Licence-lapse safety invariant:** An expired, invalid, or absent licence may withdraw Pro *authoring and administrative* capability. It must never remove published public data, break a public URL, or disable an active redirect. Concretely: doctors stay published, the public REST allowlist keeps answering, advanced filters degrade to the base filter set rather than erroring, and `RedirectResolver` keeps issuing its 301s. A redirect mapping is a promise made to search engines and to every inbound link on the internet; turning it off because an invoice went unpaid would 404 a hospital's live traffic and is the kind of harm ADR-0008 exists to forbid. Analytics remain opt-in and off by default under every licence state (ADR-0016). The lapse behaviour is *read-only degradation*, never destruction.
- **Repository and build model:** One repository holds both plugins; the release pipeline produces two independently versioned zips. This captures the maintenance simplification that motivated the merge proposal — one checkout, one test run, one changelog review — without giving up the .org channel or forking a codebase (the fork was already rejected in ADR-0008).
- **Alternatives:** (a) **Merge into a single paid plugin.** Rejected: it forfeits the WordPress.org listing, making paid acquisition the only acquisition; it makes the free tier undeliverable, contradicting vision §11 and the "Free remains complete" consequence of ADR-0008; and the `ExtensionRegistry` API that would have to be unwound is already working. It is the right answer only for a direct-sales-only business, which this is not. (b) **Keep migration inside a single Pro subscription.** Rejected: it is the status quo and it is what produces the weak-renewal problem described above; it also over-states Pro's current value, since a Pro sold today would be roughly half launch tooling and a thin slice of everything else. (c) **Give migration away in Free.** Rejected: import writes to canonical doctor data and rollback deletes posts, so it carries the highest support burden in the product, and it is the single strongest reason a hospital pays anything at all — the moment of maximum willingness to pay is exactly the migration. (d) **Extract migration into a third `doctorpilot-migration` plugin now.** Deferred, not rejected: a third artifact triples the release and compatibility matrix to serve a migration-only customer who has not yet appeared.
- **Compatibility impact:** No runtime, schema, hook, capability, or public-contract change. `ExtensionRegistry`, `DOCTORPILOT_PRO_REQUIRES_API`, table ownership, and the base extension API are all untouched. The change is commercial and documentary, plus the future addition of a licence layer, which must be additive and must satisfy the lapse invariant above. Existing Aman deployment behaviour is unaffected.
- **Consequences:** The upgrade story becomes honest: Free acquires, Migration monetises the launch, Pro monetises the ongoing directory. Against that, **Pro is not saleable as a subscription in its present state** — strip the migration tooling out of the pitch and what remains is one Elementor widget, an insurance taxonomy, four extra filters, scoped roles, and an analytics dashboard that ships disabled. The specced-but-unbuilt Pro features stop being roadmap nice-to-haves and become the precondition for charging a renewal. Two new pieces of work are now blocking revenue rather than optional: the licence/activation/update layer, and enough of FR-009/FR-017/FR-024/FR-032/FR-035/FR-046 to make year two worth paying for. Pro's test coverage (two files, ~135 lines) is also thin for code that will be sold; it needs to rise before first sale.
- **Follow-up work:**
  - *Done with this ADR:* tier matrix and sequencing recorded in `12-packaging-and-tiers.md`; `01-product-vision.md` §8/§11 and `02-requirements.md` §3/§3.1/§4.7 updated; `03-architecture.md` and `06-technical-spec.md` retagged from "Pro" to "Migration tier" where they describe migration-owned tables, capabilities, and sections; the false "bulk operations" claim removed from the Pro plugin header and `readme.txt`.
  - *Outstanding:* raise a licence-layer decision (self-hosted vs Freemius vs EDD Software Licensing) as its own ADR, since it adds a core dependency and a remote runtime call; reconcile the Free/Pro Elementor widget boundary, where sixteen widgets are registered in Free against six documented and some of them are widgets FR-032 assigns to Pro; open issues for the unbuilt Pro features against the tier they belong to; raise Pro test coverage above its current two files before the first paid release.

## ADR-0020 — One artifact: merge Pro into the base plugin

- **Status:** Accepted — 2026-08-15
- **Supersedes:** ADR-0019 in full, and the packaging half of ADR-0008. The safety half of ADR-0008 is restated below rather than dropped.
- **Context:** ADR-0019 kept the two plugins split for one dominant reason: a WordPress.org listing is free, permanent, search-visible to the exact audience typing "doctor directory" into the plugin installer, and .org guidelines forbid shipping locked or remotely unlocked commercial code in a directory build — so a merged plugin simply cannot be listed. Every other argument in that ADR was secondary, and two of them (maintenance cost, unwinding working architecture) were explicitly weighed as lighter. The product owner has now withdrawn the .org plan: distribution is direct sales to clients, with CodeCanyon as the possible marketplace. That removes the load-bearing premise. Nothing else about the code changed — `ExtensionRegistry` still works, the boundary was still clean, Pro still never reached into base internals. The decision simply lost its reason.

  The replacement channels also pull the other way. A CodeCanyon item is expected to be one self-contained plugin: an item that only works once the buyer separately installs a second plugin is a support burden and a review risk. CodeCanyon also sells at a one-time price with a bounded support window, which happens to fit ADR-0019's own finding that roughly half of Pro is launch tooling a hospital runs once — the tier split existed to stop that tooling being billed annually, and a one-time price makes the problem disappear rather than needing to be designed around. For direct sales, one plugin is simply one fewer thing to explain, install, version and break.
- **Decision:** Ship **one plugin, `doctorpilot`**, containing everything. Concretely:
  - The add-on's 34 runtime classes move into the base tree under the base namespace, at their existing relative paths. Only three files collide, and all three are exactly the files that should merge: `Support/Autoloader.php` (the add-on's copy is deleted), and `Infrastructure/Lifecycle/Activator.php` and `Deactivator.php` (hand-merged).
  - The add-on's bootstrap, its `Plugin` singleton, and its `maybe_boot()` registration handshake are deleted. The modules are booted directly from `Infrastructure\Plugin::boot()`.
  - **`ExtensionRegistry` and the `doctorpilot/registered` action are retained.** Nothing in this repository consumes them now, but they are a published contract; removing them would break any third-party add-on already written against them and would buy nothing. `DOCTORPILOT_BASE_API_VERSION` stays at 1.
  - Every table name, option key, capability, role slug and cron hook keeps its existing string, including the ones containing `_pro_`. These identify live rows on installed sites; renaming them for tidiness would strand data and stop in-flight imports. The names are historical, not descriptive, and the code says so.
  - A schema migration (version 3) adopts those tables, roles and schedules on upgrade, because merging code does not re-run an activation hook. Every step is idempotent, so it is a no-op on a site that had both plugins and a first install on a site that never had the add-on.
  - The text domain becomes `doctorpilot` throughout. No `.mo` files existed under `doctorpilot-pro`, so nothing is orphaned.
- **Safety invariant, carried forward from ADR-0008 and ADR-0019:** Security, privacy, accessibility, and data-safe uninstall are never gated behind any commercial condition. If a licence layer is ever added, an expired or absent licence may withdraw *authoring and administrative* capability only. It must never unpublish data, break a public URL, or disable an active redirect: a redirect mapping is a promise to search engines and to every inbound link on the internet, and switching it off over an unpaid invoice would 404 a hospital's live traffic. Degradation is read-only, never destructive. Analytics stay opt-in and off by default under every licence state (ADR-0016).
- **Alternatives:** (a) **Keep the split and ship a merged build artifact from split sources.** Rejected: it keeps the registration handshake, the compatibility matrix and the two-changelog overhead in order to preserve a channel that is no longer being used — cost with no remaining benefit. (b) **Merge but keep the features gateable behind a licence check.** Deferred, not rejected: it is additive and can be built later if a tiered price ever returns; building it now would add a remote runtime call and a core dependency to serve a pricing model that does not exist. (c) **Do nothing.** Rejected: the documentation would keep asserting a .org strategy the product owner has abandoned, and `12-packaging-and-tiers.md` would stay actively misleading to anyone reading it as current.
- **Retiring the old copy is the plugin's job, not the site owner's.** The add-on's files stay on disk after an upgrade, still active, still carrying lifecycle handlers written when it owned data this plugin now owns — and two of them destroy that data. Its deactivation handler clears `bstdz_doctorpilot_pro_retention` and `bstdz_doctorpilot_pro_run_import`, the same hook names kept above, so following an instruction to "deactivate the old plugin" would stop retention cleanup permanently and strand any import between chunks. Its `uninstall.php` reads *this* plugin's `delete_on_uninstall` setting and drops the redirect and import tables, so deleting it on a site with that setting on would remove every legacy-URL redirect and start 404ing live inbound traffic. `Infrastructure\LegacyAddon` therefore deactivates it through the **silent** form of `deactivate_plugins()`, which skips the `deactivate_{$plugin}` action and never runs that handler; recurring schedules are re-created on any admin request that finds them missing; and an admin notice explains the one remaining hazard, which cannot be prevented from outside — WordPress includes a plugin's `uninstall.php` on delete regardless of what any other plugin wants.
- **Compatibility impact:** No public contract changes. Same REST routes, same shortcodes, same template names and override paths, same Elementor widget names, same table schema, same option keys, same capabilities, same cron hooks. Saved Elementor widget settings, existing doctors, import history and redirects are all untouched.
- **Consequences:** One artifact to build, version, test and support. The WordPress.org funnel is given up, so every customer must now be found and paid for — that cost is accepted deliberately, not overlooked. The Free / Migration / Pro tier matrix ceases to exist; `12-packaging-and-tiers.md` is rewritten to describe a single product. The ADR-0019 observation that migration is one-time value is preserved by the choice of channel rather than by a tier boundary. Because there is no longer a free tier acting as the trial, the product needs some other way for a prospect to evaluate it before paying — a demo site, a trial licence, or the CodeCanyon preview — and that is now an open question rather than a solved one.
- **Follow-up work:**
  - *Done with this ADR:* the merge itself; schema migration 3; `LegacyAddon` and the schedule guard; a complete uninstall handler that reads its table, role and capability lists from the classes that own them rather than restating them; `12-packaging-and-tiers.md` rewritten; ADR-0008 and ADR-0019 status lines updated; stale "requires the base plugin" statements corrected across the moved files.
  - *Outstanding:* decide how a prospect evaluates the plugin without a free tier; if CodeCanyon is pursued, check the item against its submission requirements (self-contained, GPL-compatible, documentation, no external paid dependency); revisit whether a licence layer is wanted at all under a one-time price; the widget-boundary and test-coverage items carried over from ADR-0019 still stand.
