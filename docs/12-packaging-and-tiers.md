# 12 — Packaging and Route to First Sale

**Status:** Accepted — 2026-08-15. Authority for the commercial model is ADR-0020; this document is the working detail behind it. Where the two disagree, the ADR wins.

This document replaces the version dated 2026-08-11, which described three commercial tiers across two code artifacts and argued at length against merging them. That argument rested on keeping a WordPress.org listing. The product owner has withdrawn the .org plan in favour of direct sales and possibly CodeCanyon, which removes the premise; the plugins are now merged. The superseded reasoning is preserved in ADR-0019 rather than deleted, because it is still the correct analysis of a different business.

---

## 1. The decision in one paragraph

DoctorPilot ships as **one plugin, `doctorpilot`**, containing everything: the directory, search, Elementor widgets, the advanced taxonomies and filters, CSV migration, redirects, reconciliation, scoped roles, and opt-in analytics. It is sold **direct to clients**, with **CodeCanyon** as a possible marketplace. There is no free tier, no add-on, and no tier matrix. Safety, privacy, accessibility, and data-safe uninstall are not commercial features and are never gated.

---

## 2. What the product is

One artifact, one price, one install. A hospital buys it, activates it, imports its existing directory, and runs it.

| | |
|---|---|
| **Artifact** | `doctorpilot` |
| **Channels** | Direct sales; CodeCanyon under evaluation |
| **Pricing shape** | One-time, with a bounded support window |
| **Bought by** | A hospital or clinic group running a doctor directory |
| **Bought when** | At migration — the point of maximum willingness to pay |
| **Requires** | WordPress 6.4+, PHP 8.1+. Elementor optional. |

### What is in it

Canonical doctor data and the field registry; all eight taxonomies (department, specialty, symptom, condition, treatment, language, location, insurance); name/department/specialty/symptom search with deterministic ranking; the full filter set; canonical URL state with a no-JavaScript fallback; the public REST allowlist; the Elementor widget set including Doctor Grid/List, Doctor Carousel/Grid and Related Doctors, plus the dynamic-tag coverage; native templates and shortcodes; external, phone, enquiry and BookPilot booking; SEO ownership resolution and Physician schema; the global content library; CSV import with dry run, resumable chunked writing and rollback; formula-safe export; legacy-URL redirect management; reconciliation reporting; department-scoped and self-edit roles; opt-in aggregate analytics; and the platform layer (RuntimeGuard, capabilities, migrator, `ExtensionRegistry`).

`ExtensionRegistry` and the `doctorpilot/registered` action stay published even though nothing in this repository consumes them now. They are the contract a third-party add-on would be written against, and removing them would break such an add-on to save nothing.

---

## 3. What changed, and what it cost

**Gained.** One codebase, one version number, one changelog, one test run, one support surface, one thing for a client to install. The registration handshake, the compatibility matrix, and the "requires the base plugin" failure mode are all gone. A CodeCanyon item is expected to be self-contained; an item that only works after the buyer separately installs a second plugin is a review risk and a support burden, so the merge is a precondition for that channel rather than merely convenient.

**Given up, deliberately.** The WordPress.org listing was free, permanent, and search-visible to exactly the audience typing "doctor directory" into the plugin installer. Every customer must now be found and paid for. That was the strongest argument in the superseded document and it has not become wrong — it has become irrelevant, because it is not the plan.

**Now unsolved.** The free tier used to be the trial: a prospect could install it, load twenty doctors, and decide. With one paid artifact there is no such path. Something has to replace it — a demo site, a time-limited trial licence, or the CodeCanyon preview — and until one exists, a prospect's only way to evaluate the product is a sales conversation. This is the open question the merge created, and it is tracked as one rather than quietly assumed away.

---

## 4. The one-time pricing shape

The superseded document identified a real problem: roughly half the codebase is launch tooling. Import, mapping, review-flag heuristics, the Migration admin screens, and reconciliation get run once. A hospital moves 200 doctors off a legacy directory, signs off the reconciliation report, and never opens those screens again. Billing that annually creates a renewal conversation with nothing to point at — "what did we pay for this year?" has no good answer when the headline feature finished its job eleven months ago.

That analysis still holds. It is now solved by the price shape rather than by a tier boundary: a one-time fee is charged at the moment the tooling delivers its value, which is also the moment of maximum willingness to pay. Nothing needs to justify a renewal because nothing renews.

The consequence to stay honest about is the inverse. With no recurring revenue, ongoing development is funded by new sales and by paid engagements, not by a subscription base. Features that would have been "the reason to renew" — multi-location records, structured schedules and availability filters, bulk operations — are now simply features that make the next sale easier, and should be prioritised on that basis rather than on retention.

---

## 5. Licensing

There is no licensing, activation, or update code in the plugin. Under a one-time price sold direct, or through a marketplace that handles its own entitlement, that may be the correct permanent answer rather than a gap — CodeCanyon verifies purchases itself, and a direct client who has paid does not need to be policed.

If a licence layer is ever added it must be additive, and it must satisfy the safety invariant from ADR-0020: an expired or absent licence may withdraw authoring and administrative capability only. It must never unpublish data, break a public URL, or disable an active redirect. A redirect mapping is a promise made to search engines and to every inbound link on the internet; turning it off because an invoice went unpaid would 404 a hospital's live traffic. Degradation is read-only, never destructive.

---

## 6. Before first sale

1. **Evaluation path.** Decide how a prospect sees the product without a free tier (§3). Blocking.
2. **CodeCanyon fit, if pursued.** Check the item against submission requirements: self-contained, GPL-compatible, documented, no external paid dependency, and a working preview.
3. **Test coverage.** The merged migration and advanced-directory code carries two test files. That is thin for code that writes to canonical doctor data and can delete posts on rollback. Raise it before money changes hands.
4. **Widget boundary documentation.** `02-requirements.md` FR-031/FR-032 still describe a Free/Pro widget split that no longer exists. Reconcile the requirement text with the registered widget list.
5. **Upgrade note for existing installs.** Nothing is required of the site owner: `Infrastructure\LegacyAddon` deactivates a leftover `doctorpilot-pro` on the next admin page load, silently, so the add-on's own deactivation handler never runs and the schedules it would have cleared survive. The admin notice it leaves explains the one hazard that cannot be handled from outside — the old copy's `uninstall.php` drops the redirect and import tables if "Delete data on uninstall" is on, so the folder should be removed over FTP or with that setting switched off.
