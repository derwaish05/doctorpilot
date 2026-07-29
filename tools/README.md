# Demo content tools

Sample content sourced from the live Aman Hospital profile of **Dr. Ali A.
Haydar** (`https://amanhospital.org/doctor/ali-a-haydar/`), structured into the
DoctorPilot data model.

Both tools create the doctor as a **draft with review status "needs review"** —
nothing is published automatically. This is deliberate: the live source page
also contains testimonial/quote content that belongs to another person, so an
editor must review and clean the profile before publishing (ADR-0007).

## Option A — WP-CLI seed script (base plugin only)

```bash
wp eval-file wp-content/plugins/doctorpilot/tools/seed-aman-demo.php
```

Creates/updates one structured doctor with departments, specialty, languages,
symptoms, treatments, leadership positions, procedures, education,
certifications, FAQs, booking link, and legacy URL, and (optionally) sideloads
the profile photo as the featured image. Idempotent — safe to re-run.

## Option B — CSV import (DoctorPilot Pro)

Use `aman-sample.csv` with **DoctorPilot → Migration → Import** (Pro). Columns
auto-map; the dry run flags anything needing review; the import writes drafts
only. Multi-value fields use `|` as the separator (e.g. `Arabic|English|French`).

The CSV contains **12 doctors** across departments (Radiology, Cardiology,
Neurology, Dermatology, Orthopedics, OB-GYN, Urology, Pediatrics,
Gastroenterology, ENT, Ophthalmology, General Surgery), which is enough to
exercise the directory grid, filters, and search.

> **Only the first row (Dr. Ali A. Haydar) is real content** from the live Aman
> Hospital site. Rows 2–12 are **synthetic demo doctors** — plausible sample
> data, not real people — so nothing is misattributed. Replace or remove them
> before using the directory in production.

## After seeding

1. Review the draft under **DoctorPilot → Doctors** and remove any content that
   does not belong to this doctor.
2. Publish when approved.
3. In **DoctorPilot Pro → Migration → Redirects**, add
   `/doctor/ali-a-haydar/` → the published doctor so the legacy URL 301-redirects
   to `/find-a-doctor/dr-ali-a-haydar/`.

## Attribution

Content is reproduced from the hospital's own public profile for use on the
hospital's own site. Confirm rights and accuracy before publishing.
