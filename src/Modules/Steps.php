<?php
namespace Zehoro\Modules;

use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Steps / Process module.
 *
 * Registers the lkst/steps Gutenberg block (server-side rendered).
 * Outputs numbered how-to steps and emits HowTo JSON-LD schema when
 * no SEO plugin is active.
 *
 * @package Zehoro\Modules
 */
class Steps implements ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'steps', self::class, [
            'title'   => 'Steps / Process',
            'desc'    => 'Numbered how-to steps with expandable detail. Emits HowTo JSON-LD schema automatically.',
            'default' => true,
        ] );
    }

    public function init(): void {
        add_action( 'init', [ $this, 'register_block' ] );
    }

    public function register_block(): void {
        // block.json declares editorScript: "file:./index.js"; register_block_type
        // auto-enqueues build/steps/index.js with deps from index.asset.php.
        register_block_type(
            ZEHORO_DIR . 'build/steps',
            [ 'render_callback' => [ $this, 'render' ] ]
        );
    }

    // ── Frontend render ──────────────────────────────────────────────────────

    public function render( array $attributes ): string {
        $steps    = $attributes['steps']    ?? [];
        $taskName = $attributes['taskName'] ?? '';

        if ( empty( $steps ) ) return '';

        $props = get_block_wrapper_attributes( [ 'class' => 'lkst-steps' ] );

        ob_start();
        ?>
        <div <?php echo $props; ?>>
            <?php if ( ! empty( $taskName ) ) : ?>
            <p class="lkst-steps-task"><?php echo esc_html( $taskName ); ?></p>
            <?php endif; ?>
            <ol class="lkst-steps-list">
            <?php foreach ( $steps as $i => $step ) :
                $title   = sanitize_text_field( $step['title']   ?? '' );
                $content = wp_kses_post( $step['content'] ?? '' );
                if ( empty( $title ) ) continue;
            ?>
                <li class="lkst-step">
                    <div class="lkst-step-number"><?php echo esc_html( $i + 1 ); ?></div>
                    <div class="lkst-step-body">
                        <h3 class="lkst-step-title"><?php echo esc_html( $title ); ?></h3>
                        <?php if ( ! empty( $content ) ) : ?>
                        <div class="lkst-step-content"><?php echo wpautop( $content ); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
            </ol>
        </div>
        <?php
        $html = ob_get_clean();

        // Append HowTo JSON-LD (once per page, not per block)
        if ( ! wp_doing_ajax() && is_singular() && ! $this->schema_already_output() ) {
            $html .= $this->build_schema_tag( $steps, $taskName );
        }

        return $html;
    }

    // ── HowTo JSON-LD ────────────────────────────────────────────────────────

    private static bool $schema_output = false;

    private function schema_already_output(): bool {
        return self::$schema_output;
    }

    private function build_schema_tag( array $steps, string $taskName ): string {
        // Defer to SEO plugins
        if ( ArticleSchema::seo_plugin_active() ) return '';

        // Sanitize the author-supplied task name before it enters JSON-LD (stored-XSS
        // guard: an unsanitized name + unescaped slashes let a literal </script> break
        // out of the <script type="application/ld+json"> block).
        $name = ! empty( $taskName ) ? sanitize_text_field( $taskName ) : get_the_title();

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'HowTo',
            'name'     => $name,
            'step'     => [],
        ];

        foreach ( $steps as $i => $step ) {
            $title   = sanitize_text_field( $step['title']   ?? '' );
            $content = wp_strip_all_tags( $step['content'] ?? '' );
            if ( empty( $title ) ) continue;

            $schema['step'][] = [
                '@type'    => 'HowToStep',
                'position' => $i + 1,
                'name'     => $title,
                'text'     => $content,
            ];
        }

        if ( empty( $schema['step'] ) ) return '';

        self::$schema_output = true;

        // Keep slash-escaping ON (drop JSON_UNESCAPED_SLASHES): it turns any "</script>"
        // into "<\/script>", so a stray value can't close the JSON-LD block. Matches FAQ.
        return "\n" . '<script type="application/ld+json">'
            . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE )
            . "</script>\n";
    }
}
