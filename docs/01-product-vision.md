# 01 — Product Vision

**Product:** DoctorPilot — Doctor Directory & Search  
**WordPress.org title:** DoctorPilot – Doctor Directory & Search  
**Preferred slug:** `doctorpilot` (fallback: `doctorpilot-directory`)  
**Text domain:** `doctorpilot`  
**PHP namespace:** `BrainStudioz\DoctorPilot`  
**Code prefix:** `bstdz_doctorpilot_`  
**Vendor:** BrainStudioz  
**Reference deployment:** Aman Hospital (`amanhospital.org`)  
**Document status:** Product direction approved; WordPress.org slug reservation remains pending until submission.

## 1. Vision

DoctorPilot will be the structured-data and discovery layer for doctor directories on WordPress. Hospitals manage each doctor once; patients can reliably find the right profile; designers can control presentation without embedding clinical data inside a page builder.

The product is a directory and discovery tool, not a diagnostic or patient-record system.

## 2. Problem

Hospital doctor information is often stored inside hand-built pages. This creates four connected problems:

- Search and filters cannot reliably understand page-builder content.
- The same doctor details are duplicated across cards, profiles, departments, and booking pages.
- Editors can introduce inconsistent credentials, specialties, contact details, or outdated availability.
- Redesigns and migrations become manual, error-prone SEO projects.

The core problem is that **doctor data and page design are tangled together**.

## 3. Product concept

DoctorPilot stores each doctor as structured WordPress data: a dedicated custom post type, curated taxonomies, and a validated field registry. The active theme, native templates, shortcodes, and Elementor widgets render that data without becoming its owner.

One approved edit can update:

- the single-doctor profile;
- doctor cards and lists;
- department and specialty views;
- search results and related doctors;
- booking calls to action; and
- metadata and structured data used for SEO.

Search is deterministic and rules-based in v1. DoctorPilot must not describe symptom matching as diagnosis or claim AI behavior that the product does not provide.

## 4. Target customers and users

### Primary customers

- Hospitals and multi-branch healthcare networks
- Clinics and specialist practices
- Healthcare agencies building WordPress and Elementor sites

### Primary users

| User | Need |
|---|---|
| Patient or visitor | Find an appropriate doctor by trusted, understandable criteria |
| Hospital editor | Maintain a complete profile from one controlled interface |
| Department manager | Review the doctors and information assigned to a department |
| Designer or agency | Build branded directory and profile layouts without duplicating data |
| SEO or migration owner | Preserve canonical URLs, structured data, and explicit redirects |

## 5. Jobs to be done

- As a patient, I can find a doctor by name, department, specialty, symptom, language, or consultation type.
- As a hospital editor, I can maintain a doctor in one guided record and see the change everywhere.
- As a department manager, I can identify incomplete or unreviewed profiles in my department.
- As a designer, I can style directory and profile views without storing doctor information in Elementor.
- As an SEO owner, I can migrate legacy profiles with explicit one-to-one redirects and no staging canonicals.
- As a privacy owner, I can operate the directory without collecting identifiable patient or search-session data.

## 6. Positioning and competitive wedge

DoctorPilot is positioned between generic directory builders and full clinic-management suites:

- More healthcare-specific than a general directory plugin
- More focused and easier to adopt than an appointment/EHR platform
- Structured-data first rather than page-builder-content first
- Elementor-ready while remaining usable without Elementor
- Migration-safe, privacy-conscious, and accessibility-first

The initial wedge is **doctor discovery + structured profiles + flexible presentation**. Booking remains an integration boundary.

## 7. Value proposition

**A structured, Elementor-ready doctor directory for WordPress that helps hospitals manage profiles once, deliver fast guided search, and migrate doctor URLs safely.**

## 8. Release boundaries

### DoctorPilot Free MVP

- Structured doctor profiles and editorial review state
- Department, Specialty, Symptom, Condition, Treatment, Language, and single Location data
- Search by name, department, specialty, and symptom
- Core filters, shareable URLs, pagination, and no-JavaScript fallback
- Doctor Search, Grid/List, Profile, Department Grid, Symptom Directory, and Booking Button widgets
- Native template/shortcode fallback when Elementor is absent
- External URL, phone, and enquiry booking actions
- Reusable global content library (procedures, experience & education, publications, FAQs) with per-doctor assignment and independent doctor-specific items (ADR-0017)
- Clean permalinks, breadcrumbs, canonical integration, structured data, and sitemap compatibility
- Accessibility, privacy, security, and data-preserving uninstall behavior

### DoctorPilot Pro v1

- Multiple locations and location-specific contact/booking configuration
- Insurance, structured schedules, availability filters, and BookPilot integration
- Advanced search controls, additional Elementor widgets, and complete dynamic-tag coverage
- CSV import/export, migration jobs, explicit redirect management, and bulk operations
- Bulk library assignment/promotion, duplicate merge with reference repointing, library CSV import/export, and impact reporting
- Opt-in aggregate search analytics with configurable retention
- Department-scoped workflow controls and advanced permission UI

### Later, subject to separate architecture decisions

- Native appointment storage or scheduling
- Favourites and comparison
- Telemedicine workflows
- External directory synchronization
- Multilingual synonym management
- Dedicated large-directory search index
- AI-assisted features with medical-content safeguards and human approval

## 9. Non-goals

- DoctorPilot is not an EHR/EMR, patient portal, insurance-claims system, or medical-record store.
- It does not diagnose a condition or recommend treatment.
- It does not store identifiable patient symptom searches.
- It does not make Elementor the system of record.
- It does not promise real-time availability unless a provider supplies verified availability.
- It does not auto-publish migrated or AI-generated medical content.
- It does not create duplicate admin menus or parallel field schemas.

## 10. Success criteria

Success is measured against a documented test fixture and release checklist.

| Area | Release target |
|---|---|
| Profile management | A trained editor can create a complete standard profile in 10 minutes or less, excluding content approval |
| Search quality | At least 95% of an approved reference-query set returns a correct doctor or department in the first five results |
| Search performance | Public search REST response p95 is at or below 500 ms for 1,000 published doctors on the agreed benchmark environment, excluding network time |
| Migration | 100% of published legacy doctor URLs have an explicit reviewed destination; zero migrated profiles auto-publish |
| Data privacy | Public APIs expose only allowlisted published fields; analytics store no IP address, user ID, cookie ID, or raw user-agent value |
| Accessibility | Core patient journeys pass automated checks with no serious/critical violations and pass manual keyboard and screen-reader smoke tests |
| URL behavior | Filter state is shareable; reload and browser back/forward restore the same results |
| Reliability | Deactivation preserves all content; failed imports can resume without duplicating doctors |

Targets may be revised only through an ADR and corresponding updates to requirements and tests.

## 11. Business model

DoctorPilot Free is a complete, useful directory product distributed through WordPress.org. DoctorPilot Pro is a separate add-on that extends the base plugin; Pro code is not hidden or remotely unlocked inside the directory build. Accessibility, privacy, security, and safe data handling are never paywalled.

## 12. Product risks and mitigations

| Risk | Mitigation |
|---|---|
| Medical terms imply diagnosis | Clear disclaimer, curated relationships, deterministic results, no diagnostic language |
| Legacy data contains errors | Draft-only import, completeness checks, editorial approval, explicit redirect map |
| Elementor dependency narrows adoption | Native templates/shortcodes; integration loads only when compatible Elementor versions are active |
| Complex filters become slow | Bounded query inputs, cache invalidation rules, performance fixtures, derived search fields |
| Free/Pro scope becomes inconsistent | One capability matrix in `02-requirements.md`; ADR required for tier changes |
| SEO plugins duplicate canonical/schema output | Single-owner integration policy and compatibility tests |

## 13. Future direction

DoctorPilot can become the discovery front end of the BrainStudioz healthcare suite: structured doctor data in DoctorPilot, verified availability and booking through BookPilot, and carefully governed integrations for external hospital systems.
