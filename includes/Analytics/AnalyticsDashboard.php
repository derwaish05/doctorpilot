<?php
/**
 * Analytics admin dashboard.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A read-only dashboard showing popular symptoms and no-result phrases.
 *
 * Requires the analytics capability and only displays aggregate, non-identifying
 * data. A clear disclosure explains what is collected and that it is off by
 * default.
 */
final class AnalyticsDashboard {

	private const CAP = 'view_doctorpilot_analytics';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 21 );
	}

	/**
	 * Add the analytics submenu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_submenu_page(
			'doctorpilot',
			__( 'Analytics', 'doctorpilot' ),
			__( 'Analytics', 'doctorpilot' ),
			self::CAP,
			'doctorpilot-analytics',
			array( $this, 'render' )
		);
	}

	/**
	 * Render the dashboard.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view analytics.', 'doctorpilot' ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'DoctorPilot Analytics', 'doctorpilot' ) . '</h1>';

		if ( ! AnalyticsService::enabled() ) {
			echo '<div class="notice notice-info inline"><p>';
			echo esc_html__( 'Analytics are currently disabled. Enable them in DoctorPilot → Settings. Only anonymized, aggregate counts are stored — never IP, user, session, or query-to-person data.', 'doctorpilot' );
			echo '</p></div>';
		}

		$repo = new AnalyticsRepository();

		$this->table(
			__( 'Most searched symptoms (30 days)', 'doctorpilot' ),
			$repo->top( 'symptom', 30, 10 )
		);

		$this->table(
			__( 'Searches with no results (30 days)', 'doctorpilot' ),
			$repo->top( 'no_result', 30, 10 )
		);

		echo '<p class="description">' . esc_html__( 'Aggregate data helps improve discovery. It is not a diagnosis and contains no personal information.', 'doctorpilot' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render an aggregate table.
	 *
	 * @param string                                       $title Heading.
	 * @param array<int, array{label:string, total:int}>   $rows  Rows.
	 * @return void
	 */
	private function table( string $title, array $rows ): void {
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:520px"><thead><tr><th>' . esc_html__( 'Term', 'doctorpilot' ) . '</th><th>' . esc_html__( 'Count', 'doctorpilot' ) . '</th></tr></thead><tbody>';

		if ( array() === $rows ) {
			echo '<tr><td colspan="2">' . esc_html__( 'No data yet.', 'doctorpilot' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			printf( '<tr><td>%1$s</td><td>%2$d</td></tr>', esc_html( $row['label'] ), (int) $row['total'] );
		}
		echo '</tbody></table>';
	}
}
