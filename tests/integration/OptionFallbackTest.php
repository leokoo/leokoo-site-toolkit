<?php
/**
 * Zehoro\Utils\Option — read-with-fallback contract.
 *
 * Pins the defense-in-depth read path used by every flipped call site in
 * Phase 1b. The migrator should run first and remove the need for fallback
 * in practice, but the helper still has to do the right thing when it
 * hasn't.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Utils\Option;
use Zehoro\Migration\ZehoroRenameMigrator;

class OptionFallbackTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		ZehoroRenameMigrator::reset_flag();
		// Wipe both halves of every mapped pair so each test starts clean.
		foreach ( ZehoroRenameMigrator::OPTION_MAP as $old => $new ) {
			delete_option( $old );
			delete_option( $new );
		}
	}

	public function test_get_reads_canonical_when_only_canonical_is_set() {
		update_option( 'zehoro_active_modules', [ 'mod_a' ] );

		$this->assertSame( [ 'mod_a' ], Option::get( 'zehoro_active_modules', [] ) );
	}

	public function test_get_falls_back_to_legacy_when_only_legacy_is_set() {
		// Simulates a load BEFORE the migrator has run on an existing site.
		update_option( 'lkst_active_modules', [ 'legacy_mod' ] );

		$this->assertSame( [ 'legacy_mod' ], Option::get( 'zehoro_active_modules', [] ) );
	}

	public function test_get_prefers_canonical_when_both_exist() {
		// Migrator copied lkst_→zehoro_ then user edited zehoro_. Canonical wins.
		update_option( 'lkst_active_modules',   [ 'legacy_mod' ] );
		update_option( 'zehoro_active_modules', [ 'canonical_mod' ] );

		$this->assertSame( [ 'canonical_mod' ], Option::get( 'zehoro_active_modules', [] ) );
	}

	public function test_get_returns_default_when_neither_key_is_set() {
		$this->assertSame( [], Option::get( 'zehoro_active_modules', [] ) );
		$this->assertSame( 'fallback', Option::get( 'zehoro_active_modules', 'fallback' ) );
	}

	public function test_get_returns_default_for_unmapped_canonical_key() {
		// A brand-new zehoro_* key that never had an lkst_* form — no fallback
		// possible. Default flows through unchanged.
		delete_option( 'zehoro_brand_new_feature' );
		$this->assertSame( 'default', Option::get( 'zehoro_brand_new_feature', 'default' ) );
	}

	public function test_get_preserves_zero_string_as_real_value() {
		// '0' must not be treated as "missing".
		update_option( 'zehoro_lu_auto_inject', '0' );
		$this->assertSame( '0', Option::get( 'zehoro_lu_auto_inject', 'wrong-default' ) );
	}

	public function test_get_preserves_empty_string_as_real_value() {
		// Admin saved an explicitly-empty value; helper must NOT fall back to
		// legacy or return the default.
		update_option( 'zehoro_cta_primary_url', '' );
		update_option( 'lkst_cta_primary_url',   'https://legacy.example/' );

		$this->assertSame( '', Option::get( 'zehoro_cta_primary_url', 'wrong-default' ) );
	}

	public function test_get_preserves_arrays_through_the_fallback_path() {
		update_option( 'lkst_disclaimer_post_types', [ 'post', 'page' ] );

		$this->assertSame( [ 'post', 'page' ], Option::get( 'zehoro_disclaimer_post_types', [] ) );
	}

	// ── End-to-end: lkst_active_modules → zehoro_active_modules ─────────────
	// Exercises the Phase 1b flip across both Free's Plugin and the call sites
	// that consume it.

	public function test_active_modules_round_trip_through_migrator_and_helper() {
		// Existing site: lkst_active_modules has legacy data; zehoro_ unset.
		update_option( 'lkst_active_modules', [ 'tldr', 'faq', 'author_box' ] );
		$this->assertSame( [ 'tldr', 'faq', 'author_box' ], Option::get( 'zehoro_active_modules', [] ), 'pre-migration: fallback path returns legacy data' );

		// Migrator runs (mimics plugins_loaded@1): copies legacy→canonical, then
		// the module-slug migration rewrites the renamed slug in the canonical key.
		ZehoroRenameMigrator::run();

		// Canonical now holds the copy WITH the renamed slug (tldr→key_takeaways)
		// AND the newly-introduced default module (evaluation), which run() adds
		// once so a new default doesn't ship dark on an existing site.
		$this->assertSame( [ 'key_takeaways', 'faq', 'author_box', 'evaluation' ], Option::get( 'zehoro_active_modules', [] ), 'post-migration: renamed slug + new default module' );

		// Subsequent writes land on the canonical key only. Legacy unchanged
		// (rollback safety until v1.8.0 cleanup migrator) — including its old slug.
		update_option( 'zehoro_active_modules', [ 'key_takeaways', 'faq', 'author_box', 'entitymap' ] );
		$this->assertSame( [ 'key_takeaways', 'faq', 'author_box', 'entitymap' ], Option::get( 'zehoro_active_modules', [] ) );
		$this->assertSame( [ 'tldr', 'faq', 'author_box' ], get_option( 'lkst_active_modules' ), 'legacy preserved untouched as rollback safety' );
	}
}
