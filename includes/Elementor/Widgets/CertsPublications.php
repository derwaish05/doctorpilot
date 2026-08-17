<?php
/**
 * Certifications & Publications Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a doctor's certifications and publications as two titled, icon-led
 * lists. Each group can be toggled, titled, and given its own icon; publications
 * link to their URL/DOI and certifications to the credential URL. Data is
 * resolved dynamically for the selected or current queried doctor.
 */
final class CertsPublications extends AbstractWidget {

	/**
	 * Belongs to the "Single DoctorPilot" category.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'doctorpilot-single' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-certs-publications';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Certifications & Publications', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-document-file';
	}

	// -------------------------------------------------------------------------
	// Controls.
	// -------------------------------------------------------------------------

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Content', 'doctorpilot' ) ) );

		$this->add_control(
			'doctor_id',
			array(
				'label'       => __( 'Doctor ID', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'description' => __( 'Optional — for editor preview or placing off a doctor page. On a doctor page the current doctor is always used.', 'doctorpilot' ),
				'min'         => 0,
			)
		);

		// Certifications group.
		$this->add_control( 'certs_heading', array( 'label' => __( 'Certifications', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control(
			'certs_show',
			array( 'label' => __( 'Show', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' )
		);
		$this->add_control(
			'certs_title',
			array( 'label' => __( 'Title', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __( 'Certifications', 'doctorpilot' ), 'condition' => array( 'certs_show' => 'yes' ) )
		);
		$this->add_control(
			'certs_icon',
			array( 'label' => __( 'Icon', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::ICONS, 'default' => array( 'value' => 'fas fa-award', 'library' => 'fa-solid' ), 'condition' => array( 'certs_show' => 'yes' ) )
		);
		$this->add_control(
			'certs_show_org',
			array( 'label' => __( 'Show organization', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'certs_show' => 'yes' ) )
		);
		$this->add_control(
			'certs_show_dates',
			array( 'label' => __( 'Show issued / expiry dates', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'certs_show' => 'yes' ) )
		);
		$this->add_control(
			'certs_link',
			array( 'label' => __( 'Link to credential URL', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'certs_show' => 'yes' ) )
		);

		// Publications group.
		$this->add_control( 'pubs_heading', array( 'label' => __( 'Publications', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control(
			'pubs_show',
			array( 'label' => __( 'Show', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' )
		);
		$this->add_control(
			'pubs_title',
			array( 'label' => __( 'Title', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __( 'Publications', 'doctorpilot' ), 'condition' => array( 'pubs_show' => 'yes' ) )
		);
		$this->add_control(
			'pubs_icon',
			array( 'label' => __( 'Icon', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::ICONS, 'default' => array( 'value' => 'far fa-file-lines', 'library' => 'fa-regular' ), 'condition' => array( 'pubs_show' => 'yes' ) )
		);
		$this->add_control(
			'pubs_show_meta',
			array( 'label' => __( 'Show journal & date', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'pubs_show' => 'yes' ) )
		);
		$this->add_control(
			'pubs_show_desc',
			array( 'label' => __( 'Show description', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'pubs_show' => 'yes' ) )
		);
		$this->add_control(
			'pubs_link',
			array( 'label' => __( 'Link to URL / DOI', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'pubs_show' => 'yes' ) )
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();

		$this->register_layout_style();
		$this->register_icon_style();
		$this->register_text_style();
	}

	/**
	 * Layout style: card, group spacing, and title.
	 *
	 * @return void
	 */
	private function register_layout_style(): void {
		$this->start_controls_section(
			'section_layout_style',
			array( 'label' => __( 'Layout', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array( 'name' => 'card_bg', 'types' => array( 'classic', 'gradient' ), 'selector' => '{{WRAPPER}} .dp-cp' )
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-cp' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-cp' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => 'card_border', 'selector' => '{{WRAPPER}} .dp-cp' )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .dp-cp' )
		);

		$this->add_responsive_control(
			'group_gap',
			array(
				'label'      => __( 'Gap between groups', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-cp' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'item_gap',
			array(
				'label'      => __( 'Gap between items', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-cp__list' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control( 'title_heading', array( 'label' => __( 'Group title', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'title_typography', 'selector' => '{{WRAPPER}} .dp-cp__title' )
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-cp__title' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Icon style controls (colour, size, and full box options).
	 *
	 * @return void
	 */
	private function register_icon_style(): void {
		$icon = '{{WRAPPER}} .dp-cp__icon';

		$this->start_controls_section(
			'section_icon_style',
			array( 'label' => __( 'Icon', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#16b47f',
				'selectors' => array( $icon => 'color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => __( 'Size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 32 ) ),
				'selectors'  => array( $icon => 'font-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->register_icon_box_controls( $icon, '{{WRAPPER}} .dp-cp__item:hover .dp-cp__icon' );

		$this->end_controls_section();
	}

	/**
	 * Name + meta text style controls.
	 *
	 * @return void
	 */
	private function register_text_style(): void {
		$this->start_controls_section(
			'section_text_style',
			array( 'label' => __( 'Text', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_control( 'name_heading', array( 'label' => __( 'Name / title', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'name_typography', 'selector' => '{{WRAPPER}} .dp-cp__name' )
		);
		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-cp__name, {{WRAPPER}} .dp-cp__name a' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control( 'meta_heading', array( 'label' => __( 'Meta', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'meta_typography', 'selector' => '{{WRAPPER}} .dp-cp__meta' )
		);
		$this->add_control(
			'meta_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-cp__meta' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control( 'desc_heading', array( 'label' => __( 'Description', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'desc_typography', 'selector' => '{{WRAPPER}} .dp-cp__desc' )
		);
		$this->add_control(
			'desc_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-cp__desc' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Render.
	// -------------------------------------------------------------------------

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings  = $this->get_settings_for_display();
		$doctor_id = $this->resolve_doctor_id( $settings );

		if ( $doctor_id <= 0 ) {
			if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Select a doctor or place this widget on a single-doctor template.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		$data = ( new DoctorView() )->full( $doctor_id );
		if ( null === $data ) {
			return;
		}

		$certs = is_array( $data['certifications'] ?? null ) ? $data['certifications'] : array();
		$pubs  = is_array( $data['publications'] ?? null ) ? $data['publications'] : array();

		$groups = '';
		if ( 'yes' === ( $settings['certs_show'] ?? '' ) ) {
			$groups .= $this->render_certifications( $certs, $settings );
		}
		if ( 'yes' === ( $settings['pubs_show'] ?? '' ) ) {
			$groups .= $this->render_publications( $pubs, $settings );
		}

		if ( '' === $groups ) {
			if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'This doctor has no certifications or publications yet.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		// The groups are built before this point precisely so a doctor with
		// neither certifications nor publications returns above without having
		// printed a heading.
		$this->render_heading( $settings );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Group HTML is fully escaped in the builders below.
		echo '<div class="dp-cp">' . $groups . '</div>';
	}

	/**
	 * Build the certifications group HTML, or '' when empty.
	 *
	 * @param array<int, mixed>    $certs    Certification rows.
	 * @param array<string, mixed> $settings Widget settings.
	 * @return string
	 */
	private function render_certifications( array $certs, array $settings ): string {
		$icon       = $this->icon_html( $settings['certs_icon'] ?? array() );
		$show_org   = 'yes' === ( $settings['certs_show_org'] ?? '' );
		$show_dates = 'yes' === ( $settings['certs_show_dates'] ?? '' );
		$do_link    = 'yes' === ( $settings['certs_link'] ?? '' );

		$items = '';
		foreach ( $certs as $cert ) {
			if ( ! is_array( $cert ) ) {
				continue;
			}
			$name = trim( (string) ( $cert['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$url = trim( (string) ( $cert['credential_url'] ?? '' ) );

			$parts = array();
			if ( $show_org ) {
				$parts[] = trim( (string) ( $cert['organization'] ?? '' ) );
			}
			if ( $show_dates ) {
				$issued = trim( (string) ( $cert['issued_date'] ?? '' ) );
				$expiry = trim( (string) ( $cert['expiry_date'] ?? '' ) );
				if ( '' !== $issued || '' !== $expiry ) {
					$parts[] = trim( implode( ' – ', array_filter( array( $issued, $expiry ) ) ) );
				}
			}
			$meta = implode( ' · ', array_filter( $parts ) );

			$items .= $this->item_html( $icon, $name, $meta, '', $do_link ? $url : '' );
		}

		if ( '' === $items ) {
			return '';
		}

		return $this->group_html( (string) ( $settings['certs_title'] ?? '' ), $items );
	}

	/**
	 * Build the publications group HTML, or '' when empty.
	 *
	 * @param array<int, mixed>    $pubs     Publication rows.
	 * @param array<string, mixed> $settings Widget settings.
	 * @return string
	 */
	private function render_publications( array $pubs, array $settings ): string {
		$icon      = $this->icon_html( $settings['pubs_icon'] ?? array() );
		$show_meta = 'yes' === ( $settings['pubs_show_meta'] ?? '' );
		$show_desc = 'yes' === ( $settings['pubs_show_desc'] ?? '' );
		$do_link   = 'yes' === ( $settings['pubs_link'] ?? '' );

		$items = '';
		foreach ( $pubs as $pub ) {
			if ( ! is_array( $pub ) ) {
				continue;
			}
			$title = trim( (string) ( $pub['title'] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}

			$url = trim( (string) ( $pub['url'] ?? '' ) );
			$doi = trim( (string) ( $pub['doi'] ?? '' ) );
			if ( '' === $url && '' !== $doi ) {
				$url = 'https://doi.org/' . ltrim( $doi, '/' );
			}

			$meta = '';
			if ( $show_meta ) {
				$meta = trim( implode( ' · ', array_filter( array( (string) ( $pub['journal'] ?? '' ), (string) ( $pub['published_date'] ?? '' ) ) ) ) );
			}

			$desc = $show_desc ? trim( (string) ( $pub['description'] ?? '' ) ) : '';

			$items .= $this->item_html( $icon, $title, $meta, $desc, $do_link ? $url : '' );
		}

		if ( '' === $items ) {
			return '';
		}

		return $this->group_html( (string) ( $settings['pubs_title'] ?? '' ), $items );
	}

	/**
	 * Render an Elementor icon to an HTML string.
	 *
	 * @param mixed $icon ICONS control value.
	 * @return string
	 */
	private function icon_html( $icon ): string {
		if ( ! is_array( $icon ) || '' === (string) ( $icon['value'] ?? '' ) ) {
			return '';
		}
		ob_start();
		\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return (string) ob_get_clean();
	}

	/**
	 * Wrap a title + items list into a group.
	 *
	 * @param string $title Group title.
	 * @param string $items Items HTML.
	 * @return string
	 */
	private function group_html( string $title, string $items ): string {
		$html = '<div class="dp-cp__group">';
		if ( '' !== trim( $title ) ) {
			$html .= '<div class="dp-cp__title">' . esc_html( $title ) . '</div>';
		}
		$html .= '<ul class="dp-cp__list">' . $items . '</ul></div>';

		return $html;
	}

	/**
	 * Build one list item.
	 *
	 * @param string $icon Icon HTML (already safe).
	 * @param string $name Name / title.
	 * @param string $meta Secondary line.
	 * @param string $desc Optional description / summary line.
	 * @param string $url  Optional link URL.
	 * @return string
	 */
	private function item_html( string $icon, string $name, string $meta, string $desc, string $url ): string {
		$name_html = '' !== $url
			? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $name ) . '</a>'
			: esc_html( $name );

		$html  = '<li class="dp-cp__item">';
		$html .= '<span class="dp-cp__icon" aria-hidden="true">' . $icon . '</span>';
		$html .= '<span class="dp-cp__text"><span class="dp-cp__name">' . $name_html . '</span>';
		if ( '' !== $meta ) {
			$html .= '<span class="dp-cp__meta">' . esc_html( $meta ) . '</span>';
		}
		if ( '' !== $desc ) {
			$html .= '<span class="dp-cp__desc">' . nl2br( esc_html( $desc ) ) . '</span>';
		}
		$html .= '</span></li>';

		return $html;
	}
}
