=== DoctorPilot – Doctor Directory & Search ===
Contributors: brainstudioz
Tags: doctor directory, doctor search, medical directory, elementor, hospital
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.7.0
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

Booking connects to an external URL, a phone number or an enquiry form rather than shipping a second booking engine. Set the destination once site-wide and let any doctor override it.

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

= 0.7.0 =
* New: icon sizes default to 1em and accept em and rem as well as px, on every icon-size slider — Certifications, Certifications & Publications, Doctor Facts, Specialties, the achievement lists, the Experience & Education checklist, the Booking Card's button icons and the Page Header's chip and button icons. Each sets width and height as well as font-size, so inline SVGs and webfont glyphs match. Icons follow their text now instead of a fixed pixel size, so where nobody set a size by hand — Page Header 16px and 20px, Booking Card 20px, Specialties 18px — icons change size slightly.
* New: default colours for the widget text and headings. Heading controls start on #136888 (the built-in Heading, the Heading Block's heading, and each widget's section or group title); body text controls start on #000000 (the biography, item names and organizations, meta and description lines, fact labels and values, timeline entries, checklist items, specialty labels). Headings look the same as before because the stylesheet already painted them #136888; body copy that nobody coloured by hand becomes black. The Page Header hero and the Booking Card (white text on a dark banner), the directory and carousel cards, and accent elements are untouched.
* New: an Items style section on the Certifications & Publications widget — padding, border radius, gap after the icon, icon alignment, transition duration, plus Background, Border and Box-shadow across Normal and Hover tabs. "Gap between items" moved into it from the Layout section, keeping its setting.
* Note: colour and size defaults reach pages already built when Elementor rebuilds its generated CSS, which this version bump triggers on upgrade.

= 0.6.0 =
* New: every icon option in the widgets — 20 of them, across Page Header, Booking Card, Doctor Facts, Certifications, Certifications & Publications, Specialties, the achievement lists and Experience & Education — starts with a library dropdown: Elementor icon library, or pixfort icon. Choosing pixfort swaps the Elementor picker for pixfort's own, with its Line, Duotone and Solid tabs and search. The two are separate settings, so switching back restores the icon you had. The dropdown appears only while the pixfort core plugin is active, and a page that asks for a pixfort icon on a site without it falls back to its Elementor icon rather than rendering blank.
* New: a Design choice on the Experience & Education widget — Timeline (the existing rail of icon nodes and cards) or Checklist, a compact icon-marked list with one entry per line and the organization joined on by a separator you set. Timeline is the default, so pages already built are unchanged.
* Fixed: an icon chosen from Elementor's uploaded-SVG library produced an "Array to string conversion" warning on every render, because that library stores the attachment data as an array and the widgets compared it as a string.

= 0.5.1 =
* Fixed: the About Doctor widget rendered the entire page inside itself — Page Header, breadcrumbs, booking buttons and a duplicate of its own heading — after any edit to the widget. The biography is passed through `the_content` for shortcode and embed support, and Elementor's filter on that hook replaces the content with the whole rendered document rather than filtering it. That one filter is now suspended for the biography pass, through Elementor's own public API; every other content filter still runs.

= 0.5.0 =
* New: the Eyebrow and the Heading each get their own Style section, on all 19 widgets that carry the built-in heading. Both offer typography, padding, margin, border radius, transition duration, and Normal / Hover tabs with text colour, background (classic or gradient) and box shadow — set independently, so a tinted eyebrow pill above a plain heading is one choice and a banded heading under a plain eyebrow is another.
* New: "Fit background to text" per line. Both lines are block-level, so a background otherwise paints the full column width — right for a banded heading, wrong for the label an eyebrow usually wants. Off by default.
* Changed: the single "Heading" style section is now two, "Eyebrow" and "Heading". Nothing already set moves or resets; every existing control ID is unchanged, so a widget nobody has touched renders exactly as before.

= 0.4.0 =
* Removed: the Credentials field, and the values saved in it. The plugin already had a field called Headline — the line under the doctor's name on cards — so Credentials was a second, overlapping field rather than something to rename. It is gone from the doctor editor, the card, the profile header, search, completeness, the CSV import and export, the migration mapping, and the Elementor controls and dynamic tag.
* **Export your doctors before upgrading if you want to keep the credentials text.** The upgrade deletes it permanently, in every language, and the export no longer has the column afterwards.
* Changed: the "Short bio" switcher on the Doctor Grid / List and Doctor Carousel widgets is now labelled "Headline", which is what it has always controlled. Pages already built keep whatever the author chose.
* Note: if a Doctor Field dynamic tag on any template is bound to Credentials, re-point it before you save that template again — otherwise it silently falls back to Name and the page prints the doctor's name twice.

= 0.3.0 =
* New: a Highlights tab on the doctor editor, holding the academic stat cards plus two achievement lists — community outreach, and health care profession — each with its own editable heading.
* New: Community Outreach and Health Care Profession Elementor widgets, so each list can be placed and styled on its own. Academic Highlights is back to the stat grid alone.
* Changed: Location & contact moved into the Profile tab and now edits as one record rather than a repeater. A doctor has one practice location. Any further locations already stored are preserved on save and still render; they are simply not editable there.
* New: booking defaults in Settings — method, destination URL, switchboard phone, button label and link target. A doctor's own Booking tab wins wherever it is filled in; blank inherits. The default method is the enquiry form.
* New: "No booking button" for a doctor who does not take bookings, and "Use site default" as the method a new doctor starts on.
* Removed: the BookPilot booking provider and its per-doctor service field. Doctors still set to it are moved to the site default method on upgrade, so no booking button is lost.
* Changed: every doctor taxonomy now lives in its own top-level Taxonomies menu — all eight, Insurance included. Previously only Departments and Specialties had menu items.
* Changed: search analytics no longer claims to require DoctorPilot Pro; it never did since the merge. The retention window is now editable instead of fixed at 90 days.
* Fixed: saving any settings section reset the analytics retention window to 90 days.

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

= 0.7.0 =
Sets default colours (headings #136888, body text black) and makes icon sizes follow their text at 1em, and adds an Items style section to Certifications & Publications. Body copy and some icon sizes change on pages where you have not set them by hand; anything you set is kept. The Page Header hero and Booking Card are deliberately untouched.

= 0.6.0 =
Adds pixfort icons as a choice on every widget icon, behind a library dropdown, and a Checklist design for the Experience & Education widget. Purely additive — every page already built keeps the icons and the timeline it has.

= 0.5.1 =
Fixes the About Doctor widget rendering the whole page (Page Header included) inside itself. Recommended for anyone using that widget.

= 0.5.0 =
Adds full background, padding, margin, shadow and hover styling to the widget Eyebrow and Heading, as two separate Style sections. Purely additive — nothing you have already styled changes.

= 0.4.0 =
Removes the Credentials field and permanently deletes its stored values. Export your doctors first if you want that text. The card's "Short bio" switch is now correctly labelled "Headline"; nothing you have already built changes.

= 0.3.0 =
Adds site-wide booking defaults and a Highlights tab, moves location into Profile, and gives the taxonomies their own menu. Doctors set to BookPilot are migrated to the site default booking method automatically.

= 0.2.0 =
Merges DoctorPilot Pro into this plugin, and deactivates it for you. No data is moved, recreated, or lost. Read the admin notice before deleting the old folder.

= 0.1.0 =
First foundation release. Safe to activate; adds no public-facing output yet.
