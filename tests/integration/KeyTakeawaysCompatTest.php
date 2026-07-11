<?php
/**
 * Key Takeaways — backward-compat for the retired lkst/tldr block.
 *
 * Two guarantees:
 *   1. Any un-migrated lkst/tldr renders through the new seam (never unstyled
 *      or as an editor error) — the render_block safety net.
 *   2. `wp zehoro migrate-blocks` permanently rewrites lkst/tldr →
 *      zehoro/key-takeaways in post_content with heading + content preserved.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\KeyTakeaways;
use Zehoro\Cli\MigrateBlocksCommand;

class KeyTakeawaysCompatTest extends WP_UnitTestCase {

	/** Realistic saved markup for the old static block. */
	private const LEGACY_MARKUP =
		'<div class="lkst-tldr lkst-editorial-block">' .
			'<p class="lkst-tldr-heading">Key Takeaways</p>' .
			'<div class="lkst-tldr-content-wrapper">' .
				'<div class="lkst-tldr-content"><p>Hello <strong>world</strong></p></div>' .
			'</div>' .
		'</div>';

	// -------------------------------------------------------------------------
	// extract_legacy_content
	// -------------------------------------------------------------------------

	public function test_extract_pulls_only_the_content_node() {
		$out = KeyTakeaways::extract_legacy_content( self::LEGACY_MARKUP );

		$this->assertStringContainsString( 'Hello', $out );
		$this->assertStringContainsString( '<strong>world</strong>', $out );
		// The heading text and legacy wrapper classes must not leak in.
		$this->assertStringNotContainsString( 'Key Takeaways', $out );
		$this->assertStringNotContainsString( 'lkst-tldr', $out );
	}

	public function test_extract_empty_input_returns_empty() {
		$this->assertSame( '', KeyTakeaways::extract_legacy_content( '' ) );
		$this->assertSame( '', KeyTakeaways::extract_legacy_content( '   ' ) );
	}

	public function test_extract_falls_back_without_content_node_but_drops_heading() {
		$markup = '<div class="lkst-tldr"><p class="lkst-tldr-heading">Heading</p><p>Body only</p></div>';
		$out    = KeyTakeaways::extract_legacy_content( $markup );

		$this->assertStringContainsString( 'Body only', $out );
		$this->assertStringNotContainsString( 'Heading', $out );
	}

	// -------------------------------------------------------------------------
	// render_block safety net
	// -------------------------------------------------------------------------

	public function test_safety_net_rerenders_legacy_block_through_seam() {
		$module = new KeyTakeaways();
		$block  = [
			'blockName' => 'lkst/tldr',
			'attrs'     => [ 'heading' => 'Old Heading' ],
			'innerHTML' => self::LEGACY_MARKUP,
		];

		$out = $module->legacy_render_safety_net( self::LEGACY_MARKUP, $block );

		$this->assertStringContainsString( 'zehoro-key-takeaways', $out );
		$this->assertStringContainsString( 'Old Heading', $out );
		$this->assertStringContainsString( 'Hello', $out );
		$this->assertStringContainsString( '<strong>world</strong>', $out );
		// The dead legacy classes are gone.
		$this->assertStringNotContainsString( 'lkst-tldr', $out );
	}

	public function test_safety_net_ignores_other_blocks() {
		$module = new KeyTakeaways();
		$passthrough = '<p>untouched</p>';

		$this->assertSame(
			$passthrough,
			$module->legacy_render_safety_net( $passthrough, [ 'blockName' => 'core/paragraph' ] )
		);
	}

	public function test_safety_net_returns_original_when_nothing_to_render() {
		$module   = new KeyTakeaways();
		$original = '<div class="lkst-tldr"></div>';
		$block    = [ 'blockName' => 'lkst/tldr', 'attrs' => [], 'innerHTML' => '<div class="lkst-tldr"></div>' ];

		// No heading, no content → seam yields '' → keep the original markup.
		$this->assertSame( $original, $module->legacy_render_safety_net( $original, $block ) );
	}

	// -------------------------------------------------------------------------
	// The migrator (data move)
	// -------------------------------------------------------------------------

	/** Invoke the migrator's private recursive mapper (version-safe reflection). */
	private static function invoke_map_blocks( array $blocks, bool &$changed ): array {
		$ref = new ReflectionMethod( MigrateBlocksCommand::class, 'map_blocks' );
		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}
		$args = [ $blocks, &$changed ];
		return $ref->invokeArgs( null, $args );
	}

	public function test_migrator_rewrites_block_name_and_preserves_content() {
		$content =
			'<!-- wp:lkst/tldr {"heading":"My KT"} -->' . self::LEGACY_MARKUP . '<!-- /wp:lkst/tldr -->';

		$changed = false;
		$blocks  = parse_blocks( $content );
		$mapped  = self::invoke_map_blocks( $blocks, $changed );

		$this->assertTrue( $changed, 'migration should report a change' );

		$out = serialize_blocks( $mapped );
		$this->assertStringContainsString( 'wp:zehoro/key-takeaways', $out );
		$this->assertStringNotContainsString( 'wp:lkst/tldr', $out );
		$this->assertStringContainsString( '"heading":"My KT"', $out );
		$this->assertStringContainsString( '"mode":"paragraph"', $out );
		// Content carried into the text attribute.
		$this->assertStringContainsString( 'Hello', $out );
	}

	public function test_migrator_leaves_unrelated_content_unchanged() {
		$content = '<!-- wp:paragraph --><p>hi</p><!-- /wp:paragraph -->';
		$changed = false;
		$blocks  = parse_blocks( $content );

		self::invoke_map_blocks( $blocks, $changed );

		$this->assertFalse( $changed, 'no lkst/tldr present → no change' );
	}

	public function test_migrated_block_round_trips_to_real_render() {
		// Prove the migrated block, once re-parsed, renders correctly.
		$content = '<!-- wp:lkst/tldr {"heading":"Round Trip"} -->' . self::LEGACY_MARKUP . '<!-- /wp:lkst/tldr -->';

		$changed  = false;
		$blocks   = parse_blocks( $content );
		$migrated = serialize_blocks( self::invoke_map_blocks( $blocks, $changed ) );

		$rendered = do_blocks( $migrated );

		$this->assertStringContainsString( 'zehoro-key-takeaways', $rendered );
		$this->assertStringContainsString( 'Round Trip', $rendered );
		$this->assertStringContainsString( 'Hello', $rendered );
		$this->assertStringNotContainsString( 'lkst-tldr', $rendered );
	}

	/**
	 * Regression: the migrator writes through wp_update_post(), which
	 * wp_unslash()es its input. HTML in a block attribute (a list's <li>) is
	 * serialized as JSON < escapes; without wp_slash() the backslash is
	 * stripped and the attribute corrupts to literal `u003cli` text — the list
	 * renders as a single text node, failing the a11y `list` rule. This test
	 * crosses the real wp_update_post boundary (the earlier round-trip test used
	 * serialize_blocks()/do_blocks() directly and could not catch it).
	 */
	public function test_migrator_write_preserves_html_in_attributes() {
		$content =
			'<!-- wp:zehoro/key-takeaways {"mode":"list","heading":"KT","items":"<li>Alpha</li><li>Beta</li>"} /-->' .
			'<!-- wp:lkst/tldr {"heading":"Old"} -->' . self::LEGACY_MARKUP . '<!-- /wp:lkst/tldr -->';

		$id = self::factory()->post->create( [ 'post_content' => $content ] );

		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, true ) );

		$stored   = (string) get_post_field( 'post_content', $id );
		$rendered = do_blocks( $stored );

		// Legacy block converted.
		$this->assertStringNotContainsString( 'wp:lkst/tldr', $stored );
		// The list block's markup survived the write — real <li>, not corrupted text.
		$this->assertStringContainsString( '<li>Alpha</li>', $rendered );
		$this->assertStringContainsString( '<li>Beta</li>', $rendered );
		$this->assertStringNotContainsString( 'u003c', $rendered );
	}

	public function test_migrate_post_dry_run_writes_nothing() {
		$content = '<!-- wp:lkst/tldr {"heading":"Old"} -->' . self::LEGACY_MARKUP . '<!-- /wp:lkst/tldr -->';
		$id      = self::factory()->post->create( [ 'post_content' => $content ] );

		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, false ) );

		// Nothing written on a dry run — the legacy block is still there.
		$this->assertStringContainsString( 'wp:lkst/tldr', (string) get_post_field( 'post_content', $id ) );
	}
}
