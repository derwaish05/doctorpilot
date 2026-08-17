<?php
/**
 * Migration admin UI.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Admin;

use BrainStudioz\DoctorPilot\ImportExport\CsvReader;
use BrainStudioz\DoctorPilot\ImportExport\DryRunService;
use BrainStudioz\DoctorPilot\ImportExport\Exporter;
use BrainStudioz\DoctorPilot\ImportExport\ImportRunner;
use BrainStudioz\DoctorPilot\ImportExport\JobRepository;
use BrainStudioz\DoctorPilot\ImportExport\Rollback;
use BrainStudioz\DoctorPilot\Migration\MappingConfig;
use BrainStudioz\DoctorPilot\Operations\ReconciliationService;
use BrainStudioz\DoctorPilot\Redirects\RedirectRepository;
use BrainStudioz\DoctorPilot\Redirects\RedirectValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Pro migration screen: CSV import (upload → dry run → draft import),
 * redirect management, and reconciliation reporting.
 *
 * Every mutating action requires the migration capability plus a nonce. Imports
 * always land as drafts pending review, and redirects are validated before they
 * can be stored or activated.
 */
final class MigrationMenu {

	private const CAP  = 'manage_doctorpilot_migrations';
	private const SLUG = 'doctorpilot-migration';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_post_doctorpilot_import_dryrun', array( $this, 'handle_dryrun' ) );
		add_action( 'admin_post_doctorpilot_import_run', array( $this, 'handle_run' ) );
		add_action( 'admin_post_doctorpilot_add_redirect', array( $this, 'handle_add_redirect' ) );
		add_action( 'admin_post_doctorpilot_rollback', array( $this, 'handle_rollback' ) );
		add_action( 'admin_post_doctorpilot_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Add the migration submenu under the DoctorPilot menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_submenu_page(
			'doctorpilot',
			__( 'Migration', 'doctorpilot' ),
			__( 'Migration', 'doctorpilot' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the migration screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to manage migrations.', 'doctorpilot' ) );
		}

		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'import'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action  = admin_url( 'admin-post.php' );
		$preview = get_transient( 'dp_import_preview_' . get_current_user_id() );

		echo '<div class="wrap"><h1>' . esc_html__( 'DoctorPilot Migration', 'doctorpilot' ) . '</h1>';

		$notice = get_transient( 'dp_migration_notice_' . get_current_user_id() );
		if ( is_array( $notice ) && isset( $notice['message'] ) ) {
			delete_transient( 'dp_migration_notice_' . get_current_user_id() );
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success',
				esc_html( (string) $notice['message'] )
			);
		}

		// Tabs.
		$tabs = array(
			'import'    => __( 'Import', 'doctorpilot' ),
			'redirects' => __( 'Redirects', 'doctorpilot' ),
			'reports'   => __( 'Reports', 'doctorpilot' ),
		);
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
				esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => $key ), admin_url( 'admin.php' ) ) ),
				$key === $tab ? 'nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</h2>';

		if ( 'redirects' === $tab ) {
			$this->render_redirects( $action );
		} elseif ( 'reports' === $tab ) {
			$this->render_reports( $action );
		} else {
			$this->render_import( $action, is_array( $preview ) ? $preview : null );
		}

		echo '</div>';
	}

	/**
	 * Render the import tab.
	 *
	 * @param string                    $action  admin-post URL.
	 * @param array<string, mixed>|null $preview Pending dry-run preview.
	 * @return void
	 */
	private function render_import( string $action, ?array $preview ): void {
		echo '<p>' . esc_html__( 'Upload a CSV of doctors. Columns are auto-mapped; imported doctors are saved as drafts pending review — nothing is published automatically.', 'doctorpilot' ) . '</p>';

		echo '<form method="post" action="' . esc_url( $action ) . '" enctype="multipart/form-data">';
		wp_nonce_field( 'doctorpilot_import_dryrun' );
		echo '<input type="hidden" name="action" value="doctorpilot_import_dryrun" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="dp-csv">' . esc_html__( 'CSV file', 'doctorpilot' ) . '</label></th><td><input type="file" id="dp-csv" name="csv" accept=".csv" required /></td></tr>';
		echo '<tr><th scope="row"><label for="dp-mode">' . esc_html__( 'Import mode', 'doctorpilot' ) . '</label></th><td><select id="dp-mode" name="mode">';
		echo '<option value="create">' . esc_html__( 'Create only', 'doctorpilot' ) . '</option>';
		echo '<option value="upsert">' . esc_html__( 'Create or update (by source key)', 'doctorpilot' ) . '</option>';
		echo '<option value="update">' . esc_html__( 'Update existing only', 'doctorpilot' ) . '</option>';
		echo '</select></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Terms', 'doctorpilot' ) . '</th><td><label><input type="checkbox" name="create_terms" value="1" /> ' . esc_html__( 'Create missing departments/specialties/etc.', 'doctorpilot' ) . '</label></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Validate (dry run)', 'doctorpilot' ) );
		echo '</form>';

		if ( null !== $preview ) {
			$s = $preview['summary'];
			echo '<hr /><h2>' . esc_html__( 'Dry-run preview', 'doctorpilot' ) . '</h2>';
			echo '<p><em>' . esc_html__( 'No data has been written yet.', 'doctorpilot' ) . '</em></p>';
			echo '<table class="widefat striped" style="max-width:560px"><tbody>';
			$this->row( __( 'Total rows', 'doctorpilot' ), (string) ( $s['total_rows'] ?? 0 ) );
			$this->row( __( 'Sampled', 'doctorpilot' ), (string) ( $s['sampled'] ?? 0 ) );
			$this->row( __( 'Rows flagged for review', 'doctorpilot' ), (string) ( $s['flagged'] ?? 0 ) );
			$this->row( __( 'Missing name', 'doctorpilot' ), (string) ( $s['missing_name'] ?? 0 ) );
			$this->row( __( 'Duplicate source keys (sample)', 'doctorpilot' ), (string) ( $s['duplicate_keys'] ?? 0 ) );
			echo '</tbody></table>';

			echo '<form method="post" action="' . esc_url( $action ) . '" style="margin-top:1em">';
			wp_nonce_field( 'doctorpilot_import_run' );
			echo '<input type="hidden" name="action" value="doctorpilot_import_run" />';
			submit_button( __( 'Run import (as drafts)', 'doctorpilot' ), 'primary' );
			echo '</form>';
		}
	}

	/**
	 * Render the redirects tab.
	 *
	 * @param string $action admin-post URL.
	 * @return void
	 */
	private function render_redirects( string $action ): void {
		echo '<form method="post" action="' . esc_url( $action ) . '">';
		wp_nonce_field( 'doctorpilot_add_redirect' );
		echo '<input type="hidden" name="action" value="doctorpilot_add_redirect" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="dp-source">' . esc_html__( 'Legacy path', 'doctorpilot' ) . '</label></th><td><input type="text" id="dp-source" name="source_path" class="regular-text" placeholder="/doctor/dr-name/" required /></td></tr>';
		echo '<tr><th scope="row"><label for="dp-doctor">' . esc_html__( 'Doctor ID', 'doctorpilot' ) . '</label></th><td><input type="number" id="dp-doctor" name="doctor_id" min="1" required /></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Active', 'doctorpilot' ) . '</th><td><label><input type="checkbox" name="is_active" value="1" checked /> ' . esc_html__( 'Enable this 301 redirect', 'doctorpilot' ) . '</label></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add redirect', 'doctorpilot' ) );
		echo '</form>';

		$redirects = ( new RedirectRepository() )->all( 100 );
		echo '<h2>' . esc_html__( 'Existing redirects', 'doctorpilot' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Source', 'doctorpilot' ) . '</th><th>' . esc_html__( 'Doctor', 'doctorpilot' ) . '</th><th>' . esc_html__( 'Active', 'doctorpilot' ) . '</th></tr></thead><tbody>';
		if ( array() === $redirects ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No redirects yet.', 'doctorpilot' ) . '</td></tr>';
		}
		foreach ( $redirects as $r ) {
			printf(
				'<tr><td><code>%1$s</code></td><td>%2$s</td><td>%3$s</td></tr>',
				esc_html( (string) $r['source_path'] ),
				esc_html( get_the_title( (int) $r['doctor_id'] ) ?: (string) $r['doctor_id'] ),
				( (int) $r['is_active'] === 1 ) ? esc_html__( 'Yes', 'doctorpilot' ) : esc_html__( 'No', 'doctorpilot' )
			);
		}
		echo '</tbody></table>';
	}

	/**
	 * Render the reports tab.
	 *
	 * @param string $action admin-post URL.
	 * @return void
	 */
	private function render_reports( string $action ): void {
		$summary = ( new ReconciliationService() )->summary();

		echo '<h2>' . esc_html__( 'Reconciliation', 'doctorpilot' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:520px"><tbody>';
		$this->row( __( 'Source rows (all jobs)', 'doctorpilot' ), (string) $summary['source_rows'] );
		$this->row( __( 'Imported doctors', 'doctorpilot' ), (string) $summary['imported'] );
		$this->row( __( 'Published', 'doctorpilot' ), (string) $summary['published'] );
		$this->row( __( 'Drafts', 'doctorpilot' ), (string) $summary['drafts'] );
		$this->row( __( 'Pending review', 'doctorpilot' ), (string) $summary['pending'] );
		$this->row( __( 'Active redirects', 'doctorpilot' ), (string) $summary['active_redirects'] );
		echo '</tbody></table>';

		echo '<form method="post" action="' . esc_url( $action ) . '" style="margin-top:1em">';
		wp_nonce_field( 'doctorpilot_export' );
		echo '<input type="hidden" name="action" value="doctorpilot_export" />';
		submit_button( __( 'Export doctors (CSV)', 'doctorpilot' ), 'secondary' );
		echo '</form>';
	}

	/**
	 * Handle the dry-run submission.
	 *
	 * @return void
	 */
	public function handle_dryrun(): void {
		$this->guard( 'doctorpilot_import_dryrun' );

		$validator = new \BrainStudioz\DoctorPilot\ImportExport\UploadValidator();
		$path      = $validator->validate( isset( $_FILES['csv'] ) ? (array) $_FILES['csv'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( is_wp_error( $path ) ) {
			$this->redirect_notice( 'import', 'error', $path->get_error_message() );
		}

		$mode         = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : 'create';
		$create_terms = ! empty( $_POST['create_terms'] );

		$reader = new CsvReader( (string) $path );
		$header = $reader->header();
		if ( is_wp_error( $header ) ) {
			$this->redirect_notice( 'import', 'error', $header->get_error_message() );
		}

		$mapping = MappingConfig::auto_map( (array) $header );
		$summary = ( new DryRunService() )->run( $reader, $mapping );
		if ( is_wp_error( $summary ) ) {
			$this->redirect_notice( 'import', 'error', $summary->get_error_message() );
		}

		set_transient(
			'dp_import_preview_' . get_current_user_id(),
			array(
				'path'         => (string) $path,
				'mapping'      => $mapping,
				'mode'         => $mode,
				'create_terms' => $create_terms,
				'summary'      => $summary,
			),
			HOUR_IN_SECONDS
		);

		$this->redirect_notice( 'import', 'success', __( 'Dry run complete. Review the preview below.', 'doctorpilot' ) );
	}

	/**
	 * Handle starting the real import.
	 *
	 * @return void
	 */
	public function handle_run(): void {
		$this->guard( 'doctorpilot_import_run' );

		$preview = get_transient( 'dp_import_preview_' . get_current_user_id() );
		if ( ! is_array( $preview ) || ! is_readable( (string) $preview['path'] ) ) {
			$this->redirect_notice( 'import', 'error', __( 'The staged import expired. Please upload again.', 'doctorpilot' ) );
		}

		$reader = new CsvReader( (string) $preview['path'] );
		$total  = $reader->count_rows();

		$job_id = ( new JobRepository() )->create(
			basename( (string) $preview['path'] ),
			array(
				'mapping'      => $preview['mapping'],
				'create_terms' => (bool) $preview['create_terms'],
			),
			(string) $preview['mode'],
			$total
		);

		if ( $job_id <= 0 ) {
			$this->redirect_notice( 'import', 'error', __( 'Could not create the import job.', 'doctorpilot' ) );
		}

		( new ImportRunner() )->start( $job_id, (string) $preview['path'] );
		delete_transient( 'dp_import_preview_' . get_current_user_id() );

		$this->redirect_notice( 'import', 'success', __( 'Import started. Doctors are being created as drafts in the background.', 'doctorpilot' ) );
	}

	/**
	 * Handle adding a redirect.
	 *
	 * @return void
	 */
	public function handle_add_redirect(): void {
		$this->guard( 'doctorpilot_add_redirect' );

		$source    = isset( $_POST['source_path'] ) ? (string) wp_unslash( $_POST['source_path'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$doctor_id = isset( $_POST['doctor_id'] ) ? (int) $_POST['doctor_id'] : 0;
		$active    = ! empty( $_POST['is_active'] );

		$normalized = RedirectValidator::normalize_source( $source );
		$errors     = RedirectValidator::validate( $normalized, $doctor_id );

		if ( array() !== $errors ) {
			$this->redirect_notice( 'redirects', 'error', implode( ' ', $errors ) );
		}

		$destination = wp_parse_url( (string) get_permalink( $doctor_id ), PHP_URL_PATH );
		$destination = is_string( $destination ) ? user_trailingslashit( '/' . ltrim( $destination, '/' ) ) : '';

		( new RedirectRepository() )->save( $normalized, $doctor_id, $destination, $active );

		$this->redirect_notice( 'redirects', 'success', __( 'Redirect saved.', 'doctorpilot' ) );
	}

	/**
	 * Handle a job rollback.
	 *
	 * @return void
	 */
	public function handle_rollback(): void {
		$this->guard( 'doctorpilot_rollback' );

		$job_id = isset( $_POST['job_id'] ) ? (int) $_POST['job_id'] : 0;
		$result = ( new Rollback() )->rollback( $job_id );

		$this->redirect_notice(
			'reports',
			'success',
			sprintf(
				/* translators: 1: deleted count, 2: kept count. */
				__( 'Rollback complete: %1$d drafts removed, %2$d published records kept.', 'doctorpilot' ),
				(int) $result['deleted'],
				(int) $result['kept_published']
			)
		);
	}

	/**
	 * Handle CSV export streaming.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		$this->guard( 'doctorpilot_export' );

		$csv = ( new Exporter() )->to_string();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="doctorpilot-doctors.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body, formula-escaped.
		exit;
	}

	/**
	 * Capability + nonce guard for admin-post handlers.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'doctorpilot' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirect back to a tab with an admin notice, then exit.
	 *
	 * @param string $tab     Tab key.
	 * @param string $type    success|error.
	 * @param string $message Message.
	 * @return void
	 */
	private function redirect_notice( string $tab, string $type, string $message ): void {
		set_transient( 'dp_migration_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render a two-column table row.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private function row( string $label, string $value ): void {
		echo '<tr><th scope="row" style="width:60%">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}
}
