<?php
/**
 * Front-end redirect resolution.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves legacy doctor URLs to their current permalink with a 301.
 *
 * Only triggers on a 404 for an exact, active source path, and only redirects to
 * the destination doctor's current published permalink. It never performs an
 * open redirect and never falls back to the directory home.
 */
final class RedirectResolver {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Run late so genuine content wins; only unmatched requests are redirected.
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 5 );

		// Remove redirects that point at a doctor being permanently deleted.
		add_action( 'before_delete_post', array( $this, 'cleanup_for_post' ) );
	}

	/**
	 * Delete redirects targeting a doctor that is being deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function cleanup_for_post( int $post_id ): void {
		if ( 'doctorpilot_doctor' !== get_post_type( $post_id ) ) {
			return;
		}

		( new RedirectRepository() )->delete_for_doctor( $post_id );
	}

	/**
	 * Redirect the current request if it matches an active legacy path.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		// Only consider requests that would otherwise 404.
		if ( ! is_404() ) {
			return;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		$path    = RedirectValidator::normalize_source( $request );
		if ( '' === $path ) {
			return;
		}

		$redirect = ( new RedirectRepository() )->find_active( $path );
		if ( null === $redirect ) {
			return;
		}

		$doctor_id = (int) $redirect['doctor_id'];

		// Resolve to the current permalink; require a published destination.
		if ( 'publish' !== get_post_status( $doctor_id ) ) {
			return;
		}

		$destination = (string) get_permalink( $doctor_id );
		if ( '' === $destination || RedirectValidator::is_unsafe_destination_host( $destination ) ) {
			return;
		}

		wp_safe_redirect( $destination, 301 );
		exit;
	}
}
