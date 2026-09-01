<?php
/**
 * Shared wp_kses allowlists for the editorial blocks' render seams.
 *
 * One canonical security boundary so a tightening/extension lands in every
 * block at once (the per-module copies had already started to drift). Used by
 * KeyTakeaways, ProsCons, and the blocks that follow.
 *
 * @package Zehoro\Utils
 */

namespace Zehoro\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BlockSanitize {

	/**
	 * Plain text from a RichText/HTML value, ready to be esc_html()'d for output.
	 *
	 * RichText serializes its value entity-encoded (`&` → `&amp;`), so a naive
	 * wp_strip_all_tags() + esc_html() double-encodes ("Terms & Conditions" →
	 * "Terms &amp;amp; Conditions" → renders literally). Strip tags, THEN decode
	 * entities, so the single esc_html() at the output site encodes exactly once.
	 */
	public static function plain_text( string $html ): string {
		$html = preg_replace( '#<br\s*/?>#i', ' ', $html ); // soft line breaks → space, not a join
		// Explicit UTF-8 — PHP < 8.1 defaults html_entity_decode() to ISO-8859-1,
		// which mojibakes &mdash;/&hellip;/&nbsp; on the plugin's 7.4 floor.
		return trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/** Inline author formatting. wp_kses on the inline allowlist. */
	public static function inline( string $html ): string {
		return wp_kses( $html, self::allowed_inline() );
	}

	/** A list body: inline formatting + <li>. */
	public static function list_items( string $html ): string {
		$allowed       = self::allowed_inline();
		$allowed['li'] = [ 'class' => true ];
		return wp_kses( $html, $allowed );
	}

	/** Block-level content — the lossless legacy path only (multiple paragraphs, lists, media). */
	public static function rich( string $html ): string {
		$allowed               = self::allowed_inline();
		$allowed['p']          = [];
		$allowed['ul']         = [ 'class' => true ];
		$allowed['ol']         = [ 'class' => true ];
		$allowed['li']         = [ 'class' => true ];
		$allowed['h3']         = [];
		$allowed['h4']         = [];
		$allowed['h5']         = [];
		$allowed['h6']         = [];
		$allowed['blockquote'] = [];
		$allowed['img']        = [ 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'loading' => true, 'class' => true ];
		return wp_kses( $html, $allowed );
	}

	/**
	 * The inline allowlist. NO `target` on <a> — a dynamic block's output never
	 * passes through wp_targeted_link_rel, so target="_blank" would render
	 * without rel="noopener" (reverse-tabnabbing). An editorial summary does not
	 * need new-tab links; keep the surface closed.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function allowed_inline(): array {
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
