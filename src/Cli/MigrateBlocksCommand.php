<?php
/**
 * WP-CLI: migrate legacy block names in post_content.
 *
 * Generic runner over a rename REGISTRY: each module registers its legacy block
 * names + a transform handler via the `zehoro/block_migrations` filter, e.g.
 * KeyTakeaways registers `lkst/tldr` and ProsCons registers `lkst/pros-cons`,
 * `lkst/pros`, `lkst/cons`. A handler returns the replacement block array, or
 * null to leave the block as-is (block-structured content the render safety net
 * displays losslessly — never a silent lossy rewrite).
 *
 * Safe by default: a plain `wp zehoro migrate-blocks` is a DRY RUN and changes
 * nothing. Pass `--execute` to write.
 *
 * @package Zehoro\Cli
 */

namespace Zehoro\Cli;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MigrateBlocksCommand {

	public static function register(): void {
		// Idempotent: several modules call this, but the command registers once.
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'zehoro migrate-blocks', [ self::class, 'run' ] );
		}
	}

	/**
	 * The rename registry: [ legacy_block_name => callable(array $block): ?array ].
	 *
	 * Modules contribute via `add_filter( 'zehoro/block_migrations', … )` in init().
	 *
	 * @return array<string,callable>
	 */
	public static function renames(): array {
		$renames = apply_filters( 'zehoro/block_migrations', [] );
		return is_array( $renames ) ? $renames : [];
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

		if ( empty( self::renames() ) ) {
			\WP_CLI::success( 'No legacy block renames registered — nothing to migrate.' );
			return;
		}

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
					'#%d: left %d legacy block(s) as-is (block-structured — the render safety net displays them; hand-convert if you want them as native blocks).',
					$id, (int) $stats['skipped']
				) );
			}

			if ( $outcome === 'changed' ) {
				$migrated++;
				\WP_CLI::log( $execute ? sprintf( 'Migrated #%d', $id ) : sprintf( '[dry-run] Would migrate #%d', $id ) );
			}
		}

		$verb = $execute ? 'migrated' : 'would migrate';
		\WP_CLI::success( sprintf(
			'Scanned %d. %s %d post(s); left %d legacy block(s) as-is.%s',
			$scanned, ucfirst( $verb ), $migrated, $skipped_blocks,
			$execute ? '' : ' Dry run — nothing written; re-run with --execute to apply.'
		) );
	}

	/**
	 * Migrate a single post in place, using the rename registry.
	 *
	 * @return string 'unchanged' (nothing to do), 'changed' (a legacy block was
	 *                rewritten — written only when $execute is true), 'skipped'
	 *                (legacy blocks present but none convertible), or 'error'.
	 *
	 * CRITICAL: wp_update_post() internally wp_unslash()es its input, so the
	 * serialized content MUST be wp_slash()ed first. Without it, the JSON
	 * \uXXXX escapes that block serialization uses for HTML in attributes (e.g.
	 * a list's `<li>` markup → `<li>`) lose their backslash and the
	 * attribute is corrupted (`<li>` renders as literal text, breaking the list).
	 */
	public static function migrate_post( int $post_id, bool $execute = false, array &$stats = [] ): string {
		$stats   = [ 'converted' => 0, 'skipped' => 0 ];
		$renames = self::renames();
		if ( empty( $renames ) ) {
			return 'unchanged';
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		if ( $content === '' || ! self::content_has_legacy( $content, $renames ) ) {
			return 'unchanged';
		}

		$converted = 0;
		$skipped   = 0;
		$mapped    = self::map_blocks( parse_blocks( $content ), $renames, $converted, $skipped );
		$stats     = [ 'converted' => $converted, 'skipped' => $skipped ];

		if ( $converted === 0 ) {
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

	/** Cheap pre-check: does the content mention any registered legacy block name? */
	private static function content_has_legacy( string $content, array $renames ): bool {
		foreach ( array_keys( $renames ) as $name ) {
			if ( strpos( $content, 'wp:' . $name ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Recursively rewrite registered legacy blocks at any depth. A handler that
	 * returns null leaves the block in place ($skipped++). A block whose handler
	 * consumes its inner blocks (e.g. an InnerBlocks container) is replaced whole
	 * and NOT recursed into.
	 */
	private static function map_blocks( array $blocks, array $renames, int &$converted, int &$skipped ): array {
		foreach ( $blocks as &$block ) {
			$name = $block['blockName'] ?? '';
			if ( isset( $renames[ $name ] ) && is_callable( $renames[ $name ] ) ) {
				$new = call_user_func( $renames[ $name ], $block );
				if ( $new === null ) {
					$skipped++;
				} else {
					$block = $new;
					$converted++;
				}
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::map_blocks( $block['innerBlocks'], $renames, $converted, $skipped );
			}
		}
		unset( $block );

		return $blocks;
	}

	/** @return string[] */
	private static function csv( string $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}
}
