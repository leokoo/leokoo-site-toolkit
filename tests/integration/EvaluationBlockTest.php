<?php
/**
 * "We Tested" evaluation block — the render seam (card), the auto-averaged
 * score, and the self-serving-guardrailed Review JSON-LD. Plus the migrator
 * step that activates this NEW default module on existing sites.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\Evaluation;
use Zehoro\Migration\ZehoroRenameMigrator;

class EvaluationBlockTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Evaluation::reset_schema_latch(); // one-Review-per-page latch is static
	}

	private function third_party_attrs( array $override = [] ): array {
		return array_merge( [
			'subjectName'  => 'Acme Widget 3000',
			'subjectUrl'   => 'https://acme.example.com/widget',
			'itemType'     => 'Product',
			'criteria'     => [ [ 'name' => 'Build', 'score' => 4 ], [ 'name' => 'Value', 'score' => 5 ] ],
			'methodology'  => 'Used it daily for three weeks.',
			'verdict'      => 'A solid buy.',
			'pros'         => [ 'Sturdy' ],
			'cons'         => [ 'Pricey' ],
			'emitSchema'   => true,
			'headingLevel' => 3,
		], $override );
	}

	private function schema_from( string $html ): ?array {
		if ( ! preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $html, $m ) ) {
			return null;
		}
		return json_decode( $m[1], true );
	}

	// -------------------------------------------------------------------------
	// Card
	// -------------------------------------------------------------------------

	public function test_card_renders_all_sections() {
		$html = Evaluation::render_block( $this->third_party_attrs() );

		$this->assertStringContainsString( 'Acme Widget 3000', $html );
		$this->assertStringContainsString( '<h3 class="zehoro-eval__subject"', $html );
		$this->assertStringContainsString( '4.5', $html, 'overall score = (4+5)/2' );
		$this->assertStringContainsString( 'Used it daily for three weeks.', $html );
		$this->assertStringContainsString( '>Build<', $html );
		$this->assertStringContainsString( '<li>Sturdy</li>', $html );
		$this->assertStringContainsString( '<li>Pricey</li>', $html );
		$this->assertStringContainsString( 'A solid buy.', $html );
	}

	public function test_average_only_counts_scored_criteria() {
		$html = Evaluation::render_block( $this->third_party_attrs( [
			'criteria' => [ [ 'name' => 'A', 'score' => 4 ], [ 'name' => 'B', 'score' => 0 ] ],
		] ) );
		// avg of the single scored criterion = 4.0, NOT (4+0)/2 = 2.0
		$this->assertStringContainsString( '<span class="zehoro-eval__score-num">4.0</span>', $html );
	}

	public function test_empty_renders_nothing() {
		$this->assertSame( '', Evaluation::render_block( [] ) );
		$this->assertSame( '', Evaluation::render_block( [ 'criteria' => [], 'pros' => [], 'cons' => [] ] ) );
	}

	public function test_subject_script_is_neutralized_in_card_and_schema() {
		$html = Evaluation::render_block( $this->third_party_attrs( [
			'subjectName' => 'Widget</script><script>alert(1)</script>',
		] ) );

		// No live breakout anywhere in the output.
		$this->assertStringNotContainsString( '<script>alert(1)', $html );

		// Isolate the JSON-LD block. Its raw text must carry NO literal <script /
		// </script — yet the full payload must still round-trip through the
		// decoded JSON. Both true at once is only possible via hex-escaping.
		preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $html, $m );
		$this->assertNotEmpty( $m, 'a Review schema block is present' );
		$raw = $m[1];
		$this->assertStringNotContainsString( '<script', $raw, 'no literal opening tag inside the JSON-LD' );
		$this->assertStringNotContainsString( '</script', $raw, 'no literal breakout inside the JSON-LD' );
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded, 'JSON-LD is still valid JSON' );
		$this->assertStringContainsString( 'alert(1)', $decoded['itemReviewed']['name'], 'payload preserved, escaped' );
	}

	// -------------------------------------------------------------------------
	// Guardrailed schema
	// -------------------------------------------------------------------------

	public function test_schema_emitted_for_third_party_subject() {
		$schema = $this->schema_from( Evaluation::render_block( $this->third_party_attrs() ) );

		$this->assertIsArray( $schema );
		$this->assertSame( 'Review', $schema['@type'] );
		$this->assertSame( 'Acme Widget 3000', $schema['itemReviewed']['name'] );
		$this->assertSame( 'Product', $schema['itemReviewed']['@type'] );
		$this->assertEquals( 4.5, $schema['reviewRating']['ratingValue'] );
		$this->assertSame( 'Person', $schema['author']['@type'] );
	}

	public function test_schema_suppressed_when_subject_is_the_site_name() {
		$html = Evaluation::render_block( $this->third_party_attrs( [
			'subjectName' => get_bloginfo( 'name' ),
			'subjectUrl'  => '',
		] ) );
		$this->assertNull( $this->schema_from( $html ), 'self-serving: reviewing our own brand' );
		$this->assertStringContainsString( 'zehoro-eval__subject', $html, 'card still renders' );
	}

	public function test_schema_suppressed_when_subject_url_is_same_domain() {
		$html = Evaluation::render_block( $this->third_party_attrs( [
			'subjectUrl' => home_url( '/our-own-product' ),
		] ) );
		$this->assertNull( $this->schema_from( $html ), 'self-serving: subject on our own domain' );
	}

	public function test_schema_suppressed_for_www_host_variant() {
		// home is example.org; www.example.org is the SAME site.
		$html = Evaluation::render_block( $this->third_party_attrs( [
			'subjectUrl' => str_replace( '://', '://www.', home_url( '/product' ) ),
		] ) );
		$this->assertNull( $this->schema_from( $html ), 'www variant is the same host' );
	}

	public function test_schema_suppressed_for_subdomain() {
		$html = Evaluation::render_block( $this->third_party_attrs( [
			'subjectUrl' => str_replace( '://', '://shop.', home_url( '/product' ) ),
		] ) );
		$this->assertNull( $this->schema_from( $html ), 'a subdomain of the site is still us' );
	}

	public function test_schema_emitted_for_similar_but_different_domain() {
		// home is example.org; "not<site>" and same-TLD siblings must NOT over-match.
		$host = wp_parse_url( home_url(), PHP_URL_HOST ); // e.g. example.org
		foreach ( [ 'not' . $host, 'my' . $host ] as $other ) {
			Evaluation::reset_schema_latch(); // each is a fresh "page"
			$html = Evaluation::render_block( $this->third_party_attrs( [
				'subjectUrl' => 'https://' . $other . '/product',
			] ) );
			$this->assertNotNull( $this->schema_from( $html ), "$other is a genuine third party — must emit" );
		}
	}

	public function test_schema_suppressed_for_relative_subject_url() {
		$html = Evaluation::render_block( $this->third_party_attrs( [
			'subjectUrl' => '/our-own-product',
		] ) );
		$this->assertNull( $this->schema_from( $html ), 'a host-less/relative URL resolves to this site' );
	}

	public function test_defer_review_schema_filter_suppresses() {
		add_filter( 'zehoro/evaluation/defer_review_schema', '__return_true' );
		$html = Evaluation::render_block( $this->third_party_attrs() );
		remove_filter( 'zehoro/evaluation/defer_review_schema', '__return_true' );
		$this->assertNull( $this->schema_from( $html ), 'another plugin owns the Review schema' );
	}

	public function test_self_serving_filter_ignores_non_boolean_return() {
		// A buggy callback returning null must NOT un-suppress a detected self-review.
		add_filter( 'zehoro/evaluation/is_self_serving', static function () {
			return null;
		} );
		$detected = Evaluation::is_self_serving( [ 'subjectName' => get_bloginfo( 'name' ) ] );
		remove_all_filters( 'zehoro/evaluation/is_self_serving' );
		$this->assertTrue( $detected, 'null filter return falls back to the detected verdict' );
	}

	public function test_proscons_heading_nests_under_subject_level() {
		$html = Evaluation::render_block( $this->third_party_attrs( [ 'headingLevel' => 2 ] ) );
		$this->assertStringContainsString( '<h2 class="zehoro-eval__subject"', $html );
		$this->assertStringContainsString( '<h3 class="zehoro-eval__proscons-heading">', $html, 'proscons nests one level below H2' );
		$this->assertStringNotContainsString( '<h4 class="zehoro-eval__proscons-heading">', $html );
	}

	public function test_schema_suppressed_when_toggle_off() {
		$html = Evaluation::render_block( $this->third_party_attrs( [ 'emitSchema' => false ] ) );
		$this->assertNull( $this->schema_from( $html ) );
	}

	public function test_schema_suppressed_without_a_score() {
		$html = Evaluation::render_block( $this->third_party_attrs( [ 'criteria' => [] ] ) );
		$this->assertNull( $this->schema_from( $html ), 'no rating → no Review' );
	}

	public function test_bad_item_type_falls_back_to_product() {
		$schema = $this->schema_from( Evaluation::render_block( $this->third_party_attrs( [
			'itemType' => 'DefinitelyNotAllowed',
		] ) ) );
		$this->assertSame( 'Product', $schema['itemReviewed']['@type'] );
	}

	public function test_one_review_per_page() {
		$attrs = $this->third_party_attrs();
		$first  = Evaluation::render_block( $attrs );
		$second = Evaluation::render_block( $attrs ); // same page, latch already set
		$this->assertNotNull( $this->schema_from( $first ) );
		$this->assertNull( $this->schema_from( $second ), 'only the first Evaluation emits schema' );
	}

	public function test_is_self_serving_filter_can_force_suppression() {
		add_filter( 'zehoro/evaluation/is_self_serving', '__return_true' );
		$html = Evaluation::render_block( $this->third_party_attrs() );
		remove_filter( 'zehoro/evaluation/is_self_serving', '__return_true' );
		$this->assertNull( $this->schema_from( $html ) );
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function test_block_is_registered() {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'zehoro/evaluation' ) );
	}

	// -------------------------------------------------------------------------
	// New-module activation migrator (ship-dark guard)
	// -------------------------------------------------------------------------

	public function test_new_default_module_added_to_existing_allowlist() {
		ZehoroRenameMigrator::reset_flag();
		update_option( 'zehoro_active_modules', [ 'key_takeaways', 'faq' ] ); // pre-existing site
		ZehoroRenameMigrator::activate_new_default_modules();

		$this->assertContains( 'evaluation', get_option( 'zehoro_active_modules' ) );
	}

	public function test_user_deactivation_of_new_module_persists() {
		ZehoroRenameMigrator::reset_flag();
		update_option( 'zehoro_active_modules', [ 'faq' ] );
		ZehoroRenameMigrator::activate_new_default_modules(); // adds evaluation + marks seen

		update_option( 'zehoro_active_modules', [ 'faq' ] ); // user turns it back off
		ZehoroRenameMigrator::activate_new_default_modules(); // must NOT resurrect it

		$this->assertNotContains( 'evaluation', get_option( 'zehoro_active_modules' ) );
	}

	public function test_no_stored_allowlist_is_a_noop() {
		ZehoroRenameMigrator::reset_flag();
		delete_option( 'zehoro_active_modules' );
		ZehoroRenameMigrator::activate_new_default_modules();
		$this->assertFalse( get_option( 'zehoro_active_modules' ), 'fresh install seeds defaults elsewhere' );
	}
}
