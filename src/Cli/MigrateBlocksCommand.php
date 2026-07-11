<?php
/**
 * WP-CLI: migrate legacy block names in post_content.
 *
 * Currently handles the `lkst/tldr` → `zehoro/key-takeaways` rename. The static
 * legacy block is rewritten into the new dynamic, self-closing block with its
 * heading + content carried across (content mapped identically to the render
 * safety net, via KeyTakeaways::extract_legacy_content()).
 *
 * Safe by default: a plain `wp zehoro migrate-blocks` is a DRY RUN and changes
 * nothing. Pass `--execute` to write.
 *
 * @package Zehoro\Cli
 */

namespace Zehoro\Cli;

use Zehoro\Modules\KeyTakeaways;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MigrateBlocksCommand {

	/** The legacy block name this command migrates away from. */
	private const LEGACY = 'lkst/tldr';

	public static function register(): void {
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'zehoro migrate-blocks', [ self::class, 'run' ] );
		}
	}

	/**
	 * Migrate legacy Zehoro blocks to their current names in post_content.
	 *
	 * ## OPTIONS
	 *
	 * [--execute]
	 * : Apply changes. Without this flag the command is a dry run and writes nothing.
	 *
	 * [--post_type=<types>]
	 * : Comma-separated post types to scan. Default: post,page.
	 *
	 * [--post_status=<statuses>]
	 * : Comma-separated post statuses to scan. Default: any.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview what would change (safe)
	 *     wp zehoro migrate-blocks
	 *
	 *     # Apply the migration
	 *     wp zehoro migrate-blocks --execute
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Flags.
	 */
	public static function run( $args, $assoc_args ): void {
		$execute  = isset( $assoc_args['execute'] );
		$types    = self::csv( $assoc_args['post_type'] ?? 'post,page' );
		$statuses = self::csv( $assoc_args['post_status'] ?? 'any' );

		$ids = get_posts( [
			'post_type'        => $types,
			'post_status'      => $statuses,
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		] );

		$scanned        = 0;
		$migrated       = 0;
		$skipped_blocks = 0;

		foreach ( $ids as $id ) {
			$scanned++;

			$stats   = [];
			$outcome = self::migrate_post( (int) $id, $execute, $stats );
			if ( $outcome === 'unchanged' ) {
				continue;
			}
			if ( $outcome === 'error' ) {
				\WP_CLI::warning( sprintf( '#%d failed to update', $id ) );
				continue;
			}

			// Block-structured legacy blocks are left in place (the render safety
			// net displays them losslessly) rather than flattened — never a silent
			// lossy rewrite.
			if ( ! empty( $stats['skipped'] ) ) {
				$skipped_blocks += (int) $stats['skipped'];
				\WP_CLI::warning( sprintf(
					'#%d: left %d block-structured %s block(s) as-is (multi-paragraph/list — the render safety net displays them; hand-convert if you want them as native blocks).',
					$id, (int) $stats['skipped'], self::LEGACY
				) );
			}

			if ( $outcome === 'changed' ) {
				$migrated++;
				\WP_CLI::log( $execute ? sprintf( 'Migrated #%d', $id ) : sprintf( '[dry-run] Would migrate #%d', $id ) );
			}
		}

		$verb = $execute ? 'migrated' : 'would migrate';
		\WP_CLI::success( sprintf(
			'Scanned %d. %s %d post(s); left %d block-structured %s block(s) as-is.%s',
			$scanned, ucfirst( $verb ), $migrated, $skipped_blocks, self::LEGACY,
			$execute ? '' : ' Dry run — nothing written; re-run with --execute to apply.'
		) );
	}

	/**
	 * Migrate a single post in place.
	 *
	 * @return string 'unchanged' (nothing to do), 'changed' (a legacy block was
	 *                rewritten — written only when $execute is true), or 'error'
	 *                (the write failed).
	 *
	 * CRITICAL: wp_update_post() internally wp_unslash()es its input, so the
	 * serialized content MUST be wp_slash()ed first. Without it, the JSON
	 * \uXXXX escapes that block serialization uses for HTML in attributes (e.g.
	 * a list's `<li>` markup → `<li>`) lose their backslash and the
	 * attribute is corrupted (`<li>` renders as literal text, breaking the list).
	 */
	public static function migrate_post( int $post_id, bool $execute = false, array &$stats = [] ): string {
		$stats   = [ 'converted' => 0, 'skipped' => 0 ];
		$content = (string) get_post_field( 'post_content', $post_id );
		if ( $content === '' || strpos( $content, 'wp:' . self::LEGACY ) === false ) {
			return 'unchanged';
		}

		$converted = 0;
		$skipped   = 0;
		$mapped    = self::map_blocks( parse_blocks( $content ), $converted, $skipped );
		$stats     = [ 'converted' => $converted, 'skipped' => $skipped ];

		if ( $converted === 0 ) {
			// Legacy blocks present but none convertible (all block-structured).
			return $skipped > 0 ? 'skipped' : 'unchanged';
		}

		if ( $execute ) {
			$result = wp_update_post( [
				'ID'           => $post_id,
				'post_content' => wp_slash( serialize_blocks( $mapped ) ),
			], true );
			if ( is_wp_error( $result ) ) {
				return 'error';
			}
		}

		return 'changed';
	}

	/**
	 * Recursively replace legacy blocks at any depth. $converted counts blocks
	 * rewritten; $skipped counts legacy blocks left in place because their
	 * content has block-level structure the constrained block would flatten.
	 */
	private static function map_blocks( array $blocks, int &$converted, int &$skipped ): array {
		foreach ( $blocks as &$block ) {
			if ( ( $block['blockName'] ?? '' ) === self::LEGACY ) {
				$new = self::rewrite_tldr( $block );
				if ( $new === null ) {
					$skipped++; // leave the original lkst/tldr block untouched
				} else {
					$block = $new;
					$converted++;
				}
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::map_blocks( $block['innerBlocks'], $converted, $skipped );
			}
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * Build the replacement zehoro/key-takeaways block from a legacy lkst/tldr
	 * block, or return null when the content has block-level structure the
	 * constrained block would flatten (multi-paragraph / list / headings). Those
	 * are left as lkst/tldr and rendered losslessly by the safety net — the
	 * migrator NEVER performs a silent lossy rewrite.
	 */
	private static function rewrite_tldr( array $block ): ?array {
		$attrs   = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : [];
		$heading = isset( $attrs['heading'] ) ? (string) $attrs['heading'] : '';
		$content = KeyTakeaways::extract_legacy_content( (string) ( $block['innerHTML'] ?? '' ) );
		if ( $content === '' && isset( $attrs['content'] ) ) {
			$content = (string) $attrs['content'];
		}

		$plan = self::plan_conversion( $content );
		if ( $plan === null ) {
			return null;
		}
		if ( $heading !== '' ) {
			$plan['heading'] = $heading;
		}

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
	 * null when the content has block-level structure (caller skips it).
	 */
	private static function plan_conversion( string $content ): ?array {
		$content = trim( $content );
		if ( $content === '' ) {
			return [ 'mode' => 'paragraph' ]; // empty legacy box → clean removal
		}

		// A single <p> wrapping the whole thing is just an inline paragraph.
		if ( preg_match( '#^<p\b[^>]*>(.*)</p>$#is', $content, $m ) ) {
			$inner = $m[1];
			return self::has_block_tags( $inner ) ? null : [ 'mode' => 'paragraph', 'text' => $inner ];
		}

		if ( self::has_block_tags( $content ) ) {
			return null; // multiple paragraphs / list / headings → leave as-is
		}

		return [ 'mode' => 'paragraph', 'text' => $content ];
	}

	private static function has_block_tags( string $html ): bool {
		return (bool) preg_match( '#<\s*(p|div|ul|ol|li|h[1-6]|blockquote|table|figure|section|article|pre|hr)\b#i', $html );
	}

	/** @return string[] */
	private static function csv( string $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}
}
