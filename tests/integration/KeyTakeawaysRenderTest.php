<?php
/**
 * Key Takeaways — the single render seam (KeyTakeaways::render_html).
 *
 * Covers both modes, the empty-block contract, heading-level clamping, the
 * default heading, output escaping (XSS), and wrapper-attribute passthrough.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\KeyTakeaways;

class KeyTakeawaysRenderTest extends WP_UnitTestCase {

	// -------------------------------------------------------------------------
	// Modes
	// -------------------------------------------------------------------------

	public function test_list_mode_renders_semantic_list() {
		$html = KeyTakeaways::render_html( [
			'mode'         => 'list',
			'heading'      => 'My takeaways',
			'headingLevel' => 2,
			'items'        => '<li>First point</li><li>Second point</li>',
		] );

		$this->assertStringContainsString( '<section class="zehoro-key-takeaways">', $html );
		$this->assertStringContainsString( '<h2 class="zehoro-key-takeaways__title">My takeaways</h2>', $html );
		$this->assertStringContainsString( '<ul class="zehoro-key-takeaways__list">', $html );
		$this->assertStringContainsString( '<li>First point</li>', $html );
		$this->assertStringContainsString( '<li>Second point</li>', $html );
		$this->assertStringNotContainsString( '<p class="zehoro-key-takeaways__summary">', $html );
	}

	public function test_paragraph_mode_renders_summary() {
		$html = KeyTakeaways::render_html( [
			'mode'    => 'paragraph',
			'heading' => 'TL;DR',
			'text'    => 'A short, quotable summary.',
		] );

		$this->assertStringContainsString( '<p class="zehoro-key-takeaways__summary">A short, quotable summary.</p>', $html );
		$this->assertStringContainsString( '>TL;DR</h2>', $html );
		$this->assertStringNotContainsString( '<ul', $html );
	}

	public function test_inline_formatting_is_preserved() {
		$html = KeyTakeaways::render_html( [
			'mode'  => 'list',
			'items' => '<li>Has <strong>bold</strong> and <a href="https://example.com">a link</a></li>',
		] );

		$this->assertStringContainsString( '<strong>bold</strong>', $html );
		$this->assertStringContainsString( '<a href="https://example.com">a link</a>', $html );
	}

	// -------------------------------------------------------------------------
	// Empty-block contract — render nothing rather than an empty box
	// -------------------------------------------------------------------------

	public function test_empty_list_renders_nothing() {
		$this->assertSame( '', KeyTakeaways::render_html( [ 'mode' => 'list', 'items' => '' ] ) );
		$this->assertSame( '', KeyTakeaways::render_html( [ 'mode' => 'list', 'items' => '<li></li>' ] ) );
	}

	public function test_empty_paragraph_renders_nothing() {
		$this->assertSame( '', KeyTakeaways::render_html( [ 'mode' => 'paragraph', 'text' => '   ' ] ) );
	}

	public function test_missing_attributes_render_nothing() {
		$this->assertSame( '', KeyTakeaways::render_html( [] ) );
	}

	// -------------------------------------------------------------------------
	// Heading
	// -------------------------------------------------------------------------

	public function test_heading_level_is_honored_within_range() {
		$html = KeyTakeaways::render_html( [ 'mode' => 'paragraph', 'headingLevel' => 3, 'text' => 'x' ] );
		$this->assertStringContainsString( '<h3 class="zehoro-key-takeaways__title">', $html );
	}

	public function test_out_of_range_heading_level_clamps_to_h2() {
		foreach ( [ 1, 5, 6, 0, -1 ] as $level ) {
			$html = KeyTakeaways::render_html( [ 'mode' => 'paragraph', 'headingLevel' => $level, 'text' => 'x' ] );
			$this->assertStringContainsString( '<h2 class="zehoro-key-takeaways__title">', $html, "level {$level} should clamp to h2" );
		}
	}

	public function test_blank_heading_falls_back_to_default() {
		$html = KeyTakeaways::render_html( [ 'mode' => 'paragraph', 'heading' => '', 'text' => 'body' ] );
		$this->assertStringContainsString( 'Key takeaways</h2>', $html );
	}

	// -------------------------------------------------------------------------
	// Security — the seam is the only escaping boundary
	// -------------------------------------------------------------------------

	public function test_script_in_items_is_stripped() {
		$html = KeyTakeaways::render_html( [
			'mode'  => 'list',
			'items' => '<li>ok<script>alert(1)</script></li>',
		] );

		// The executable <script> tag must be gone. Inert text may remain —
		// wp_kses unwraps disallowed tags, keeping their text, which cannot
		// execute. That is the correct, documented behavior.
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '</script>', $html );
		$this->assertStringContainsString( 'ok', $html );
	}

	public function test_javascript_href_is_stripped() {
		$html = KeyTakeaways::render_html( [
			'mode' => 'paragraph',
			'text' => '<a href="javascript:alert(1)">click</a>',
		] );

		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_heading_cannot_inject_markup() {
		$html = KeyTakeaways::render_html( [
			'mode'    => 'paragraph',
			'heading' => '</h2><script>alert(1)</script>',
			'text'    => 'body',
		] );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '</h2><script>', $html );
	}

	public function test_event_handler_attribute_is_stripped() {
		$html = KeyTakeaways::render_html( [
			'mode'  => 'list',
			'items' => '<li><a href="https://x.test" onclick="steal()">x</a></li>',
		] );

		$this->assertStringNotContainsString( 'onclick', $html );
	}

	// -------------------------------------------------------------------------
	// Wrapper attributes
	// -------------------------------------------------------------------------

	public function test_wrapper_attributes_passthrough() {
		$html = KeyTakeaways::render_html(
			[ 'mode' => 'paragraph', 'text' => 'x' ],
			'class="zehoro-key-takeaways wp-block-zehoro-key-takeaways" id="tldr-1"'
		);

		$this->assertStringContainsString(
			'<section class="zehoro-key-takeaways wp-block-zehoro-key-takeaways" id="tldr-1">',
			$html
		);
	}

	public function test_default_wrapper_when_none_supplied() {
		$html = KeyTakeaways::render_html( [ 'mode' => 'paragraph', 'text' => 'x' ] );
		$this->assertStringContainsString( '<section class="zehoro-key-takeaways">', $html );
	}

	// -------------------------------------------------------------------------
	// End-to-end — block.json registration → render.php → seam → wrapper attrs
	// -------------------------------------------------------------------------

	public function test_block_type_is_registered() {
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( 'zehoro/key-takeaways' ),
			'block.json registration should be active'
		);
	}

	public function test_end_to_end_render_via_do_blocks() {
		$content = '<!-- wp:zehoro/key-takeaways {"mode":"list","heading":"KT","items":"<li>Alpha</li><li>Beta</li>"} /-->';
		$out     = do_blocks( $content );

		// Wrapper class comes from get_block_wrapper_attributes() in render.php.
		$this->assertStringContainsString( 'wp-block-zehoro-key-takeaways', $out );
		$this->assertStringContainsString( 'zehoro-key-takeaways__title', $out );
		$this->assertStringContainsString( '>KT</h2>', $out );
		$this->assertStringContainsString( '<li>Alpha</li>', $out );
		$this->assertStringContainsString( '<li>Beta</li>', $out );
	}

	public function test_anchor_support_is_declared() {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'zehoro/key-takeaways' );
		$this->assertNotNull( $type );
		$this->assertTrue( ! empty( $type->supports['anchor'] ), 'anchor support enables deep links to the takeaways' );
		$this->assertFalse( ! empty( $type->supports['html'] ), 'raw HTML editing stays off' );
	}
}
