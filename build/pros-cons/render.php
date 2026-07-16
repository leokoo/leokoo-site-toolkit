<?php
/**
 * Server-side render for zehoro/pros-cons.
 *
 * Thin delegate to the single render seam Zehoro\Modules\ProsCons::render_html()
 * (also used by the legacy safety net), so the future connected/smart version
 * can intercept the source in one place.
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

$zehoro_pc_show    = isset( $attributes['show'] ) ? (string) $attributes['show'] : 'both';
$zehoro_pc_wrapper = get_block_wrapper_attributes(
	[ 'class' => 'zehoro-pros-cons zehoro-pros-cons--' . preg_replace( '/[^a-z]/', '', $zehoro_pc_show ) ]
);

// render_html escapes/sanitizes every field internally.
echo \Zehoro\Modules\ProsCons::render_html( $attributes, $zehoro_pc_wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
