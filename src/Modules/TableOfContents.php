<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;

use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Table of Contents Module
 *
 * Parses H2/H3 headings, injects anchor IDs, and renders a collapsible TOC.
 * The preparse step reads raw post_content (no do_shortcode) so:
 *   - shortcodes are not expanded unnecessarily early
 *   - expensive form/gallery shortcodes don\'t fire outside their normal context
 *   - Gutenberg block HTML already contains headings as plain HTML tags
 * Usage: [zehoro_toc]
 *
 * @package Zehoro\Modules
 */
class TableOfContents implements \Zehoro\Core\ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'table_of_contents', self::class, [
            'title'   => 'Table of Contents',
            'desc'    => 'Wirecutter-style TOC. Auto-injects at the top of posts, or use [zehoro_toc].',
            'default' => true,
            'settings_page' => 'zehoro-toc-settings'
        ] );
    }


    public function init(): void {
        if ( is_admin() ) {
            add_action( 'admin_init', [ $this, 'register_settings' ] );
        }
        add_action( 'wp',          [ $this, 'preparse_toc_headings' ], 10 );
        add_filter( 'the_content', [ $this, 'process_content' ], 15 );
        // The TOC auto-injects, so the global enqueue gate (which scans
        // STORED content) can't see it — force the stylesheet + CSS
        // variables to load when a TOC of ≥2 entries is actually coming.
        add_filter( 'zehoro/load_global_styles', [ $this, 'force_styles_when_toc_renders' ] );
        // toc.js (the click-to-open handler) must load whenever a TOC will
        // render — NOT only when the [zehoro_toc] shortcode is present, or the
        // auto-injected TOC renders styled but inert (can't expand).
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_toc_js' ] );
        // Canonical zehoro_toc + legacy lkst_toc alias (existing posts).
        add_shortcode( 'zehoro_toc', [ $this, 'render_shortcode' ] );
        add_shortcode( 'lkst_toc',   [ $this, 'render_shortcode' ] );
    }

    public static function get_defaults(): array {
        return [
            'post_types' => [ 'post', 'reviews', 'buying-guides' ],
            'insertion'  => 'auto',
        ];
    }

    /**
     * `zehoro/load_global_styles` filter: force the shared stylesheet (and
     * its CSS variables) to load when a TOC will actually render on this
     * view — the auto-inject case the default gate can't detect.
     *
     * Runs after `preparse_toc_headings` (on `wp`) has populated
     * `$lkst_toc_items`, so we know whether a ≥2-entry TOC is coming and
     * load CSS precisely then, preserving the optimisation everywhere else.
     */
    public function force_styles_when_toc_renders( $load ) {
        if ( $load ) return $load;
        return $this->toc_will_render() ? true : $load;
    }

    /**
     * Enqueue toc.js exactly when the TOC will render — the auto-inject case
     * the old shortcode-only gate missed, which left the styled TOC unable to
     * open. Runs on `wp_enqueue_scripts`, after `preparse_toc_headings` (on
     * `wp`) has populated the heading list.
     */
    public function maybe_enqueue_toc_js(): void {
        if ( $this->toc_will_render() ) {
            wp_enqueue_script( 'zehoro-toc', ZEHORO_URL . 'assets/toc.js', [], ZEHORO_VERSION, true );
            // Keep the JS scroll-to-top reset label in sync with the translated
            // server-rendered label (build_toc_html uses the same string).
            wp_localize_script( 'zehoro-toc', 'zehoroTocL10n', [
                'defaultLabel' => __( 'Sections in this article', 'zehoro-toolkit' ),
            ] );
        }
    }

    /**
     * Single source of truth for "is a ≥2-entry TOC actually rendering on this
     * view?" — shared by the style gate and the script enqueue so they can
     * never disagree. Relies on the `$lkst_toc_items` global populated by
     * preparse_toc_headings() on the `wp` hook.
     */
    private function toc_will_render(): bool {
        if ( ! is_singular() ) return false;

        global $lkst_toc_items;
        if ( empty( $lkst_toc_items ) || count( $lkst_toc_items ) < 2 ) return false;

        // Shortcode mode only injects where the placeholder is present.
        $settings = \Zehoro\Utils\Option::get( 'zehoro_toc_settings', static::get_defaults() );
        if ( ( $settings['insertion'] ?? 'auto' ) === 'shortcode' ) {
            $post = get_post();
            if ( ! $post
                || ( strpos( $post->post_content, '[zehoro_toc]' ) === false
                  && strpos( $post->post_content, '[lkst_toc]' ) === false ) ) {
                return false;
            }
        }

        return true;
    }

    public function register_settings(): void {
        register_setting( 'zehoro_toc_group', 'zehoro_toc_settings', [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => static::get_defaults(),
        ] );
    }

    public function sanitize_settings( $input ): array {
        if ( ! is_array( $input ) ) return static::get_defaults();
        $out = [ 'post_types' => [] ];
        if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            $valid_pts = array_keys( get_post_types( [ 'public' => true ] ) );
            foreach ( $input['post_types'] as $pt ) {
                $pt = sanitize_key( $pt );
                if ( in_array( $pt, $valid_pts, true ) ) {
                    $out['post_types'][] = $pt;
                }
            }
        }
        $out['insertion'] = ( isset( $input['insertion'] ) && $input['insertion'] === 'shortcode' ) ? 'shortcode' : 'auto';
        return $out;
    }

    /**
     * Runs on the `wp` hook — before Bricks renders any elements.
     * Pre-parses post headings so a [zehoro_toc] shortcode in a sidebar
     * element can render immediately, before the_content processes.
     *
     * Uses raw post_content (no do_shortcode) because:
     *   1. Gutenberg block HTML already contains <h2>/<h3> as plain HTML.
     *   2. Calling do_shortcode() here expands all shortcodes twice, which
     *      causes side effects in forms, galleries, and other stateful shortcodes.
     */
    public function preparse_toc_headings(): void {
        if ( ! is_singular() ) return;
        $settings = \Zehoro\Utils\Option::get( 'zehoro_toc_settings', static::get_defaults() );
        if ( ! in_array( get_post_type(), $settings['post_types'], true ) ) return;

        global $lkst_toc_items;
        if ( ! empty( $lkst_toc_items ) ) return;

        $post = get_post();
        if ( ! $post ) return;

        $cache_key = 'lkst_toc_' . $post->ID;
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && isset( $cached['time'] ) && $cached['time'] === $post->post_modified ) {
            $lkst_toc_items = $cached['items'];
            return;
        }

        // Raw content — no shortcode expansion, no wpautop.
        // Headings in Gutenberg posts are already plain HTML here.
        $content        = $post->post_content;
        $pattern        = '/<h([2-3])([^>]*)>(.*?)<\/h\1>/is';
        $lkst_toc_items = [];

        preg_replace_callback( $pattern, function ( $matches ) use ( &$lkst_toc_items ) {
            $level = $matches[1];
            $attrs = $matches[2];
            $text  = strip_tags( $matches[3] );
            if ( preg_match( '/id=[\'"]([^\'"]+)[\'"]/i', $attrs, $id_m ) ) {
                $id = $id_m[1];
            } else {
                $id = sanitize_title( $text );
                if ( empty( $id ) ) $id = 'section-' . count( $lkst_toc_items );
            }
            $lkst_toc_items[] = [ 'level' => $level, 'id' => $id, 'text' => trim( $text ) ];
            return $matches[0];
        }, $content );
        
        set_transient( $cache_key, [ 'time' => $post->post_modified, 'items' => $lkst_toc_items ], 7 * DAY_IN_SECONDS );
    }

    public function process_content( $content ): string {
        // the_content can hand a filter null (an upstream filter returning null,
        // an empty draft) — coerce before the strict return type / string ops.
        $content = (string) $content;
        if ( ! is_singular() ) return $content;
        if ( isset( $_GET['bricks'] ) || isset( $_GET['etchwp'] ) || isset( $_GET['elementor-preview'] ) ) return $content;

        global $lkst_toc_processing, $lkst_toc_items;
        if ( $lkst_toc_processing ) return $content;

        $settings  = \Zehoro\Utils\Option::get( 'zehoro_toc_settings', static::get_defaults() );
        $post_type = get_post_type();
        if ( ! in_array( $post_type, $settings['post_types'], true ) ) return $content;

        $pattern   = '/<h([2-3])([^>]*)>(.*?)<\/h\1>/is';
        $new_items = [];

        $content = preg_replace_callback( $pattern, function ( $matches ) use ( &$new_items ) {
            $level      = $matches[1];
            $attributes = $matches[2];
            $text       = strip_tags( $matches[3] );
            if ( preg_match( '/id=[\'"]([^\'"]+)[\'"]/i', $attributes, $id_matches ) ) {
                $id = $id_matches[1];
            } else {
                $id          = sanitize_title( $text );
                if ( empty( $id ) ) $id = 'section-' . uniqid();
                $attributes .= ' id="' . esc_attr( $id ) . '"';
            }
            $new_items[] = [ 'level' => $level, 'id' => $id, 'text' => trim( $text ) ];
            return "<h{$level}{$attributes}>{$matches[3]}</h{$level}>";
        }, $content );

        $items = ! empty( $lkst_toc_items ) ? $lkst_toc_items : $new_items;

        // Canonical + legacy placeholder literals — existing posts carry
        // [lkst_toc], new content uses [zehoro_toc]; both must work.
        $placeholders = [ '[zehoro_toc]', '[lkst_toc]' ];

        if ( empty( $items ) || count( $items ) < 2 ) {
            // Remove any TOC placeholder — not enough headings to render.
            return str_replace( $placeholders, '', $content );
        }

        $toc_html = $this->build_toc_html( $items );

        if ( strpos( $content, '[zehoro_toc]' ) !== false || strpos( $content, '[lkst_toc]' ) !== false ) {
            $content = str_replace( $placeholders, $toc_html, $content );
        } elseif ( $settings['insertion'] === 'auto' ) {
            $content = $toc_html . $content;
        }

        return $content;
    }

    /**
     * Shortcode handler — used when the TOC is placed via a builder shortcode element
     * (e.g., a Bricks shortcode widget in the sidebar) rather than inside post content.
     *
     * Returns the built TOC HTML when enough headings exist, or an empty string.
     * Never returns the literal "[zehoro_toc]" string — that would render as visible
     * text inside the shortcode element (Bug 4).
     */
    public function render_shortcode(): string {
        global $lkst_toc_items;
        if ( ! empty( $lkst_toc_items ) && count( $lkst_toc_items ) >= 2 ) {
            return $this->build_toc_html( $lkst_toc_items );
        }
        // Not enough headings — output nothing.
        return '';
    }

    private function build_toc_html( array $items ): string {
        $html  = '<div class="zehoro-toc-wrapper" data-zehoro-toc>';
        $html .= '<div class="zehoro-toc-header">';
        $html .= '<div class="zehoro-toc-title"><strong>' . esc_html_x( 'BROWSE', 'table of contents eyebrow label', 'zehoro-toolkit' ) . '</strong> <span class="zehoro-toc-sep">|</span> <div class="zehoro-toc-active-text-wrapper"><span class="zehoro-toc-active-text">' . esc_html__( 'Sections in this article', 'zehoro-toolkit' ) . '</span></div></div>';
        $html .= '<button class="zehoro-toc-toggle" aria-expanded="false" aria-label="' . esc_attr__( 'Toggle table of contents', 'zehoro-toolkit' ) . '">';
        $html .= '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '<div class="zehoro-toc-body"><ul class="zehoro-toc-list">';
        foreach ( $items as $item ) {
            $depth_class = ( $item['level'] == '3' ) ? ' zehoro-toc-depth-3' : ' zehoro-toc-depth-2';
            $html .= '<li class="zehoro-toc-item' . $depth_class . '"><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a></li>';
        }
        $html .= '</ul></div>';
        $html .= '</div>';
        return $html;
    }

    public function render_page(): void {
        $s          = \Zehoro\Utils\Option::get( 'zehoro_toc_settings', static::get_defaults() );
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $pt_exclude = [ 'attachment', 'bricks_template', 'etch_template', 'elementor_library', 'ifso_triggers' ];
        ?>
        <div class="wrap">
            <p style="margin-top:14px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=zehoro-dashboard' ) ); ?>" class="button button-secondary">&larr; <?php esc_html_e( 'Back to Modules', 'zehoro-toolkit' ); ?></a></p>
            <h1><?php esc_html_e( 'Table of Contents Settings', 'zehoro-toolkit' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'zehoro_toc_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Insertion Method', 'zehoro-toolkit' ); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="zehoro_toc_settings[insertion]" value="auto" <?php checked( $s['insertion'], 'auto' ); ?>>
                                <strong><?php esc_html_e( 'Auto-inject', 'zehoro-toolkit' ); ?></strong>
                                <?php esc_html_e( '(Automatically adds the TOC to the very top of the post content)', 'zehoro-toolkit' ); ?>
                            </label><br><br>
                            <label>
                                <input type="radio" name="zehoro_toc_settings[insertion]" value="shortcode" <?php checked( $s['insertion'], 'shortcode' ); ?>>
                                <strong><?php esc_html_e( 'Shortcode Only', 'zehoro-toolkit' ); ?></strong>
                                <?php esc_html_e( '(Only renders where you place the [zehoro_toc] shortcode)', 'zehoro-toolkit' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Active Post Types', 'zehoro-toolkit' ); ?></th>
                        <td>
                            <?php foreach ( $post_types as $slug => $pt ) :
                                if ( in_array( $slug, $pt_exclude, true ) ) continue; ?>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox"
                                           name="zehoro_toc_settings[post_types][]"
                                           value="<?php echo esc_attr( $slug ); ?>"
                                           <?php checked( in_array( $slug, $s['post_types'], true ) ); ?>>
                                    <?php echo esc_html( $pt->label ); ?>
                                    <code style="margin-left:4px;"><?php echo esc_html( $slug ); ?></code>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e( 'Select which post types the TOC should be generated for.', 'zehoro-toolkit' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Settings', 'zehoro-toolkit' ) ); ?>
            </form>
        </div>
        <?php
    }
}