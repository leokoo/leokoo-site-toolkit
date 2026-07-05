<?php
namespace Zehoro\REST;

use Zehoro\Core\Plugin;
use Zehoro\Utils\Option;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST endpoints powering the Modules page.
 *
 * The toggle endpoint replaces the legacy "tick checkboxes → click Save"
 * flow with a per-card switch that flips state immediately on click
 * (matches the WPExtended pattern in
 * `zorasi-roadmaps/specs/phase-0-module-filtering.md`).
 *
 * Endpoint:
 *   POST /wp-json/zehoro/v1/modules/{slug}/toggle
 *     → 200 { success: true,  enabled: bool, slug: string }
 *     → 404 { code: 'module_not_registered', message: '…' } when slug unknown
 *
 * Auth: X-WP-Nonce header (wp_create_nonce('wp_rest')) +
 *       permission_callback current_user_can( 'manage_options' ).
 *
 * @package Zehoro\REST
 */
class ModulesController {

	public const NAMESPACE = 'zehoro/v1';
	public const ROUTE_TOGGLE = '/modules/(?P<slug>[a-z0-9_]+)/toggle';
	public const ROUTE_BULK   = '/modules/bulk';

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE_TOGGLE, [
			'methods'             => 'POST',
			'callback'            => [ $this, 'toggle' ],
			'permission_callback' => [ $this, 'permission_check' ],
			'args'                => [
				'slug' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
				],
			],
		] );

		// Bulk enable/disable — an explicit slug list (persona presets), or
		// every module in one group, or every module.
		//   POST /wp-json/zehoro/v1/modules/bulk { enable: bool, slugs?: string[], group?: string }
		//     → 200 { success: true, enabled: bool, slugs: string[], active_count: int }
		//     → 404 { code: 'no_modules_matched' } when nothing matches
		register_rest_route( self::NAMESPACE, self::ROUTE_BULK, [
			'methods'             => 'POST',
			'callback'            => [ $this, 'bulk' ],
			'permission_callback' => [ $this, 'permission_check' ],
			'args'                => [
				'enable' => [
					'required' => true,
					'type'     => 'boolean',
				],
				'slugs'  => [
					'required' => false,
					'type'     => 'array',
					'default'  => [],
					'items'    => [ 'type' => 'string' ],
				],
				'group'  => [
					'required'          => false,
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_key',
				],
			],
		] );
	}

	public function permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function toggle( WP_REST_Request $request ): WP_REST_Response {
		$slug       = (string) $request->get_param( 'slug' );
		$registered = Plugin::get_registered_modules();

		if ( ! isset( $registered[ $slug ] ) ) {
			return new WP_REST_Response( [
				'code'    => 'module_not_registered',
				'message' => sprintf( 'No module is registered under slug "%s".', $slug ),
			], 404 );
		}

		$active = (array) Option::get( 'zehoro_active_modules', [] );

		if ( in_array( $slug, $active, true ) ) {
			$active  = array_values( array_diff( $active, [ $slug ] ) );
			$enabled = false;
		} else {
			$active[] = $slug;
			$active   = array_values( array_unique( $active ) );
			$enabled  = true;
		}

		update_option( 'zehoro_active_modules', $active );

		return new WP_REST_Response( [
			'success' => true,
			'enabled' => $enabled,
			'slug'    => $slug,
		], 200 );
	}

	/**
	 * Bulk enable/disable. Resolution: explicit slug list (filtered to
	 * registered modules — used by persona presets) > group > everything.
	 * One option write either way — the per-module init cost only exists
	 * on the next page load.
	 */
	public function bulk( WP_REST_Request $request ): WP_REST_Response {
		$enable     = (bool) $request->get_param( 'enable' );
		$group      = (string) $request->get_param( 'group' );
		$slugs      = array_map( 'sanitize_key', (array) $request->get_param( 'slugs' ) );
		$registered = Plugin::get_registered_modules();

		$targets = [];
		if ( [] !== $slugs ) {
			$targets = array_values( array_intersect( $slugs, array_keys( $registered ) ) );
		} else {
			// The sidebar scope is a BUCKET (Loop / Blocks / Conversion / Toolkit);
			// match on the module's bucket, but still accept a raw fine-group key for
			// back-compat / programmatic callers.
			foreach ( $registered as $slug => $meta ) {
				$g = (string) ( $meta['group'] ?? 'other' );
				if ( '' === $group || $g === $group || Plugin::group_bucket( $g ) === $group ) {
					$targets[] = $slug;
				}
			}
		}

		if ( [] === $targets ) {
			return new WP_REST_Response( [
				'code'    => 'no_modules_matched',
				'message' => 'No registered modules matched the request.',
			], 404 );
		}

		$active = (array) Option::get( 'zehoro_active_modules', [] );
		$active = $enable
			? array_values( array_unique( array_merge( $active, $targets ) ) )
			: array_values( array_diff( $active, $targets ) );

		update_option( 'zehoro_active_modules', $active );

		return new WP_REST_Response( [
			'success'      => true,
			'enabled'      => $enable,
			'slugs'        => $targets,
			'active_count' => count( $active ),
		], 200 );
	}
}
