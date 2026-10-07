# Aman Hospital migration & launch checklist (M5-TASK-005)

Operational runbook for cutting over Aman Hospital doctor URLs to the new
DoctorPilot directory. Nothing here hardcodes Aman specifics into reusable code —
the hospital details live in the reviewed CSV and the redirect entries.

Canonical production host: **amanhospital.org**. The staging host
`amanhospital.tempurl.host` must never become canonical and must not appear in
any stored URL at launch.

## 1. Prepare data

- [ ] Export the legacy doctor inventory to CSV with columns including a stable
      `source_key`, `name`, `department`, `specialty`, `language`,
      `legacy_url` (old path, e.g. `/doctor/ali-a-haydar/`).
- [ ] Import via **DoctorPilot → Migration → Import** using **Create only** (or
      **Create or update** on re-runs). All doctors import as **drafts / needs
      review** — none are published automatically.

## 2. Editorial review (before publishing)

Confirm for each doctor, especially **Dr. Ali A. Haydar**:

- [ ] Correct name, specialty, and pronouns.
- [ ] No wrong-doctor content (the importer flags "Dr. Kanso"-style mismatches).
- [ ] No placeholder counters (e.g. `1+`).
- [ ] Testimonials/quotes belong to this doctor.
- [ ] Correct contact and booking destination.
- [ ] No `tempurl.host` staging URLs remain.
- [ ] Doctor approved → set status to **Published**.

## 3. Redirects

- [ ] For every legacy URL, add an explicit redirect under **Migration →
      Redirects**: source `/doctor/{slug}/` → destination doctor.
- [ ] The engine issues a **301** only when the redirect is active and the
      destination doctor is **published**; it resolves to the doctor's current
      permalink (`/find-a-doctor/dr-{slug}/`).
- [ ] Confirm the validator rejected any self-redirect, loop, query-only source,
      directory-home fallback, or staging/external destination.
- [ ] Never redirect all legacy pages to the directory home; never guess a
      mapping from the name.

## 4. Reconcile

Under **Migration → Reports**, confirm the counts line up:

- [ ] Imported doctors ≈ source rows.
- [ ] Published count matches the approved set.
- [ ] Active redirects ≈ number of legacy published URLs.

## 5. Cutover

- [ ] Replace all staging-domain URLs; set production canonicals.
- [ ] Regenerate the XML sitemap and submit it to search engines.
- [ ] Update internal doctor links.
- [ ] Clear WordPress and CDN caches (the base plugin bumps its cache generation
      automatically on content changes).
- [ ] Test search and booking on desktop and mobile.
- [ ] Verify `/doctor/ali-a-haydar/` 301-redirects to
      `https://amanhospital.org/find-a-doctor/dr-ali-a-haydar/`.

## 6. Post-launch

- [ ] Monitor 404s and redirect loops for the first weeks.
- [ ] Keep the legacy→new mapping in the migration log for reference.
- [ ] Roll back an import only via **Reports → rollback**, which removes just the
      unpublished, job-created drafts and never deletes published doctors.

## Status

Tooling implemented and shipped in DoctorPilot (it lived in the DoctorPilot Pro
add-on until that was merged in at 0.2.0). The live cutover must be executed by
an operator in the production environment and its evidence recorded here.
