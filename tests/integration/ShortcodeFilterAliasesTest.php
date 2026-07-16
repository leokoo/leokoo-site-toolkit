<?php
/**
 * Phase 2 backward-compat — shortcode aliases + filter-hook dual emission.
 *
 * Pins that:
 *   - Every renamed shortcode keeps its legacy [lkst_*] form registered
 *     alongside the canonical [zehoro_*] one. Existing published content
 *     using [lkst_*] continues to render.
 *   - Every renamed theme filter hook fires under BOTH `zehoro/*` (canonical)
 *     AND `lkst/*` (deprecated) names so existing custom theme code listening
 *     on the old name keeps getting the call.
 *
 * @package Zehoro\Tests\Integration
 */

class ShortcodeFilterAliasesTest extends WP_UnitTestCase {

	// ── Shortcode aliases ───────────────────────────────────────────────────

	/**
	 * The pills pairs moved to Pro in the lean-Free reorg stage B; the
	 * ContentBox (lkst_box) + LastUpdated pairs moved in stage C — all now
	 * covered by Pro's MovedStageBModulesTest / MovedStageCModulesTest.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function shortcode_pairs(): array {
		return [
			[ 'lkst_author_box',         'zehoro_author_box' ],
			[ 'lkst_author_socials',     'zehoro_author_socials' ],
			[ 'lkst_faq',                'zehoro_faq' ],
			[ 'lkst_toc',                'zehoro_toc' ],
		];
	}

	/** @dataProvider shortcode_pairs */
	public function test_both_legacy_and_canonical_shortcodes_are_registered( string $legacy, string $canonical ) {
		// Boot the relevant module so its init() runs and the shortcodes register.
		$this->boot_modules_for_shortcode( $legacy );

		$this->assertTrue( shortcode_exists( $canonical ), "canonical $canonical must be registered" );
		$this->assertTrue( shortcode_exists( $legacy ),    "legacy $legacy must remain registered for back-compat" );
	}

	/** @dataProvider shortcode_pairs */
	public function test_legacy_and_canonical_share_the_same_handler( string $legacy, string $canonical ) {
		global $shortcode_tags;
		$this->boot_modules_for_shortcode( $legacy );

		$this->assertArrayHasKey( $canonical, $shortcode_tags );
		$this->assertArrayHasKey( $legacy,    $shortcode_tags );
		$this->assertSame(
			$shortcode_tags[ $canonical ],
			$shortcode_tags[ $legacy ],
			'legacy and canonical shortcode names must route to the same handler'
		);
	}

	// ── Filter hook dual emission ───────────────────────────────────────────

	/** @return array<int, array{module: string, init_method: callable, canonical: string, legacy: string, fire: callable}> */
	public static function hook_pairs(): array {
		return [
			[
				'class'        => \Zehoro\Modules\AuthorBox::class,
				'canonical'    => 'zehoro/author_box/cta_primary',
				'legacy'       => 'lkst/author_box/cta_primary',
				'trigger_path' => 'render_author_box',
			],
			[
				'class'        => \Zehoro\Modules\AuthorBox::class,
				'canonical'    => 'zehoro/author_box/cta_secondary',
				'legacy'       => 'lkst/author_box/cta_secondary',
				'trigger_path' => 'render_author_box',
			],
		];
	}

	/** @dataProvider hook_pairs */
	public function test_canonical_hook_fires( string $class, string $canonical, string $legacy, string $trigger_path ) {
		$fired = false;
		add_filter( $canonical, function( $value ) use ( &$fired ) { $fired = true; return $value; }, 10, 99 );

		$this->trigger( $trigger_path );

		$this->assertTrue( $fired, "canonical hook $canonical should fire when $trigger_path runs" );
	}

	/** @dataProvider hook_pairs */
	public function test_legacy_hook_still_fires_for_back_compat( string $class, string $canonical, string $legacy, string $trigger_path ) {
		// We expect WP to log a deprecation notice for the legacy hook — declare
		// that to WP_UnitTestCase so it doesn't fail the test.
		$this->setExpectedDeprecated( $legacy );

		$fired = false;
		add_filter( $legacy, function( $value ) use ( &$fired ) { $fired = true; return $value; }, 10, 99 );

		$this->trigger( $trigger_path );

		$this->assertTrue( $fired, "legacy hook $legacy must keep firing via apply_filters_deprecated" );
	}

	// ── helpers ─────────────────────────────────────────────────────────────

	private function boot_modules_for_shortcode( string $legacy_name ): void {
		static $booted = [];

		$map = [
			'lkst_author_box'     => \Zehoro\Modules\AuthorBox::class,
			'lkst_author_socials' => \Zehoro\Modules\AuthorBox::class,
			'lkst_faq'            => \Zehoro\Modules\FAQ::class,
			'lkst_toc'            => \Zehoro\Modules\TableOfContents::class,
		];
		$class = $map[ $legacy_name ] ?? null;
		if ( $class === null || isset( $booted[ $class ] ) ) return;

		( new $class() )->init();
		$booted[ $class ] = true;
	}

	private function trigger( string $what ): void {
		switch ( $what ) {
			case 'render_author_box':
				$module = new \Zehoro\Modules\AuthorBox();
				$module->init();
				// Create + log in as a user, then render.
				$uid = self::factory()->user->create( [ 'role' => 'author' ] );
				$post = self::factory()->post->create_and_get( [ 'post_author' => $uid ] );
				global $wp_query;
				$wp_query->queried_object_id = $post->ID;
				$module->render_box( [ 'author_id' => $uid ] );
				break;
		}
	}
}
