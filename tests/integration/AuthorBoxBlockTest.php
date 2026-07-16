<?php
/**
 * Author Box block — the render seam (AuthorBox::render_block, Person + Org
 * modes, toggles, escaping) + the ArticleSchema E-E-A-T enrichment
 * (author worksFor the canonical Org by @id; sameAs includes the social links).
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\AuthorBox;
use Zehoro\Modules\ArticleSchema;

class AuthorBoxBlockTest extends WP_UnitTestCase {

	private function author_with_meta(): int {
		$id = self::factory()->user->create( [
			'role'         => 'author',
			'display_name' => 'Jane Expert',
			'description'  => 'Twenty years in the field.',
		] );
		update_user_meta( $id, 'zehoro_author_tagline', 'Fittings specialist' );
		update_user_meta( $id, 'zehoro_chip_1', 'BEng' );
		update_user_meta( $id, 'zehoro_chip_2', 'CSWIP 3.1' );
		update_user_meta( $id, 'zehoro_social_linkedin', 'https://linkedin.com/in/jane' );
		update_user_meta( $id, 'zehoro_social_x', 'https://x.com/jane' );
		return $id;
	}

	// -------------------------------------------------------------------------
	// Person mode
	// -------------------------------------------------------------------------

	public function test_person_card_renders_from_author_meta() {
		$id   = $this->author_with_meta();
		$html = AuthorBox::render_block( [ 'mode' => 'person', 'authorId' => $id ] );

		$this->assertStringContainsString( 'zehoro-author-box--person', $html );
		$this->assertStringContainsString( 'Jane Expert', $html );
		$this->assertStringContainsString( 'Fittings specialist', $html );
		$this->assertStringContainsString( 'Twenty years in the field.', $html );
		$this->assertStringContainsString( '<li class="zehoro-author-box__credential">BEng</li>', $html );
		$this->assertStringContainsString( 'linkedin.com/in/jane', $html );
	}

	public function test_toggles_hide_sections() {
		$id   = $this->author_with_meta();
		$html = AuthorBox::render_block( [
			'mode'            => 'person',
			'authorId'        => $id,
			'showBio'         => false,
			'showCredentials' => false,
			'showSocials'     => false,
		] );

		$this->assertStringContainsString( 'Jane Expert', $html, 'name always shows' );
		$this->assertStringNotContainsString( 'Twenty years', $html );
		$this->assertStringNotContainsString( 'BEng', $html );
		$this->assertStringNotContainsString( 'zehoro-author-box__socials', $html );
	}

	public function test_no_author_renders_nothing() {
		$this->assertSame( '', AuthorBox::render_block( [ 'mode' => 'person', 'authorId' => 0 ] ) );
	}

	public function test_credential_is_escaped() {
		$id = self::factory()->user->create( [ 'role' => 'author', 'display_name' => 'X' ] );
		update_user_meta( $id, 'zehoro_chip_1', '<script>alert(1)</script>' );
		$html = AuthorBox::render_block( [ 'mode' => 'person', 'authorId' => $id ] );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_social_links_carry_noopener() {
		$id   = $this->author_with_meta();
		$html = AuthorBox::render_block( [ 'mode' => 'person', 'authorId' => $id ] );
		$this->assertMatchesRegularExpression( '/<a class="zehoro-author-box__social"[^>]*rel="noopener/', $html );
	}

	// -------------------------------------------------------------------------
	// Organization mode
	// -------------------------------------------------------------------------

	public function test_org_card_renders_site_identity() {
		$html = AuthorBox::render_block( [ 'mode' => 'organization' ] );
		$this->assertStringContainsString( 'zehoro-author-box--organization', $html );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $html );
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function test_block_is_registered() {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'zehoro/author-box' ) );
	}

	// -------------------------------------------------------------------------
	// ArticleSchema enrichment — the differentiator (no duplicate Org)
	// -------------------------------------------------------------------------

	public function test_article_schema_author_worksFor_the_canonical_org_by_id() {
		$author = $this->author_with_meta();
		$post   = self::factory()->post->create_and_get( [ 'post_type' => 'post', 'post_author' => $author ] );
		$schema = ArticleSchema::build_schema( $post );

		$this->assertArrayHasKey( 'worksFor', $schema['author'] );
		$this->assertNotEmpty( $schema['publisher']['@id'] );
		$this->assertSame(
			$schema['publisher']['@id'],
			$schema['author']['worksFor']['@id'],
			'worksFor must reference the SAME @id as the publisher Org (one canonical node, no duplicate)'
		);
	}

	public function test_article_schema_sameAs_includes_author_socials() {
		$author = $this->author_with_meta();
		$post   = self::factory()->post->create_and_get( [ 'post_type' => 'post', 'post_author' => $author ] );
		$schema = ArticleSchema::build_schema( $post );

		$this->assertContains( 'https://linkedin.com/in/jane', $schema['author']['sameAs'] );
		$this->assertContains( 'https://x.com/jane', $schema['author']['sameAs'] );
	}
}
