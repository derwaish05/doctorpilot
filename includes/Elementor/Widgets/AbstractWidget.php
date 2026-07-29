<?php
/**
 * Base class for DoctorPilot Elementor widgets.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Elementor\Integration;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared behaviour for DoctorPilot widgets.
 *
 * Every widget belongs to the DoctorPilot category, depends on the shared
 * frontend stylesheet (loaded only where a widget is present), and renders
 * through the same view/search/booking services as native output — never a
 * parallel query engine and never raw meta.
 */
abstract class AbstractWidget extends Widget_Base {

	/**
	 * Widget category.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'doctorpilot' );
	}

	/**
	 * Style dependencies.
	 *
	 * @return array<int, string>
	 */
	public function get_style_depends(): array {
		return array( Integration::STYLE_HANDLE );
	}

	/**
	 * Default keywords for discovery in the editor.
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'doctor', 'doctorpilot', 'directory', 'medical', 'hospital' );
	}

	/**
	 * Add a responsive columns control to the current controls section.
	 *
	 * @param int $default Default column count.
	 * @return void
	 */
	protected function add_columns_control( int $default = 3 ): void {
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'doctorpilot' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => (string) $default,
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'selectors'      => array(
					'{{WRAPPER}} .dp-directory' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				),
			)
		);
	}

	/**
	 * Add card name typography/color style controls bound to scoped selectors.
	 *
	 * @return void
	 */
	protected function add_card_style_controls(): void {
		$this->start_controls_section(
			'section_card_style',
			array(
				'label' => __( 'Card', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Name color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dp-card__name a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'name_typography',
				'selector' => '{{WRAPPER}} .dp-card__name',
			)
		);

		$this->add_responsive_control(
			'card_gap',
			array(
				'label'      => __( 'Gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .dp-directory' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}
}
