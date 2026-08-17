<?php
/**
 * Secure CSV upload validation.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\ImportExport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates an uploaded CSV before any parsing or mutation occurs.
 *
 * Checks the migration capability, extension, size, and MIME type, and moves
 * the file to a private location outside the web root's execution path. Never
 * trusts the client-provided filename or type alone.
 */
final class UploadValidator {

	/**
	 * Maximum accepted upload size in bytes (10 MB).
	 *
	 * @var int
	 */
	private const MAX_BYTES = 10485760;

	/**
	 * Validate and stage an uploaded CSV file.
	 *
	 * @param array<string, mixed> $file A single entry from $_FILES.
	 * @return string|\WP_Error Absolute path to the staged private file, or error.
	 */
	public function validate( array $file ) {
		if ( ! current_user_can( 'manage_doctorpilot_migrations' ) ) {
			return new \WP_Error( 'doctorpilot_forbidden', __( 'You are not allowed to import doctors.', 'doctorpilot' ) );
		}

		if ( ! isset( $file['tmp_name'], $file['name'], $file['size'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return new \WP_Error( 'doctorpilot_invalid_parameter', __( 'No valid file was uploaded.', 'doctorpilot' ) );
		}

		if ( (int) $file['size'] <= 0 || (int) $file['size'] > self::MAX_BYTES ) {
			return new \WP_Error( 'doctorpilot_invalid_parameter', __( 'The file is empty or exceeds the size limit.', 'doctorpilot' ) );
		}

		$filename = sanitize_file_name( (string) $file['name'] );
		$ext      = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			return new \WP_Error( 'doctorpilot_invalid_parameter', __( 'Only .csv files are accepted.', 'doctorpilot' ) );
		}

		// Verify the real type, not just the extension.
		$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], $filename, array( 'csv' => 'text/csv' ) );
		$allowed_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		if ( ! empty( $check['type'] ) && ! in_array( (string) $check['type'], $allowed_types, true ) ) {
			return new \WP_Error( 'doctorpilot_invalid_parameter', __( 'The uploaded file does not look like a CSV.', 'doctorpilot' ) );
		}

		return $this->stage_private_copy( (string) $file['tmp_name'], $filename );
	}

	/**
	 * Move the uploaded file into a private, non-executable directory.
	 *
	 * @param string $tmp_name Temp upload path.
	 * @param string $filename Sanitized filename.
	 * @return string|\WP_Error
	 */
	private function stage_private_copy( string $tmp_name, string $filename ) {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'doctorpilot_internal_error', __( 'The uploads directory is not writable.', 'doctorpilot' ) );
		}

		$dir = trailingslashit( $uploads['basedir'] ) . 'doctorpilot-imports';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'doctorpilot_internal_error', __( 'Could not create the private import directory.', 'doctorpilot' ) );
		}

		// Deny direct web access and PHP execution in the import directory.
		$this->write_protection( $dir );

		$target = trailingslashit( $dir ) . uniqid( 'dpimport_', true ) . '-' . $filename;

		// Use the WP filesystem move where possible; fall back to rename/copy.
		if ( ! @move_uploaded_file( $tmp_name, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new \WP_Error( 'doctorpilot_internal_error', __( 'Could not stage the uploaded file.', 'doctorpilot' ) );
		}

		return $target;
	}

	/**
	 * Write .htaccess/index.php guards into the import directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function write_protection( string $dir ): void {
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
