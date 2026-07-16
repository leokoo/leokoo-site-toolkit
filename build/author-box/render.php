<?php
/**
 * Server-side render for zehoro/author-box.
 *
 * Thin delegate to the render seam Zehoro\Modules\AuthorBox::render_block().
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

$zehoro_ab_mode    = ( ( $attributes['mode'] ?? 'person' ) === 'organization' ) ? 'organization' : 'person';
$zehoro_ab_wrapper = get_block_wrapper_attributes(
	[ 'class' => 'zehoro-author-box zehoro-author-box--' . $zehoro_ab_mode ]
);

echo \Zehoro\Modules\AuthorBox::render_block( $attributes, $zehoro_ab_wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
