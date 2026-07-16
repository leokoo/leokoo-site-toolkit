<?php
/**
 * Server-side render for zehoro/evaluation.
 *
 * Thin delegate to the render seam Zehoro\Modules\Evaluation::render_block().
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused (dynamic block).
 * @var WP_Block $block      Block instance.
 *
 * @package Zehoro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zehoro_eval_wrapper = get_block_wrapper_attributes( [ 'class' => 'zehoro-eval' ] );

echo \Zehoro\Modules\Evaluation::render_block( $attributes, $zehoro_eval_wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
