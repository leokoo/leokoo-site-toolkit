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

		$scanned  = 0;
		$found    = 0;
		$migrated = 0;

		foreach ( $ids as $id ) {
			$scanned++;
			$content = (string) get_post_field( 'post_content', $id );
			if ( strpos( $content, 'wp:' . self::LEGACY ) === false ) {
				continue;
			}
			$found++;

			$changed = false;
			$mapped  = self::map_blocks( parse_blocks( $content ), $changed );
			if ( ! $changed ) {
				continue;
			}

			if ( $execute ) {
				$result = wp_update_post( [
					'ID'           => $id,
					'post_content' => serialize_blocks( $mapped ),
				], true );

				if ( is_wp_error( $result ) ) {
					\WP_CLI::warning( sprintf( '#%d failed: %s', $id, $result->get_error_message() ) );
					continue;
				}
				$migrated++;
				\WP_CLI::log( sprintf( 'Migrated #%d', $id ) );
			} else {
				\WP_CLI::log( sprintf( '[dry-run] Would migrate #%d', $id ) );
			}
		}

		if ( $execute ) {
			\WP_CLI::success( sprintf(
				'Scanned %d, found %d with %s, migrated %d.',
				$scanned, $found, self::LEGACY, $migrated
			) );
		} else {
			\WP_CLI::success( sprintf(
				'Scanned %d, found %d with %s. Dry run — nothing written. Re-run with --execute to apply.',
				$scanned, $found, self::LEGACY
			) );
		}
	}

	/**
	 * Recursively replace legacy blocks. Sets $changed = true if anything was
	 * rewritten at any depth.
	 */
	private static function map_blocks( array $blocks, bool &$changed ): array {
		foreach ( $blocks as &$block ) {
			if ( ( $block['blockName'] ?? '' ) === self::LEGACY ) {
				$block   = self::rewrite_tldr( $block );
				$changed = true;
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::map_blocks( $block['innerBlocks'], $changed );
			}
		}
		unset( $block );

		return $blocks;
	}

	/** Build the replacement zehoro/key-takeaways block from a legacy lkst/tldr block. */
	private static function rewrite_tldr( array $block ): array {
		$attrs   = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : [];
		$heading = isset( $attrs['heading'] ) ? (string) $attrs['heading'] : '';
		$text    = KeyTakeaways::extract_legacy_content( (string) ( $block['innerHTML'] ?? '' ) );
		if ( $text === '' && isset( $attrs['content'] ) ) {
			$text = (string) $attrs['content'];
		}

		$new_attrs = [ 'mode' => 'paragraph' ];
		if ( $heading !== '' ) {
			$new_attrs['heading'] = $heading;
		}
		if ( $text !== '' ) {
			$new_attrs['text'] = $text;
		}

		return [
			'blockName'    => 'zehoro/key-takeaways',
			'attrs'        => $new_attrs,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/** @return string[] */
	private static function csv( string $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}
}
