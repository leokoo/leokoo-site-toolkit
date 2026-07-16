<?php
namespace Zehoro\Modules;

use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class ArticleSchema implements ModuleInterface {

    /**
     * Default post-type → schema.org @type map.
     * Filterable via zehoro_article_schema_type_map.
     */
    private static array $type_map = [
        'post'     => 'BlogPosting',
        'page'     => 'WebPage',
        'recipe'   => 'Recipe',
        'recipes'  => 'Recipe',
        'review'   => 'Review',
        'reviews'  => 'Review',
        'service'  => 'Service',
        'services' => 'Service',
        'product'  => 'Product',
    ];

    public static function register(): void {
        Plugin::register_module( 'article_schema', self::class, [
            'title'   => 'Article Schema (E-E-A-T)',
            'desc'    => 'Post-type-aware JSON-LD schema (BlogPosting, Recipe, Review, Service…). Skips automatically if Yoast / SEOPress / RankMath / AIOSEO / SureRank is active.',
            'default' => true
        ] );
    }

    public function init(): void {
        add_action( 'wp_head',     [ $this, 'output_schema' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
    }

    // ── SEO plugin conflict detection ────────────────────────────────────────

    /**
     * Returns true if a major SEO plugin that already outputs schema is active.
     * Use add_filter( 'zehoro_article_schema_force', '__return_true' ) to bypass.
     */
    public static function seo_plugin_active(): bool {
        // "Active" here means "another SEO plugin owns schema, so suppress ours."
        // Delegates to the canonical Compat\SeoPlugin detector + override logic
        // (the legacy `zehoro_article_schema_force` filter is honoured inside it),
        // so the plugin list + the user override live in ONE place.
        return ! \Zehoro\Compat\SeoPlugin::should_emit_schema();
    }

    /**
     * Returns true if WP Review Pro is active.
     * When true, suppress our schema on review post types to avoid duplicate
     * Review JSON-LD (WP Review Pro emits its own at priority 10).
     * Use add_filter( 'zehoro_article_schema_suppress_wp_review', '__return_false' )
     * to override if you want both outputs (not recommended).
     */
    public static function wp_review_pro_active(): bool {
        return apply_filters(
            'zehoro_article_schema_suppress_wp_review',
            defined( 'MTS_WP_REVIEW_DB_TABLE' )
        );
    }

    // ── Schema type resolution ───────────────────────────────────────────────

    public static function get_schema_type( string $post_type ): string {
        $map = apply_filters( 'zehoro_article_schema_type_map', self::$type_map );
        return $map[ $post_type ] ?? 'Article';
    }

    // ── Schema builder (shared by front-end output and editor preview) ───────

    public static function build_schema( \WP_Post $post ): array {
        $post_type   = get_post_type( $post );
        $schema_type = self::get_schema_type( $post_type );

        // Author
        $author_id   = $post->post_author;
        $author_name = get_the_author_meta( 'display_name', $author_id );
        $author_url  = get_author_posts_url( $author_id );

        $author_schema = [
            '@type' => 'Person',
            'name'  => $author_name,
            'url'   => $author_url,
        ];

        $custom_url = get_the_author_meta( 'user_url', $author_id );
        if ( ! empty( $custom_url ) ) {
            $author_schema['url'] = esc_url( $custom_url );
        }

        // Read the canonical post-rename key first, falling back to the legacy one for installs
        // that haven't run ZehoroRenameMigrator yet. Before this, ArticleSchema read only the
        // legacy `lkst_author_*` key while the migrator had moved the value to `zehoro_author_*`,
        // so a migrated site silently dropped the author's sameAs from the JSON-LD (audit round-2
        // RENAME-DRIFT-1 — an E-E-A-T signal loss).
        $linkedin = get_the_author_meta( 'zehoro_author_linkedin', $author_id ) ?: get_the_author_meta( 'lkst_author_linkedin', $author_id );
        $twitter  = get_the_author_meta( 'zehoro_author_twitter', $author_id ) ?: get_the_author_meta( 'lkst_author_twitter', $author_id );
        $same_as  = [ $linkedin, $twitter ];
        // Enrich sameAs with the Author Box social profiles — the SAME links the
        // visual card renders, so schema + card agree (a stronger E-E-A-T
        // identity signal). Author Box: block #4.
        foreach ( [ 'facebook', 'linkedin', 'x', 'youtube' ] as $net ) {
            $url = get_the_author_meta( 'zehoro_social_' . $net, $author_id ) ?: get_the_author_meta( 'lkst_social_' . $net, $author_id );
            if ( $url ) {
                $same_as[] = $url;
            }
        }
        $same_as = array_values( array_unique( array_filter( $same_as ) ) );
        if ( ! empty( $same_as ) ) {
            $author_schema['sameAs'] = array_map( 'esc_url', $same_as );
        }

        // The author works for the publisher Organization. Reference it by @id
        // (set on the publisher node below) instead of inlining a second
        // Organization — one canonical Org node, penalty-safe (the Author Box
        // block's dual-mode differentiator, done in schema not a duplicate box).
        $org_id = home_url() . '#organization';
        $author_schema['worksFor'] = [ '@id' => $org_id ];

        // Core schema. @type already resolved above via get_schema_type() — the filterable
        // post-type → schema-type map (zehoro_article_schema_type_map). A stray hardcoded
        // reassignment here used to clobber it back to BlogPosting/Article every time,
        // silently breaking custom-CPT mappings (e.g. a 'service' CPT mapped to 'Service').
        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => $schema_type,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post->ID ),
            ],
            'headline'      => get_the_title( $post->ID ),
            'datePublished' => get_the_date( 'c', $post->ID ),
            'dateModified'  => get_the_modified_date( 'c', $post->ID ),
            'author'        => $author_schema,
            'publisher'     => [
                '@type' => 'Organization',
                '@id'   => $org_id,
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url(),
            ],
        ];

        // Publisher logo
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
            if ( $logo_url ) {
                $schema['publisher']['logo'] = [
                    '@type' => 'ImageObject',
                    'url'   => esc_url( $logo_url ),
                ];
            }
        }

        // Featured image
        $thumbnail_id = get_post_thumbnail_id( $post->ID );
        if ( $thumbnail_id ) {
            $image_url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
            if ( $image_url ) {
                $schema['image'] = esc_url( $image_url );
            }
        }

        // Word count
        $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
        if ( $word_count > 0 ) {
            $schema['wordCount'] = $word_count;
        }

        $schema = apply_filters( 'zehoro_article_schema', $schema, $post );

        // Back-compat: fire the deprecated lkst_ filter name for one release so
        // existing site code (and Zehoro Toolkit Pro's EntityMap) keeps working
        // until everything migrates to zehoro_article_schema.
        if ( has_filter( 'lkst_article_schema' ) ) {
            $schema = apply_filters_deprecated( 'lkst_article_schema', [ $schema, $post ], '1.6.1', 'zehoro_article_schema' );
        }

        return $schema;
    }

    // ── Front-end JSON-LD output ─────────────────────────────────────────────

    public function output_schema(): void {
        if ( ! is_singular() ) return;

        if ( self::seo_plugin_active() ) return;

        global $post;

        // WP Review Pro handles its own Review JSON-LD — don't duplicate it.
        if ( self::wp_review_pro_active() ) return;

        // Skip pages unless explicitly opted in
        if ( get_post_type( $post ) === 'page' && ! apply_filters( 'zehoro_article_schema_on_pages', false ) ) {
            return;
        }

        $schema = self::build_schema( $post );

        echo "\n<!-- LKST Article Schema -->\n";
        echo '<script type="application/ld+json">';
        // Keep slash-escaping ON so a </script> in any schema value (incl. anything a
        // `zehoro_article_schema` filter injects) becomes <\/script> and can't close the
        // block — defense-in-depth parity with Steps/FAQ.
        echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE );
        echo "</script>\n";
    }

    // ── Gutenberg / Classic editor sidebar meta box ──────────────────────────

    public function register_meta_box(): void {
        $post_types = array_values(
            array_diff(
                array_keys( get_post_types( [ 'public' => true ] ) ),
                [ 'attachment' ]
            )
        );

        add_meta_box(
            'zehoro_article_schema_preview',
            '🔍 Article Schema (LKST)',
            [ $this, 'render_meta_box' ],
            $post_types,
            'side',
            'default'
        );
    }

    public function render_meta_box( \WP_Post $post ): void {
        // ── Conflict warning — SEO plugin ────────────────────────────────────
        if ( self::seo_plugin_active() ) {
            $label = \Zehoro\Compat\SeoPlugin::label();
            echo '<p style="color:#856404;background:#fff3cd;padding:8px 10px;border-radius:4px;font-size:12px;margin:0;line-height:1.5;">';
            echo '⚠️ <strong>' . esc_html__( 'Schema output paused.', 'zehoro-toolkit' ) . '</strong><br>'
                . esc_html(
                    $label
                        ? sprintf( /* translators: %s: SEO plugin name */ __( '%s is active and already emits structured data — Zehoro won\'t duplicate it.', 'zehoro-toolkit' ), $label )
                        : __( 'An SEO plugin is active and already emits structured data — Zehoro won\'t duplicate it.', 'zehoro-toolkit' )
                );
            echo '</p>';
            echo '<p style="font-size:11px;color:#999;margin:8px 0 0;">' . esc_html__( 'To keep Zehoro\'s schema instead, set Zehoro → Settings → Schema output to “Always”, or:', 'zehoro-toolkit' ) . '<br><code>add_filter(\'zehoro_article_schema_force\', \'__return_true\');</code></p>';
            return;
        }

        // ── Conflict warning — WP Review Pro ─────────────────────────────────
        if ( self::wp_review_pro_active() ) {
            echo '<p style="color:#856404;background:#fff3cd;padding:8px 10px;border-radius:4px;font-size:12px;margin:0;line-height:1.5;">';
            echo '⚠️ <strong>Schema output disabled.</strong><br>WP Review Pro is active and handles its own Review JSON-LD — LKST will not duplicate it.';
            echo '</p>';
            echo '<p style="font-size:11px;color:#999;margin:8px 0 0;">To override:<br><code>add_filter(\'zehoro_article_schema_suppress_wp_review\', \'__return_false\');</code></p>';
            return;
        }

        // ── Build preview data ──────────────────────────────────────────────
        $schema      = self::build_schema( $post );
        $schema_type = $schema['@type'];
        $has_image   = ! empty( $schema['image'] );
        $has_logo    = ! empty( $schema['publisher']['logo'] );
        $word_count  = $schema['wordCount'] ?? 0;
        $headline    = $schema['headline'];
        $short_hl    = mb_strlen( $headline ) > 45 ? mb_substr( $headline, 0, 42 ) . '…' : $headline;
        $json        = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        ?>
        <style>
        #lkst-schema-preview{font-size:12px;}
        #lkst-schema-preview table{width:100%;border-collapse:collapse;}
        #lkst-schema-preview td{padding:3px 0;vertical-align:top;}
        #lkst-schema-preview td:first-child{color:#555;width:38%;padding-right:6px;font-weight:500;}
        .lkst-ok{color:#2e7d32;font-weight:600;}
        .lkst-warn{color:#b45309;font-weight:600;}
        #lkst-schema-json{
            background:#f5f5f5;padding:8px;border-radius:4px;
            font-size:10px;font-family:monospace;
            overflow:auto;max-height:200px;
            white-space:pre-wrap;word-break:break-all;
            margin:8px 0 0;display:none;
        }
        #lkst-schema-toggle{
            font-size:11px;cursor:pointer;color:#0073aa;
            text-decoration:underline;display:inline-block;margin-top:6px;
            background:none;border:none;padding:0;
        }
        </style>
        <div id="lkst-schema-preview">
            <table>
                <tr>
                    <td>@type</td>
                    <td><strong><?php echo esc_html( $schema_type ); ?></strong></td>
                </tr>
                <tr>
                    <td>Headline</td>
                    <td title="<?php echo esc_attr( $headline ); ?>"><?php echo esc_html( $short_hl ); ?></td>
                </tr>
                <tr>
                    <td>Author</td>
                    <td><?php echo esc_html( $schema['author']['name'] ); ?></td>
                </tr>
                <tr>
                    <td>Published</td>
                    <td><?php echo esc_html( get_the_date( 'd M Y', $post->ID ) ); ?></td>
                </tr>
                <tr>
                    <td>Modified</td>
                    <td><?php echo esc_html( get_the_modified_date( 'd M Y', $post->ID ) ); ?></td>
                </tr>
                <tr>
                    <td>Image</td>
                    <td class="<?php echo $has_image ? 'lkst-ok' : 'lkst-warn'; ?>">
                        <?php echo $has_image ? '✓ Set' : '✗ Missing'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Logo</td>
                    <td class="<?php echo $has_logo ? 'lkst-ok' : 'lkst-warn'; ?>">
                        <?php echo $has_logo ? '✓ Set' : '✗ Missing'; ?>
                    </td>
                </tr>
                <?php if ( $word_count > 0 ) : ?>
                <tr>
                    <td>Words</td>
                    <td><?php echo esc_html( number_format( $word_count ) ); ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <button type="button" id="lkst-schema-toggle" onclick="
                var p=document.getElementById('lkst-schema-json');
                var open=p.style.display!=='none';
                p.style.display=open?'none':'block';
                this.textContent=open?'▼ View full JSON-LD':'▲ Hide JSON-LD';
            ">▼ View full JSON-LD</button>

            <pre id="lkst-schema-json"><?php echo esc_html( $json ); ?></pre>
        </div>
        <?php
    }
}
