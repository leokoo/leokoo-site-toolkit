<?php
/**
 * Server-side render for zehoro/faq.
 *
 * Thin delegate to the render seam Zehoro\Modules\FAQ::render_container().
 * $content is the rendered faq-item inner blocks.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks (the FAQ items).
 * @var WP_Block $block      Block instance.
 *
 * @package Zehoro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zehoro_faq_wrapper = get_block_wrapper_attributes( [ 'class' => 'zehoro-faq' ] );

echo \Zehoro\Modules\FAQ::render_container( $attributes, $content, $zehoro_faq_wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
