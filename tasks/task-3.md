# Task 3 — Elementor Integration & Pro Extension Contracts

> Legacy planning reference. The canonical implementation sequence is indexed in `tasks/README.md`; use `milestone-4-elementor.md` and `milestone-6-pro-features.md` for executable work.

**Covers development phases:** Elementor presentation and guarded extension integration
**Tier:** Free core widgets plus DoctorPilot Pro v1 extensions
**Goal:** Make canonical DoctorPilot data designable in Elementor while preserving native fallback and a strict Free/Pro ownership boundary.
**Depends on:** Tasks 1 and 2
**Blocks:** Task 4
**Requirements:** FR-031–FR-036, FR-039–FR-040, NFR-001, NFR-005, NFR-008, NFR-010
**Acceptance criteria:** AC-010–AC-014

## Scope

### Free plugin

- Detect compatible Elementor versions before referencing Elementor classes; keep DoctorPilot fully functional when Elementor is absent or incompatible.
- Provide the Free core widgets: Doctor Search, Doctor Grid/List, Doctor Profile, Department Grid, Symptom Directory, and Booking Button.
- Reuse the same search request/service, doctor view model, serializer, templates/partials, booking CTA resolver, and design tokens as native output.
- Register prefixed widget names and dependencies through Elementor APIs; load assets only for widgets present on the page.
- Expose controls appropriate to each widget: typography, color, spacing, border/radius, image ratio, columns, responsive behavior, focus/hover, loading, and empty states.
- Ensure widget settings override DoctorPilot global design tokens without storing canonical doctor content in Elementor document JSON.
- Provide meaningful live editor previews, semantic output, valid headings, scoped selectors, keyboard behavior, visible focus, reduced motion, and RTL support.

### DoctorPilot Pro add-on

- Add Advanced Doctor Search, Doctor Carousel, Doctor Information/section widgets, Doctor Locations, Doctor Schedule, Related Doctors, Results Count, Active Filters, and Pagination.
- Add complete dynamic tags and custom Theme Builder conditions only when compatible Elementor Pro APIs are active.
- Extend the Free field registry and services through versioned hooks/interfaces; do not register duplicate CPTs, taxonomies, canonical fields, controllers, routes, menus, serializers, or search engines.
- Implement location-aware and BookPilot booking through `BookingProviderInterface`, `BookingCapabilities`, and validated `BookingCta` values. Keep Free external URL, phone, and enquiry actions available.
- Negotiate a compatible base API version. Missing/incompatible Free, Pro, Elementor, or Elementor Pro produces a safe actionable notice and never a fatal error.

## Non-goals

- Canonical content stored in Elementor, unconditional Elementor/Elementor Pro dependencies, deprecated API copies, globally leaking CSS, native appointment storage, or fabricated availability.
- CSV import/export, redirect management, analytics dashboard, and release migration execution (Task 4).

## Change-impact constraints

- **Storage/schema:** Elementor reads canonical view models; widget/document settings contain presentation and query configuration only.
- **Public API:** widgets use the Task 2 REST/search contracts rather than creating parallel endpoints.
- **Permissions/privacy:** preview and editor paths must not bypass public/private visibility or object capabilities.
- **Compatibility:** test Elementor absent, Free, and Pro plus DoctorPilot Pro absent/incompatible; guard every optional class/API boundary.
- **Migration/backward compatibility:** widget identifiers and settings need stable defaults and documented upgrade handling.

## Acceptance criteria

- With Elementor inactive, native templates and shortcodes continue to render the directory and profile.
- With Elementor Free active, every core widget renders canonical DoctorPilot data and loads only declared assets.
- Updating a doctor field updates native and Elementor output without editing Elementor content.
- Widget controls produce responsive, accessible output and override global DoctorPilot design defaults.
- With Elementor Pro absent, DoctorPilot Pro does not fatal; dynamic-tag/Theme Builder features remain disabled with a clear dependency notice.
- Pro widgets and complete dynamic tags are not shipped as remotely unlocked or dormant Pro implementation in the WordPress.org Free package.
- Booking provider output is revalidated; a failure shows only an explicit safe fallback and no unverified availability.

## Verification

- Compatibility matrix: Elementor absent, supported Elementor Free, supported Elementor Pro, DoctorPilot Pro absent, compatible, and incompatible.
- Contract tests for widget registration/dependencies, dynamic-tag/condition guards, base API negotiation, booking providers, unsafe output, and safe fallback.
- Manual: build a core grid and profile, edit canonical data and confirm propagation, test Pro section widgets/conditions when dependencies exist, and verify no duplicate menus/routes.
- Accessibility and responsive review at supported breakpoints, including keyboard/focus, headings, empty/loading states, RTL, and reduced motion.
- Theme compatibility and asset-loading/query-count checks. Report exactly what ran.

## Docs to sync

`01-product-vision.md`, `02-requirements.md`, `03-architecture.md`, `04-decisions.md` if an integration contract changes, `06-technical-spec.md`, Free and Pro readmes/changelogs, and any design-system document that is actually added.
