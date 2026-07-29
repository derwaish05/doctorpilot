<?php
/**
 * Native shortcodes for directory and profile output.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Frontend;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `[doctorpilot_directory]` and `[doctorpilot_profile]`.
 *
 * These provide accessible, server-rendered output that works without
 * Elementor or JavaScript. Interactive AJAX search arrives in Milestone 3 and
 * will reuse the same query/view services.
 */
final class Shortcodes {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'doctorpilot_directory', array( $this, 'directory' ) );
		add_shortcode( 'doctorpilot_profile', array( $this, 'profile' ) );
	}

	/**
	 * Render a paginated grid of published doctors.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function directory( $atts ): string {
		$atts = shortcode_atts(
			array(
				'per_page' => 12,
				'columns'  => 3,
			),
			is_array( $atts ) ? $atts : array(),
			'doctorpilot_directory'
		);

		$paged = max( 1, (int) get_query_var( 'paged' ) ?: (int) get_query_var( 'page' ) );

		$query = new \WP_Query(
			array(
				'post_type'      => DoctorPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, min( 48, (int) $atts['per_page'] ) ),
				'paged'          => $paged,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$view    = new DoctorView();
		$columns = max( 1, min( 4, (int) $atts['columns'] ) );

		ob_start();

		echo '<div class="dp-directory dp-cols-' . esc_attr( (string) $columns ) . '">';

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$card = $view->card( get_the_ID() );
				if ( null !== $card ) {
					self::render_card( $card );
				}
			}
		} else {
			echo '<p class="dp-empty">' . esc_html__( 'No doctors found.', 'doctorpilot' ) . '</p>';
		}

		echo '</div>';

		echo wp_kses_post(
			paginate_links(
				array(
					'total'   => (int) $query->max_num_pages,
					'current' => $paged,
				)
			) ?? ''
		);

		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Render a single doctor profile by ID.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function profile( $atts ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'doctorpilot_profile'
		);

		$view = new DoctorView();
		$data = $view->full( (int) $atts['id'] );

		if ( null === $data ) {
			return '';
		}

		ob_start();
		include Templates::locate( 'profile-content.php' );

		return (string) ob_get_clean();
	}

	/**
	 * Render a doctor card from view-model data.
	 *
	 * @param array<string, mixed> $card Card data from DoctorView::card().
	 * @return void
	 */
	public static function render_card( array $card ): void {
		include Templates::locate( 'card-doctor.php', '' );
	}
}
