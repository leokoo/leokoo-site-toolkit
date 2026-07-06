<?php
/**
 * Steps HowTo JSON-LD — stored-XSS guard on the author-supplied task name.
 *
 * A `</script>` in the block's taskName must not be able to close the
 * `<script type="application/ld+json">` block and inject markup.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\Steps;

class StepsSchemaXssTest extends WP_UnitTestCase {

	private function build_schema( array $steps, string $taskName ): string {
		$obj = ( new ReflectionClass( Steps::class ) )->newInstanceWithoutConstructor();
		$m   = new ReflectionMethod( Steps::class, 'build_schema_tag' );
		$m->setAccessible( true );
		return (string) $m->invoke( $obj, $steps, $taskName );
	}

	public function test_task_name_cannot_break_out_of_the_script_block() {
		$evil = '</script><script>alert(1)</script>';
		$html = $this->build_schema( [ [ 'title' => 'Step 1', 'content' => 'do X' ] ], $evil );

		$this->assertStringContainsString( 'application/ld+json', $html, 'schema still emits' );
		// The breakout sequence must NOT appear: the name is sanitized (tags stripped)
		// AND slashes are escaped, so a literal </script> can't close the block.
		$this->assertStringNotContainsString( '</script><script>', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	public function test_a_benign_name_still_renders() {
		$html = $this->build_schema( [ [ 'title' => 'Step 1', 'content' => 'x' ] ], 'How to brew coffee' );
		$this->assertStringContainsString( 'How to brew coffee', $html );
		$this->assertStringContainsString( '"@type":"HowTo"', $html );
	}
}
