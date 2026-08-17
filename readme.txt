=== DoctorPilot – Doctor Directory & Search ===
Contributors: brainstudioz
Tags: doctor directory, doctor search, medical directory, elementor, hospital
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build searchable doctor directories with specialties, symptoms, locations, insurance, Elementor widgets, CSV migration and appointment links.

== Description ==

DoctorPilot stores every doctor as structured WordPress data — a dedicated post type with departments, specialties, symptoms, languages, and locations — and lets Elementor control the presentation. Update a doctor once and the change appears everywhere: profile, cards, department pages, search results, related doctors, and schema.

Everything ships in this one plugin. There is no separate add-on to install.

* Eight taxonomies: department, specialty, symptom, condition, treatment, language, location, and insurance provider.
* Search and filtering with deterministic ranking, canonical URL state, and a working no-JavaScript fallback.
* Elementor widgets including Doctor Grid/List, Doctor Carousel/Grid, Related Doctors, and the doctor profile blocks, plus dynamic tags when Elementor Pro is present.
* Native templates and shortcodes, so the directory works with no page builder at all.
* Migration tooling for moving off a legacy directory: secure CSV import with auto-mapping, a no-write dry run, resumable and idempotent draft-only importing, rollback, formula-safe export, an explicit legacy-URL redirect engine, and reconciliation reporting.
* Department-scoped and self-edit roles, enforced by capability and object scope rather than by role name.
* Opt-in aggregate analytics, off by default and never identifying a visitor.

Booking connects to an external URL, a phone number, an enquiry form, or BookPilot AI rather than shipping a second booking engine.

== Installation ==

1. Upload the `doctorpilot` folder to `/wp-content/plugins/`, or install through the Plugins screen.
2. Activate the plugin. Requires WordPress 6.4+ and PHP 8.1+.
3. Open the DoctorPilot menu to review Health and Settings.

== Frequently Asked Questions ==

= Does deactivating the plugin delete my doctors? =
No. Deactivation preserves all data. Data is removed only if you explicitly enable "Delete data on uninstall" and then delete the plugin.

= I have DoctorPilot Pro installed. What do I do? =
Nothing, mostly. Update this plugin and DoctorPilot Pro is deactivated for you on the next admin page load, because its features are now built in. Your doctors, import history, redirects and analytics are kept — the tables, roles and scheduled jobs are adopted automatically.

You can then remove the old folder. One caution, which the plugin also shows you as an admin notice: that old copy still carries its own uninstall script from when it owned the redirect and import tables. If you have "Delete data on uninstall" switched on in DoctorPilot Settings, deleting it from the Plugins screen would drop those tables. Either switch that setting off first, or delete the doctorpilot-pro folder over FTP.

= Do I need Elementor? =
No. DoctorPilot works without Elementor and provides native output. Elementor and Elementor Pro unlock additional presentation features.

== Changelog ==

= 0.2.0 =
* DoctorPilot Pro is merged into this plugin. One plugin now contains the directory, search, Elementor widgets, advanced taxonomies and filters, CSV migration, legacy-URL redirects, reconciliation, scoped roles, and opt-in analytics.
* An installed DoctorPilot Pro is deactivated automatically, without running its deactivation handler — that handler would have cleared the retention and import schedules this plugin now owns, stopping cleanup and stalling any import mid-job.
* Existing data is adopted in place: same tables, same option keys, same capabilities, same cron hooks. No re-import and no re-activation needed.
* Uninstall now removes everything the "Delete data on uninstall" setting promises: doctors and library content, taxonomy terms, prefixed meta, all four tables, options, cached search responses, cron events, roles and capabilities. It reads the table, role and capability lists from the classes that create them, so it cannot drift out of step again.
* New: Doctor Carousel / Grid widget — a curated set of doctors as a scroll-snap carousel or a static grid, chosen by term.
* New: per-element show/hide switches for the doctor card, honoured identically by the server and by the client-side renderer.
* Fixed: the analytics table is now dropped on uninstall when data deletion is enabled. It was created but never removed.

= 0.1.0 =
* Initial Free foundation: bootstrap, runtime guard (WP 6.4 / PHP 8.1), activation/deactivation, opt-in uninstall, autoloader and package layout, capabilities, versioned settings, REST foundation, admin health/settings shell, logging, and schema migrator.

== Upgrade Notice ==

= 0.2.0 =
Merges DoctorPilot Pro into this plugin, and deactivates it for you. No data is moved, recreated, or lost. Read the admin notice before deleting the old folder.

= 0.1.0 =
First foundation release. Safe to activate; adds no public-facing output yet.
