<?php
/**
 * Community outreach Elementor widget.
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
 * The doctor's community outreach activities and achievements as a standalone
 * icon-led list. Reads the Highlights tab's "Community outreach" fields.
 */
final class CommunityOutreach extends AbstractWidget {

	use AchievementList;

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-community-outreach';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Community Outreach', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function list_key(): string {
		return 'community_outreach';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function title_key(): string {
		return 'community_outreach_title';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function default_title(): string {
		return __( 'Community Outreach Activities / Achievements', 'doctorpilot' );
	}
}
