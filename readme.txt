=== Zehoro Toolkit ===
Contributors: leokoo
Tags: table of contents, faq, schema, author box, structured data
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.33.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editorial toolkit for content sites — E-E-A-T Article schema, Table of Contents, FAQ, author boxes, and content blocks. Coexists with your SEO plugin.

== Description ==

**Zehoro Toolkit** is a lightweight editorial toolkit for content-driven WordPress sites. It adds the on-page building blocks that help readers — and search engines, and AI answer engines — understand your content, without taking over your SEO setup.

It is **plug-and-play**: no API keys, no external accounts, no heavy configuration. Activate it and the blocks and schema are available immediately.

= What you get =

* **Table of Contents** — automatic, accessible, generated from your headings.
* **Key Takeaways** — a scannable summary box for the top of a post.
* **FAQ** — an accessible question-and-answer accordion (native `details`/`summary`).
* **Pros & Cons** — a two-column verdict box for reviews and comparisons.
* **"We Tested" evaluation** — a hands-on scorecard (per-criteria scores, methodology, verdict) with penalty-safe Review schema for genuine third-party subjects.
* **Author boxes** — surface the human behind the post (an E-E-A-T signal that AI answer engines increasingly weigh).
* **Article schema (E-E-A-T)** — Article / author / publisher JSON-LD that reflects real authorship and freshness signals.

(More editorial and conversion blocks — Callout, Stat Callout, Steps, Testimonial, product boxes and more — live in Zehoro Toolkit Pro.)

= Works *alongside* your SEO plugin =

Zehoro is **not** an SEO plugin and does not try to be one. It deliberately does not touch meta titles, sitemaps, canonicals, or robots — leave those to Yoast, Rank Math, SEOPress, AIOSEO, SureRank, Slim SEO, or whichever you run.

The one place the two could overlap is structured data (schema). Zehoro detects an active SEO plugin and, by default, **pauses its own schema output** so you never get duplicate markup. Prefer Zehoro's schema? One click in the settings switches it back on. You stay in control.

= Part of a bigger picture =

Zehoro Toolkit is the free base for **Zehoro Toolkit Pro**, which adds the content-to-revenue loop — Google Search Console–fed recommendations (what to fix next), entity / internal-linking intelligence, conversion tracking, and content ROI. The free toolkit is fully useful on its own; Pro is optional.

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New**, search for "Zehoro Toolkit", and click **Install Now**.
2. Activate the plugin.
3. (Optional) Visit **Zehoro → Start Here** to see the available modules. Everything works with default settings — there is nothing you must configure.

That's it. No keys, no accounts.

== Frequently Asked Questions ==

= Does this conflict with my SEO plugin (Yoast / Rank Math / etc.)? =

No. Zehoro doesn't manage titles, sitemaps, canonicals, or robots, so there's no overlap there. For structured data, Zehoro detects your SEO plugin and stands its own schema down by default to avoid duplicate markup. You can override that in the Zehoro settings if you'd rather Zehoro emit the schema.

= Do I need an API key or an account? =

No. The free toolkit is entirely self-contained and plug-and-play.

= Does it send my data anywhere? =

No. The free plugin does not phone home or transmit your content. (The optional Pro add-on connects only to services *you* configure, such as your own Google Search Console — never without your action.)

= Will it slow my site down? =

It's built to be light: front-end assets load only on pages that actually use a Zehoro block or feature.

= Is it compatible with the block editor and page builders? =

The content features are native Gutenberg blocks. Page builders can use the shortcode / auto-injected equivalents where applicable.

== Screenshots ==

1. The Start Here screen — every available module at a glance.
2. Table of Contents rendered on a post.
3. The FAQ block (accordion plus structured data).
4. SEO-plugin coexistence: Zehoro detects your SEO plugin and stands its schema down, with a one-click override.

== Changelog ==

= 1.33.0 =
* New **"We Tested" evaluation block** — a hands-on scorecard (per-criteria scores, methodology, pros/cons, verdict). Emits Review schema only for genuine third-party subjects; a review of your own site or brand is automatically suppressed to stay within search-engine guidelines.

= 1.32.0 =
* New **Author Box block** — auto-fills from the post author (bio, credentials, links), with an Organization mode. Also enriches the Article schema author with employer and social profiles, without creating duplicate schema nodes.

= 1.31.0 =
* New **FAQ block** — an accessible question-and-answer accordion built on native `details`/`summary`. It leads on the visible, quotable accordion rather than FAQ markup (Google retired FAQ rich results in May 2026).

= 1.30.0 =
* New consolidated **Pros & Cons block** — a scannable two-column (or single-list) box, replacing the older three-block set. Existing content migrates automatically.

= 1.29.0 =
* New **Key Takeaways block** — a summary box for the top of a post. Replaces the older TL;DR block, with automatic migration of existing content.

= 1.26.0 – 1.28.0 =
* Modules screen re-organised for clarity; verified audit remediations (schema override, module grouping, updater token). No change to how your content renders.

= 1.24.4 =
* Fixed: FAQ and "Last Updated" structured data now honor the central schema-coexistence setting. Setting schema output to "never" (or letting an SEO plugin handle schema under "auto") now silences FAQPage and dateModified JSON-LD too — not just Article schema — preventing duplicate markup.

= 1.24.3 =
* Security: the email-capture webhook now uses WordPress's safe HTTP client, blocking SSRF to private/loopback addresses. Surfaced by an external audit. No feature change.

= 1.24.2 =
* Fixed the email-capture Content Box (and the email content slot), which an incomplete rename had broken — the form now submits, the download link is delivered, and per-box webhook overrides apply again.

= 1.24.1 =
* Suite-card members (Blocks / Schema / Reading & Trust) now show a settings link, so every module's settings page is reachable without typing the URL.

= 1.24.0 =
* Your data is now safe on uninstall: settings are preserved by default. A new "Danger Zone" adds an opt-in "delete on uninstall" toggle and an "Erase all data now" button.

= 1.23.1 =
* Uninstall now clears both the current `zehoro_*` and legacy `lkst_*` data; hardened the module-save handler.

= 1.23.0 =
* Module "suites": the Modules grid collapses commodity groups into single cards with sub-toggles (Kadence-Blocks style), so you meet about 15 cards instead of 50.

= 1.22.2 =
* Module grouping fix: ten loop/SEO modules that were landing in "Other" are now grouped correctly.

= 1.22.1 =
* Housekeeping for public distribution: added the GPLv2-or-later license header and the full license text, and guarded the GitHub auto-updater so it cleanly no-ops in repository builds (WordPress.org serves updates). No change to the plugin's features.

= 1.22.0 =
* Added one canonical SEO-plugin detector and **coexist-by-default** behaviour: when a dedicated SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO, SureRank, Slim SEO, The SEO Framework, Squirrly, Schema Pro) is active, Zehoro pauses its own schema to avoid duplicate markup. A new admin notice names the detected plugin and offers a one-click "Use Zehoro's schema instead" override.
* Internationalization: text-domain loading aligned for translation; bundled translation template.

== Upgrade Notice ==

= 1.33.0 =
Adds the "We Tested" evaluation block (with penalty-safe review schema) — the fifth and final launch block. Recommended update.

= 1.24.3 =
Security hardening for the email-capture webhook. Safe to update.

= 1.24.0 =
Your settings are now preserved on uninstall by default (with an opt-in delete). Recommended update.

= 1.22.1 =
Licensing and distribution housekeeping. No feature changes.
