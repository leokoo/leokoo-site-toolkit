<?php
/**
 * Key Takeaways — backward-compat for the retired lkst/tldr block + module slug.
 *
 * Guarantees (each with a test that can actually go RED):
 *   1. The module-slug rename tldr→key_takeaways is migrated inside the stored
 *      zehoro_active_modules allowlist, so the module stays active (block +
 *      safety net + CLI register) on upgrading sites — NOT just fresh ones.
 *   2. Any un-migrated lkst/tldr renders through the seam LOSSLESSLY — multiple
 *      paragraphs and lists survive, they are not flattened into a run-on line.
 *   3. The migrator converts clean inline content and LEAVES block-structured
 *      content as-is (never a silent lossy rewrite), preserving sibling content.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\KeyTakeaways;
use Zehoro\Cli\MigrateBlocksCommand;
use Zehoro\Migration\ZehoroRenameMigrator;

class KeyTakeawaysCompatTest extends WP_UnitTestCase {

	/** Realistic saved markup for the old static block (single inline paragraph). */
	private const LEGACY_MARKUP =
		'<div class="lkst-tldr lkst-editorial-block">' .
			'<p class="lkst-tldr-heading">Key Takeaways</p>' .
			'<div class="lkst-tldr-content-wrapper">' .
				'<div class="lkst-tldr-content"><p>Hello <strong>world</strong></p></div>' .
			'</div>' .
		'</div>';

	private static function legacy_block( string $inner_content, string $heading = 'Old' ): array {
		return [
			'blockName' => 'lkst/tldr',
			'attrs'     => [ 'heading' => $heading ],
			'innerHTML' => '<div class="lkst-tldr"><div class="lkst-tldr-content">' . $inner_content . '</div></div>',
		];
	}

	private static function legacy_post_content( string $inner_content, string $heading = 'Old' ): string {
		return '<!-- wp:lkst/tldr {"heading":"' . $heading . '"} -->' .
			'<div class="lkst-tldr"><div class="lkst-tldr-content">' . $inner_content . '</div></div>' .
			'<!-- /wp:lkst/tldr -->';
	}

	// =========================================================================
	// 1. CRITICAL — the module-slug migration keeps the module active on upgrade
	// =========================================================================

	public function test_module_slug_is_migrated_inside_active_modules_allowlist() {
		// The exact regression: an upgrading site's stored allowlist carries the
		// dead 'tldr' slug, so Plugin::init's in_array('key_takeaways',$active)
		// gate is false and the whole feature ships dark.
		update_option( 'zehoro_active_modules', [ 'tldr', 'faq', 'author_box' ] );
		ZehoroRenameMigrator::reset_flag();
		ZehoroRenameMigrator::migrate_module_slugs();

		$active = get_option( 'zehoro_active_modules' );
		$this->assertContains( 'key_takeaways', $active, 'renamed module must be active so the block registers' );
		$this->assertNotContains( 'tldr', $active, 'the dead slug must be gone' );
		$this->assertContains( 'faq', $active, 'sibling modules untouched' );
		$this->assertContains( 'author_box', $active );
	}

	public function test_legacy_only_site_migrates_to_canonical_with_renamed_slug() {
		// A pre-canonical site: only lkst_active_modules set. run() copies it to
		// the canonical key, THEN the slug migration renames tldr→key_takeaways
		// there. The legacy key is left untouched (rollback safety).
		delete_option( 'zehoro_active_modules' ); // bootstrap pre-seeds it; simulate a pre-canonical site
		update_option( 'lkst_active_modules', [ 'tldr', 'faq' ] );
		ZehoroRenameMigrator::reset_flag();
		ZehoroRenameMigrator::run();

		$active = get_option( 'zehoro_active_modules' );
		$this->assertContains( 'key_takeaways', $active, 'copied + slug-renamed into canonical' );
		$this->assertNotContains( 'tldr', $active );
		$this->assertSame( [ 'tldr', 'faq' ], get_option( 'lkst_active_modules' ), 'legacy untouched (rollback safety)' );
	}

	public function test_slug_migration_is_idempotent_via_its_own_flag() {
		update_option( 'zehoro_active_modules', [ 'tldr' ] );
		ZehoroRenameMigrator::reset_flag();
		ZehoroRenameMigrator::migrate_module_slugs(); // sets SLUG_MIGRATION_FLAG

		// Re-seed and re-run: the flag must prevent a second pass.
		update_option( 'zehoro_active_modules', [ 'tldr' ] );
		ZehoroRenameMigrator::migrate_module_slugs();
		$this->assertContains( 'tldr', get_option( 'zehoro_active_modules' ), 'flag should gate a second run' );
	}

	public function test_slug_migration_uses_a_flag_independent_of_the_key_rename() {
		// A site that already ran the key rename (MIGRATION_FLAG set) must still
		// get its slug fixed — the bug was folding this into the gated path.
		update_option( ZehoroRenameMigrator::MIGRATION_FLAG, '1' );
		delete_option( ZehoroRenameMigrator::SLUG_MIGRATION_FLAG );
		update_option( 'zehoro_active_modules', [ 'tldr' ] );

		ZehoroRenameMigrator::run(); // key rename no-ops (flag set); slug rename must still run

		$this->assertContains( 'key_takeaways', get_option( 'zehoro_active_modules' ) );
	}

	// =========================================================================
	// 2. HIGH — the safety net renders legacy content LOSSLESSLY
	// =========================================================================

	public function test_safety_net_rerenders_single_paragraph_through_seam() {
		$module = new KeyTakeaways();
		$block  = self::legacy_block( '<p>Hello <strong>world</strong></p>' );

		$out = $module->legacy_render_safety_net( $block['innerHTML'], $block );

		$this->assertStringContainsString( 'zehoro-key-takeaways', $out );
		$this->assertStringContainsString( '>Old</h2>', $out );
		$this->assertStringContainsString( 'Hello', $out );
		$this->assertStringContainsString( '<strong>world</strong>', $out );
		$this->assertStringNotContainsString( 'lkst-tldr', $out );
	}

	public function test_safety_net_preserves_multiple_paragraphs() {
		$module = new KeyTakeaways();
		$block  = self::legacy_block( '<p>First para.</p><p>Second para.</p>' );

		$out = $module->legacy_render_safety_net( $block['innerHTML'], $block );

		$this->assertStringContainsString( 'First para.', $out );
		$this->assertStringContainsString( 'Second para.', $out );
		// The two paragraphs must NOT be collapsed into a run-on line.
		$this->assertStringNotContainsString( 'First para.Second para.', $out );
		$this->assertSame( 2, substr_count( $out, '<p' ), 'both paragraph boundaries survive' );
	}

	public function test_safety_net_preserves_a_bulleted_list() {
		$module = new KeyTakeaways();
		$block  = self::legacy_block( '<ul><li>Alpha</li><li>Beta</li></ul>' );

		$out = $module->legacy_render_safety_net( $block['innerHTML'], $block );

		$this->assertStringContainsString( '<li>Alpha</li>', $out );
		$this->assertStringContainsString( '<li>Beta</li>', $out );
	}

	public function test_safety_net_ignores_other_blocks() {
		$module      = new KeyTakeaways();
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
		$this->assertSame( $original, $module->legacy_render_safety_net( $original, $block ) );
	}

	// =========================================================================
	// extract_legacy_content
	// =========================================================================

	public function test_extract_pulls_only_the_content_node() {
		$out = KeyTakeaways::extract_legacy_content( self::LEGACY_MARKUP );
		$this->assertStringContainsString( 'Hello', $out );
		$this->assertStringContainsString( '<strong>world</strong>', $out );
		$this->assertStringNotContainsString( 'Key Takeaways', $out );
		$this->assertStringNotContainsString( 'lkst-tldr', $out );
	}

	public function test_extract_empty_input_returns_empty() {
		$this->assertSame( '', KeyTakeaways::extract_legacy_content( '' ) );
		$this->assertSame( '', KeyTakeaways::extract_legacy_content( '   ' ) );
	}

	// =========================================================================
	// 3. The migrator — convert clean inline, LEAVE block-structured, real path
	// =========================================================================

	public function test_migrator_converts_inline_content_to_paragraph() {
		$id    = self::factory()->post->create( [ 'post_content' => self::legacy_post_content( '<p>Just <strong>inline</strong> content.</p>', 'KT' ) ] );
		$stats = [];

		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, true, $stats ) );
		$this->assertSame( 1, $stats['converted'] );
		$this->assertSame( 0, $stats['skipped'] );

		$stored = (string) get_post_field( 'post_content', $id );
		$this->assertStringContainsString( 'wp:zehoro/key-takeaways', $stored );
		$this->assertStringNotContainsString( 'wp:lkst/tldr', $stored );

		$rendered = do_blocks( $stored );
		$this->assertStringContainsString( '<strong>inline</strong>', $rendered );
		$this->assertStringContainsString( 'zehoro-key-takeaways__summary', $rendered );
		$this->assertStringContainsString( '>KT</h2>', $rendered );
	}

	public function test_migrator_leaves_block_structured_content_as_is() {
		// Multi-paragraph content must NOT be lossy-converted; left for the safety net.
		$content = self::legacy_post_content( '<p>One.</p><p>Two.</p>', 'H' );
		$id      = self::factory()->post->create( [ 'post_content' => $content ] );
		$stats   = [];

		$this->assertSame( 'skipped', MigrateBlocksCommand::migrate_post( $id, true, $stats ) );
		$this->assertSame( 0, $stats['converted'] );
		$this->assertSame( 1, $stats['skipped'] );

		// Nothing written — the legacy block is preserved intact.
		$stored = (string) get_post_field( 'post_content', $id );
		$this->assertStringContainsString( 'wp:lkst/tldr', $stored );
		$this->assertStringContainsString( '<p>One.</p><p>Two.</p>', $stored );
	}

	public function test_migrator_preserves_html_in_attributes_through_wp_slash() {
		// A new list block (HTML in `items`) beside a legacy block: the migrator
		// re-serializes the whole post; without wp_slash the <li> markup corrupts.
		$content =
			'<!-- wp:zehoro/key-takeaways {"mode":"list","heading":"KT","items":"<li>Alpha</li><li>Beta</li>"} /-->' .
			self::legacy_post_content( '<p>Convert me.</p>' );
		$id = self::factory()->post->create( [ 'post_content' => $content ] );

		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, true ) );

		$rendered = do_blocks( (string) get_post_field( 'post_content', $id ) );
		$this->assertStringContainsString( '<li>Alpha</li>', $rendered );
		$this->assertStringContainsString( '<li>Beta</li>', $rendered );
		$this->assertStringNotContainsString( 'u003c', $rendered );
	}

	public function test_migrator_preserves_untouched_sibling_content() {
		$sibling = '<!-- wp:paragraph --><p>Untouched sibling with an &amp; entity.</p><!-- /wp:paragraph -->';
		$content = $sibling . "\n\n" . self::legacy_post_content( '<p>Convert me.</p>', 'KT' );
		$id      = self::factory()->post->create( [ 'post_content' => $content ] );

		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, true ) );

		$stored = (string) get_post_field( 'post_content', $id );
		$this->assertStringContainsString( '<p>Untouched sibling with an &amp; entity.</p>', $stored );
		$this->assertStringContainsString( 'wp:zehoro/key-takeaways', $stored );
	}

	public function test_migrate_post_dry_run_writes_nothing() {
		$id = self::factory()->post->create( [ 'post_content' => self::legacy_post_content( '<p>Convert me.</p>' ) ] );

		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, false ) );
		$this->assertStringContainsString( 'wp:lkst/tldr', (string) get_post_field( 'post_content', $id ) );
	}

	public function test_migrate_post_unchanged_when_no_legacy_block() {
		$id = self::factory()->post->create( [ 'post_content' => '<!-- wp:paragraph --><p>hi</p><!-- /wp:paragraph -->' ] );
		$this->assertSame( 'unchanged', MigrateBlocksCommand::migrate_post( $id, true ) );
	}

	// =========================================================================
	// The safety-net FILTER WIRING (must be red-able — not a direct handler call)
	// =========================================================================

	public function test_safety_net_is_actually_hooked_on_render_block() {
		// Render an un-migrated legacy block through the REAL pipeline. A direct
		// handler call can't catch a broken/removed add_filter — this can.
		$rendered = do_blocks( self::legacy_post_content( '<p>Legacy body here.</p>', 'Legacy H' ) );

		$this->assertStringContainsString( 'zehoro-key-takeaways', $rendered );
		$this->assertStringContainsString( 'Legacy body here.', $rendered );
		$this->assertStringNotContainsString( 'lkst-tldr', $rendered );
	}

	// =========================================================================
	// Mixed post — converted + skipped written together (real serialize path)
	// =========================================================================

	public function test_migrator_writes_mixed_post_faithfully() {
		$convertible = self::legacy_post_content( '<p>Convert me.</p>', 'C' );
		$skipped     = self::legacy_post_content( '<p>One.</p><p>Two.</p>', 'S' );
		$id          = self::factory()->post->create( [ 'post_content' => $convertible . "\n\n" . $skipped ] );

		$stats = [];
		$this->assertSame( 'changed', MigrateBlocksCommand::migrate_post( $id, true, $stats ) );
		$this->assertSame( 1, $stats['converted'] );
		$this->assertSame( 1, $stats['skipped'] );

		$stored = (string) get_post_field( 'post_content', $id );
		$this->assertStringContainsString( 'wp:zehoro/key-takeaways', $stored, 'convertible block migrated' );
		$this->assertStringContainsString( 'wp:lkst/tldr', $stored, 'block-structured block left as-is' );
		$this->assertStringContainsString( '<p>One.</p><p>Two.</p>', $stored, 'skipped block survives re-serialization intact' );
	}

	// =========================================================================
	// Should-fix coverage: heading casing, empty box, inline media
	// =========================================================================

	public function test_legacy_default_heading_casing_is_preserved() {
		// The old block defaulted to 'Key Takeaways' (title case); a legacy block
		// using the default (no stored heading) must not silently become 'Key takeaways'.
		$module = new KeyTakeaways();
		$block  = self::legacy_block( '<p>x</p>' );
		unset( $block['attrs']['heading'] );

		$out = $module->legacy_render_safety_net( $block['innerHTML'], $block );
		$this->assertStringContainsString( 'Key Takeaways', $out );
		$this->assertStringNotContainsString( 'Key takeaways</h2>', $out );
	}

	public function test_migrator_skips_empty_box_without_rewrite() {
		$id    = self::factory()->post->create( [ 'post_content' => self::legacy_post_content( '', 'E' ) ] );
		$stats = [];
		$this->assertSame( 'skipped', MigrateBlocksCommand::migrate_post( $id, true, $stats ) );
		$this->assertSame( 0, $stats['converted'] );
	}

	public function test_image_bearing_content_is_skipped_and_rendered_losslessly() {
		$inner = '<p>See <img src="https://x.test/a.png" alt="a" /> here.</p>';

		// Migrator leaves it (the inline path would drop the img).
		$id    = self::factory()->post->create( [ 'post_content' => self::legacy_post_content( $inner ) ] );
		$stats = [];
		$this->assertSame( 'skipped', MigrateBlocksCommand::migrate_post( $id, true, $stats ) );

		// Safety net renders the image losslessly.
		$module = new KeyTakeaways();
		$block  = self::legacy_block( $inner );
		$out    = $module->legacy_render_safety_net( $block['innerHTML'], $block );
		$this->assertStringContainsString( '<img', $out );
		$this->assertStringContainsString( 'a.png', $out );
	}

	public function test_image_only_content_renders_without_leaking_legacy_markup() {
		// No visible text — only an image. Must render through the seam, not fall
		// back to the raw (now-unstyled) lkst-* markup.
		$module = new KeyTakeaways();
		$block  = self::legacy_block( '<p><img src="https://x.test/a.png" alt="" /></p>' );

		$out = $module->legacy_render_safety_net( $block['innerHTML'], $block );
		$this->assertStringContainsString( '<img', $out );
		$this->assertStringContainsString( 'zehoro-key-takeaways', $out );
		$this->assertStringNotContainsString( 'lkst-tldr', $out );
	}

	// =========================================================================
	// End-to-end seam guard: migration → active module → block registered
	// =========================================================================

	public function test_migration_reactivates_the_module_that_registers_the_block() {
		$registry = WP_Block_Type_Registry::get_instance();

		update_option( 'zehoro_active_modules', [ 'tldr' ] );
		ZehoroRenameMigrator::reset_flag();
		ZehoroRenameMigrator::migrate_module_slugs();
		$active = get_option( 'zehoro_active_modules' );

		// Prove the now-active module actually registers the block from a clean
		// registry state (register_block re-registers, restoring it for other tests).
		if ( $registry->is_registered( 'zehoro/key-takeaways' ) ) {
			unregister_block_type( 'zehoro/key-takeaways' );
		}
		( new KeyTakeaways() )->register_block();
		$registered = $registry->is_registered( 'zehoro/key-takeaways' );

		$this->assertContains( 'key_takeaways', $active, 'migration flips the gate predicate Plugin::init reads' );
		$this->assertTrue( $registered, 'the reactivated module registers the block' );
	}
}
