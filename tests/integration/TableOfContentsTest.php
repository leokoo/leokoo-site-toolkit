<?php
/**
 * TableOfContents — DOM-mutation regression coverage.
 *
 * TOC is in the same regression class as ContentStream (LKST Pro): both
 * mutate the_content via a preg_replace_callback, both must short-circuit
 * for builder previews, both inject markup at a specific structural point.
 *
 * Testing strategy:
 *   - Use real posts with crafted Gutenberg-style heading HTML so the regex
 *     parser sees actual content shape, not a sanitised abstraction.
 *   - Test the public surface (process_content, render_shortcode,
 *     preparse_toc_headings, sanitize_settings). No private methods.
 *   - For builder-preview short-circuits, set $_GET — restore in tear_down.
 *   - For under-2-headings guard, the module's own comment (Bug 4) says
 *     the [lkst_toc] placeholder must NOT render as visible text. Verify.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\TableOfContents;

class TableOfContentsTest extends WP_UnitTestCase {

    private TableOfContents $toc;

    public function set_up(): void {
        parent::set_up();
        $this->toc = new TableOfContents();

        // Reset module globals between tests.
        global $lkst_toc_items, $lkst_toc_processing;
        $lkst_toc_items      = null;
        $lkst_toc_processing = false;

        // Default settings — auto insertion on `post` only.
        update_option( 'lkst_toc_settings', [
            'post_types' => [ 'post' ],
            'insertion'  => 'auto',
        ] );
    }

    public function tear_down(): void {
        delete_option( 'lkst_toc_settings' );
        global $lkst_toc_items, $lkst_toc_processing;
        $lkst_toc_items      = null;
        $lkst_toc_processing = false;
        unset( $_GET['bricks'], $_GET['etchwp'], $_GET['elementor-preview'] );
        parent::tear_down();
    }

    /** Create a post with N H2 headings and a body paragraph between each. */
    private function post_with_headings( int $h2_count, string $extra_html = '' ): int {
        $body = '';
        for ( $i = 1; $i <= $h2_count; $i++ ) {
            $body .= "<h2>Section {$i}</h2>\n<p>Body of section {$i}.</p>\n";
        }
        $body .= $extra_html;

        $post_id = $this->factory->post->create( [
            'post_type'    => 'post',
            'post_status'  => 'publish',
            'post_content' => $body,
        ] );
        $this->go_to( get_permalink( $post_id ) );

        // go_to() fires the `wp` action, which fires preparse_toc_headings(),
        // which populates global $lkst_toc_items from the seeded body. For
        // tests that pass a DIFFERENT body to process_content(), we don't
        // want the seeded items leaking through — process_content() prefers
        // the global over re-parsing. Clear it so each test starts fresh.
        global $lkst_toc_items;
        $lkst_toc_items = null;

        // Also clear the transient cache so re-running preparse_toc_headings()
        // in tests that exercise it directly hits the cold path.
        delete_transient( 'lkst_toc_' . $post_id );

        return $post_id;
    }

    // -------------------------------------------------------------------------
    // sanitize_settings
    // -------------------------------------------------------------------------

    public function test_sanitize_non_array_returns_defaults() {
        $out = $this->toc->sanitize_settings( 'garbage' );
        $this->assertSame( TableOfContents::get_defaults(), $out );
    }

    public function test_sanitize_drops_unknown_post_types() {
        $out = $this->toc->sanitize_settings( [
            'post_types' => [ 'post', 'nonexistent_pt', 'reviews' ],
            'insertion'  => 'auto',
        ] );
        // `reviews` does not exist in the test env — only `post` survives.
        $this->assertSame( [ 'post' ], $out['post_types'] );
    }

    public function test_sanitize_invalid_insertion_falls_back_to_auto() {
        $out = $this->toc->sanitize_settings( [
            'post_types' => [ 'post' ],
            'insertion'  => 'whatever',
        ] );
        $this->assertSame( 'auto', $out['insertion'] );
    }

    public function test_sanitize_accepts_shortcode_insertion() {
        $out = $this->toc->sanitize_settings( [
            'post_types' => [ 'post' ],
            'insertion'  => 'shortcode',
        ] );
        $this->assertSame( 'shortcode', $out['insertion'] );
    }

    // -------------------------------------------------------------------------
    // process_content — auto insertion
    // -------------------------------------------------------------------------

    public function test_under_two_headings_returns_content_unchanged() {
        // 1 heading — should NOT inject a TOC.
        $this->post_with_headings( 1 );
        $content = "<h2>Only One</h2>\n<p>body</p>";

        $output = $this->toc->process_content( $content );
        $this->assertStringNotContainsString( 'zehoro-toc-wrapper', $output );
    }

    public function test_two_or_more_headings_inject_toc_at_top_under_auto() {
        $this->post_with_headings( 2 );
        $content = "<h2>One</h2>\n<p>a</p>\n<h2>Two</h2>\n<p>b</p>";

        $output = $this->toc->process_content( $content );

        // TOC HTML present.
        $this->assertStringContainsString( 'zehoro-toc-wrapper', $output );
        // Both heading anchors listed.
        $this->assertStringContainsString( 'href="#one"', $output );
        $this->assertStringContainsString( 'href="#two"', $output );
        // TOC appears BEFORE the first heading (auto = prepend).
        $this->assertLessThan(
            strpos( $output, '<h2' ),
            strpos( $output, 'zehoro-toc-wrapper' ),
            'Auto insertion must prepend the TOC before the first heading'
        );
    }

    public function test_anchor_ids_injected_for_headings_without_id() {
        $this->post_with_headings( 2 );
        $content = "<h2>First Heading</h2>\n<p>x</p>\n<h2>Second</h2>\n<p>y</p>";

        $output = $this->toc->process_content( $content );

        // Soften from '<h2 id="..."' to just 'id="..."' so a legitimate
        // refactor that adds a class attribute to the heading doesn't break
        // the test. The CONTRACT we're verifying is "the anchor ID is present
        // on a heading," not the exact attribute order.
        $this->assertStringContainsString( 'id="first-heading"', $output );
        $this->assertStringContainsString( 'id="second"', $output );
    }

    public function test_existing_heading_ids_preserved() {
        $this->post_with_headings( 2 );
        $content = '<h2 id="custom-anchor">First</h2><p>x</p><h2>Second</h2><p>y</p>';

        $output = $this->toc->process_content( $content );

        // Existing ID kept.
        $this->assertStringContainsString( 'id="custom-anchor"', $output );
        // TOC links to the kept ID.
        $this->assertStringContainsString( 'href="#custom-anchor"', $output );
    }

    public function test_h3_headings_get_depth_3_class() {
        $this->post_with_headings( 1 );
        $content = "<h2>Top</h2>\n<h3>Sub</h3>\n<p>body</p>";

        $output = $this->toc->process_content( $content );

        $this->assertStringContainsString( 'zehoro-toc-depth-2', $output );
        $this->assertStringContainsString( 'zehoro-toc-depth-3', $output );
    }

    // -------------------------------------------------------------------------
    // process_content — shortcode insertion mode
    // -------------------------------------------------------------------------

    public function test_shortcode_mode_replaces_placeholder_with_toc() {
        update_option( 'lkst_toc_settings', [
            'post_types' => [ 'post' ],
            'insertion'  => 'shortcode',
        ] );
        $this->post_with_headings( 2 );
        $content = "<p>intro</p>\n[lkst_toc]\n<h2>One</h2>\n<p>a</p>\n<h2>Two</h2>";

        $output = $this->toc->process_content( $content );

        $this->assertStringContainsString( 'zehoro-toc-wrapper', $output );
        $this->assertStringNotContainsString( '[lkst_toc]', $output );
    }

    public function test_shortcode_mode_does_not_auto_inject() {
        update_option( 'lkst_toc_settings', [
            'post_types' => [ 'post' ],
            'insertion'  => 'shortcode',
        ] );
        $this->post_with_headings( 2 );
        // No [lkst_toc] placeholder in the content.
        $content = "<h2>One</h2>\n<p>a</p>\n<h2>Two</h2>\n<p>b</p>";

        $output = $this->toc->process_content( $content );

        $this->assertStringNotContainsString( 'zehoro-toc-wrapper', $output );
    }

    public function test_placeholder_stripped_when_under_two_headings() {
        // Bug 4 in the module: [lkst_toc] must not render as visible text
        // if there aren't enough headings to build the TOC.
        update_option( 'lkst_toc_settings', [
            'post_types' => [ 'post' ],
            'insertion'  => 'shortcode',
        ] );
        $this->post_with_headings( 1 );
        $content = "<h2>Only One</h2>\n<p>a</p>\n[lkst_toc]\n<p>b</p>";

        $output = $this->toc->process_content( $content );

        $this->assertStringNotContainsString( '[lkst_toc]', $output );
        $this->assertStringNotContainsString( 'zehoro-toc-wrapper', $output );
    }

    // -------------------------------------------------------------------------
    // process_content — short-circuits
    // -------------------------------------------------------------------------

    public function test_post_type_not_in_settings_skips_toc() {
        update_option( 'lkst_toc_settings', [
            'post_types' => [ 'page' ], // doesn't include `post`
            'insertion'  => 'auto',
        ] );
        $this->post_with_headings( 3 );
        $content = "<h2>A</h2><h2>B</h2><h2>C</h2>";

        $output = $this->toc->process_content( $content );

        $this->assertStringNotContainsString( 'zehoro-toc-wrapper', $output );
    }

    public function test_bricks_preview_short_circuits() {
        // Order matters: post_with_headings() calls go_to(), which rebuilds
        // $_GET from the URL's query string and wipes anything we set first.
        // Set the preview flag AFTER navigating.
        $this->post_with_headings( 3 );
        $_GET['bricks'] = '1';
        $content = "<h2>A</h2><h2>B</h2><h2>C</h2>";

        $output = $this->toc->process_content( $content );

        $this->assertStringNotContainsString( 'zehoro-toc-wrapper', $output );
        $this->assertSame( $content, $output );
    }

    public function test_etchwp_preview_short_circuits() {
        $this->post_with_headings( 3 );
        $_GET['etchwp'] = '1';
        $content = "<h2>A</h2><h2>B</h2><h2>C</h2>";

        $output = $this->toc->process_content( $content );

        $this->assertSame( $content, $output );
    }

    public function test_elementor_preview_short_circuits() {
        $this->post_with_headings( 3 );
        $_GET['elementor-preview'] = '1';
        $content = "<h2>A</h2><h2>B</h2><h2>C</h2>";

        $output = $this->toc->process_content( $content );

        $this->assertSame( $content, $output );
    }

    public function test_processing_flag_reentry_guard() {
        // Simulate a nested the_content call (e.g. a related-posts loop
        // inside a post template that re-fires the_content filter chain).
        // Module sets a global flag to bail out of re-entry — verify.
        global $lkst_toc_processing;
        $lkst_toc_processing = true;

        $this->post_with_headings( 3 );
        $content = "<h2>A</h2><h2>B</h2><h2>C</h2>";

        $output = $this->toc->process_content( $content );

        // Re-entry guard returns content unchanged.
        $this->assertSame( $content, $output );

        $lkst_toc_processing = false;
    }

    // -------------------------------------------------------------------------
    // preparse_toc_headings + render_shortcode (sidebar/builder placement)
    // -------------------------------------------------------------------------

    public function test_preparse_populates_global_items() {
        $post_id = $this->post_with_headings( 2 );

        global $lkst_toc_items;
        $lkst_toc_items = null;

        $this->toc->preparse_toc_headings();

        $this->assertIsArray( $lkst_toc_items );
        $this->assertCount( 2, $lkst_toc_items );
        $this->assertSame( 'Section 1', $lkst_toc_items[0]['text'] );
        $this->assertSame( 'section-1', $lkst_toc_items[0]['id'] );
    }

    public function test_render_shortcode_returns_empty_with_no_items() {
        global $lkst_toc_items;
        $lkst_toc_items = [];

        $output = $this->toc->render_shortcode();

        $this->assertSame( '', $output );
    }

    public function test_render_shortcode_returns_empty_with_one_item() {
        global $lkst_toc_items;
        $lkst_toc_items = [ [ 'level' => '2', 'id' => 'only', 'text' => 'Only' ] ];

        $output = $this->toc->render_shortcode();

        $this->assertSame(
            '',
            $output,
            'Single-item TOC must not render — keeps sidebar shortcodes invisible until useful'
        );
    }

    public function test_render_shortcode_returns_toc_with_two_or_more_items() {
        global $lkst_toc_items;
        $lkst_toc_items = [
            [ 'level' => '2', 'id' => 'a', 'text' => 'A' ],
            [ 'level' => '2', 'id' => 'b', 'text' => 'B' ],
        ];

        $output = $this->toc->render_shortcode();

        $this->assertStringContainsString( 'zehoro-toc-wrapper', $output );
        $this->assertStringContainsString( 'href="#a"', $output );
        $this->assertStringContainsString( 'href="#b"', $output );
    }

    // -------------------------------------------------------------------------
    // Security + robustness regression guards
    // -------------------------------------------------------------------------

    public function test_toc_js_never_reintroduces_innerhtml() {
        // Stored DOM-XSS guard: the scroll-spy must build its label with
        // textContent / createElement, never innerHTML — an entity-encoded
        // heading ("&lt;img onerror=…&gt;") would otherwise round-trip back
        // into live markup when the marquee re-renders the active label.
        $js = file_get_contents( dirname( __DIR__, 2 ) . '/assets/toc.js' );
        $this->assertIsString( $js );
        // Guard the sink, not the token: assignment TO innerHTML is the DOM-XSS
        // vector (reading it, or naming it in a comment, is harmless).
        $this->assertDoesNotMatchRegularExpression(
            '/\.innerHTML\s*\+?=/',
            $js,
            'toc.js must not assign to innerHTML — heading text is untrusted (stored DOM-XSS).'
        );
    }

    public function test_process_content_is_null_safe() {
        // the_content can hand a filter null (an upstream filter returning
        // null, an empty draft). The strict return type must not fatal —
        // process_content coerces to string before touching it.
        $this->post_with_headings( 1 );
        $output = $this->toc->process_content( null );
        $this->assertIsString( $output );
    }

    public function test_toc_markup_uses_translatable_default_label() {
        // The front-end label is now translatable; the English source string
        // must still render by default so untranslated sites are unchanged.
        global $lkst_toc_items;
        $lkst_toc_items = [
            [ 'level' => '2', 'id' => 'a', 'text' => 'A' ],
            [ 'level' => '2', 'id' => 'b', 'text' => 'B' ],
        ];
        $output = $this->toc->render_shortcode();
        $this->assertStringContainsString( 'Sections in this article', $output );
        $this->assertStringContainsString( 'BROWSE', $output );
    }
}
