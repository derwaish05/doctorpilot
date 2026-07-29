<?php
/**
 * Doctor Grid / List Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Application\Search\SearchService;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;
use BrainStudioz\DoctorPilot\Frontend\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays doctors as a grid or compact list of cards.
 *
 * Uses the shared search service (published-only, deterministic order) and the
 * same card template as native output, so the widget stays in parity with the
 * rest of the directory.
 */
final class DoctorGridList extends AbstractWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-grid-list';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Doctor Grid / List', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-posts-grid';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_query',
			array( 'label' => __( 'Query', 'doctorpilot' ) )
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'default' => 'grid',
				'options' => array(
					'grid' => array( 'title' => __( 'Grid', 'doctorpilot' ), 'icon' => 'eicon-gallery-grid' ),
					'list' => array( 'title' => __( 'List', 'doctorpilot' ), 'icon' => 'eicon-editor-list-ul' ),
				),
			)
		);

		$this->add_columns_control( 3 );

		$this->add_control(
			'per_page',
			array(
				'label'   => __( 'Doctors per page', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 48,
				'default' => 12,
			)
		);

		$this->add_control(
			'sort',
			array(
				'label'   => __( 'Sort by', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'name',
				'options' => array(
					'name'       => __( 'Name', 'doctorpilot' ),
					'featured'   => __( 'Featured', 'doctorpilot' ),
					'experience' => __( 'Most experienced', 'doctorpilot' ),
					'recent'     => __( 'Recently added', 'doctorpilot' ),
				),
			)
		);

		$this->add_control(
			'pagination',
			array(
				'label'        => __( 'Show pagination', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();

		$this->add_card_style_controls();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$per_page = isset( $settings['per_page'] ) ? (int) $settings['per_page'] : 12;
		$sort     = isset( $settings['sort'] ) ? sanitize_key( (string) $settings['sort'] ) : 'name';
		$layout   = ( isset( $settings['layout'] ) && 'list' === $settings['layout'] ) ? 'list' : 'grid';
		$columns  = isset( $settings['columns'] ) ? (int) $settings['columns'] : 3;

		$paged = max( 1, (int) get_query_var( 'paged' ) ?: (int) get_query_var( 'page' ) );

		$request = SearchRequestFactory::from_array(
			array(
				'sort'     => $sort,
				'per_page' => $per_page,
				'page'     => $paged,
			),
			$per_page
		);

		$result = ( new SearchService() )->search( $request );
		$view   = new DoctorView();

		$grid_class = 'list' === $layout ? 'dp-directory dp-list dp-cols-1' : 'dp-directory dp-cols-' . max( 1, min( 4, $columns ) );

		echo '<div class="dp-directory-widget">';

		if ( array() === $result['ids'] ) {
			echo '<p class="dp-empty">' . esc_html__( 'No doctors found.', 'doctorpilot' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<div class="' . esc_attr( $grid_class ) . '">';
		foreach ( $result['ids'] as $id ) {
			$card = $view->card( (int) $id );
			if ( null !== $card ) {
				include Templates::locate( 'card-doctor.php' );
			}
		}
		echo '</div>';

		if ( ! empty( $settings['pagination'] ) && 'yes' === $settings['pagination'] && (int) $result['total_pages'] > 1 ) {
			echo wp_kses_post(
				(string) paginate_links(
					array(
						'total'   => (int) $result['total_pages'],
						'current' => (int) $result['page'],
					)
				)
			);
		}

		echo '</div>';
	}
}
