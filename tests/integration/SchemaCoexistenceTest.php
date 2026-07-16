<?php
/**
 * Schema coexistence — FAQ (and, in Pro, Last Updated) honors the CENTRAL policy.
 *
 * Before: only Article Schema obeyed the central `zehoro_schema_output`
 * (auto|always|never) + the `zehoro/emit_schema` filter. FAQ ran a parallel
 * policy and Last Updated had none — so a user-level "never" silenced Article
 * Schema but left duplicate FAQPage / dateModified JSON-LD. These pin that the
 * Free emitters route through `SeoPlugin::should_emit_schema()`.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\FAQ;
use Zehoro\Compat\SeoPlugin;

class SchemaCoexistenceTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( SeoPlugin::OPTION );
		remove_all_filters( 'zehoro/emit_schema' );
		parent::tear_down();
	}

	private function faq_schema(): string {
		$faq = new FAQ();
		$faq->render_shortcode( [ 'question' => 'Does it coexist?' ], 'Yes.' );
		ob_start();
		$faq->output_schema();
		return (string) ob_get_clean();
	}

	// ── Default (auto, no SEO plugin) → all emit ──────────────────────────────

	public function test_faq_emits_by_default() {
		$this->assertStringContainsString( '"FAQPage"', $this->faq_schema() );
	}

	// ── Central "never" → silences FAQ + Last Updated too (the fix) ────────────

	public function test_central_never_silences_faq() {
		update_option( SeoPlugin::OPTION, 'never' );
		$out = $this->faq_schema();
		$this->assertStringNotContainsString( '"FAQPage"', $out, 'a global never must silence FAQ schema' );
	}

	// ── The zehoro/emit_schema filter also gates both ─────────────────────────

	public function test_emit_schema_filter_false_silences_faq() {
		add_filter( 'zehoro/emit_schema', '__return_false' );
		$this->assertStringNotContainsString( '"FAQPage"', $this->faq_schema() );
	}

	// ── Per-type off still wins (FAQ) ─────────────────────────────────────────

	public function test_faq_per_type_off_still_disables() {
		update_option( 'zehoro_faq_schema_mode', 'off' );
		$out = $this->faq_schema();
		delete_option( 'zehoro_faq_schema_mode' );
		$this->assertStringNotContainsString( '"FAQPage"', $out );
	}
}
