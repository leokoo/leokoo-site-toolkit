<?php
/**
 * Server-side render for zehoro/faq-item.
 *
 * Thin delegate to the render seam Zehoro\Modules\FAQ::render_item(). $content
 * is the rendered answer inner blocks (already-escaped core-block output).
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks (the answer).
 * @var WP_Block $block      Block instance.
 *
 * @package Zehoro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zehoro_faq_item_wrapper = get_block_wrapper_attributes( [ 'class' => 'zehoro-faq__item' ] );

echo \Zehoro\Modules\FAQ::render_item( $attributes, $content, $zehoro_faq_item_wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
