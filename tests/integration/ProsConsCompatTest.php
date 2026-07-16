<?php
/**
 * Pros & Cons — backward-compat for the retired lkst/pros, lkst/cons and the
 * lkst/pros-cons InnerBlocks container.
 *
 * Guarantees (each with a red-able test):
 *   1. Any un-migrated legacy block renders through the seam (no lkst-* leak).
 *   2. The migrator converts standalone pros/cons and UNPACKS the container's
 *      inner blocks into one consolidated zehoro/pros-cons.
 *   3. The safety-net filter is actually hooked (do_blocks over an un-migrated post).
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\ProsCons;
use Zehoro\Cli\MigrateBlocksCommand;

class ProsConsCompatTest extends WP_UnitTestCase {

	private static function pros_markup( string $items ): string {
		return '<div class="wp-block-lkst-pros lkst-pros"><h4 class="lkst-pros-heading">'
			. '<span class="lkst-pros-icon">✅</span> Pros</h4>'
			. '<ul class="lkst-pros-list">' . $items . '</ul></div>';
	}

	private static function cons_markup( string $items ): string {
		return '<div class="wp-block-lkst-cons lkst-cons"><h4 class="lkst-cons-heading">'
			. '<span class="lkst-cons-icon">❌</span> Cons</h4>'
			. '<ul class="lkst-cons-list">' . $items . '</ul></div>';
	}

	private static function container_content( string $pros_items, string $cons_items ): string {
		return "<!-- wp:lkst/pros-cons -->\n"
			. '<div class="wp-block-lkst-pros-cons lkst-pros-cons-wrapper lkst-editorial-block">'
			. "<!-- wp:lkst/pros -->\n" . self::pros_markup( $pros_items ) . "\n<!-- /wp:lkst/pros -->"
			. "<!-- wp:lkst/cons -->\n" . self::cons_markup( $cons_items ) . "\n<!-- /wp:lkst/cons -->"
			. "</div>\n<!-- /wp:lkst/pros-cons -->";
	}

	// -------------------------------------------------------------------------
	// Safety net
	// -------------------------------------------------------------------------

	public function test_safety_net_rerenders_standalone_pros() {
		$module  = new ProsCons();
		$markup  = self::pros_markup( '<li>Fast</li><li>Cheap</li>' );
		$block   = [ 'blockName' => 'lkst/pros', 'innerHTML' => $markup ];

		$out = $module->legacy_render_safety_net( $markup, $block );

		$this->assertStringContainsString( 'zehoro-pros-cons--pros', $out );
		$this->assertStringContainsString( '<li>Fast</li>', $out );
		$this->assertStringNotContainsString( 'lkst-pros', $out );
	}

	public function test_safety_net_rerenders_standalone_cons() {
		$module = new ProsCons();
		$markup = self::cons_markup( '<li>Loud</li>' );
		$block  = [ 'blockName' => 'lkst/cons', 'innerHTML' => $markup ];

		$out = $module->legacy_render_safety_net( $markup, $block );

		$this->assertStringContainsString( 'zehoro-pros-cons--cons', $out );
		$this->assertStringContainsString( '<li>Loud</li>', $out );
		$this->assertStringNotContainsString( 'lkst-cons', $out );
	}

	public function test_safety_net_strips_container_wrapper() {
		$module = new ProsCons();
		// The container's rendered content, with its (already-rewritten) inner boxes.
		$content = '<div class="wp-block-lkst-pros-cons lkst-pros-cons-wrapper lkst-editorial-block">'
			. '<div class="zehoro-pros-cons zehoro-pros-cons--pros">INNER</div></div>';
		$block   = [ 'blockName' => 'lkst/pros-cons', 'innerHTML' => $content ];

		$out = $module->legacy_render_safety_net( $content, $block );

		$this->assertStringContainsString( 'INNER', $out );
		$this->assertStringNotContainsString( 'lkst-pros-cons-wrapper', $out );
	}

	public function test_safety_net_ignores_other_blocks() {
		$module = new ProsCons();
		$this->assertSame( '<p>x</p>', $module->legacy_render_safety_net( '<p>x</p>', [ 'blockName' => 'core/paragraph' ] ) );
	}

	public function test_safety_net_is_actually_hooked_on_render_block() {
		$content  = '<!-- wp:lkst/pros -->' . self::pros_markup( '<li>Good</li>' ) . '<!-- /wp:lkst/pros -->';
		$rendered = do_blocks( $content );

		$this->assertStringContainsString( 'zehoro-pros-cons', $rendered );
		$this->assertStringContainsString( '<li>Good</li>', $rendered );
		$this->assertStringNotContainsString( 'lkst-pros', $rendered );
	}

	// -------------------------------------------------------------------------
	// Migrator — standalone + container unpack (real write path)
	// -------------------------------------------------------------------------

	public function test_migrator_converts_standalone_pros() {
		$content = '<!-- wp:lkst/pros -->' . self::pros_markup( '<li>Fast</li>' ) . '<!-- /wp:lkst/pros -->';
		$id      = self::factory()->post->create( [ 'post_content' => $content ] );

		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, true ) );

		$stored = (string) get_post_field( 'post_content', $id );
		$this->assertStringContainsString( 'wp:zehoro/pros-cons', $stored );
		$this->assertStringContainsString( '"show":"pros"', $stored );
		$this->assertStringNotContainsString( 'wp:lkst/pros', $stored );

		$rendered = do_blocks( $stored );
		$this->assertStringContainsString( '<li>Fast</li>', $rendered );
	}

	public function test_migrator_unpacks_the_container_into_one_block() {
		$id = self::factory()->post->create( [
			'post_content' => self::container_content( '<li>Fast</li><li>Cheap</li>', '<li>Loud</li>' ),
		] );

		$stats = [];
		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, true, $stats ) );
		$this->assertSame( 1, $stats['converted'], 'the container is replaced whole' );

		$stored = (string) get_post_field( 'post_content', $id );
		$this->assertStringContainsString( 'wp:zehoro/pros-cons', $stored );
		$this->assertStringContainsString( '"show":"both"', $stored );
		$this->assertStringNotContainsString( 'wp:lkst/pros-cons', $stored );
		$this->assertStringNotContainsString( 'wp:lkst/pros', $stored );

		$rendered = do_blocks( $stored );
		$this->assertStringContainsString( '<li>Fast</li>', $rendered );
		$this->assertStringContainsString( '<li>Cheap</li>', $rendered );
		$this->assertStringContainsString( '<li>Loud</li>', $rendered );
		$this->assertStringContainsString( 'zehoro-pros-cons__pros', $rendered );
		$this->assertStringContainsString( 'zehoro-pros-cons__cons', $rendered );
	}

	public function test_migrator_preserves_html_in_list_via_wp_slash() {
		$content = '<!-- wp:lkst/pros -->' . self::pros_markup( '<li>Has <strong>bold</strong></li>' ) . '<!-- /wp:lkst/pros -->';
		$id      = self::factory()->post->create( [ 'post_content' => $content ] );

		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, true ) );

		$rendered = do_blocks( (string) get_post_field( 'post_content', $id ) );
		$this->assertStringContainsString( '<strong>bold</strong>', $rendered );
		$this->assertStringNotContainsString( 'u003c', $rendered );
	}

	public function test_migrator_skips_empty_legacy_block() {
		$content = '<!-- wp:lkst/pros -->' . self::pros_markup( '' ) . '<!-- /wp:lkst/pros -->';
		$id      = self::factory()->post->create( [ 'post_content' => $content ] );

		$stats = [];
		$this->assertSame( 'skipped', MigrateBlocksCommand::migrate_post( $id, true, $stats ) );
		$this->assertStringContainsString( 'wp:lkst/pros', (string) get_post_field( 'post_content', $id ) );
	}
}
