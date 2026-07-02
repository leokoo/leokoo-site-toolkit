<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Home Filter Pills Module
 *
 * Renders a navigation pill bar mapping site "topics" to their archive URLs.
 * Each pill is a real <a> tag — click navigates to the archive (clean SEO
 * URL, shareable, no JS, no AJAX). Active state derived from current URL.
 *
 * Site-specific pill items configured via the `zehoro/home_filter_pills/items`
 * filter. Each item array:
 *   [
 *     'label' => string,           // visible pill text
 *     'url'   => string,           // destination URL
 *     'count' => null|int|array,   // optional count badge spec, see below
 *   ]
 *
 * Count spec can be:
 *   - int                              (fixed value)
 *   - ['type' => 'category', 'slug' => 'wordpress']
 *   - ['type' => 'tax', 'taxonomy' => 'product-categories', 'slug' => '...']
 *   - ['type' => 'cpt', 'post_type' => 'reviews']
 *
 * Usage:
 *   [zehoro_home_filter_pills]                  — default dark scheme
 *   [zehoro_home_filter_pills scheme="light"]   — light-bg scheme
 *
 * @package Zehoro\Modules
 */
class HomeFilterPills implements \Zehoro\Core\ModuleInterface {

	public static function register(): void {
		Plugin::register_module( 'home_filter_pills', self::class, [
			'title'   => 'Home Filter Pills',
			'desc'    => 'Cross-CPT topic navigation pills. Pills configured via zehoro/home_filter_pills/items filter. Use [zehoro_home_filter_pills scheme="light|dark"].',
			'default' => true,
		] );
	}

	public function init(): void {
		// Canonical zehoro_home_filter_pills + legacy lkst_home_filter_pills.
		add_shortcode( 'zehoro_home_filter_pills', [ $this, 'render' ] );
		add_shortcode( 'lkst_home_filter_pills',   [ $this, 'render' ] );
		// This bar targets the homepage/front index — a non-singular view the
		// global stylesheet gate (is_singular) skips. Force the shared sheet when
		// pills are configured and we're on home/front, mirroring TableOfContents.
		add_filter( 'zehoro/load_global_styles', [ $this, 'force_styles_on_home' ] );
	}

	/**
	 * `zehoro/load_global_styles` filter: force the shared stylesheet on the
	 * home/front index when the pills are actually configured (non-empty items).
	 * Evaluated at wp_enqueue_scripts. Erring toward loading is harmless.
	 */
	public function force_styles_on_home( $load ) {
		if ( $load ) return $load;
		if ( ! ( is_home() || is_front_page() ) ) return $load;
		$items = apply_filters( 'zehoro/home_filter_pills/items', [] );
		return ( ! empty( $items ) && is_array( $items ) ) ? true : $load;
	}

	public function render( $atts ): string {
		$atts = shortcode_atts( [
			'scheme' => 'dark',
		], $atts );

		// Canonical zehoro/* hook + legacy lkst/* fallback.
		$items = apply_filters( 'zehoro/home_filter_pills/items', [] );
		if ( has_filter( 'lkst/home_filter_pills/items' ) ) {
			$items = apply_filters_deprecated( 'lkst/home_filter_pills/items', [ $items ], '1.7.0', 'zehoro/home_filter_pills/items' );
		}
		if ( empty( $items ) || ! is_array( $items ) ) return '';

		$scheme       = in_array( $atts['scheme'], [ 'light', 'dark' ], true ) ? $atts['scheme'] : 'dark';
		$current_path = $this->get_current_path();

		$html = sprintf(
			'<div class="lkst-cat-pills lkst-cat-pills--%s" role="navigation" aria-label="%s">',
			esc_attr( $scheme ),
			esc_attr__( 'Filter by topic', 'zehoro-toolkit' )
		);

		foreach ( $items as $item ) {
			$label = isset( $item['label'] ) ? (string) $item['label'] : '';
			$url   = isset( $item['url'] )   ? (string) $item['url']   : '';
			if ( $label === '' || $url === '' ) continue;

			$is_active    = $this->url_matches_current( $url, $current_path );
			$count        = $this->resolve_count( $item['count'] ?? null );
			$badge        = $count > 0 ? ' <span class="lkst-pill-count">' . esc_html( (string) $count ) . '</span>' : '';
			$active_class = $is_active ? ' is-active' : '';

			$html .= sprintf(
				'<a href="%s" class="lkst-cat-pill%s"%s>%s%s</a>',
				esc_url( $url ),
				$active_class,
				$is_active ? ' aria-current="page"' : '',
				esc_html( $label ),
				$badge
			);
		}

		return $html . '</div>';
	}

	private function get_current_path(): string {
		$path = $_SERVER['REQUEST_URI'] ?? '/';
		$path = parse_url( $path, PHP_URL_PATH ) ?: '/';
		return rtrim( $path, '/' ) . '/';
	}

	private function url_matches_current( string $url, string $current_path ): bool {
		$url_path = parse_url( $url, PHP_URL_PATH ) ?: '/';
		$url_path = rtrim( $url_path, '/' ) . '/';
		return $url_path === $current_path;
	}

	private function resolve_count( $spec ): int {
		if ( is_int( $spec ) ) return $spec;
		if ( ! is_array( $spec ) ) return 0;

		$type = $spec['type'] ?? '';

		if ( $type === 'category' ) {
			$term = get_term_by( 'slug', $spec['slug'] ?? '', 'category' );
			return $term ? (int) $term->count : 0;
		}
		if ( $type === 'tax' ) {
			$term = get_term_by( 'slug', $spec['slug'] ?? '', $spec['taxonomy'] ?? '' );
			return $term ? (int) $term->count : 0;
		}
		if ( $type === 'cpt' ) {
			$counts = wp_count_posts( $spec['post_type'] ?? '' );
			return $counts ? (int) ( $counts->publish ?? 0 ) : 0;
		}

		return 0;
	}
}
