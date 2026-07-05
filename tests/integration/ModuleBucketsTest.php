<?php
/**
 * Buckets — the four top-level module groupings (one level above the fine-grained
 * groups) shown on the Modules page. "The Loop" is the front door; Blocks /
 * Conversion / Toolkit are configure-once furniture.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Core\Plugin;

class ModuleBucketsTest extends WP_UnitTestCase {

	public function test_bucket_labels_lead_with_the_loop(): void {
		$labels = Plugin::bucket_labels();
		$this->assertSame(
			[ Plugin::BUCKET_LOOP, Plugin::BUCKET_BLOCKS, Plugin::BUCKET_CONVERSION, Plugin::BUCKET_TOOLKIT ],
			array_keys( $labels ),
			'The Loop is the front door — first in display order.'
		);
		$this->assertSame( 'The Loop', $labels[ Plugin::BUCKET_LOOP ] );
		$this->assertSame( Plugin::BUCKET_LOOP, Plugin::BUCKET_PRIMARY );
	}

	public function test_groups_map_to_the_right_bucket(): void {
		$this->assertSame( Plugin::BUCKET_LOOP,       Plugin::group_bucket( 'seo' ) );
		$this->assertSame( Plugin::BUCKET_LOOP,       Plugin::group_bucket( 'ai' ) );
		$this->assertSame( Plugin::BUCKET_BLOCKS,     Plugin::group_bucket( 'editorial_blocks' ) );
		$this->assertSame( Plugin::BUCKET_BLOCKS,     Plugin::group_bucket( 'schema' ) );
		$this->assertSame( Plugin::BUCKET_CONVERSION, Plugin::group_bucket( 'conversion' ) );
		$this->assertSame( Plugin::BUCKET_TOOLKIT,    Plugin::group_bucket( 'reading_ux' ) );
		$this->assertSame( Plugin::BUCKET_TOOLKIT,    Plugin::group_bucket( 'admin' ) );
	}

	public function test_unknown_group_falls_into_toolkit_not_loop(): void {
		// Toolkit is the catch-all — never let an unknown group pollute The Loop.
		$this->assertSame( Plugin::BUCKET_TOOLKIT, Plugin::group_bucket( 'brand_new_group' ) );
	}

	// ── The three reclassifications that keep The Loop pure intelligence ──────────

	public function test_edit_log_is_a_loop_organ(): void {
		Plugin::register_module( 'edit_log', '\\Zehoro\\Pro\\Modules\\EditLog', [ 'title' => 'Edit Log', 'desc' => '...' ] );
		$group = Plugin::get_registered_modules()['edit_log']['group'];
		$this->assertSame( 'seo', $group, 'edit_log records the edits the needle-measurement reads.' );
		$this->assertSame( Plugin::BUCKET_LOOP, Plugin::group_bucket( $group ) );
	}

	public function test_nav_pills_are_toolkit_not_loop(): void {
		Plugin::register_module( 'category_pills',    '\\Zehoro\\Modules\\CategoryPills',   [ 'title' => 'Category Pills', 'desc' => '...' ] );
		Plugin::register_module( 'home_filter_pills', '\\Zehoro\\Modules\\HomeFilterPills', [ 'title' => 'Home Filter Pills', 'desc' => '...' ] );
		$reg = Plugin::get_registered_modules();
		foreach ( [ 'category_pills', 'home_filter_pills' ] as $slug ) {
			$this->assertSame( 'admin', $reg[ $slug ]['group'], "$slug is front-end nav UX, not a loop diagnostic." );
			$this->assertSame( Plugin::BUCKET_TOOLKIT, Plugin::group_bucket( $reg[ $slug ]['group'] ) );
		}
	}
}
