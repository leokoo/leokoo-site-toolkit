<?php
/**
 * Key Takeaways — an answer-first summary block for the top of a post.
 *
 * The block is dynamic: its JS save() returns null and ALL front-end markup is
 * produced by the single server-side seam {@see self::render_html()}. Keeping
 * one render function is deliberate — it is the interception point the future
 * connected/smart version hooks, and it lets the legacy `lkst/tldr` safety net
 * re-render old content through exactly the same markup.
 *
 * History: this supersedes the `lkst/tldr` block (module slug `tldr`). The
 * block name changed from `lkst/tldr` to `zehoro/key-takeaways`; existing
 * content is preserved by {@see self::legacy_render_safety_net()} (renders any
 * stray old block through the new seam) and migrated permanently by
 * `wp zehoro migrate-blocks`. Block-name stability is otherwise sacred.
 *
 * @package Zehoro\Modules
 */

namespace Zehoro\Modules;

use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KeyTakeaways implements ModuleInterface {

	/** Legacy block name this module replaces. */
	private const LEGACY_BLOCK = 'lkst/tldr';

	/**
	 * The retired lkst/tldr block defaulted its heading to "Key Takeaways"
	 * (title case). Gutenberg omits default-valued attributes, so a legacy block
	 * that used the default has no stored heading — the legacy paths fall back to
	 * this old default rather than the new sentence-case one, so upgraded content
	 * does not silently change casing.
	 */
	public const LEGACY_DEFAULT_HEADING = 'Key Takeaways';

	public static function register(): void {
		Plugin::register_module( 'key_takeaways', self::class, [
			'title'   => 'Key Takeaways',
			'desc'    => 'An answer-first summary box — scannable bullets or a short TL;DR — for the top of your articles.',
			'default' => true,
		] );
	}

	public function init(): void {
		add_action( 'init', [ $this, 'register_block' ] );
		// Belt-and-suspenders: re-render any un-migrated legacy block through
		// the new seam so it never appears unstyled or as an editor error.
		add_filter( 'render_block', [ $this, 'legacy_render_safety_net' ], 10, 2 );
		// Contribute this module's rename to the block migrator registry.
		add_filter( 'zehoro/block_migrations', [ self::class, 'register_migration' ] );

		\Zehoro\Cli\MigrateBlocksCommand::register();
	}

	/** Register this module's legacy block rename with the migrator. */
	public static function register_migration( array $renames ): array {
		$renames[ self::LEGACY_BLOCK ] = [ self::class, 'migrate_legacy_block' ];
		return $renames;
	}

	/**
	 * Migrator handler for a legacy lkst/tldr block. Returns the replacement
	 * zehoro/key-takeaways block, or null when the content has block-level
	 * structure the constrained block would flatten (left as-is; the safety net
	 * renders it losslessly — never a silent lossy rewrite).
	 */
	public static function migrate_legacy_block( array $block ): ?array {
		$attrs   = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : [];
		$heading = isset( $attrs['heading'] ) ? (string) $attrs['heading'] : '';
		$content = self::extract_legacy_content( (string) ( $block['innerHTML'] ?? '' ) );
		if ( $content === '' && isset( $attrs['content'] ) ) {
			$content = (string) $attrs['content'];
		}

		$plan = self::plan_conversion( $content );
		if ( $plan === null ) {
			return null;
		}
		// Preserve the legacy default heading casing when the old block used the default.
		$plan['heading'] = ( $heading !== '' ) ? $heading : self::LEGACY_DEFAULT_HEADING;

		return [
			'blockName'    => 'zehoro/key-takeaways',
			'attrs'        => $plan,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * Decide how to carry legacy content into the new block WITHOUT losing
	 * structure. Returns new-block attrs (mode + text) for clean inline content;
	 * null when the content has block-level structure (caller leaves it as-is).
	 */
	private static function plan_conversion( string $content ): ?array {
		$content = trim( $content );
		if ( $content === '' ) {
			return null;
		}
		if ( preg_match( '#^<p\b[^>]*>(.*)</p>$#is', $content, $m ) ) {
			$inner = $m[1];
			return self::has_block_tags( $inner ) ? null : [ 'mode' => 'paragraph', 'text' => $inner ];
		}
		if ( self::has_block_tags( $content ) ) {
			return null;
		}
		return [ 'mode' => 'paragraph', 'text' => $content ];
	}

	private static function has_block_tags( string $html ): bool {
		// `img` is included so an image-bearing box is left as-is (the paragraph
		// path's inline allowlist would drop it); the safety net renders it.
		return (bool) preg_match( '#<\s*(p|div|ul|ol|li|h[1-6]|blockquote|table|figure|section|article|pre|hr|img)\b#i', $html );
	}

	public function register_block(): void {
		register_block_type( ZEHORO_DIR . 'build/key-takeaways' );
	}

	/**
	 * The single render seam. All front-end output for this block — new blocks
	 * and legacy ones — flows through here.
	 *
	 * @param array  $attributes         Block attributes.
	 * @param string $wrapper_attributes Pre-computed wrapper attribute string
	 *                                   (from get_block_wrapper_attributes()).
	 *                                   Empty on the safety-net path, where a
	 *                                   plain class is used instead.
	 */
	public static function render_html( array $attributes, string $wrapper_attributes = '' ): string {
		$mode = isset( $attributes['mode'] ) ? (string) $attributes['mode'] : 'list';
		if ( ! in_array( $mode, [ 'list', 'paragraph', 'rich' ], true ) ) {
			$mode = 'list';
		}

		// Build the body first — an empty block renders nothing at all rather
		// than an empty box (better for a11y and the document outline).
		$body = '';
		if ( $mode === 'rich' ) {
			// Legacy/lossless path (safety net only — never persisted by the
			// editor or migrator): preserve block-level structure from a retired
			// lkst/tldr box rather than flatten it into one inline paragraph.
			// Media-only content counts as visible so it renders through the seam
			// instead of falling back to the raw (unstyled) legacy markup.
			$rich = \Zehoro\Utils\BlockSanitize::rich( isset( $attributes['text'] ) ? (string) $attributes['text'] : '' );
			if ( self::has_rich_content( $rich ) ) {
				$body = '<div class="zehoro-key-takeaways__summary">' . $rich . '</div>';
			}
		} elseif ( $mode === 'paragraph' ) {
			$text = \Zehoro\Utils\BlockSanitize::inline( isset( $attributes['text'] ) ? (string) $attributes['text'] : '' );
			if ( self::has_visible_text( $text ) ) {
				$body = '<p class="zehoro-key-takeaways__summary">' . $text . '</p>';
			}
		} else {
			$items = \Zehoro\Utils\BlockSanitize::list_items( isset( $attributes['items'] ) ? (string) $attributes['items'] : '' );
			if ( self::has_visible_text( $items ) ) {
				$body = '<ul class="zehoro-key-takeaways__list">' . $items . '</ul>';
			}
		}

		if ( $body === '' ) {
			return '';
		}

		// Heading (plain text — the editor disallows inline formats here).
		$level = isset( $attributes['headingLevel'] ) ? (int) $attributes['headingLevel'] : 2;
		if ( $level < 2 || $level > 4 ) {
			$level = 2;
		}
		$heading = \Zehoro\Utils\BlockSanitize::plain_text( isset( $attributes['heading'] ) ? (string) $attributes['heading'] : '' );
		if ( $heading === '' ) {
			$heading = __( 'Key takeaways', 'zehoro-toolkit' );
		}
		$title = sprintf(
			'<h%1$d class="zehoro-key-takeaways__title">%2$s</h%1$d>',
			$level,
			esc_html( $heading )
		);

		if ( $wrapper_attributes === '' ) {
			$wrapper_attributes = 'class="zehoro-key-takeaways"';
		}

		return sprintf( '<section %s>%s%s</section>', $wrapper_attributes, $title, $body );
	}

	/**
	 * Safety net for content still saved as the legacy `lkst/tldr` block.
	 *
	 * The old block was static (its markup lives in post_content) and its source
	 * is retired, so without this filter an un-migrated block would render with
	 * dead `lkst-*` classes (now unstyled). We detect it, lift the authored
	 * heading + content, and re-render through the seam. The permanent fix is
	 * `wp zehoro migrate-blocks`; this guarantees correctness until it is run.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block array.
	 */
	public function legacy_render_safety_net( $block_content, $block ) {
		if ( ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== self::LEGACY_BLOCK ) {
			return $block_content;
		}

		$attrs   = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : [];
		$heading = ( isset( $attrs['heading'] ) && $attrs['heading'] !== '' )
			? (string) $attrs['heading']
			: self::LEGACY_DEFAULT_HEADING;

		$content = self::extract_legacy_content( is_string( $block_content ) ? $block_content : '' );
		if ( $content === '' && isset( $attrs['content'] ) ) {
			$content = (string) $attrs['content'];
		}

		$html = self::render_html( [
			'heading'      => $heading,
			'headingLevel' => 2,
			// 'rich' preserves the legacy box's block-level structure (multiple
			// paragraphs / lists) losslessly — the inline-only paragraph path
			// would collapse them into one run-on line.
			'mode'         => 'rich',
			'text'         => $content,
		] );

		if ( $html === '' ) {
			return $block_content;
		}

		// This block isn't in the parsed content on this path, so its style
		// handle isn't auto-enqueued — force it on.
		$handle = 'zehoro-key-takeaways-style';
		if ( wp_style_is( $handle, 'registered' ) && ! wp_style_is( $handle, 'enqueued' ) ) {
			wp_enqueue_style( $handle );
		}

		return $html;
	}

	/**
	 * Pull the inner HTML of a legacy `.lkst-tldr-content` node out of saved
	 * block markup. Falls back to the whole fragment when the node is absent.
	 * Public so the CLI migrator maps content identically.
	 */
	public static function extract_legacy_content( string $html ): string {
		if ( trim( $html ) === '' || ! class_exists( 'DOMDocument' ) ) {
			return '';
		}

		$doc  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<?xml encoding="utf-8"?><div id="zehoro-kt-root">' . $html . '</div>',
			LIBXML_NOERROR | LIBXML_NOWARNING
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$xpath  = new \DOMXPath( $doc );
		$target = $xpath->query(
			"//*[contains(concat(' ', normalize-space(@class), ' '), ' lkst-tldr-content ')]"
		);
		$node = ( $target && $target->length ) ? $target->item( 0 ) : null;
		if ( ! $node ) {
			// No known content node — fall back to the wrapper minus the legacy
			// heading, so we never duplicate the heading in the new shell.
			$root = $xpath->query( "//*[@id='zehoro-kt-root']" )->item( 0 );
			if ( ! $root ) {
				return '';
			}
			foreach ( $xpath->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' lkst-tldr-heading ')]", $root ) as $h ) {
				$h->parentNode->removeChild( $h );
			}
			$node = $root;
		}

		$inner = '';
		foreach ( $node->childNodes as $child ) {
			$inner .= $doc->saveHTML( $child );
		}

		return trim( $inner );
	}

	// -------------------------------------------------------------------------
	// Sanitization
	// -------------------------------------------------------------------------

	private static function has_visible_text( string $html ): bool {
		return trim( wp_strip_all_tags( $html ) ) !== '';
	}

	/** Visible text OR embedded media — used by the rich (legacy) branch. */
	private static function has_rich_content( string $html ): bool {
		return self::has_visible_text( $html )
			|| (bool) preg_match( '#<(img|figure|iframe|video|audio)\b#i', $html );
	}

}
