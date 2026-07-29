# Task 1 — Free Foundation & Doctor Data Model

> Legacy planning reference. The canonical implementation sequence is indexed in `tasks/README.md`; use `milestone-1-foundation.md` and `milestone-2-doctor-directory.md` for executable work.

**Covers development phases:** Foundation and doctor management
**Tier:** DoctorPilot Free MVP
**Goal:** Establish the independently useful Free plugin, canonical doctor data model, and secure editorial workflow.
**Depends on:** none
**Blocks:** Tasks 2, 3, and 4
**Requirements:** FR-001–FR-008, FR-010–FR-014, NFR-006–NFR-010
**Acceptance criteria:** AC-001–AC-004, AC-020–AC-022

## Scope

- Create the runtime-guarded plugin scaffold for WordPress 6.4+ and PHP 8.1+: `doctorpilot.php`, autoloading/bootstrap, version constants, activation/deactivation, `uninstall.php`, `readme.txt`, and translation loading.
- Use `BrainStudioz\DoctorPilot`, the `bstdz_doctorpilot_` code/option prefix, `_doctorpilot_` private meta prefix, and the layout in `06-technical-spec.md` §2.
- Register the internal `doctorpilot_doctor` post type with mapped capabilities and a configurable `/find-a-doctor/{post_name}/` default URL. Do not force `dr-`; an import may explicitly preserve such a slug.
- Register the prefixed Free taxonomies: Department, Specialty, Symptom, Condition, Treatment, Language, and Location. Keep Symptom, Condition, and Treatment independent. Location supports one record in Free; Insurance is Pro and is not registered by Free.
- Implement `FieldRegistry` as the single authority for field keys, storage, defaults, tier, sanitization, validation, visibility, REST shape, search behavior, and Elementor eligibility.
- Implement the canonical fields, bounded repeaters, recursive sanitization, and invariants in `06-technical-spec.md` §5. Store canonical taxonomy relationships as terms and validate primary department/specialty references.
- Build the guided editor groups: Profile, Professional Information, Clinical Services, Experience & Education, Certifications & Memberships, Publications, Location & Contact, Booking, SEO, and FAQs.
- Use WordPress post status for publication and `_doctorpilot_review_status` for the separate review state. Compute completeness; never accept it from clients. Block invalid publication unless an administrator uses a logged explicit override.
- Define and enforce mapped capabilities from `06-technical-spec.md` §6. Grant administrator capabilities idempotently. Custom role presets, department scoping, and doctor self-editing are Pro, not Free foundation work.
- Add the versioned `bstdz_doctorpilot_settings` object and validation from `06-technical-spec.md` §13, including safe uninstall defaults.
- Add an admin health/dashboard foundation for schema version, cache generation, incomplete profiles, dependency compatibility, and actionable failures.
- Implement lifecycle behavior: version checks, ordered/idempotent upgrades, one-time rewrite flush, data-preserving deactivation, and explicit opt-in deletion of DoctorPilot-owned data only.

## Non-goals

- Public templates, shortcodes, REST search, Elementor widgets, booking rendering, CSV migration, redirect management, analytics, department-scoped roles, self-edit roles, and bulk migration tools.
- A second field schema, editable publication-status meta, public exposure of raw post meta, or hardcoded Aman Hospital hosts and URLs.

## Change-impact constraints

- **Storage/schema:** canonical storage must match `06-technical-spec.md` §5; derived completeness/search/sort values must be rebuildable.
- **Public API:** no public doctor-meta exposure through the core post REST controller; `show_in_rest` remains false.
- **Permissions/privacy:** capability and object checks protect writes; private license/contact/internal fields remain excluded from public serializers.
- **Compatibility:** the base plugin must boot without Elementor, Elementor Pro, DoctorPilot Pro, SEO plugins, or booking providers.
- **Migration/backward compatibility:** schema changes are versioned and idempotent; deactivation never deletes data.

## Acceptance criteria

- An authorized editor can save a structured doctor as draft; completeness updates and nothing becomes public.
- A doctor can have multiple specialties and symptoms, while primary terms remain valid assigned terms.
- Public URL behavior defaults to `/find-a-doctor/{post_name}/`; explicitly supplied `dr-...` slugs remain possible.
- Symptom, Condition, and Treatment are separate prefixed taxonomies.
- Free enforces at most one canonical location record without changing the storage key used by Pro.
- Private email, license, internal audit, and other non-public fields cannot leak through registration or serialization.
- Deactivation preserves content and settings. Uninstall preserves data by default and deletes only DoctorPilot-owned data after explicit opt-in.
- All authenticated writes require both request-intent validation and server-side capability/object checks.

## Verification

- PHP lint; PHPCS with the WordPress ruleset; Plugin Check.
- Unit tests for field schema snapshots, sanitizers, validators, repeater limits, primary-term invariants, completeness, and settings validation.
- Integration tests for CPT/taxonomy/meta registration, capability mapping, schema upgrades, activation, deactivation, both uninstall settings, and multisite bounds.
- Manual smoke test: create/edit/save/publish a valid doctor, reject or warn on an invalid primary term, confirm URL behavior, confirm private fields are not exposed, and confirm deactivation preserves data.
- Report exactly what ran; “not run” is not “passed.”

## Docs to sync

`01-product-vision.md`, `02-requirements.md`, `03-architecture.md`, `04-decisions.md` when a durable decision changes, `06-technical-spec.md`, `readme.txt`, and `CHANGELOG.md`.
