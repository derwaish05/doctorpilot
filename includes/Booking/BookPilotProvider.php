<?php
/**
 * REMOVED in schema version 4 — this file is a tombstone, not code.
 *
 * The BookPilot booking provider was withdrawn. Its registration is gone from
 * Infrastructure/Plugin.php, and the `bookpilot` booking method and the
 * per-doctor service-ID field are gone from Directory/ProFields.php.
 *
 * Doctors still holding `booking_method = bookpilot` are moved to the site
 * default method by Migrator migration 4, and BookingResolver falls back to the
 * site default for any method it no longer recognises, so no doctor loses a
 * booking button.
 *
 * Delete this file. It is only here because the tooling that wrote the rest of
 * the change could not remove a file from disk.
 *
 * @package BrainStudioz\DoctorPilot
 */
