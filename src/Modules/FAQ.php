<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * FAQ Module (Free Version)
 *
 * Provides a manually placed shortcode [zehoro_faq] for styled accordions.
 * Automatically aggregates all FAQs on the page and outputs FAQPage JSON-LD schema.
 * Features a "Polite Pre-flight Check" to disable schema if an SEO plugin is detected.
 *
 * @package Zehoro\Modules
 */
class FAQ implements ModuleInterface {
    
    private array $faqs = [];

    public static function register(): void {
        Plugin::register_module( 'faq', self::class, [
            'title'         => 'FAQ Accordions',
            'desc'          => 'Beautiful FAQ accordions that automatically generate FAQPage JSON-LD schema. Use [zehoro_faq question="..."]Answer[/zehoro_faq].',
            'default'       => true,
            'settings_page' => 'zehoro-faq'
        ] );
    }

    public function init(): void {
        // Canonical zehoro_faq + legacy lkst_faq alias (existing posts).
        add_shortcode( 'zehoro_faq', [ $this, 'render_shortcode' ] );
        add_shortcode( 'lkst_faq',   [ $this, 'render_shortcode' ] );
        add_action( 'wp_footer', [ $this, 'output_schema' ], 20 );
        add_action( 'init', [ $this, 'register_blocks' ] );

        if ( is_admin() ) {
            $this->register_admin_hooks();
        }
    }

    public function register_blocks(): void {
        register_block_type( ZEHORO_DIR . 'build/faq' );
        register_block_type( ZEHORO_DIR . 'build/faq-item' );
    }

    /**
     * Render seam — the FAQ container. Wraps the rendered faq-item inner blocks.
     * The block emits NO schema (FAQ rich results were retired 2026-05-07; the
     * value is the accessible, quotable accordion).
     */
    public static function render_container( array $attributes, string $content, string $wrapper = '' ): string {
        $content = trim( $content );
        if ( $content === '' ) {
            return '';
        }
        if ( $wrapper === '' ) {
            $wrapper = 'class="zehoro-faq"';
        }
        return sprintf( '<div %s>%s</div>', $wrapper, $content );
    }

    /**
     * Render seam — one FAQ item as an accessible <details>/<summary> accordion.
     * $content is the rendered answer inner blocks (already-escaped core-block
     * output). The question is plain text (the editor disallows inline formats).
     */
    public static function render_item( array $attributes, string $content, string $wrapper = '' ): string {
        $question = trim( wp_strip_all_tags( isset( $attributes['question'] ) ? (string) $attributes['question'] : '' ) );
        $answer   = trim( $content );
        if ( $question === '' && $answer === '' ) {
            return '';
        }
        if ( $wrapper === '' ) {
            $wrapper = 'class="zehoro-faq__item"';
        }
        $open = ! empty( $attributes['startOpen'] ) ? ' open' : '';

        return sprintf(
            '<details %1$s%2$s><summary class="zehoro-faq__question">%3$s</summary><div class="zehoro-faq__answer">%4$s</div></details>',
            $wrapper,
            $open,
            esc_html( $question ),
            $answer
        );
    }

    private function register_admin_hooks(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
    }

    public function register_settings(): void {
        register_setting( 'zehoro_faq_group', 'zehoro_faq_schema_mode', [ 'default' => 'auto', 'sanitize_callback' => 'sanitize_text_field' ] );
    }

    public function register_settings_page(): void {
        add_submenu_page(
            null,
            __( 'FAQ Settings', 'zehoro-toolkit' ),
            __( 'FAQ Settings', 'zehoro-toolkit' ),
            'manage_options',
            'zehoro-faq',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $mode = \Zehoro\Utils\Option::get( 'zehoro_faq_schema_mode', 'auto' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'FAQ Accordion Settings', 'zehoro-toolkit' ); ?></h1>
            <p><?php esc_html_e( 'Use the shortcode to output styled FAQs in your content:', 'zehoro-toolkit' ); ?><br>
            <code>[zehoro_faq question="Your question?"]Your answer.[/zehoro_faq]</code></p>

            <form method="post" action="options.php">
                <?php settings_fields( 'zehoro_faq_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="zehoro_faq_schema_mode"><?php esc_html_e( 'FAQPage Schema (JSON-LD)', 'zehoro-toolkit' ); ?></label></th>
                        <td>
                            <select name="zehoro_faq_schema_mode" id="zehoro_faq_schema_mode">
                                <option value="auto" <?php selected($mode, 'auto'); ?>><?php esc_html_e('Auto (Disable if SEO plugin detected)', 'zehoro-toolkit'); ?></option>
                                <option value="force" <?php selected($mode, 'force'); ?>><?php esc_html_e('Always Output Schema', 'zehoro-toolkit'); ?></option>
                                <option value="off" <?php selected($mode, 'off'); ?>><?php esc_html_e('Never Output Schema', 'zehoro-toolkit'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('To prevent Google Search Console errors, we recommend Auto. We will safely step aside and let your SEO plugin handle schema if we detect Yoast, RankMath, or SureRank are active.', 'zehoro-toolkit'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_shortcode( $atts, $content = null ): string {
        $atts = shortcode_atts( [
            'question' => 'Question?',
        ], $atts, 'lkst_faq' );

        $question = sanitize_text_field( $atts['question'] );
        $answer = do_shortcode( wpautop( wp_kses_post( $content ) ) );

        // Store for schema output later
        $this->faqs[] = [
            'question' => $question,
            'answer'   => wp_strip_all_tags( $answer )
        ];

        // Return HTML accordion
        return sprintf(
            '<details class="lkst-faq-accordion" style="margin-bottom:1.5em; border:1px solid var(--lkst-bg-light, #E2E8F0); border-radius:6px; background-color:#fff;">
                <summary style="font-weight:600; font-size:18px; cursor:pointer; padding:16px 20px; list-style-position:inside; color:var(--lkst-primary-contrast, #2C3E50);">%s</summary>
                <div class="lkst-faq-answer" style="padding:0 20px 20px 20px; line-height:1.6; color:#4A5568;">%s</div>
            </details>',
            esc_html( $question ),
            $answer
        );
    }

    public function output_schema(): void {
        if ( empty( $this->faqs ) ) return;

        // A per-type opt-out, then the CENTRAL schema-coexistence policy
        // (auto-detect / always / never + the zehoro/emit_schema filter): the
        // single source of truth, shared with Article Schema and Last Updated.
        // 'auto'/'always' both mean "on, governed centrally"; only 'off'
        // hard-disables FAQ schema, and a central "never" silences it regardless.
        $faq_mode = \Zehoro\Utils\Option::get( 'zehoro_faq_schema_mode', 'auto' );
        if ( 'off' === $faq_mode ) return;
        // 'force' ("Always Output Schema") is an explicit user choice that must
        // override the coexistence gate — otherwise picking "always" is silently
        // ignored whenever an SEO plugin is active. 'auto' still defers centrally.
        if ( 'force' !== $faq_mode && ! \Zehoro\Compat\SeoPlugin::should_emit_schema() ) {
            echo '<!-- Zehoro: FAQPage schema paused by the schema-coexistence setting (an SEO plugin is active, or output is set to "never"). -->';
            return;
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => []
        ];

        foreach ( $this->faqs as $faq ) {
            $schema['mainEntity'][] = [
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $faq['answer']
                ]
            ];
        }

        echo '<script type="application/ld+json" class="lkst-faq-schema">' . wp_json_encode( $schema ) . '</script>' . "\n";
    }
}