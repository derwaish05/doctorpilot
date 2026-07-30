<?php
/**
 * SEO ownership and head output.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Seo;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits canonical, meta description, Open Graph, and Physician schema for a
 * single doctor — but only when no established SEO plugin owns the head.
 *
 * This guarantees exactly one SEO artifact owner and avoids duplicate canonical
 * or schema output when Yoast, Rank Math, AIOSEO, or SEOPress is active.
 */
final class SeoOwner {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! self::owns_seo() ) {
			return;
		}

		add_action( 'wp_head', array( $this, 'render_head' ), 1 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'document_title_parts', array( $this, 'document_title' ) );
	}

	/**
	 * Set the document title for a doctor: the SEO title override when present,
	 * otherwise the post title (WordPress default).
	 *
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public function document_title( array $parts ): array {
		if ( ! is_singular( DoctorPostType::POST_TYPE ) ) {
			return $parts;
		}

		$seo_title = trim( (string) get_post_meta( (int) get_queried_object_id(), FieldRegistry::META_PREFIX . 'seo_title', true ) );
		if ( '' !== $seo_title ) {
			$parts['title'] = $seo_title;
		}

		return $parts;
	}

	/**
	 * Whether DoctorPilot should own SEO output.
	 *
	 * @return bool
	 */
	public static function owns_seo(): bool {
		$other_active = defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' );

		/**
		 * Filter whether DoctorPilot owns SEO output for doctor views.
		 *
		 * @param bool $owns Whether DoctorPilot owns SEO.
		 */
		return (bool) apply_filters( 'doctorpilot/seo/owns_output', ! $other_active );
	}

	/**
	 * Output canonical, description, Open Graph, and JSON-LD for a doctor.
	 *
	 * @return void
	 */
	public function render_head(): void {
		if ( ! is_singular( DoctorPostType::POST_TYPE ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		$view    = new DoctorView();
		$data    = $view->full( $post_id );

		if ( null === $data ) {
			return;
		}

		$canonical = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'seo_canonical_url', true );
		if ( '' === $canonical ) {
			$canonical = (string) get_permalink( $post_id );
		}

		// Meta description: the override, else the doctor's post description —
		// the headline (excerpt) when present, otherwise the biography.
		$description = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'seo_description', true );
		if ( '' === $description ) {
			$intro = trim( wp_strip_all_tags( (string) $data['intro'] ) );
			if ( '' !== $intro ) {
				$description = wp_trim_words( $intro, 30, '…' );
			} else {
				$content     = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
				$description = '' !== $content ? wp_trim_words( $content, 30, '…' ) : '';
			}
		}

		echo "\n<!-- DoctorPilot SEO -->\n";
		printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );

		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		}

		printf( '<meta property="og:type" content="profile" />' . "\n" );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( (string) $data['name'] ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $canonical ) );

		if ( ! empty( $data['photo']['url'] ) ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( (string) $data['photo']['url'] ) );
		}

		$schema = Schema::physician( $data, $canonical );
		if ( array() !== $schema ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
		}
		echo "<!-- /DoctorPilot SEO -->\n";
	}

	/**
	 * Apply the per-doctor indexing preference.
	 *
	 * @param array<string, bool> $robots Robots directives.
	 * @return array<string, bool>
	 */
	public function robots( array $robots ): array {
		// Never index filtered/search query-string combinations to avoid
		// thousands of duplicate indexable pages.
		if ( self::has_search_params() ) {
			$robots['noindex'] = true;

			return $robots;
		}

		if ( ! is_singular( DoctorPostType::POST_TYPE ) ) {
			return $robots;
		}

		$status = (string) get_post_meta( (int) get_queried_object_id(), FieldRegistry::META_PREFIX . 'seo_index_status', true );
		if ( 'noindex' === $status ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = false;
		}

		return $robots;
	}

	/**
	 * Whether the current request carries DoctorPilot search/filter parameters.
	 *
	 * @return bool
	 */
	private static function has_search_params(): bool {
		$keys = array( 'q', 'department', 'specialty', 'symptom', 'language', 'gender', 'accepting_new', 'online', 'sort', 'view' );

		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of public query state.
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				return true;
			}
		}

		return false;
	}
}
