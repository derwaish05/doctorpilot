# Sample / demo content (Aman Hospital)

Reviewed demo content for **Dr. Ali A. Haydar**, drawn only from facts verified
on the live profile `https://amanhospital.org/doctor/ali-a-haydar/`
(fetched 2026-07-28).

## Provenance & verified facts

| Field | Value | Source |
|---|---|---|
| Name | Dr. Ali A. Haydar | page title / H1 |
| Positions | Chief Medical Officer; Chair of Diagnostic & Interventional Radiology | profile header |
| Primary specialty | Interventional Radiology | profile |
| Department | Radiology | profile |
| Languages | Arabic, English, French | profile |
| Experience | 15 years | profile ("15+"), normalized to an integer |
| Location | Aman Hospital, Doha, Qatar | profile / site |
| Meta description | board-certified interventional radiology consultant… | `<meta description>` |
| Photo | `…/DR-ALI-HAYDAR-1-970x1024-1-1.webp` | `og:image` |
| Legacy URL | `/doctor/ali-a-haydar/` | canonical |
| New URL | `/find-a-doctor/dr-ali-a-haydar/` | target structure |

## Intentionally excluded (data-quality issues on the legacy page)

Per the migration acceptance criteria (ADR-0007 / `docs/aman-launch-checklist.md`):

- The incorrect **"Dr. Kanso"** testimonials that appear on the legacy page —
  they belong to a different doctor.
- Placeholder counters such as **"1+"**.
- Any staging (`tempurl.host`) URLs.

Nothing clinical was invented: procedures, FAQs, and claims are limited to
verified, general, non-diagnostic information.

## Two ways to load it

1. **CSV import** (exercises the importer end-to-end):
   `aman-doctors.csv` uses headers the importer auto-maps. Go to
   **DoctorPilot → Migration → Import**, upload it, run the dry run, then import.
   Rows land as **drafts / needs review**.

2. **Seed script** (adds the richer structured fields CSV can't carry — leadership,
   location, FAQs, redirect, and the featured photo):
   ```bash
   wp eval-file wp-content/plugins/doctorpilot/samples/seed-aman.php
   ```

## After loading

The doctor is created as a **draft marked needs-review**. Confirm the details,
then **Publish**. The `/doctor/ali-a-haydar/` → `/find-a-doctor/dr-ali-a-haydar/`
301 redirect only resolves once the destination is published.
