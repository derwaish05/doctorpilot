<?php
/**
 * Single-doctor page header Elementor widget (dynamic data).
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the hero header for a single doctor from canonical data: breadcrumb,
 * name, role, key facts, credential chips, photo with a specialty badge, and the
 * booking / call actions. The doctor is the selected one or the current queried
 * doctor, so a single design serves every doctor on a single-doctor template.
 */
final class PageHeader extends AbstractWidget {

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
		return 'doctorpilot-page-header';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Page header with dynamic data', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-single-post';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Header', 'doctorpilot' ) )
		);

		$this->add_control(
			'doctor_id',
			array(
				'label'       => __( 'Doctor ID', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'description' => __( 'Leave empty to use the current doctor (for single-doctor templates).', 'doctorpilot' ),
				'min'         => 0,
			)
		);

		$this->add_control(
			'home_label',
			array(
				'label'   => __( 'Breadcrumb: home label', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Home', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'doctors_label',
			array(
				'label'   => __( 'Breadcrumb: directory label', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Our Doctors', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'availability',
			array(
				'label'       => __( 'Availability text', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Sunday – Thursday', 'doctorpilot' ),
				'description' => __( 'Optional. Shown as the Availability row.', 'doctorpilot' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_colors',
			array(
				'label' => __( 'Colors', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'bg_start',
			array(
				'label'     => __( 'Background: start', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero' => '--dp-hero-bg-1: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'bg_end',
			array(
				'label'     => __( 'Background: end', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero' => '--dp-hero-bg-2: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'accent',
			array(
				'label'     => __( 'Accent (buttons & badge)', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero' => '--dp-accent: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings  = $this->get_settings_for_display();
		$doctor_id = isset( $settings['doctor_id'] ) ? (int) $settings['doctor_id'] : 0;

		if ( $doctor_id <= 0 ) {
			$queried = get_queried_object();
			if ( $queried instanceof \WP_Post && DoctorPostType::POST_TYPE === $queried->post_type ) {
				$doctor_id = (int) $queried->ID;
			}
		}

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

		$view = $this->prepare( $data, $settings );

		echo '<section class="dp-hero">';
		echo '<div class="dp-hero__inner">';

		// Breadcrumb.
		echo '<nav class="dp-hero__crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'doctorpilot' ) . '">';
		printf( '<a href="%1$s">%2$s</a>', esc_url( home_url( '/' ) ), esc_html( $view['home_label'] ) );
		if ( '' !== $view['archive'] ) {
			echo ' <span class="dp-hero__sep">&rsaquo;</span> ';
			printf( '<a href="%1$s">%2$s</a>', esc_url( $view['archive'] ), esc_html( $view['doctors_label'] ) );
		}
		echo ' <span class="dp-hero__sep">&rsaquo;</span> ';
		echo '<span class="dp-hero__current">' . esc_html( $view['name'] ) . '</span>';
		echo '</nav>';

		echo '<div class="dp-hero__grid">';

		// Media column.
		echo '<div class="dp-hero__media">';
		echo '<div class="dp-hero__photo">';
		if ( '' !== $view['photo_url'] ) {
			printf( '<img src="%1$s" alt="%2$s" loading="lazy" />', esc_url( $view['photo_url'] ), esc_attr( $view['photo_alt'] ) );
		} else {
			echo '<span class="dp-hero__placeholder" aria-hidden="true"></span>';
		}
		if ( '' !== $view['specialty'] ) {
			echo '<span class="dp-hero__badge">' . esc_html( $view['specialty'] ) . '</span>';
		}
		echo '</div>';
		echo '</div>';

		// Body column.
		echo '<div class="dp-hero__body">';
		echo '<h1 class="dp-hero__name">' . esc_html( $view['name'] ) . '</h1>';
		if ( '' !== $view['role'] ) {
			echo '<p class="dp-hero__role">' . esc_html( $view['role'] ) . '</p>';
		}

		echo '<div class="dp-hero__meta">';
		$this->meta_row( __( 'Primary Specialty', 'doctorpilot' ), $view['specialty'] );
		$this->meta_row( __( 'Department', 'doctorpilot' ), $view['department'] );
		$this->meta_row( __( 'Languages', 'doctorpilot' ), $view['languages'] );
		$this->meta_row( __( 'Availability', 'doctorpilot' ), $view['availability'] );
		$this->meta_row( __( 'Email', 'doctorpilot' ), $view['email'] );
		$this->meta_row( __( 'Phone', 'doctorpilot' ), $view['phone'] );
		echo '</div>';

		if ( array() !== $view['certs'] ) {
			echo '<ul class="dp-hero__tags">';
			foreach ( $view['certs'] as $cert ) {
				echo '<li class="dp-hero__chip">';
				$this->icon_award();
				echo '<span>' . esc_html( $cert ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}

		if ( '' !== $view['book_url'] || '' !== $view['tel'] ) {
			echo '<div class="dp-hero__actions">';
			if ( '' !== $view['book_url'] ) {
				printf(
					'<a class="dp-hero__btn dp-hero__btn--primary" href="%1$s"%2$s>',
					esc_url( $view['book_url'] ),
					$view['book_target'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant, safe attribute string.
				);
				$this->icon_calendar();
				echo '<span>' . esc_html( $view['book_label'] ) . '</span></a>';
			}
			if ( '' !== $view['tel'] ) {
				printf( '<a class="dp-hero__btn dp-hero__btn--ghost" href="tel:%1$s">', esc_attr( $view['tel'] ) );
				$this->icon_phone();
				echo '<span>' . esc_html__( 'Call Now', 'doctorpilot' ) . '</span></a>';
			}
			echo '</div>';
		}

		echo '</div>'; // body.
		echo '</div>'; // grid.
		echo '</div>'; // inner.
		echo '</section>';
	}

	/**
	 * Reduce the view model + settings to the flat values the header renders.
	 *
	 * @param array<string, mixed> $data     Doctor view model.
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<string, mixed>
	 */
	private function prepare( array $data, array $settings ): array {
		$specialty  = isset( $data['primary_specialty']['name'] ) ? (string) $data['primary_specialty']['name'] : '';
		$department = isset( $data['primary_department']['name'] ) ? (string) $data['primary_department']['name'] : '';

		$languages = array();
		foreach ( (array) ( $data['languages'] ?? array() ) as $term ) {
			if ( isset( $term['name'] ) ) {
				$languages[] = (string) $term['name'];
			}
		}

		// Role: first leadership entry (string for line input, array for legacy
		// rows), falling back to the primary specialty.
		$role = '';
		if ( ! empty( $data['leadership'] ) && is_array( $data['leadership'] ) ) {
			$first = reset( $data['leadership'] );
			if ( is_string( $first ) ) {
				$role = $first;
			} elseif ( is_array( $first ) ) {
				$role = (string) ( $first['title'] ?? $first['name'] ?? '' );
			}
		}
		if ( '' === $role ) {
			$role = $specialty;
		}

		// Email + phone: the first location that provides each.
		$email = '';
		$phone = '';
		foreach ( (array) ( $data['locations'] ?? array() ) as $loc ) {
			if ( '' === $email && ! empty( $loc['public_email'] ) ) {
				$email = (string) $loc['public_email'];
			}
			if ( '' === $phone && ! empty( $loc['phone'] ) ) {
				$phone = (string) $loc['phone'];
			}
		}

		$certs = array();
		foreach ( (array) ( $data['certifications'] ?? array() ) as $cert ) {
			$name = (string) ( $cert['name'] ?? '' );
			if ( '' !== $name ) {
				$certs[] = $name;
			}
		}

		$booking     = is_array( $data['booking'] ?? null ) ? $data['booking'] : array();
		$book_url    = (string) ( $booking['url'] ?? '' );
		$book_label  = '' !== (string) ( $booking['label'] ?? '' ) ? (string) $booking['label'] : __( 'Book an Appointment', 'doctorpilot' );
		$book_target = '_blank' === ( $booking['target'] ?? '' ) ? ' target="_blank" rel="noopener"' : '';

		// A tel: link — prefer a phone, else a phone-type booking destination.
		$tel_source = '' !== $phone ? $phone : ( 0 === strpos( $book_url, 'tel:' ) ? substr( $book_url, 4 ) : '' );
		$tel        = preg_replace( '/[^0-9+]/', '', $tel_source );

		$photo     = is_array( $data['photo'] ?? null ) ? $data['photo'] : array();
		$photo_url = (string) ( $photo['url'] ?? '' );

		return array(
			'name'          => (string) ( $data['name'] ?? '' ),
			'role'          => $role,
			'specialty'     => $specialty,
			'department'    => $department,
			'languages'     => implode( ', ', $languages ),
			'availability'  => (string) ( $settings['availability'] ?? '' ),
			'email'         => $email,
			'phone'         => $phone,
			'tel'           => (string) $tel,
			'certs'         => $certs,
			'book_url'      => $book_url,
			'book_label'    => $book_label,
			'book_target'   => $book_target,
			'photo_url'     => $photo_url,
			'photo_alt'     => '' !== (string) ( $photo['alt'] ?? '' ) ? (string) $photo['alt'] : (string) ( $data['name'] ?? '' ),
			'archive'       => (string) get_post_type_archive_link( DoctorPostType::POST_TYPE ),
			'home_label'    => '' !== (string) ( $settings['home_label'] ?? '' ) ? (string) $settings['home_label'] : __( 'Home', 'doctorpilot' ),
			'doctors_label' => '' !== (string) ( $settings['doctors_label'] ?? '' ) ? (string) $settings['doctors_label'] : __( 'Our Doctors', 'doctorpilot' ),
		);
	}

	/**
	 * Render one label/value meta row when the value is non-empty.
	 *
	 * @param string $label Row label.
	 * @param string $value Row value.
	 * @return void
	 */
	private function meta_row( string $label, string $value ): void {
		if ( '' === $value ) {
			return;
		}
		echo '<div class="dp-hero__row">';
		echo '<span class="dp-hero__k">' . esc_html( $label ) . '</span>';
		echo '<span class="dp-hero__v">: ' . esc_html( $value ) . '</span>';
		echo '</div>';
	}

	/**
	 * Award icon for credential chips.
	 *
	 * @return void
	 */
	private function icon_award(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG.
		echo '<svg class="dp-hero__ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.5 12.9 17 22l-5-3-5 3 1.5-9.1"/></svg>';
	}

	/**
	 * Calendar icon for the booking button.
	 *
	 * @return void
	 */
	private function icon_calendar(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG.
		echo '<svg class="dp-hero__ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
	}

	/**
	 * Phone icon for the call button.
	 *
	 * @return void
	 */
	private function icon_phone(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG.
		echo '<svg class="dp-hero__ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>';
	}
}
