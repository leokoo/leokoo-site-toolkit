<?php
/**
 * Server-side render for zehoro/key-takeaways.
 *
 * Thin delegate: every path flows through the single render seam
 * Zehoro\Modules\KeyTakeaways::render_html() (also used by the legacy
 * lkst/tldr safety net), so the future connected/smart version can intercept
 * the source in one place without a block deprecation.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content (unused — dynamic block).
 * @var WP_Block $block      Block instance.
 *
 * @package Zehoro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zehoro_kt_wrapper = get_block_wrapper_attributes( [ 'class' => 'zehoro-key-takeaways' ] );

// render_html escapes/sanitizes every field internally (esc_html + wp_kses).
echo \Zehoro\Modules\KeyTakeaways::render_html( $attributes, $zehoro_kt_wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
