<?php
/**
 * Pros & Cons — the single render seam (ProsCons::render_html) + registration.
 *
 * Covers show modes (both/pros/cons), the empty contract, heading level,
 * custom titles, XSS, wrapper passthrough, and end-to-end do_blocks render.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\ProsCons;

class ProsConsRenderTest extends WP_UnitTestCase {

	public function test_both_columns_render() {
		$html = ProsCons::render_html( [
			'show' => 'both',
			'pros' => '<li>Fast</li><li>Cheap</li>',
			'cons' => '<li>Loud</li>',
		] );

		$this->assertStringContainsString( '<div class="zehoro-pros-cons zehoro-pros-cons--both">', $html );
		$this->assertStringContainsString( 'zehoro-pros-cons__pros', $html );
		$this->assertStringContainsString( 'zehoro-pros-cons__cons', $html );
		$this->assertStringContainsString( '<li>Fast</li>', $html );
		$this->assertStringContainsString( '<li>Loud</li>', $html );
		$this->assertStringContainsString( '>Pros</h3>', $html );
		$this->assertStringContainsString( '>Cons</h3>', $html );
	}

	public function test_pros_only() {
		$html = ProsCons::render_html( [ 'show' => 'pros', 'pros' => '<li>Good</li>', 'cons' => '<li>Bad</li>' ] );
		$this->assertStringContainsString( 'zehoro-pros-cons--pros', $html );
		$this->assertStringContainsString( 'zehoro-pros-cons__pros', $html );
		$this->assertStringNotContainsString( 'zehoro-pros-cons__cons', $html );
		$this->assertStringNotContainsString( '<li>Bad</li>', $html );
	}

	public function test_cons_only() {
		$html = ProsCons::render_html( [ 'show' => 'cons', 'pros' => '<li>Good</li>', 'cons' => '<li>Bad</li>' ] );
		$this->assertStringContainsString( 'zehoro-pros-cons--cons', $html );
		$this->assertStringContainsString( 'zehoro-pros-cons__cons', $html );
		$this->assertStringNotContainsString( 'zehoro-pros-cons__pros', $html );
	}

	public function test_empty_renders_nothing() {
		$this->assertSame( '', ProsCons::render_html( [ 'show' => 'both' ] ) );
		$this->assertSame( '', ProsCons::render_html( [ 'show' => 'both', 'pros' => '<li></li>', 'cons' => '' ] ) );
	}

	public function test_custom_titles() {
		$html = ProsCons::render_html( [
			'show'      => 'both',
			'prosTitle' => 'Upsides',
			'consTitle' => 'Downsides',
			'pros'      => '<li>x</li>',
			'cons'      => '<li>y</li>',
		] );
		$this->assertStringContainsString( '>Upsides</h3>', $html );
		$this->assertStringContainsString( '>Downsides</h3>', $html );
	}

	public function test_heading_level_clamps() {
		foreach ( [ 1, 5, 9 ] as $bad ) {
			$html = ProsCons::render_html( [ 'show' => 'pros', 'headingLevel' => $bad, 'pros' => '<li>x</li>' ] );
			$this->assertStringContainsString( '<h3 class="zehoro-pros-cons__title">', $html, "level {$bad} clamps to h3" );
		}
		$html = ProsCons::render_html( [ 'show' => 'pros', 'headingLevel' => 2, 'pros' => '<li>x</li>' ] );
		$this->assertStringContainsString( '<h2 class="zehoro-pros-cons__title">', $html );
	}

	public function test_script_is_stripped() {
		$html = ProsCons::render_html( [ 'show' => 'pros', 'pros' => '<li>ok<script>alert(1)</script></li>' ] );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( 'ok', $html );
	}

	public function test_wrapper_passthrough() {
		$html = ProsCons::render_html(
			[ 'show' => 'pros', 'pros' => '<li>x</li>' ],
			'class="zehoro-pros-cons zehoro-pros-cons--pros wp-block-zehoro-pros-cons" id="pc-1"'
		);
		$this->assertStringContainsString( 'id="pc-1"', $html );
		$this->assertStringContainsString( 'wp-block-zehoro-pros-cons', $html );
	}

	// -------------------------------------------------------------------------
	// Registration + end-to-end
	// -------------------------------------------------------------------------

	public function test_block_type_is_registered() {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'zehoro/pros-cons' ) );
	}

	public function test_end_to_end_render_via_do_blocks() {
		$content = '<!-- wp:zehoro/pros-cons {"show":"both","pros":"<li>Alpha</li>","cons":"<li>Beta</li>"} /-->';
		$out     = do_blocks( $content );

		$this->assertStringContainsString( 'wp-block-zehoro-pros-cons', $out );
		$this->assertStringContainsString( '<li>Alpha</li>', $out );
		$this->assertStringContainsString( '<li>Beta</li>', $out );
	}
}
