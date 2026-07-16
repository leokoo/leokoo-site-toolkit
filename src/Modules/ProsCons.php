<?php
/**
 * Pros & Cons — a scannable two-column (or single-list) box for reviews.
 *
 * Consolidates the retired 3-block set into ONE dynamic block:
 *   - lkst/pros-cons  (an InnerBlocks container of lkst/pros + lkst/cons)
 *   - lkst/pros       (standalone)
 *   - lkst/cons       (standalone)
 * → zehoro/pros-cons with a `show` toggle (both | pros | cons).
 *
 * Dynamic block: JS save() returns null and ALL front-end markup comes from the
 * single seam {@see self::render_html()} — also used by the legacy safety net.
 *
 * Backward-compat mirrors Key Takeaways:
 *   - a `render_block` safety net renders any un-migrated legacy block through
 *     the seam (never dead lkst-* markup),
 *   - migrator handlers (via the `zehoro/block_migrations` registry) convert the
 *     3 legacy blocks — the container by unpacking its inner pros/cons.
 * (The editor bridges that keep old blocks valid in Gutenberg live in the block
 * source, src/blocks/pros-cons/index.js.)
 *
 * @package Zehoro\Modules
 */

namespace Zehoro\Modules;

use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProsCons implements ModuleInterface {

	private const LEGACY_CONTAINER = 'lkst/pros-cons';
	private const LEGACY_PROS      = 'lkst/pros';
	private const LEGACY_CONS      = 'lkst/cons';

	public static function register(): void {
		Plugin::register_module( 'pros_cons', self::class, [
			'title'   => 'Pros & Cons',
			'desc'    => 'A scannable two-column Pros & Cons box for reviews and comparisons (or a single Pros or Cons list).',
			'default' => true,
		] );
	}

	public function init(): void {
		add_action( 'init', [ $this, 'register_block' ] );
		add_filter( 'render_block', [ $this, 'legacy_render_safety_net' ], 10, 2 );
		add_filter( 'zehoro/block_migrations', [ self::class, 'register_migration' ] );

		\Zehoro\Cli\MigrateBlocksCommand::register();
	}

	public function register_block(): void {
		register_block_type( ZEHORO_DIR . 'build/pros-cons' );
	}

	// -------------------------------------------------------------------------
	// The single render seam
	// -------------------------------------------------------------------------

	public static function render_html( array $attributes, string $wrapper_attributes = '' ): string {
		$show = isset( $attributes['show'] ) ? (string) $attributes['show'] : 'both';
		if ( ! in_array( $show, [ 'both', 'pros', 'cons' ], true ) ) {
			$show = 'both';
		}

		$level = isset( $attributes['headingLevel'] ) ? (int) $attributes['headingLevel'] : 3;
		if ( $level < 2 || $level > 4 ) {
			$level = 3;
		}

		$cols = '';
		if ( $show !== 'cons' ) {
			$cols .= self::render_col( 'pros', $attributes['prosTitle'] ?? '', $attributes['pros'] ?? '', $level );
		}
		if ( $show !== 'pros' ) {
			$cols .= self::render_col( 'cons', $attributes['consTitle'] ?? '', $attributes['cons'] ?? '', $level );
		}

		if ( $cols === '' ) {
			return ''; // nothing to show → render nothing
		}

		if ( $wrapper_attributes === '' ) {
			$wrapper_attributes = 'class="zehoro-pros-cons zehoro-pros-cons--' . $show . '"';
		}

		return sprintf( '<div %s>%s</div>', $wrapper_attributes, $cols );
	}

	private static function render_col( string $variant, $title, $items, int $level ): string {
		$items = self::sanitize_list( (string) $items );
		if ( trim( wp_strip_all_tags( $items ) ) === '' ) {
			return ''; // empty column renders nothing
		}

		$title = trim( wp_strip_all_tags( (string) $title ) );
		if ( $title === '' ) {
			$title = ( $variant === 'pros' )
				? __( 'Pros', 'zehoro-toolkit' )
				: __( 'Cons', 'zehoro-toolkit' );
		}

		return sprintf(
			'<div class="zehoro-pros-cons__col zehoro-pros-cons__%1$s"><h%2$d class="zehoro-pros-cons__title">%3$s</h%2$d><ul class="zehoro-pros-cons__list">%4$s</ul></div>',
			$variant,
			$level,
			esc_html( $title ),
			$items
		);
	}

	// -------------------------------------------------------------------------
	// Legacy render safety net
	// -------------------------------------------------------------------------

	public function legacy_render_safety_net( $block_content, $block ) {
		if ( ! is_array( $block ) ) {
			return $block_content;
		}
		$name    = $block['blockName'] ?? '';
		$content = is_string( $block_content ) ? $block_content : '';

		if ( $name === self::LEGACY_PROS ) {
			return self::rerender_single( $content, 'pros', 'lkst-pros-list', $block_content );
		}
		if ( $name === self::LEGACY_CONS ) {
			return self::rerender_single( $content, 'cons', 'lkst-cons-list', $block_content );
		}
		if ( $name === self::LEGACY_CONTAINER ) {
			// Inner lkst/pros + lkst/cons are rewritten by their own passes; strip
			// this container's dead wrapper so no lkst-* markup leaks.
			$inner = self::inner_of_class( $content, 'lkst-pros-cons-wrapper' );
			return $inner !== '' ? $inner : $block_content;
		}

		return $block_content;
	}

	private static function rerender_single( string $content, string $variant, string $list_class, $fallback ): string {
		$items = self::inner_of_class( $content, $list_class );
		if ( trim( wp_strip_all_tags( $items ) ) === '' ) {
			return $fallback; // nothing extractable — leave original rather than blank it
		}

		self::enqueue_style();

		// The old heading was static boilerplate ("✅ Pros"); let the new block
		// default the title. Carry only the list content + show mode.
		$html = self::render_html( [ 'show' => $variant, $variant => $items ] );
		return $html !== '' ? $html : $fallback;
	}

	// -------------------------------------------------------------------------
	// Migrator handlers (registered via zehoro/block_migrations)
	// -------------------------------------------------------------------------

	public static function register_migration( array $renames ): array {
		$renames[ self::LEGACY_PROS ]      = [ self::class, 'migrate_pros' ];
		$renames[ self::LEGACY_CONS ]      = [ self::class, 'migrate_cons' ];
		$renames[ self::LEGACY_CONTAINER ] = [ self::class, 'migrate_container' ];
		return $renames;
	}

	public static function migrate_pros( array $block ): ?array {
		return self::migrate_single( $block, 'pros', 'lkst-pros-list' );
	}

	public static function migrate_cons( array $block ): ?array {
		return self::migrate_single( $block, 'cons', 'lkst-cons-list' );
	}

	private static function migrate_single( array $block, string $variant, string $list_class ): ?array {
		$items = self::inner_of_class( (string) ( $block['innerHTML'] ?? '' ), $list_class );
		if ( trim( wp_strip_all_tags( $items ) ) === '' ) {
			return null; // empty → leave as-is (no gratuitous rewrite)
		}

		// The old heading was static boilerplate; let the new block default the title.
		return self::new_block( [ 'show' => $variant, $variant => self::sanitize_list( $items ) ] );
	}

	public static function migrate_container( array $block ): ?array {
		$attrs = [ 'show' => 'both' ];

		foreach ( ( $block['innerBlocks'] ?? [] ) as $inner ) {
			$inner_name = $inner['blockName'] ?? '';
			$inner_html = (string) ( $inner['innerHTML'] ?? '' );

			if ( $inner_name === self::LEGACY_PROS ) {
				$items = self::inner_of_class( $inner_html, 'lkst-pros-list' );
				if ( trim( wp_strip_all_tags( $items ) ) !== '' ) {
					$attrs['pros'] = self::sanitize_list( $items );
				}
			} elseif ( $inner_name === self::LEGACY_CONS ) {
				$items = self::inner_of_class( $inner_html, 'lkst-cons-list' );
				if ( trim( wp_strip_all_tags( $items ) ) !== '' ) {
					$attrs['cons'] = self::sanitize_list( $items );
				}
			}
		}

		if ( empty( $attrs['pros'] ) && empty( $attrs['cons'] ) ) {
			return null; // nothing to carry across
		}

		return self::new_block( $attrs );
	}

	private static function new_block( array $attrs ): array {
		return [
			'blockName'    => 'zehoro/pros-cons',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** Inner HTML of the first element carrying $class (DOM, robust to malformed input). */
	public static function inner_of_class( string $html, string $class ): string {
		if ( trim( $html ) === '' || ! class_exists( 'DOMDocument' ) ) {
			return '';
		}

		$doc  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<?xml encoding="utf-8"?><div id="zehoro-pc-root">' . $html . '</div>',
			LIBXML_NOERROR | LIBXML_NOWARNING
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$xpath = new \DOMXPath( $doc );
		$nodes = $xpath->query(
			"//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')]"
		);
		if ( ! $nodes || ! $nodes->length ) {
			return '';
		}

		$inner = '';
		foreach ( $nodes->item( 0 )->childNodes as $child ) {
			$inner .= $doc->saveHTML( $child );
		}

		return trim( $inner );
	}

	private static function enqueue_style(): void {
		$handle = 'zehoro-pros-cons-style';
		if ( wp_style_is( $handle, 'registered' ) && ! wp_style_is( $handle, 'enqueued' ) ) {
			wp_enqueue_style( $handle );
		}
	}

	private static function sanitize_list( string $html ): string {
		$allowed       = self::allowed_inline();
		$allowed['li'] = [ 'class' => true ];
		return wp_kses( $html, $allowed );
	}

	private static function allowed_inline(): array {
		// No `target` on <a> — a dynamic block's output never passes through
		// wp_targeted_link_rel, so target="_blank" would render without noopener.
		return [
			'a'      => [ 'href' => true, 'title' => true, 'rel' => true ],
			'strong' => [],
			'b'      => [],
			'em'     => [],
			'i'      => [],
			'code'   => [],
			'mark'   => [],
			'sub'    => [],
			'sup'    => [],
			's'      => [],
			'del'    => [],
			'ins'    => [],
			'br'     => [],
			'span'   => [ 'class' => true ],
		];
	}
}
