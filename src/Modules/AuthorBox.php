<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;

use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Author Box Module
 *
 * Renders a styled author card with biography, social icons, and CTA buttons.
 * Resolves author_id safely whether inside or outside the WP loop —
 * Bricks/Etch text elements render shortcodes outside the_loop, so
 * get_the_author_meta(\'ID\') returns 0; we fall back to get_queried_object_id().
 * Usage: [zehoro_author_box author_id=""] or [zehoro_author_socials author_id=""]
 *
 * @package Zehoro\Modules
 */
class AuthorBox implements \Zehoro\Core\ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'author_box', self::class, [
            'title'   => 'Author Box',
            'desc'    => 'Display a full author card with biography, social icons, and call-to-action buttons. Use [zehoro_author_box].',
            'default' => true,
            'settings_page' => 'zehoro-author-box'
        ] );
    }


    public function init(): void {
        add_filter( 'user_contactmethods', [ $this, 'add_contact_methods' ] );
        add_action( 'wp_enqueue_scripts',  [ $this, 'enqueue_dashicons' ] );
        add_action( 'init', [ $this, 'register_block' ] );
        // Shortcodes — register canonical `zehoro_*` names + legacy `lkst_*`
        // aliases (so existing posts with `[lkst_author_box]` keep rendering
        // through v1.x; cleanup in v2.0).
        add_shortcode( 'zehoro_author_box',    [ $this, 'render_box' ] );
        add_shortcode( 'zehoro_author_socials', [ $this, 'render_socials' ] );
        add_shortcode( 'lkst_author_box',      [ $this, 'render_box' ] );
        add_shortcode( 'lkst_author_socials',  [ $this, 'render_socials' ] );
    }

    public function register_block(): void {
        register_block_type( ZEHORO_DIR . 'build/author-box' );
    }

    /** Social networks shown in the card, in order. */
    private const NETWORKS = [
        'linkedin' => 'LinkedIn',
        'x'        => 'X',
        'facebook' => 'Facebook',
        'youtube'  => 'YouTube',
    ];

    /**
     * Block render seam. The block emits NO schema — ArticleSchema owns the
     * canonical author Person (enriched with worksFor + sameAs); this is the
     * visual E-E-A-T card only.
     */
    public static function render_block( array $attributes, string $wrapper = '' ): string {
        $mode = self::resolve_mode( $attributes );
        $body = ( $mode === 'organization' )
            ? self::render_org_body( $attributes )
            : self::render_person_body( $attributes );

        if ( $body === '' ) {
            return '';
        }
        if ( $wrapper === '' ) {
            $wrapper = 'class="zehoro-author-box zehoro-author-box--' . $mode . '"';
        }
        return sprintf( '<div %s>%s</div>', $wrapper, $body );
    }

    private static function render_person_body( array $attributes ): string {
        $author_id = self::resolve_author( (int) ( $attributes['authorId'] ?? 0 ) );
        if ( ! $author_id ) {
            return '';
        }

        $name = get_the_author_meta( 'display_name', $author_id );
        if ( $name === '' ) {
            return '';
        }
        $avatar  = get_avatar_url( $author_id, [ 'size' => 96 ] );
        $tagline = (string) get_user_meta( $author_id, 'zehoro_author_tagline', true );
        if ( $tagline === '' ) {
            $tagline = (string) get_user_meta( $author_id, 'lkst_author_tagline', true );
        }

        $html  = '<div class="zehoro-author-box__header">';
        if ( $avatar ) {
            $html .= '<img class="zehoro-author-box__avatar" src="' . esc_url( $avatar ) . '" alt="' . esc_attr( $name ) . '" width="72" height="72" loading="lazy" />';
        }
        $html .= '<div class="zehoro-author-box__identity"><span class="zehoro-author-box__name">' . esc_html( $name ) . '</span>';
        if ( $tagline !== '' ) {
            $html .= '<span class="zehoro-author-box__tagline">' . esc_html( $tagline ) . '</span>';
        }
        $html .= '</div></div>';

        if ( self::flag( $attributes, 'showBio' ) ) {
            $bio = (string) get_the_author_meta( 'description', $author_id );
            if ( $bio !== '' ) {
                $html .= '<p class="zehoro-author-box__bio">' . nl2br( esc_html( $bio ) ) . '</p>';
            }
        }

        if ( self::flag( $attributes, 'showCredentials' ) ) {
            $chips = array_filter( [
                (string) get_user_meta( $author_id, 'zehoro_chip_1', true ) ?: (string) get_user_meta( $author_id, 'lkst_chip_1', true ),
                (string) get_user_meta( $author_id, 'zehoro_chip_2', true ) ?: (string) get_user_meta( $author_id, 'lkst_chip_2', true ),
                (string) get_user_meta( $author_id, 'zehoro_chip_3', true ) ?: (string) get_user_meta( $author_id, 'lkst_chip_3', true ),
            ] );
            if ( $chips ) {
                $html .= '<ul class="zehoro-author-box__credentials">';
                foreach ( $chips as $chip ) {
                    $html .= '<li class="zehoro-author-box__credential">' . esc_html( $chip ) . '</li>';
                }
                $html .= '</ul>';
            }
        }

        if ( self::flag( $attributes, 'showSocials' ) ) {
            $html .= self::render_socials_links( $author_id );
        }

        return $html;
    }

    private static function render_org_body( array $attributes ): string {
        $name = get_bloginfo( 'name' );
        if ( $name === '' ) {
            return '';
        }
        $logo_id  = (int) get_theme_mod( 'custom_logo' );
        $logo_url = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        $desc     = (string) get_bloginfo( 'description' );

        $html = '<div class="zehoro-author-box__header">';
        if ( $logo_url ) {
            $html .= '<img class="zehoro-author-box__logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />';
        }
        $html .= '<div class="zehoro-author-box__identity"><span class="zehoro-author-box__name">' . esc_html( $name ) . '</span></div></div>';

        if ( self::flag( $attributes, 'showBio' ) && $desc !== '' ) {
            $html .= '<p class="zehoro-author-box__bio">' . esc_html( $desc ) . '</p>';
        }

        return $html;
    }

    private static function render_socials_links( int $author_id ): string {
        $links = '';
        foreach ( self::NETWORKS as $key => $label ) {
            $url = (string) get_user_meta( $author_id, 'zehoro_social_' . $key, true );
            if ( $url === '' ) {
                $url = (string) get_user_meta( $author_id, 'lkst_social_' . $key, true );
            }
            if ( $url !== '' ) {
                $links .= sprintf(
                    '<li><a class="zehoro-author-box__social" href="%s" rel="noopener noreferrer nofollow">%s</a></li>',
                    esc_url( $url ),
                    esc_html( $label )
                );
            }
        }
        if ( $links === '' ) {
            return '';
        }
        return '<ul class="zehoro-author-box__socials" aria-label="' . esc_attr__( 'Author social profiles', 'zehoro-toolkit' ) . '">' . $links . '</ul>';
    }

    /** A boolean attribute that defaults to TRUE when unset. */
    private static function flag( array $attributes, string $key ): bool {
        return ! isset( $attributes[ $key ] ) || ! empty( $attributes[ $key ] );
    }

    /**
     * The card mode ('person' | 'organization') — the single source of truth for
     * both the render seam (body) and the block wrapper's modifier class, so the
     * two can never desync.
     */
    public static function resolve_mode( array $attributes ): string {
        return ( ( $attributes['mode'] ?? 'person' ) === 'organization' ) ? 'organization' : 'person';
    }

    /** Static author resolver for the block seam (mirrors resolve_author_id). */
    public static function resolve_author( int $override ): int {
        if ( $override > 0 ) {
            return $override;
        }
        $id = (int) get_the_author_meta( 'ID' );
        if ( $id ) {
            return $id;
        }
        // Fall back to the queried object ONLY when it is a post — never a term
        // (category/tag/date archive) or user (author archive), whose numeric id
        // would otherwise be misread as a post id and resolve an unrelated author.
        $queried = get_queried_object();
        if ( $queried instanceof \WP_Post && (int) $queried->post_author ) {
            return (int) $queried->post_author;
        }
        return 0;
    }

    public function add_contact_methods( array $methods ): array {
        $methods['lkst_social_facebook'] = 'Facebook URL';
        $methods['lkst_social_linkedin'] = 'LinkedIn URL';
        $methods['lkst_social_x']        = 'X (Twitter) URL';
        $methods['lkst_social_youtube']  = 'YouTube URL';
        $methods['lkst_author_tagline']  = 'Author Tagline (shown under name)';
        $methods['lkst_chip_1']          = 'Credential Chip 1';
        $methods['lkst_chip_2']          = 'Credential Chip 2';
        $methods['lkst_chip_3']          = 'Credential Chip 3';
        return $methods;
    }

    public function enqueue_dashicons(): void {
        if ( ! is_singular() ) return;
        $post = get_post();
        if ( ! $post ) return;
        $content = $post->post_content;
        if (   has_shortcode( $content, 'zehoro_author_box' )
            || has_shortcode( $content, 'zehoro_author_socials' )
            || has_shortcode( $content, 'lkst_author_box' )
            || has_shortcode( $content, 'lkst_author_socials' ) ) {
            wp_enqueue_style( 'dashicons' );
        }
    }

    /**
     * Resolve author ID from shortcode attr, WP loop, or queried object.
     * Returns 0 if the author cannot be determined.
     */
    private function resolve_author_id( $atts_author_id ): int {
        // 1. Explicit shortcode attribute wins.
        if ( ! empty( $atts_author_id ) ) {
            return (int) $atts_author_id;
        }
        // 2. Inside the WP loop, get_the_author_meta(\'ID\') is reliable.
        $id = (int) get_the_author_meta( 'ID' );
        if ( $id ) {
            return $id;
        }
        // 3. Outside the loop (e.g. Bricks sidebar element): fall back to the
        //    post\'s post_author — but ONLY when the queried object is actually a
        //    post, not a term/user archive whose id would misresolve an author.
        $queried = get_queried_object();
        if ( $queried instanceof \WP_Post && (int) $queried->post_author ) {
            return (int) $queried->post_author;
        }
        return 0;
    }

    public function render_box( $atts ): string {
        $atts      = shortcode_atts( [ 'author_id' => null ], $atts );
        $author_id = $this->resolve_author_id( $atts['author_id'] );
        if ( ! $author_id ) return '';

        $name    = get_the_author_meta( 'display_name', $author_id );
        $bio     = get_the_author_meta( 'description',  $author_id );
        $avatar  = get_avatar_url( $author_id, [ 'size' => 96 ] );
        $tagline = get_user_meta( $author_id, 'lkst_author_tagline', true );
        $chips   = array_filter( [
            get_user_meta( $author_id, 'lkst_chip_1', true ),
            get_user_meta( $author_id, 'lkst_chip_2', true ),
            get_user_meta( $author_id, 'lkst_chip_3', true ),
        ] );

        $networks = [
            'facebook' => [ 'label' => 'Facebook', 'dashicon' => 'dashicons-facebook' ],
            'linkedin' => [ 'label' => 'LinkedIn', 'dashicon' => 'dashicons-linkedin' ],
            'x'        => [ 'label' => 'X',        'dashicon' => 'dashicons-twitter'  ],
            'youtube'  => [ 'label' => 'YouTube',  'dashicon' => 'dashicons-youtube'  ],
        ];
        $social_links = '';
        foreach ( $networks as $key => $net ) {
            $url = get_user_meta( $author_id, 'lkst_social_' . $key, true );
            if ( $url ) {
                $social_links .= sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s" class="lkst-ab-social lkst-social-%s"><span class="dashicons %s"></span></a>',
                    esc_url( $url ), esc_attr( $net['label'] ), esc_attr( $key ), esc_attr( $net['dashicon'] )
                );
            }
        }

        // CTA URL defaults are intentionally EMPTY — buttons hide unless the
        // site owner configures them via wp_options (zehoro_cta_primary_url,
        // zehoro_cta_secondary_url) or the lkst/author_box/cta_* filters.
        // (The lkst/* filter names continue firing during Phase 2's
        // backward-compat window; Option::get falls back to lkst_* legacy
        // wp_options keys when canonical is unset.) Previous defaults
        // ('/blog/' and '#newsletter') silently rendered broken links on
        // most sites; better to hide than to mislead.
        // Canonical hook is zehoro/* ; legacy lkst/* fires too via
        // apply_filters_deprecated so any custom theme code listening on the
        // old name still gets the call.
        $cta_primary = apply_filters( 'zehoro/author_box/cta_primary', [
            'label' => \Zehoro\Utils\Option::get( 'zehoro_cta_primary_label', 'Read more articles' ),
            'url'   => \Zehoro\Utils\Option::get( 'zehoro_cta_primary_url',   '' ),
        ] );
        if ( has_filter( 'lkst/author_box/cta_primary' ) ) {
            $cta_primary = apply_filters_deprecated( 'lkst/author_box/cta_primary', [ $cta_primary ], '1.7.0', 'zehoro/author_box/cta_primary' );
        }
        $cta_secondary = apply_filters( 'zehoro/author_box/cta_secondary', [
            'label' => \Zehoro\Utils\Option::get( 'zehoro_cta_secondary_label', 'Get the newsletter' ),
            'url'   => \Zehoro\Utils\Option::get( 'zehoro_cta_secondary_url',   '' ),
        ] );
        if ( has_filter( 'lkst/author_box/cta_secondary' ) ) {
            $cta_secondary = apply_filters_deprecated( 'lkst/author_box/cta_secondary', [ $cta_secondary ], '1.7.0', 'zehoro/author_box/cta_secondary' );
        }

        $html  = '<div class="lkst-author-box">';
        $html .= '<div class="lkst-ab-header">';
        if ( $avatar ) {
            $html .= '<img src="' . esc_url( $avatar ) . '" alt="' . esc_attr( $name ) . '" class="lkst-ab-avatar" width="80" height="80" loading="lazy">';
        }
        $html .= '<div class="lkst-ab-identity">';
        $html .= '<strong class="lkst-ab-name">' . esc_html( $name ) . '</strong>';
        if ( $tagline ) {
            $html .= '<span class="lkst-ab-tagline">' . esc_html( $tagline ) . '</span>';
        }
        $html .= '</div></div>';

        if ( $bio ) {
            $html .= '<p class="lkst-ab-bio">' . nl2br( esc_html( $bio ) ) . '</p>';
        }

        if ( $chips ) {
            $html .= '<div class="lkst-ab-chips">';
            foreach ( $chips as $chip ) {
                $html .= '<span class="lkst-ab-chip">' . esc_html( $chip ) . '</span>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="lkst-ab-ctas">';
        if ( ! empty( $cta_primary['label'] ) && ! empty( $cta_primary['url'] ) ) {
            $html .= '<a href="' . esc_url( $cta_primary['url'] ) . '" class="lkst-ab-btn-primary">' . esc_html( $cta_primary['label'] ) . ' &rarr;</a>';
        }
        if ( ! empty( $cta_secondary['label'] ) && ! empty( $cta_secondary['url'] ) ) {
            $html .= '<a href="' . esc_url( $cta_secondary['url'] ) . '" class="lkst-ab-btn-secondary">' . esc_html( $cta_secondary['label'] ) . ' &#8599;</a>';
        }
        $html .= '</div>';

        if ( $social_links ) {
            $html .= '<div class="lkst-ab-socials"><span class="lkst-ab-socials-label">Follow</span>' . $social_links . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    public function render_socials( $atts ): string {
        $atts      = shortcode_atts( [ 'author_id' => null ], $atts );
        $author_id = $this->resolve_author_id( $atts['author_id'] );
        if ( ! $author_id ) return '';

        $networks = [
            'facebook' => [ 'label' => 'Facebook', 'dashicon' => 'dashicons-facebook' ],
            'linkedin' => [ 'label' => 'LinkedIn', 'dashicon' => 'dashicons-linkedin' ],
            'x'        => [ 'label' => 'X',        'dashicon' => 'dashicons-twitter'  ],
            'youtube'  => [ 'label' => 'YouTube',  'dashicon' => 'dashicons-youtube'  ],
        ];
        $links = '';
        foreach ( $networks as $key => $net ) {
            $url = get_user_meta( $author_id, 'lkst_social_' . $key, true );
            if ( $url ) {
                $links .= sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s" class="lkst-author-social-link lkst-social-%s"><span class="dashicons %s"></span></a>',
                    esc_url( $url ), esc_attr( $net['label'] ), esc_attr( $key ), esc_attr( $net['dashicon'] )
                );
            }
        }
        if ( ! $links ) return '';
        return '<div class="lkst-author-socials"><span class="lkst-author-socials__label">Connect</span>' . $links . '</div>';
    }
}