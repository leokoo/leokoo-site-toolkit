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

	public function test_item_question_is_escaped() {
		$html = FAQ::render_item( [ 'question' => '</summary><script>alert(1)</script>' ], '<p>A</p>' );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '</summary><script>', $html );
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

	public function test_end_to_end_via_do_blocks() {
		$content =
			'<!-- wp:zehoro/faq -->' .
			'<!-- wp:zehoro/faq-item {"question":"What is it?"} -->' .
			'<!-- wp:paragraph --><p>It is a thing.</p><!-- /wp:paragraph -->' .
			'<!-- /wp:zehoro/faq-item -->' .
			'<!-- /wp:zehoro/faq -->';

		$out = do_blocks( $content );

		$this->assertStringContainsString( 'wp-block-zehoro-faq', $out );
		$this->assertStringContainsString( '<summary class="zehoro-faq__question">What is it?</summary>', $out );
		$this->assertStringContainsString( 'zehoro-faq__answer', $out );
		$this->assertStringContainsString( 'It is a thing.', $out );
		$this->assertStringContainsString( '<details', $out );
	}

	public function test_block_emits_no_schema() {
		// Per the 2026 decision: the FAQ block emits NO FAQPage JSON-LD (dead
		// rich results); the value is the accessible accordion.
		$content =
			'<!-- wp:zehoro/faq --><!-- wp:zehoro/faq-item {"question":"Q?"} -->' .
			'<!-- wp:paragraph --><p>A.</p><!-- /wp:paragraph -->' .
			'<!-- /wp:zehoro/faq-item --><!-- /wp:zehoro/faq -->';

		$out = do_blocks( $content );

		$this->assertStringNotContainsString( 'application/ld+json', $out );
		$this->assertStringNotContainsString( 'FAQPage', $out );
	}
}
