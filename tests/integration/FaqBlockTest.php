<?php
/**
 * FAQ blocks — the render seams (FAQ::render_container / render_item),
 * registration, end-to-end accordion render, and the no-schema guarantee.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\FAQ;

class FaqBlockTest extends WP_UnitTestCase {

	// -------------------------------------------------------------------------
	// render_item
	// -------------------------------------------------------------------------

	public function test_item_renders_details_accordion() {
		$html = FAQ::render_item( [ 'question' => 'What is it?' ], '<p>It is a thing.</p>' );

		$this->assertStringContainsString( '<details', $html );
		$this->assertStringContainsString( '<summary class="zehoro-faq__question">What is it?</summary>', $html );
		$this->assertStringContainsString( '<div class="zehoro-faq__answer"><p>It is a thing.</p></div>', $html );
	}

	public function test_item_start_open() {
		$open   = FAQ::render_item( [ 'question' => 'Q', 'startOpen' => true ], '<p>A</p>' );
		$closed = FAQ::render_item( [ 'question' => 'Q', 'startOpen' => false ], '<p>A</p>' );

		$this->assertMatchesRegularExpression( '/<details[^>]*\sopen>/', $open );
		$this->assertDoesNotMatchRegularExpression( '/<details[^>]*\sopen>/', $closed );
	}

	public function test_item_empty_renders_nothing() {
		$this->assertSame( '', FAQ::render_item( [], '' ) );
		$this->assertSame( '', FAQ::render_item( [ 'question' => '' ], '   ' ) );
	}

	public function test_raw_tags_in_question_are_stripped_defense_in_depth() {
		// Defense-in-depth: raw tags are stripped (question collapses to empty →
		// item renders nothing). The load-bearing ESCAPE guarantee is pinned by
		// test_entity_encoded_script_in_question_is_neutralized, not this test.
		$html = FAQ::render_item( [ 'question' => '</summary><script>alert(1)</script>' ], '<p>A</p>' );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_question_html_entities_decode_as_utf8() {
		// &mdash;/&hellip; must not mojibake (PHP 7.4 html_entity_decode default charset).
		$html = FAQ::render_item( [ 'question' => 'Setup &mdash; step by step&hellip;' ], '<p>A</p>' );
		$this->assertStringContainsString( "Setup \u{2014} step by step\u{2026}", $html );
	}

	public function test_question_with_empty_answer_still_renders() {
		// A question with no answer yet is a valid transitional state — render the
		// labelled disclosure (an empty answer body is acceptable).
		$html = FAQ::render_item( [ 'question' => 'Q?' ], '' );
		$this->assertStringContainsString( '<summary class="zehoro-faq__question">Q?</summary>', $html );
		$this->assertStringContainsString( '<div class="zehoro-faq__answer"></div>', $html );
	}

	public function test_entity_encoded_script_in_question_is_neutralized() {
		// The REAL stored-XSS vector: RichText stores a typed <script> as
		// entity-encoded '&lt;script&gt;'. plain_text() decodes it back to raw
		// '<script>', so esc_html() at the output site is the ONLY guard. This
		// case survives the tag-strip and fails iff esc_html is dropped.
		$html = FAQ::render_item( [ 'question' => 'Cost &lt; $5 &amp; &lt;script&gt;alert(1)&lt;/script&gt;' ], '<p>A</p>' );

		$this->assertStringNotContainsString( '<script', $html, 'no raw script tag may reach the summary' );
		$this->assertStringContainsString( '&lt;script&gt;', $html, 'the script text is escaped, not executed' );
		$this->assertStringContainsString( 'Cost &lt; $5 &amp;', $html );
	}

	public function test_item_wrapper_passthrough() {
		$html = FAQ::render_item(
			[ 'question' => 'Q' ],
			'<p>A</p>',
			'class="zehoro-faq__item wp-block-zehoro-faq-item" id="faq-1"'
		);
		$this->assertStringContainsString( 'id="faq-1"', $html );
		$this->assertStringContainsString( 'wp-block-zehoro-faq-item', $html );
	}

	// -------------------------------------------------------------------------
	// render_container
	// -------------------------------------------------------------------------

	public function test_container_wraps_content() {
		$html = FAQ::render_container( [], '<details>x</details>' );
		$this->assertStringContainsString( '<div class="zehoro-faq"><details>x</details></div>', $html );
	}

	public function test_container_empty_renders_nothing() {
		$this->assertSame( '', FAQ::render_container( [], '' ) );
		$this->assertSame( '', FAQ::render_container( [], '   ' ) );
	}

	// -------------------------------------------------------------------------
	// Registration + end-to-end
	// -------------------------------------------------------------------------

	public function test_blocks_are_registered() {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'zehoro/faq' ) );
		$this->assertTrue( $registry->is_registered( 'zehoro/faq-item' ) );
	}

	private static function faq_block( string $inner, string $item_attrs = '{"question":"What is it?"}' ): string {
		return '<!-- wp:zehoro/faq -->' .
			'<!-- wp:zehoro/faq-item ' . $item_attrs . ' -->' . $inner . '<!-- /wp:zehoro/faq-item -->' .
			'<!-- /wp:zehoro/faq -->';
	}

	public function test_end_to_end_via_do_blocks() {
		$out = do_blocks( self::faq_block( '<!-- wp:paragraph --><p>It is a thing.</p><!-- /wp:paragraph -->' ) );

		// Container wrapper must be present distinctly (not just the child's
		// wp-block-zehoro-faq-item, which contains 'wp-block-zehoro-faq' as a substring).
		$this->assertMatchesRegularExpression( '/<div class="[^"]*\bzehoro-faq\b[^"]*">\s*<details/', $out );
		$this->assertStringContainsString( '<summary class="zehoro-faq__question">What is it?</summary>', $out );
		$this->assertStringContainsString( 'zehoro-faq__answer', $out );
		$this->assertStringContainsString( 'It is a thing.', $out );
	}

	public function test_start_open_round_trips_through_do_blocks() {
		$out = do_blocks( self::faq_block(
			'<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->',
			'{"question":"Q?","startOpen":true}'
		) );
		$this->assertMatchesRegularExpression( '/<details[^>]*\sopen>/', $out );
	}

	public function test_defaults_closed_through_do_blocks() {
		// startOpen omitted → must NOT render ' open' (guards boolean coercion).
		$out = do_blocks( self::faq_block( '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->', '{"question":"Q?"}' ) );
		$this->assertDoesNotMatchRegularExpression( '/<details[^>]*\sopen>/', $out );
	}

	public function test_question_empty_item_collapses() {
		// A filled answer with no question must NOT render an unlabeled <summary>.
		$out = do_blocks( self::faq_block(
			'<!-- wp:paragraph --><p>Orphan answer</p><!-- /wp:paragraph -->',
			'{"question":""}'
		) );
		$this->assertStringNotContainsString( '<details', $out );
		$this->assertStringNotContainsString( 'Orphan answer', $out );
	}

	public function test_question_ampersand_is_encoded_exactly_once() {
		// RichText stores '&' entity-encoded; the seam must not double-encode.
		$out = do_blocks( self::faq_block(
			'<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->',
			'{"question":"Terms &amp; Conditions"}'
		) );
		$this->assertStringContainsString( 'Terms &amp; Conditions</summary>', $out );
		$this->assertStringNotContainsString( '&amp;amp;', $out );
	}

	public function test_block_render_output_has_no_schema() {
		// The block's render output must contain no JSON-LD. This fails if a
		// future change makes the render seams echo FAQPage schema.
		$out = do_blocks( self::faq_block( '<!-- wp:paragraph --><p>A.</p><!-- /wp:paragraph -->' ) );

		$this->assertStringNotContainsString( 'application/ld+json', $out );
		$this->assertStringNotContainsString( 'FAQPage', $out );
	}

	public function test_shortcode_still_emits_faqpage_schema() {
		// Pin the block-vs-shortcode boundary: the legacy shortcode path DOES
		// still populate + emit FAQPage (back-compat). 'force' mode bypasses the
		// SEO-plugin coexistence gate.
		update_option( 'zehoro_faq_schema_mode', 'force' );
		$faq = new FAQ();
		$faq->render_shortcode( [ 'question' => 'Q?' ], 'Answer.' );

		ob_start();
		$faq->output_schema();
		$emitted = (string) ob_get_clean();

		$this->assertStringContainsString( 'FAQPage', $emitted );
	}
}
