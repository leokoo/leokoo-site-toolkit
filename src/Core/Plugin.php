<?php
namespace Zehoro\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core plugin bootstrap.
 *
 * Implements a Module Registry Pattern. Modules self-register their meta
 * and the plugin uses this registry to power the dashboard and frontend.
 *
 * @package Zehoro\Core
 */
class Plugin {

	/** @var array Registered modules meta. Keyed by slug. */
	private static array $registry = [];

	/** @var ModuleInterface[] Live instances of active modules. */
	private array $modules = [];

	/**
	 * Register a module into the toolkit registry.
	 *
	 * Modules pass minimum `title`, `desc`, `default`; everything else
	 * (tier, group, docs, keywords, order, has_settings) is auto-derived
	 * from the class namespace, the slug map, and the settings_page
	 * presence. Modules CAN override any of these via $meta and the
	 * explicit value wins.
	 *
	 * @param string $slug  Module slug (e.g. 'table_of_contents').
	 * @param string $class Fully-qualified class name.
	 * @param array  $meta  Registration metadata.
	 *
	 * Accepted keys (any can be left out — sensible defaults apply):
	 *   title         (string)  Display label. Falls back to slug.
	 *   desc          (string)  One-sentence card body.
	 *   default       (bool)    Active on fresh install.
	 *   settings_page (string)  WP page slug for this module's settings.
	 *   tier          (string)  'free' | 'pro' — auto: namespace check.
	 *   group         (string)  Group slug (see GROUPS const). Auto: slug map.
	 *   docs          (string)  External docs URL.
	 *   keywords      (string[]) Search index terms.
	 *   order         (int)     Sort within group. Default 100.
	 *   has_settings  (bool)    Auto-derived from non-empty settings_page.
	 */
	public static function register_module( string $slug, string $class, array $meta ): void {
		$enriched = array_merge( $meta, [ 'class' => $class ] );

		// Auto-derived fields — explicit $meta values win.
		if ( ! isset( $enriched['tier'] ) ) {
			$enriched['tier'] = self::detect_tier( $class );
		}
		if ( ! isset( $enriched['group'] ) ) {
			$enriched['group'] = self::detect_group( $slug );
		}
		if ( ! isset( $enriched['order'] ) ) {
			$enriched['order'] = 100;
		}
		if ( ! isset( $enriched['has_settings'] ) ) {
			$enriched['has_settings'] = ! empty( $enriched['settings_page'] );
		}
		if ( ! isset( $enriched['keywords'] ) || ! is_array( $enriched['keywords'] ) ) {
			$enriched['keywords'] = [];
		}
		if ( ! isset( $enriched['type'] ) ) {
			$enriched['type'] = self::detect_type( $slug );
		}
		if ( ! isset( $enriched['needs'] ) || ! is_array( $enriched['needs'] ) ) {
			$enriched['needs'] = self::detect_needs( $slug );
		}

		self::$registry[ $slug ] = $enriched;
	}

	/**
	 * Retrieve all registered modules for the dashboard.
	 */
	public static function get_registered_modules(): array {
		return self::$registry;
	}

	// ── Group taxonomy + tier/group detection ────────────────────────────────

	/**
	 * Canonical group taxonomy. Slug → display label.
	 * Stable order — drives the sidebar nav.
	 *
	 * Source: `~/Code/Zorasi/roadmaps/specs/phase-0-module-filtering.md`
	 * (WPExtended-pattern adaptation for Zehoro Toolkit).
	 */
	public const GROUPS = [
		'editorial_blocks' => 'Editorial Blocks',
		'schema'           => 'Schema',
		'reading_ux'       => 'Reading & UX',
		'seo'              => 'SEO',
		'conversion'       => 'Conversion',
		'ai'               => 'AI Assistance',
		'workflow'         => 'Workflow',
		'admin'            => 'Admin & Plumbing',
		'other'            => 'Other',
	];

	/**
	 * Group labels, translated. The GROUPS values are constants (so they can't
	 * be wrapped in `__()` in place); this maps each key to a LITERAL `__()`
	 * call so the strings are extractable by the i18n tooling — and falls back
	 * to the English label for any future key not listed here.
	 *
	 * @return array<string,string>
	 */
	public static function group_labels(): array {
		$i18n = [
			'editorial_blocks' => __( 'Editorial Blocks', 'zehoro-toolkit' ),
			'schema'           => __( 'Schema', 'zehoro-toolkit' ),
			'reading_ux'       => __( 'Reading & UX', 'zehoro-toolkit' ),
			'seo'              => __( 'SEO', 'zehoro-toolkit' ),
			'conversion'       => __( 'Conversion', 'zehoro-toolkit' ),
			'ai'               => __( 'AI Assistance', 'zehoro-toolkit' ),
			'workflow'         => __( 'Workflow', 'zehoro-toolkit' ),
			'admin'            => __( 'Admin & Plumbing', 'zehoro-toolkit' ),
			'other'            => __( 'Other', 'zehoro-toolkit' ),
		];
		$out = [];
		foreach ( self::GROUPS as $k => $label ) {
			$out[ $k ] = $i18n[ $k ] ?? $label;
		}
		return $out;
	}

	// ── Buckets ─────────────────────────────────────────────────────────────────
	//
	// One level ABOVE the fine-grained groups. The Modules page splits the buckets
	// into two TIERS (see bucket_tier()):
	//   Core    — "The Loop": the CMS-agnostic content-business engine (what becomes
	//             the Laravel core). The daily driver.
	//   Surface — Blocks / Conversion / Utilities: the WordPress-specific rendering
	//             (a per-connector toolbox you'd strip for a Shopify/Ghost surface).
	// Order matters — The Loop leads. Utilities (key still 'toolkit') is the catch-all;
	// unknown groups fall here — watch it for junk-drawer creep.

	public const BUCKET_LOOP       = 'loop';
	public const BUCKET_BLOCKS     = 'blocks';
	public const BUCKET_CONVERSION = 'conversion';
	public const BUCKET_TOOLKIT    = 'toolkit'; // label: "Utilities"

	/** The Core tier — one bucket, rendered first + set apart from the Surface trio. */
	public const BUCKET_PRIMARY = self::BUCKET_LOOP;

	/** Tier keys — the two sections the buckets group under on the Modules page. */
	public const TIER_CORE    = 'core';
	public const TIER_SURFACE = 'surface';

	/**
	 * Bucket labels, in display order (The Loop first). Literal `__()` calls so the
	 * strings stay i18n-extractable.
	 *
	 * @return array<string,string>
	 */
	public static function bucket_labels(): array {
		return [
			self::BUCKET_LOOP       => __( 'The Loop', 'zehoro-toolkit' ),
			self::BUCKET_BLOCKS     => __( 'Blocks', 'zehoro-toolkit' ),
			self::BUCKET_CONVERSION => __( 'Conversion', 'zehoro-toolkit' ),
			self::BUCKET_TOOLKIT    => __( 'Utilities', 'zehoro-toolkit' ),
		];
	}

	/**
	 * Which tier a bucket sits under. The Loop is the Core (portable engine);
	 * everything else is the WordPress rendering Surface.
	 */
	public static function bucket_tier( string $bucket ): string {
		return $bucket === self::BUCKET_LOOP ? self::TIER_CORE : self::TIER_SURFACE;
	}

	/**
	 * Map a fine-grained group to its bucket. Unknown groups fall to Toolkit (the
	 * catch-all). Loop = the intelligence engines (SEO diagnostics + AI + the edit
	 * log that feeds the needle-measurement); Blocks = editorial blocks + their
	 * schema; Conversion = CTA/lead-capture; Toolkit = reader UX + plumbing + nav.
	 */
	public static function group_bucket( string $group ): string {
		static $map = [
			'seo'              => self::BUCKET_LOOP,
			'ai'               => self::BUCKET_LOOP,
			'workflow'         => self::BUCKET_LOOP,
			'editorial_blocks' => self::BUCKET_BLOCKS,
			'schema'           => self::BUCKET_BLOCKS,
			'conversion'       => self::BUCKET_CONVERSION,
			'reading_ux'       => self::BUCKET_TOOLKIT,
			'admin'            => self::BUCKET_TOOLKIT,
		];
		return $map[ $group ] ?? self::BUCKET_TOOLKIT;
	}

	/**
	 * Auto-detect tier from class namespace.
	 *   Zehoro\Modules\*       → free
	 *   Zehoro\Pro\Modules\*   → pro
	 *
	 * Modules can override by passing 'tier' explicitly to register_module().
	 */
	private static function detect_tier( string $class ): string {
		return ( strpos( $class, '\\Zehoro\\Pro\\' ) === 0 || strpos( $class, 'Zehoro\\Pro\\' ) === 0 )
			? 'pro'
			: 'free';
	}

	/**
	 * Map a module slug to its group. Hardcoded — modules can override
	 * by passing 'group' to register_module(). Unknown slugs map to 'other'.
	 */
	private static function detect_group( string $slug ): string {
		static $map = [
			// Editorial Blocks — content the author drops into posts.
			'tldr'              => 'editorial_blocks',
			'faq'               => 'editorial_blocks',
			'steps'             => 'editorial_blocks',
			'pros_cons'         => 'editorial_blocks',
			'callout'           => 'editorial_blocks',
			'testimonial'       => 'editorial_blocks',
			'author_box'        => 'editorial_blocks',
			'content_box'       => 'editorial_blocks',
			'stat_callout'      => 'editorial_blocks',
			'inline_product'    => 'editorial_blocks',
			'product_box'       => 'editorial_blocks',
			'review_box'        => 'editorial_blocks',
			'comparison_table'  => 'editorial_blocks',
			'versus_box'        => 'editorial_blocks',
			'product_roundup'   => 'editorial_blocks',
			'product_verdict'   => 'editorial_blocks',
			'coupon_box'        => 'editorial_blocks',

			// Schema — JSON-LD emitters.
			'article_schema'    => 'schema',
			'pros_cons_schema'  => 'schema',
			'faq_schema'        => 'schema',
			'steps_schema'      => 'schema',

			// Reading & UX — reader-facing freshness/trust + navigation.
			'table_of_contents' => 'reading_ux',
			'last_updated'      => 'reading_ux',
			'freshness_log'     => 'reading_ux',
			'disclosure'        => 'reading_ux',
			'disclaimer'        => 'reading_ux',

			// SEO — data + intelligence engines (the loop's diagnostics + maps).
			'entity_map'             => 'seo',
			'google_search_console'  => 'seo',
			'ctr_rescue'             => 'seo',
			'cannibalisation_check'  => 'seo',
			'refresh_trigger'        => 'seo',
			'orphan_check'           => 'seo',
			'topical_gap'            => 'seo',
			'entity_index'           => 'seo',
			'dataforseo'             => 'seo',
			'ga4'                    => 'seo',
			'edit_log'               => 'seo', // a loop organ — records the edits the needle-measurement reads

			// AI Assistance — AI-driven analysis / rewriting.
			'ai_visibility'          => 'ai',
			'rewrite_context'        => 'ai',

			// Conversion — CTA / lead capture.
			'content_stream'    => 'conversion',
			'inline_subscribe'  => 'conversion',
			'sticky_bar'        => 'conversion',
			'cta_swap'          => 'conversion',

			// Admin & Plumbing — operational + front-end nav UX (all → Toolkit bucket).
			'css_auditor'       => 'admin',
			'archive_cleanup'   => 'admin',
			'rss_support'       => 'admin',
			'styles'            => 'admin',
			'category_pills'    => 'admin', // front-end nav UX, not a loop diagnostic
			'home_filter_pills' => 'admin', // front-end nav UX, not a loop diagnostic
		];
		return $map[ $slug ] ?? 'other';
	}

	/**
	 * Module "type" for the card's type pill:
	 *   block  → a Gutenberg block the author inserts,
	 *   tool   → an admin analysis / dashboard screen,
	 *   module → an automatic behavior (the default; left unbadged to avoid noise).
	 * Override by passing 'type' to register_module().
	 */
	private static function detect_type( string $slug ): string {
		static $blocks = [
			'callout', 'faq', 'pros_cons', 'stat_callout', 'steps', 'testimonial',
			'tldr', 'inline_product', 'comparison_table', 'coupon_box', 'product_box',
			'product_roundup', 'product_verdict', 'review_box', 'versus_box',
		];
		static $tools = [
			'topical_gap', 'ctr_rescue', 'cannibalisation_check', 'refresh_trigger',
			'orphan_check', 'google_search_console', 'ai_visibility', 'css_auditor',
		];
		if ( in_array( $slug, $blocks, true ) ) return 'block';
		if ( in_array( $slug, $tools, true ) )  return 'tool';
		return 'module';
	}

	/**
	 * External capabilities a module needs, for the card's capability pills:
	 *   ai  → calls a BYOK LLM (or the MCP/agent lane),
	 *   gsc → needs Google Search Console connected.
	 * Override by passing 'needs' to register_module().
	 *
	 * @return string[]
	 */
	private static function detect_needs( string $slug ): array {
		static $ai  = [ 'rewrite_context', 'ai_visibility', 'entity_map' ];
		static $gsc = [ 'ctr_rescue', 'cannibalisation_check', 'refresh_trigger', 'orphan_check', 'google_search_console' ];
		$needs = [];
		if ( in_array( $slug, $ai,  true ) ) $needs[] = 'ai';
		if ( in_array( $slug, $gsc, true ) ) $needs[] = 'gsc';
		return $needs;
	}

	public function init(): void {
		// 1. Auto-discover and register all modules
		$dir = __DIR__ . '/../Modules/';
		foreach ( glob( $dir . '*.php' ) as $file ) {
			$class = '\\Zehoro\\Modules\\' . basename( $file, '.php' );
			if ( method_exists( $class, 'register' ) ) {
				$class::register();
			}
		}

		// 2. Fetch active settings — canonical key is zehoro_active_modules
		// with automatic fallback to legacy lkst_active_modules during the
		// rename transition window (see src/Migration/ZehoroRenameMigrator).
		$default_active = array_keys( array_filter( self::$registry, function( $m ) { return ! empty( $m['default'] ); } ) );
		$active = \Zehoro\Utils\Option::get( 'zehoro_active_modules', $default_active );

		// Admin: init dashboard
		if ( is_admin() ) {
			$admin = new \Zehoro\Admin\Dashboard( $active );
			$admin->init();

			// SEO-plugin coexistence notice — surfaces the schema stand-down +
			// the manual override when a dedicated SEO plugin is detected.
			if ( class_exists( '\\Zehoro\\Admin\\SeoCoexistenceNotice' ) ) {
				( new \Zehoro\Admin\SeoCoexistenceNotice() )->init();
			}
		}

		// REST endpoints — registered regardless of is_admin since they're
		// called by JS from the admin UI (which runs server-side in admin,
		// hits REST as cross-context HTTP).
		add_action( 'rest_api_init', function() {
			( new \Zehoro\REST\ModulesController() )->register_routes();
		} );

		// Frontend assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Register custom block category
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ], 10, 2 );

		// 3. Initialise active modules
		foreach ( self::$registry as $slug => $data ) {
			if ( in_array( $slug, $active, true ) ) {
				$class = $data['class'];
				if ( class_exists( $class ) && is_subclass_of( $class, ModuleInterface::class ) ) {
					$module = new $class();
					$module->init();
					$this->modules[ $slug ] = $module;
				}
			}
		}
	}

	public function register_block_category( $categories, $post ) {
		return array_merge(
			[
				[
					'slug'  => 'zehoro-toolkit',
					'title' => __( 'Zehoro Toolkit', 'zehoro-toolkit' ),
					'icon'  => 'admin-tools',
				],
			],
			$categories
		);
	}

	/**
	 * Return a live module instance (or null if not active).
	 */
	public function get_module( string $slug ): ?ModuleInterface {
		return $this->modules[ $slug ] ?? null;
	}

	public function enqueue_assets(): void {
		// Do not load on page-builder canvas previews.
		if ( isset( $_GET['bricks'] ) || isset( $_GET['etchwp'] ) || isset( $_GET['elementor-preview'] ) ) return;

		// Gate the 27 KB monolithic stylesheet (+ inline CSS variables) to
		// singular views that actually reference toolkit content. The CSS
		// variables target lkst-/zehoro- classed elements only; pages with
		// none of our blocks/shortcodes/classes get neither the file nor
		// the variables — and need neither.
		$load_global = is_singular() && \Zehoro\Utils\PageContent::has_zehoro_content();

		/**
		 * Force-load the global stylesheet on the current request.
		 *
		 * Themes that ship Zehoro-styled HTML outside of post_content
		 * (e.g. a Bricks header that uses `lkst-` classes) can opt in via
		 * `add_filter( 'zehoro/load_global_styles', '__return_true' )`.
		 *
		 * @param bool $load Default: true iff is_singular() and content references zehoro/lkst.
		 */
		$load_global = (bool) apply_filters( 'zehoro/load_global_styles', $load_global );

		if ( $load_global ) {
			wp_enqueue_style( 'zehoro-toolkit', ZEHORO_URL . 'assets/style.css', [], ZEHORO_VERSION );

			$primary   = \Zehoro\Utils\Option::get( 'zehoro_color_primary',          '#E8A020' );
			$contrast  = \Zehoro\Utils\Option::get( 'zehoro_color_primary_contrast', '#0F1A2E' );
			$secondary = \Zehoro\Utils\Option::get( 'zehoro_color_secondary',        '#1ECFC4' );
			$bg_dark   = \Zehoro\Utils\Option::get( 'zehoro_color_bg_dark',          '#0F1A2E' );
			$bg_light  = \Zehoro\Utils\Option::get( 'zehoro_color_bg_light',         '#F5F0E8' );
			wp_add_inline_style( 'zehoro-toolkit', sprintf(
				':root{--lkst-primary-color:%s;--lkst-primary-contrast:%s;--lkst-secondary-color:%s;--lkst-bg-dark:%s;--lkst-bg-light:%s;}',
				esc_attr( $primary ), esc_attr( $contrast ), esc_attr( $secondary ),
				esc_attr( $bg_dark ), esc_attr( $bg_light )
			) );
		}

		// toc.js enqueue now lives in TableOfContents::maybe_enqueue_toc_js(),
		// gated on "a TOC will actually render" (auto-inject OR shortcode) —
		// the shortcode-only check here missed every auto-injected TOC, which
		// then rendered styled but couldn't open.
	}

	public static function activate(): void {
		// Initialize to scan default modules on activation
		$dir = __DIR__ . '/../Modules/';
		foreach ( glob( $dir . '*.php' ) as $file ) {
			$class = '\\Zehoro\\Modules\\' . basename( $file, '.php' );
			if ( method_exists( $class, 'register' ) ) {
				$class::register();
			}
		}
		$default_active = array_keys( array_filter( self::$registry, function( $m ) { return ! empty( $m['default'] ); } ) );
		
		// Activation seeds the canonical key. If the site had a legacy
		// lkst_active_modules, the rename migrator (run earlier in the
		// activation hook) already copied it across — add_option is a no-op
		// on the existing-data path.
		add_option( 'zehoro_active_modules', $default_active );
	}

	public static function deactivate(): void {
		global $wpdb;
		// Deactivation transient cleanup covers both legacy (lkst_) and
		// canonical (zehoro_) prefixes during the rename transition.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lkst_%' OR option_name LIKE '_transient_timeout_lkst_%' OR option_name LIKE '_transient_zehoro_%' OR option_name LIKE '_transient_timeout_zehoro_%'" );
		flush_rewrite_rules();
	}
}