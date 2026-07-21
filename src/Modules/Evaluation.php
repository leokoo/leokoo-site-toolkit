<?php
/**
 * Evaluation — the "We Tested" editorial scorecard (launch block #5).
 *
 * A theme-neutral card for a hands-on evaluation of a THIRD-PARTY subject:
 * a "how we tested" methodology note, per-criteria scores (auto-averaged into
 * an overall score), pros/cons, and a verdict. It is the editorial-trust
 * counterpart to Pro's affiliate Review Box — no price, no buy-buttons; those
 * monetisation surfaces stay in Pro.
 *
 * Dynamic block: JS save() returns null and ALL front-end markup comes from the
 * single seam {@see self::render_block()} — the same seam a future connected
 * version would upgrade.
 *
 * Schema: unlike the other launch blocks (which emit none), this one CAN emit
 * Review JSON-LD — but behind a hard, code-level SELF-SERVING GUARDRAIL. A
 * "review" of your own site/brand is exactly what Google's spam policy
 * penalises, so {@see self::is_self_serving()} refuses to emit when the reviewed
 * subject is this site (same-domain URL, or a name that matches the site name).
 * Only genuine third-party subjects get structured data, one Review per URL,
 * and WP Review Pro (if active) wins.
 *
 * @package Zehoro\Modules
 */

namespace Zehoro\Modules;

use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Evaluation implements ModuleInterface {

	/** One Review rich result per URL — set once the first Evaluation emits. */
	private static bool $schema_emitted = false;

	/** schema.org types valid for Review.itemReviewed; anything else → Product. */
	private const ALLOWED_ITEM_TYPES = [
		'Product', 'Book', 'SoftwareApplication', 'Movie',
		'MusicAlbum', 'LocalBusiness', 'Course', 'Recipe', 'Game',
	];

	public static function register(): void {
		Plugin::register_module( 'evaluation', self::class, [
			'title'   => 'We Tested (Evaluation)',
			'desc'    => 'A hands-on evaluation scorecard: how you tested, per-criteria scores, pros/cons, and a verdict. Emits penalty-safe Review schema for third-party subjects only.',
			'default' => true,
		] );
	}

	public function init(): void {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block(): void {
		register_block_type( ZEHORO_DIR . 'build/evaluation' );
	}

	// -------------------------------------------------------------------------
	// The single render seam (card + guardrailed schema)
	// -------------------------------------------------------------------------

	public static function render_block( array $attributes, string $wrapper = '' ): string {
		$subject     = trim( (string) ( $attributes['subjectName'] ?? '' ) );
		$subject_url = trim( (string) ( $attributes['subjectUrl'] ?? '' ) );
		$methodology = trim( (string) ( $attributes['methodology'] ?? '' ) );
		$verdict     = trim( (string) ( $attributes['verdict'] ?? '' ) );
		$criteria    = is_array( $attributes['criteria'] ?? null ) ? $attributes['criteria'] : [];
		$pros        = is_array( $attributes['pros'] ?? null ) ? $attributes['pros'] : [];
		$cons        = is_array( $attributes['cons'] ?? null ) ? $attributes['cons'] : [];
		$level       = self::heading_level( $attributes );

		$avg      = self::average_score( $criteria );
		$rows     = self::render_criteria( $criteria );
		$proscons = self::render_pros_cons( $pros, $cons, min( $level + 1, 6 ) );

		// Empty contract: nothing meaningful to show → render nothing.
		if ( $subject === '' && $rows === '' && $verdict === '' && $methodology === '' && $proscons === '' ) {
			return '';
		}

		if ( $wrapper === '' ) {
			$wrapper = 'class="zehoro-eval"';
		}

		$html = '<div class="zehoro-eval__header">';
		if ( $subject !== '' ) {
			$name = esc_html( $subject );
			if ( $subject_url !== '' ) {
				$name = '<a class="zehoro-eval__subject-link" href="' . esc_url( $subject_url ) . '" rel="noopener nofollow">' . $name . '</a>';
			}
			$html .= sprintf( '<h%1$d class="zehoro-eval__subject">%2$s</h%1$d>', $level, $name );
		}
		if ( $avg > 0 ) {
			$html .= '<div class="zehoro-eval__score" aria-label="'
				/* translators: %s: the averaged criteria score, e.g. "4.5" */
				. esc_attr( sprintf( __( 'Overall score: %s out of 5', 'zehoro-toolkit' ), number_format_i18n( $avg, 1 ) ) )
				. '"><span class="zehoro-eval__score-num">' . esc_html( number_format_i18n( $avg, 1 ) ) . '</span>'
				. '<span class="zehoro-eval__score-max">/5</span></div>';
		}
		$html .= '</div>';

		if ( $methodology !== '' ) {
			$html .= '<p class="zehoro-eval__methodology"><span class="zehoro-eval__methodology-label">'
				. esc_html__( 'How we tested', 'zehoro-toolkit' ) . '</span> '
				. nl2br( esc_html( $methodology ) ) . '</p>';
		}

		if ( $rows !== '' ) {
			$html .= '<div class="zehoro-eval__criteria">' . $rows . '</div>';
		}

		$html .= $proscons;

		if ( $verdict !== '' ) {
			$html .= '<div class="zehoro-eval__verdict"><span class="zehoro-eval__verdict-label">'
				. esc_html__( 'Our verdict', 'zehoro-toolkit' ) . '</span> <span class="zehoro-eval__verdict-text">'
				. nl2br( esc_html( $verdict ) ) . '</span></div>';
		}

		$card = sprintf( '<div %s>%s</div>', $wrapper, $html );

		return $card . self::maybe_schema( $attributes, $avg );
	}

	// -------------------------------------------------------------------------
	// Rendering helpers
	// -------------------------------------------------------------------------

	private static function render_criteria( array $criteria ): string {
		$out = '';
		foreach ( $criteria as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$name  = trim( (string) ( $c['name'] ?? '' ) );
			$score = isset( $c['score'] ) ? (float) $c['score'] : 0.0;
			$score = max( 0.0, min( 5.0, $score ) );
			if ( $name === '' && $score <= 0 ) {
				continue;
			}
			$pct = (int) round( ( $score / 5 ) * 100 );

			$out .= '<div class="zehoro-eval__criterion">';
			$out .= '<div class="zehoro-eval__criterion-head"><span class="zehoro-eval__criterion-name">'
				. esc_html( $name ) . '</span>';
			if ( $score > 0 ) {
				$out .= '<span class="zehoro-eval__criterion-score">' . esc_html( number_format_i18n( $score, 1 ) ) . '</span>';
			}
			$out .= '</div>';
			if ( $score > 0 ) {
				$label = $name !== ''
					/* translators: 1: the criterion name, e.g. "Build quality"; 2: its score, e.g. "4.0" */
					? sprintf( __( '%1$s: %2$s out of 5', 'zehoro-toolkit' ), $name, number_format_i18n( $score, 1 ) )
					/* translators: %s: the criterion score, e.g. "4.0" */
					: sprintf( __( '%s out of 5', 'zehoro-toolkit' ), number_format_i18n( $score, 1 ) );
				$out .= '<div class="zehoro-eval__bar" role="img" aria-label="' . esc_attr( $label ) . '">'
					. '<span class="zehoro-eval__bar-fill" style="width:' . esc_attr( (string) $pct ) . '%"></span></div>';
			}
			$out .= '</div>';
		}
		return $out;
	}

	/**
	 * @param int $sub_level Heading level for the Pros/Cons subheadings — one
	 *                       below the subject heading so heading order never skips.
	 */
	private static function render_pros_cons( array $pros, array $cons, int $sub_level = 4 ): string {
		$clean = static function ( array $items ): array {
			$out = [];
			foreach ( $items as $i ) {
				$i = trim( (string) $i );
				if ( $i !== '' ) {
					$out[] = $i;
				}
			}
			return $out;
		};
		$pros = $clean( $pros );
		$cons = $clean( $cons );
		if ( ! $pros && ! $cons ) {
			return '';
		}
		$sub_level = ( $sub_level >= 2 && $sub_level <= 6 ) ? $sub_level : 4;
		$h         = 'h' . $sub_level;

		$out = '<div class="zehoro-eval__proscons">';
		if ( $pros ) {
			$out .= '<div class="zehoro-eval__pros"><' . $h . ' class="zehoro-eval__proscons-heading">'
				. esc_html__( 'Pros', 'zehoro-toolkit' ) . '</' . $h . '><ul>';
			foreach ( $pros as $p ) {
				$out .= '<li>' . esc_html( $p ) . '</li>';
			}
			$out .= '</ul></div>';
		}
		if ( $cons ) {
			$out .= '<div class="zehoro-eval__cons"><' . $h . ' class="zehoro-eval__proscons-heading">'
				. esc_html__( 'Cons', 'zehoro-toolkit' ) . '</' . $h . '><ul>';
			foreach ( $cons as $c ) {
				$out .= '<li>' . esc_html( $c ) . '</li>';
			}
			$out .= '</ul></div>';
		}
		$out .= '</div>';
		return $out;
	}

	private static function heading_level( array $attributes ): int {
		$level = isset( $attributes['headingLevel'] ) ? (int) $attributes['headingLevel'] : 3;
		return ( $level >= 2 && $level <= 4 ) ? $level : 3;
	}

	public static function average_score( array $criteria ): float {
		$scores = [];
		foreach ( $criteria as $c ) {
			if ( is_array( $c ) && isset( $c['score'] ) && (float) $c['score'] > 0 ) {
				$scores[] = min( 5.0, max( 0.0, (float) $c['score'] ) );
			}
		}
		if ( ! $scores ) {
			return 0.0;
		}
		return array_sum( $scores ) / count( $scores );
	}

	// -------------------------------------------------------------------------
	// Guardrailed Review schema
	// -------------------------------------------------------------------------

	/**
	 * True when the reviewed subject is THIS site/brand — a self-serving review,
	 * which Google's spam policy penalises. Signals: the subject links to our own
	 * domain, or its name matches the site name. Extensible via filter.
	 */
	public static function is_self_serving( array $attributes ): bool {
		$self      = false;
		$site_host = self::normalize_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		$url = trim( (string) ( $attributes['subjectUrl'] ?? '' ) );
		if ( $url !== '' ) {
			$subject_host = self::normalize_host( (string) wp_parse_url( esc_url_raw( $url ), PHP_URL_HOST ) );
			if ( $subject_host === '' ) {
				// No parseable host → a root-relative / same-site link (or malformed
				// input): it resolves to THIS site, so treat as self — penalty-safe.
				$self = true;
			} elseif ( $site_host !== '' && self::hosts_same_site( $subject_host, $site_host ) ) {
				// Exact host, a www variant, or a subdomain either way
				// (shop.example.com ↔ example.com) — all the same organisation.
				$self = true;
			}
		}

		if ( ! $self ) {
			$subject   = trim( (string) ( $attributes['subjectName'] ?? '' ) );
			$site_name = trim( (string) get_bloginfo( 'name' ) );
			if ( $subject !== '' && $site_name !== '' && strcasecmp( $subject, $site_name ) === 0 ) {
				$self = true;
			}
		}

		/**
		 * Filter the self-serving verdict. Return true to force-suppress the
		 * Review schema, false to allow it. A NON-boolean return falls back to
		 * the detected value, so a buggy callback can never accidentally
		 * un-suppress a real self-review.
		 *
		 * @param bool  $self       Whether the subject is this site/brand.
		 * @param array $attributes Block attributes.
		 */
		$filtered = apply_filters( 'zehoro/evaluation/is_self_serving', $self, $attributes );
		return is_bool( $filtered ) ? $filtered : $self;
	}

	/** Lowercase, drop a trailing dot, strip a leading `www.` — for host comparison. */
	private static function normalize_host( string $host ): string {
		$host = strtolower( rtrim( trim( $host ), '.' ) );
		if ( strpos( $host, 'www.' ) === 0 ) {
			$host = substr( $host, 4 );
		}
		return $host;
	}

	/**
	 * True when two normalized hosts belong to the same site: equal, or one is a
	 * subdomain of the other (str_ends_with is PHP 8.0 — substr keeps the 7.4 floor).
	 */
	private static function hosts_same_site( string $a, string $b ): bool {
		if ( $a === '' || $b === '' ) {
			return false;
		}
		if ( $a === $b ) {
			return true;
		}
		$a_suffix = '.' . $a; // $b is a subdomain of $a  → $b ends with ".$a"
		$b_suffix = '.' . $b; // $a is a subdomain of $b  → $a ends with ".$b"
		return substr( $b, -strlen( $a_suffix ) ) === $a_suffix
			|| substr( $a, -strlen( $b_suffix ) ) === $b_suffix;
	}

	/**
	 * Build the Review JSON-LD, or '' when any guard fails (schema off, no
	 * subject, no rating, self-serving, WP Review Pro active, already emitted).
	 */
	private static function maybe_schema( array $attributes, float $avg ): string {
		if ( empty( $attributes['emitSchema'] ) ) {
			return '';
		}
		$subject = trim( (string) ( $attributes['subjectName'] ?? '' ) );
		if ( $subject === '' || $avg <= 0 ) {
			return '';
		}
		// Non-duplication: WP Review Pro (or any review plugin, via the filter)
		// owns Review JSON-LD for this page.
		$defer = class_exists( ArticleSchema::class ) && ArticleSchema::wp_review_pro_active();
		/**
		 * Let another plugin claim the Review JSON-LD for this URL. Return true
		 * to defer (suppress this block's Review).
		 *
		 * @param bool  $defer      Whether a review plugin already owns the schema.
		 * @param array $attributes Block attributes.
		 */
		if ( apply_filters( 'zehoro/evaluation/defer_review_schema', $defer, $attributes ) ) {
			return '';
		}
		// The guardrail: never emit a review of ourselves.
		if ( self::is_self_serving( $attributes ) ) {
			return '';
		}
		// One Review rich result per URL (Google).
		if ( self::$schema_emitted ) {
			return '';
		}

		$item_type = in_array( $attributes['itemType'] ?? '', self::ALLOWED_ITEM_TYPES, true )
			? (string) $attributes['itemType']
			: 'Product';

		$item_reviewed = [ '@type' => $item_type, 'name' => $subject ];
		$subject_url   = esc_url_raw( trim( (string) ( $attributes['subjectUrl'] ?? '' ) ) );
		if ( $subject_url !== '' ) {
			$item_reviewed['url'] = $subject_url;
		}

		$author_name = get_the_author_meta( 'display_name' ) ?: get_bloginfo( 'name' );

		$schema = [
			'@context'     => 'https://schema.org',
			'@type'        => 'Review',
			'itemReviewed' => $item_reviewed,
			'reviewRating' => [
				'@type'       => 'Rating',
				'ratingValue' => round( $avg, 1 ),
				'bestRating'  => 5,
				'worstRating' => 1,
			],
			'author'       => [ '@type' => 'Person', 'name' => $author_name ],
		];
		$body = trim( wp_strip_all_tags( (string) ( $attributes['verdict'] ?? '' ) ) );
		if ( $body !== '' ) {
			$schema['reviewBody'] = $body;
		}

		/**
		 * Filter the Evaluation Review JSON-LD before output. Return false (or a
		 * non-array) to suppress it entirely.
		 *
		 * @param array $schema     The schema.org Review array.
		 * @param array $attributes Block attributes.
		 */
		$schema = apply_filters( 'zehoro/evaluation/schema', $schema, $attributes );
		if ( ! is_array( $schema ) || ! $schema ) {
			return '';
		}

		// JSON_HEX_* neutralises a literal `</script>` (or `<`, `&`, quotes)
		// inside any string value — without them an author could close the
		// JSON-LD <script> early and inject markup (stored XSS).
		$json = wp_json_encode(
			$schema,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		if ( ! $json ) {
			return '';
		}

		self::$schema_emitted = true;
		return "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}

	/** Test-only: reset the one-per-page latch between test cases. */
	public static function reset_schema_latch(): void {
		self::$schema_emitted = false;
	}
}
