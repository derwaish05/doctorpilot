=== DoctorPilot – Doctor Directory & Search ===
Contributors: brainstudioz
Tags: doctor directory, doctor search, medical directory, elementor, hospital
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build searchable doctor directories with specialties, symptoms, locations, Elementor widgets and appointment links.

== Description ==

DoctorPilot stores every doctor as structured WordPress data — a dedicated post type with departments, specialties, symptoms, languages, and locations — and lets Elementor control the presentation. Update a doctor once and the change appears everywhere: profile, cards, department pages, search results, related doctors, and schema.

This release (0.1.0) is the Free foundation: safe loading and lifecycle, canonical identifiers, capabilities, versioned settings, a REST foundation, and an admin health/settings shell. Directory, search, and Elementor widgets arrive in subsequent releases.

Includes customizable doctor directory, search, and profile building blocks for Elementor. Booking connects to an external URL or BookPilot AI rather than a second booking engine.

== Installation ==

1. Upload the `doctorpilot` folder to `/wp-content/plugins/`, or install through the Plugins screen.
2. Activate the plugin. Requires WordPress 6.4+ and PHP 8.1+.
3. Open the DoctorPilot menu to review Health and Settings.

== Frequently Asked Questions ==

= Does deactivating the plugin delete my doctors? =
No. Deactivation preserves all data. Data is removed only if you explicitly enable "Delete data on uninstall" and then delete the plugin.

= Do I need Elementor? =
No. DoctorPilot works without Elementor and provides native output. Elementor and Elementor Pro unlock additional presentation features.

== Changelog ==

= 0.1.0 =
* Initial Free foundation: bootstrap, runtime guard (WP 6.4 / PHP 8.1), activation/deactivation, opt-in uninstall, autoloader and package layout, capabilities, versioned settings, REST foundation, admin health/settings shell, logging, and schema migrator.

== Upgrade Notice ==

= 0.1.0 =
First foundation release. Safe to activate; adds no public-facing output yet.
