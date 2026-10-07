<?php
/**
 * Health care profession achievements Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns\AchievementList;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The doctor's health care profession achievements as a standalone icon-led
 * list. Reads the Highlights tab's "Health care profession" fields.
 */
final class HealthcareAchievements extends AbstractWidget {

	use AchievementList;

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-healthcare-achievements';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Health Care Profession', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-medal';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function list_key(): string {
		return 'healthcare_achievements';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function title_key(): string {
		return 'healthcare_achievements_title';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function default_title(): string {
		return __( 'Health Care Profession Achievements', 'doctorpilot' );
	}
}
