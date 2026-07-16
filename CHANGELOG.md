# Changelog

All notable changes to the **Zehoro Toolkit** will be documented in this file.

## [1.33.0] - 2026-07-16

### Added — "We Tested" evaluation block (launch block #5)
`zehoro/evaluation`: an editorial evaluation scorecard — a "how we tested" methodology note, per-criteria
scores (auto-averaged into an overall score), pros/cons, and a verdict. Theme-neutral, server-rendered through
one seam. It's the trust-signal counterpart to Pro's affiliate Review Box — no price, no buy-buttons (those
stay in Pro).

Unlike the other launch blocks (which emit no schema), this one **can** emit `Review` JSON-LD — but behind a
hard, code-level **self-serving guardrail**: a "review" of your own site/brand is exactly what Google's spam
policy penalises, so schema is suppressed automatically when the reviewed subject is this site — a link to the
same host (including `www.`, subdomains, and root-relative URLs) or a name matching the site name. Only genuine
third-party subjects get structured data — one Review per
URL, `itemReviewed` restricted to an allowlist of schema.org types, `</script>` breakout neutralised, and WP
Review Pro wins if active. The editor shows a live warning when a subject looks self-serving.

### Fixed — a new default module no longer ships dark on existing sites
`zehoro_active_modules` is a stored positive allowlist with no default-merge (so a deactivated module stays
off). The side-effect: a brand-new default module was absent from every existing site's stored list and would
never activate. The rename migrator now introduces each new default module **once** (opt-out model — it lights
up on upgrade, but a later user deactivation sticks), tracked in `zehoro_seen_new_modules`.

## [1.32.0] - 2026-07-16

### Added — Author Box block (E-E-A-T trust card; launch block #4)
`zehoro/author-box`: a theme-neutral author trust card that **auto-fills from the post author** — avatar,
name, tagline, bio, credential chips, and social links — with a one-click **Organization mode** (site name,
logo, description) for company-authored posts. Per-section toggles (bio / credentials / socials). Server-rendered
through one seam; the editor preview is a real `ServerSideRender` of that seam, so what you see is what ships.
The block emits **no schema of its own** — it is the visible card only.

### Changed — richer author E-E-A-T in Article schema (no duplicate nodes)
`ArticleSchema` now models the author as a real person *inside your organization*: the author `Person` gains
`worksFor` referencing the publisher `Organization` **by the same `@id`** (one canonical Org node, never a
duplicate), and the author's `sameAs` is enriched with their configured social profiles. This is where the
author's structured identity lives — the card renders, the schema attests, with no overlap between them.
Non-duplication with a dedicated SEO plugin's schema is preserved (Article schema stays opt-in / off when a
schema plugin owns it).

### Fixed — JSON-LD URL escaping + safer author resolution (Refuter audit)
Article schema URLs (`sameAs`, author `url`, publisher `logo`, `image`) now use `esc_url_raw` instead of
`esc_url` — the display-context escaper turned `&` into `&#038;`, corrupting any query-string profile URL for
the crawler; a rejected scheme is dropped rather than leaving a blank `sameAs` entry. The Author Box author
resolver now falls back to the queried object **only when it is a post**, so a Person card placed on a
category/author archive can no longer misread a term/user id as a post id and render an unrelated author.

## [1.31.0] - 2026-07-12

### Added — FAQ block (accessible accordion, no schema; launch block #3)
`zehoro/faq` + `zehoro/faq-item`: a question-and-answer accordion built on native `<details>`/`<summary>`
(keyboard- and screen-reader-accessible), with **rich answers** (inner blocks — paragraphs, lists) and an
"open by default" toggle per item. Server-rendered through one seam; theme-neutral. **No FAQPage JSON-LD** —
Google retired FAQ rich results on 7 May 2026, so the block leads on the visible, quotable accordion rather
than penalty-adjacent markup. The existing `[zehoro_faq]` shortcode is unchanged for backward-compat.

### Fixed — heading/title/question no longer double-HTML-encoded
RichText serializes its value entity-encoded, so a naive `wp_strip_all_tags()` + `esc_html()` double-encoded
the Key Takeaways heading, the Pros/Cons titles, and the FAQ question — `Terms & Conditions` rendered as
literal `Terms &amp; Conditions`. A shared `BlockSanitize::plain_text()` now decodes entities before the single
`esc_html()` at the output site (caught by the FAQ audit's cross-block lens).

## [1.30.0] - 2026-07-12

### Added — Pros & Cons block (consolidated, launch block #2)
A single dynamic `zehoro/pros-cons` block: a scannable two-column Pros & Cons box (semantic ✓/✕ list markers,
theme-neutral, CSS-variable accents you can override) with a **show** toggle (both | pros | cons) that also
covers a single Pros or Cons list. Server-rendered through one seam, heading level H2–H4, i18n, zero outbound
HTTP, **no schema** (a standalone pros/cons list has no valid type — penalty-safe), empty renders nothing.

### Changed — retired the 3-block `lkst/*` set; consolidated into one
Replaces the retired `lkst/pros-cons` (an InnerBlocks container), `lkst/pros`, and `lkst/cons`. Backward-compat
mirrors Key Takeaways: a `render_block` **safety net** renders any un-migrated legacy block through the seam
(the container's dead wrapper stripped — no `lkst-*` leak); **editor bridges** keep all three valid in Gutenberg
(no invalid-content warning) with a one-click transform to the consolidated block; and `wp zehoro migrate-blocks`
converts them, **unpacking the container's inner pros/cons into one block**. The migrator is now a generalized
**rename registry** (the `zehoro/block_migrations` filter) shared by every block rename.

## [1.29.0] - 2026-07-12

### Added — Key Takeaways block (first launch block; supersedes `lkst/tldr`)
A dynamic, server-rendered **Key Takeaways** block (`zehoro/key-takeaways`) for the answer-first summary at
the top of a post — scannable bullets *or* a short TL;DR paragraph, a quote-ready passage readers and AI
answer engines can lift verbatim. WYSIWYG editor (heading + list/paragraph via a block-toolbar toggle),
configurable heading level (H2–H4), theme-neutral in light + dark, semantic `<section>`/`<h*>`/`<ul>`, fully
translatable, zero outbound HTTP, and **no schema markup** (there is no valid type — the value is the visible
passage). Empty blocks render nothing rather than an empty box. All output flows through **one** server-side
render seam (`Zehoro\Modules\KeyTakeaways::render_html()`) — the single interception point the future
connected/smart version hooks without a block deprecation.

### Changed — retired the legacy `lkst/tldr` block; renamed to `zehoro/key-takeaways`
The brand-correct name + reframe. Existing content is preserved several ways:
- The module slug `tldr` → `key_takeaways`. Because the active-module allowlist stores slugs **by value**, the
  rename migrator now rewrites `tldr` → `key_takeaways` inside the stored `zehoro_active_modules` (own
  idempotency flag, runs before module bootstrap) — without it the module would ship **dark** on every
  upgrading site (block unregistered, safety net + CLI never hooked). Group/curation references updated.
- A `render_block` **safety net** re-renders any un-migrated `lkst/tldr` through the seam **losslessly** —
  multiple paragraphs, lists and inline images keep their structure instead of collapsing to a run-on line.
- An **editor bridge** keeps un-migrated `lkst/tldr` blocks valid in Gutenberg (no "invalid content" warning,
  no Block-Recovery data loss) and offers a one-click **transform to Key Takeaways**. Legacy content that used
  the old default heading keeps its "Key Takeaways" title-case (no silent casing change on upgrade).
- **`wp zehoro migrate-blocks`** (dry run by default; `--execute` to write) permanently rewrites clean inline
  `lkst/tldr` into `zehoro/key-takeaways`. Block-structured legacy content (multi-paragraph / lists) is **left
  as-is and reported**, never silently flattened — the safety net displays it; hand-convert if desired.

The old pre-built `build/tldr` assets are removed. Block-name stability otherwise remains sacred — this is the
sanctioned, non-breaking rename pattern for the wider `lkst-*` → `zehoro-*` cleanup.

### Known limitations (deliberate, documented trade-offs)
- The Key Takeaways editor uses `RichText multiline="li"` for the bullet list — soft-deprecated by WordPress
  but fully functional through 6.9; a future migration to InnerBlocks would break the single attribute-driven
  render seam, so it is a deliberate deferral (tracked for a pre-wp.org decision).
- The block's dark styling keys off `prefers-color-scheme` (the OS setting, not the theme's); the
  `currentColor` border keeps the box visible on a dark theme under a light OS regardless.

## [1.28.0] - 2026-07-07

### Changed — Modules page re-cut along Core vs Surface (the loop is one engine, not 34 toggles)
The flat four-bucket nav now groups into two tiers that match the product's real architecture:
- **Core · the content-business engine** — holds **The Loop** (the CMS-agnostic diagnostics + measurement;
  what becomes the Laravel core).
- **Surface · WordPress rendering** — **Blocks / Conversion / Utilities** (the per-connector toolbox you'd
  strip for a Shopify/Ghost surface). The **Toolkit** bucket is now labelled **Utilities**.
New `Plugin::bucket_tier()` + `TIER_CORE`/`TIER_SURFACE` constants encode the split; the bucket keys are
unchanged (`toolkit` stays the internal key). No modules move on the Free side — this is IA only.

### Fixed — Modules-page status-pill counts ignored the active bucket
The top **All / Active / Inactive / Free / Pro** pills counted *every* card and only ever refreshed the
Active/Inactive two, and never re-ran when you switched buckets — so selecting **The Loop** still read
`All (34)` while the bucket held 14. `updatePillCounts()` now scopes every pill to the selected bucket +
search and recomputes on each filter change (`All (20)` under The Loop, etc.).

## [1.27.3] - 2026-07-06

### Fixed — author sameAs dropped from JSON-LD after the brand rename (Dev-Team re-audit, RENAME-DRIFT-1)
`ArticleSchema` read the author's LinkedIn/Twitter from the legacy `lkst_author_*` user-meta key,
but `ZehoroRenameMigrator` moves that value to `zehoro_author_*` on upgrade — so on any migrated
site the author's `sameAs` (an E-E-A-T authorship signal) silently vanished from the article schema.
It now reads the canonical `zehoro_author_*` key with a fallback to the legacy one for un-migrated
installs. Refuter-verified (the `?:` fallback is safe; the canonical key matches the migrator + DataEraser).

## [1.27.2] - 2026-07-06

### Fixed (security) — stored-XSS hardening in JSON-LD schema
From the Dev-Team bug audit + refuter (each verified with an adversarial probe):
- **Steps HowTo block** — the author `taskName` reached the JSON-LD `name` unsanitized and was
  emitted with `JSON_UNESCAPED_SLASHES`, so a literal `</script>` could break out of the
  `<script type="application/ld+json">` block. Now `sanitize_text_field`'d **and** slash-escaped
  (a 16-vector breakout probe came back clean).
- **Article schema parity** — dropped `JSON_UNESCAPED_SLASHES` from `ArticleSchema`'s JSON-LD too.
  Not exploitable via `post_title` today (WP strips tags), but it gives no safety net for any
  free text a `zehoro_article_schema` filter injects. Defense-in-depth parity with Steps/FAQ.

## [1.27.1] - 2026-07-06

### Fixed — bucket bulk enable/disable now syncs folded suite cards
From the adversarial review of the 1.27.0 buckets IA: a bucket-scoped "Enable all" /
"Disable all" updated the server correctly but left the **suite cards** (Blocks,
Schema, Reading & Trust) visually stale until a reload — the JS looked up the returned
member slugs (faq, tldr, article_schema, …) as top-level cards, but folded suite
members live *inside* a suite card, so every one was silently skipped (and the
Active/Inactive pill counts then read the stale cards). Because a bucket spans whole
suites, this bit every bucket bulk action. The handler now falls back to the folded
`.zehoro-suite-member` and resyncs the enclosing suite card + counts (a shared
`applyBulkToSlug` used by both the bulk and preset paths).

## [1.27.0] - 2026-07-06

### Changed — Modules page grouped into 4 buckets, with The Loop as the front door
The Modules page left-nav listed 8 flat groups, which read as an undifferentiated
"lab." It now groups modules into **4 buckets** one level above the fine-grained
groups: **The Loop** (the daily-driver intelligence engine) is rendered first and
set apart; **Blocks**, **Conversion**, and **Toolkit** fall under a "Set up once"
heading as configure-once site furniture. Clicking a bucket filters the grid to it;
bulk enable/disable is now bucket-scoped (still accepts a fine-group key for
back-compat). Three reclassifications keep The Loop pure intelligence: `edit_log`
moves **into** the Loop (it records the edits the needle-measurement reads), and
`category_pills` + `home_filter_pills` move **out** into Toolkit (front-end nav UX,
not diagnostics). Toolkit is the catch-all — unknown groups fall there, never into
The Loop. New `Plugin::bucket_labels()` / `Plugin::group_bucket()` + `ModuleBucketsTest`.

## [1.26.0] - 2026-07-02

### Fixed — verified audit remediations (schema override · pills styling · updater token)
Three independently-verified fixes from the platform audit pass (175 tests green):

- **FAQ "Always Output Schema" now overrides SEO-plugin coexistence.** `zehoro_faq_schema_mode = 'force'` fell through the coexistence gate exactly like `'auto'`, so a user who explicitly chose "Always Output Schema" had it silently suppressed whenever an SEO plugin was active. `'force'` now emits before the `should_emit_schema()` check (dual FAQPage schema alongside an SEO plugin is the intended meaning of "always").
- **Category / Home Filter pills now load their CSS on archives + home.** Both modules render on non-singular views, but the shared stylesheet gate only loads on `is_singular()` — so the pills rendered unstyled by default. Each module now forces the stylesheet via the `zehoro/load_global_styles` filter when it will actually render on the current view, mirroring how `TableOfContents` handles its auto-injected TOC (evaluated from the query context at enqueue time).
- **Free updater no longer forwards Pro's encrypted token to GitHub.** Pro stores `zehoro_pro_github_token` encrypted at rest (`v1:`/`b64:` ciphertext); Free read it with a bare `get_option` and passed the ciphertext to `setAuthentication()`, yielding a GitHub `401` and a broken update check. Auth is now deferred to `plugins_loaded` and skipped when the token carries an encryption prefix (falling back to anonymous public-repo checks); a hand-set plaintext `ZEHORO_GITHUB_TOKEN` still authenticates.

## [1.25.6] - 2026-06-29

### Removed — orphaned `.zui` dead CSS (synced with Pro 1.174.3)
Pruned the never-built `.zui-momentum` / `.zui-pill` / `.zui-bars` / `.zui-dots` components from the shared admin stylesheet — CSS authored from a design comp but never rendered in any markup (0 references in either plugin). −1.66 KB, no functional change.

## [1.25.5] - 2026-06-29

### Fixed / tidied — shared `.zui` admin stylesheet (synced with Pro 1.174.1)
The `.zui` design system is shared verbatim between Free and Pro; this syncs the admin UX pass:
- **Accessibility:** two tokens darkened to pass WCAG-AA contrast — `--zui-muted-2` (`#8a8678`→`#757160`; the `.zui-help` text is now 4.61:1) and `--zui-gold` (`#b3801f`→`#92691a`; gold header/label text now ≥4.65:1).
- **Responsive:** the app shell stacks at ≤1080px (before the WP admin menu squeezes the content column); added a `.zui-scroll-x` wrapper utility for wide tables.
- **Chore:** pruned the dead `.zui` nav-rail CSS (the in-page rail was abandoned for the WP submenu) — −1.3 KB. No functional change.

## [1.25.4] - 2026-06-29

### Fixed — `has_zehoro_content()` now detects `zehoro-pro/*` blocks
The enqueue gate `PageContent::has_zehoro_content()` matched `wp:zehoro/` **with a trailing slash**, so `wp:zehoro-pro/*` block markup (the Pro product/review blocks) wasn't recognised — meaning a page whose only toolkit content was a Pro block loaded **without** the Pro block stylesheet (unstyled box). The check is now slashless (`wp:zehoro`), mirroring the existing `wp:lkst` match that already covers `lkst` + `lkst-pro`.

## [1.25.3] - 2026-06-26

### Fixed — legacy-rename migrator left the hot `zehoro_active_modules` option un-autoloaded
On sites upgraded from the old `lkst_*`-named plugin, `ZehoroRenameMigrator` copied **every** renamed option with `autoload=false` — including `zehoro_active_modules`, which is read on **every** request to bootstrap the modules. So on those migrated sites it fired an individual `SELECT` per pageview instead of riding WordPress's single cached autoload query. The migrator now autoloads the genuinely-hot keys — `active_modules` + the 5 CSS-variable colours (read on every Zehoro-content page) — while keeping the ~26 module-conditional settings (author box / TOC / disclaimer / FAQ / content box / last-updated) `autoload=false` so they don't bloat the autoload bundle on pages that never render them. (Fresh installs were already fine — `add_option` autoloads by default; this only affected legacy-migrated sites.) Found by an external audit (Gemini), applied **selectively** rather than the proposed blanket `autoload=true`, which would have over-corrected. +1 test (175 green).

## [1.25.2] - 2026-06-26

### Fixed — Article Schema ignored the post-type → @type map (every non-post emitted "Article")
`ArticleSchema::build_schema()` resolved the JSON-LD `@type` from the filterable post-type map (`zehoro_article_schema_type_map`) — then a stray hardcoded line immediately reassigned it to `BlogPosting` (for posts) or `Article` (for everything else), so the **entire shipped map was dead**: a page emitted `Article` instead of `WebPage`; a `recipe` / `service` / `review` CPT emitted `Article` instead of its mapped type; the filter had no effect on `@type`. Removed the clobbering line. (Article Schema only emits when no dedicated SEO plugin — Yoast / RankMath / SEOPress / AIOSEO / SureRank — is active; that coexistence stand-down is unchanged.) Found by an external audit (Gemini), verified against the code. +2 tests (174 green).

## [1.25.1] - 2026-06-24

### Fixed — shared `.zui` button text colour (specificity)
Syncs the shared `admin-zui.css` fix from Pro 1.138.3: `<a>` buttons now keep their own text colour instead of the link blue (the rule is scoped to real links, `a:not(.zui-btn)`). Both plugins enqueue the shared stylesheet on Zehoro admin screens, so both must carry the fix.

## [1.25.0] - 2026-06-24

### Changed — Admin UX: the Claude Design system
The Free admin (the Modules grid + suites, and the Visual Styles / Author Box / RSS settings pages) adopts the shared "warm editorial" design language — warm paper, near-black outlines, IBM Plex type — via a scoped `assets/admin-zui.css` (shared with Pro). The JS-driven modules grid keeps its existing markup, data-attributes, and behaviour (search, filter, per-card toggles, suite cards); the restyle is additive and scoped under `.zui`. 172 tests green. (UNDEPLOYED — on the `ux/claude-design-v1.1` branch for review.)

## [1.24.4] - 2026-06-24

### Fixed — FAQ and Last Updated schema now honor the central coexistence setting
The schema-coexistence override (Article Schema settings: `auto` / `always` / `never`, plus the `zehoro/emit_schema` filter) is meant to be the single switch for whether Zehoro emits structured data. It governed Article Schema, but **FAQ** ran a parallel policy and **Last Updated** had none — so setting it to **never** silenced Article Schema yet left duplicate `FAQPage` and `dateModified` JSON-LD on the page (the exact duplicate markup the coexistence system exists to prevent).

- **FAQ** and **Last Updated** now route their JSON-LD through the same `SeoPlugin::should_emit_schema()` gate as Article Schema. A central **never** silences all three; **auto** defers all three when a known SEO plugin (Yoast / Rank Math / SureRank / …) is active; **always** (and the `zehoro/emit_schema` filter) forces them on. Per-module toggles can only *further* restrict (e.g. FAQ's per-type "off"), never override the central policy.
- **Behavior note:** if you previously relied on the per-module FAQ "always" to force FAQPage schema *alongside* an active SEO plugin while the central setting was "auto", set the central setting to "always" instead — the central policy is now the source of truth.

+7 tests (172 green).

## [1.24.3] - 2026-06-23

### Security — email-capture webhook now uses `wp_safe_remote_post` (SSRF hardening)
- The Content Box / ContentStream email-capture form delivers each submission to an **admin-configured** webhook via `wp_remote_post`, which does not apply WordPress's loopback/private-range guard. A visitor tampering with the form's hidden webhook field could in principle aim that server-side POST at an internal address (`127.0.0.1`, the cloud-metadata endpoint `169.254.169.254`) — a *blind* SSRF (POST-only, fixed body, no response returned to the caller). Switched to `wp_safe_remote_post()` so the request is blocked from private/loopback targets. Defence-in-depth: the URL stays admin-set and the form remains nonce-protected. Surfaced by an external code audit, verified against the code before fixing.

## [1.24.2] - 2026-06-18

### Fixed — the email-capture box (Content Box `type="email"`, also the ContentStream "email" slot) was silently broken by an incomplete rename
- A half-finished `lkst_* → zehoro_*` rename left the email box's render, its inline JS, and its AJAX handler disagreeing — so the widget didn't work:
  - **The form never submitted.** The inline submit handler gated on `classList.contains('zehoro-box-email-form')`, but the form's class was `lkst-box-email-form` — so the guard always failed, `preventDefault()` never ran, and the form did a broken full-page reload instead of the AJAX submit. (Fixed the JS to match the form, keeping the widget's identifiers internally consistent with its `lkst-`-named nonce/action/message.)
  - **The download link was dropped.** `handle_submission()` read `$_POST['lkst_box_file_url']`, but the form field is `zehoro_box_file_url` — so the file URL passed to the delivery webhook was always empty.
  - **The per-shortcode webhook override was ignored.** Same mismatch on `zehoro_box_webhook` vs `lkst_box_webhook` — the per-article webhook was never read, so it always fell back to the global setting.
- The handler now reads the field names the form actually sends (with `lkst_box_*` fallbacks so any page cached before the rename keeps working). **+4 regression tests** (165 green) that pin the render↔JS↔handler contract: the form's class must match its JS submit hook, and its fields/nonce/action must match what the handler reads — so this class of rename-drift fails loudly next time.

## [1.24.1] - 2026-06-15

### Fixed — suite-card members now expose their settings link
- When a module is collapsed into a **suite card** (the Blocks / Schema / Reading & Trust grouping from 1.23.0), its row showed only a toggle — so the settings page for FAQ, Article Schema, Last Updated, Content Box, etc. was reachable **only by typing the URL**. Each suite-member row now shows a ⚙ settings link (when the module has a settings page), so nothing is buried. `collapse_suites()` carries `settings_link` into each member.

## [1.24.0] - 2026-06-15

### Added — Danger Zone: your data is safe on uninstall
- **Deleting (or temporarily uninstalling) the plugin no longer wipes your settings.** WordPress can't tell a permanent removal from a delete-then-reinstall, so Zehoro now **preserves all data by default** — a reinstall picks up exactly where you left off.
- New **Danger Zone** at the bottom of the dashboard with two controls: an opt-in checkbox — *"Delete all Zehoro data when I delete this plugin"* (off by default) — and an **"Erase all data now"** button (with a confirm) for an immediate clean wipe.
- Both run a shared `Maintenance\DataEraser` that clears every canonical `zehoro_*` and legacy `lkst_*` option, post meta, user meta, and transient, then flushes the object cache (so it's correct on Redis/Memcached sites too). `uninstall.php` now calls the same eraser — but only when the opt-in is set.

## [1.23.1] - 2026-06-15

### Fixed — uninstall completeness + a hardening (5-agent audit)
- **Uninstall now cleans canonical `zehoro_*` data, not just legacy `lkst_*`.** After the v1.7.0 rename the plugin writes `zehoro_*` keys, but `uninstall.php` still only removed the old `lkst_*` ones — so deleting the plugin orphaned ~30 options (plus the migration flag, which made a reinstall *skip* migration and resurrect stale state). `uninstall.php` is now driven off the rename migrator's authoritative key map and clears both prefixes' options, post meta, user meta, and transients.
- **Module-save (noscript path) hardened:** the form handler now `sanitize_key()`s the posted module slugs and intersects them against the real registry before saving (parity with the REST route) — nonce + capability were already enforced; this stops junk keys accumulating in `zehoro_active_modules`.
- De-branded a leftover demo placeholder ("Acme Corp" → "Acme Inc.") in the Testimonial block for naming consistency.

## [1.23.0] - 2026-06-15

### Added — module "suites" (the Kadence-Blocks model, for onboarding)
- The Modules grid now collapses the commodity groups into **single suite cards with sub-toggles inside**, so a new user meets ~15 cards instead of ~50. Three suites: **Blocks** (the 16 editorial blocks), **Schema**, and **Reading & Trust**. Each suite card has a **master toggle** (turn the whole set on/off at once, via the existing bulk route) and an **expandable list** to disable individual features — exactly how Kadence Blocks works. The master shows an *indeterminate* state when only some members are on.
- The spine modules (CTR Rescue, the entity layer, GSC diagnostics, etc.) stay as distinct cards — they are not co-activated and they are the differentiated features.
- `Dashboard::collapse_suites()` is the pure, unit-tested partition the grid renders from; suites are filterable via `zehoro/module_suites`.
- **Browser-verified** (Playwright): the master cascade, per-member toggle (master → indeterminate), regular-card toggles, search-by-block-name, and the left-nav counts all work and persist; zero JS errors.

## [1.22.2] - 2026-06-15

### Fixed — module grouping (onboarding)
- **Ten loop/intelligence modules were falling into the "Other" group** on the Modules grid because the slug→group map hadn't kept pace as the spine shipped. Grouped them: Cannibalisation, Refresh Trigger, Orphan Check, Topical Gap, Entity Index, DataForSEO and GA4 → **SEO**; AI Visibility + Rewrite-with-Context → **AI Assistance**; Edit Log → **Admin & Plumbing**. "Other" should now be empty (or close to it).

## [1.22.1] - 2026-06-14

### Added — licensing + wordpress.org distribution readiness
- **GPLv2-or-later license** declared in the plugin header (`License` + `License URI`) and the full canonical GPLv2 text shipped as `LICENSE` (the previous `LICENSE` was a 6-line stub — only the license *header*, missing the actual terms).
- **GitHub auto-updater now guarded with `file_exists()`** so it cleanly no-ops when the package ships without `vendor/`. A wordpress.org build (which must exclude the self-hosted updater per repo rules) drops in with **no source fork** — GitHub / self-hosted installs keep auto-updates exactly as before (`vendor/` present), while a wp.org build lets wordpress.org serve updates.
- **`.distignore`** — excludes dev tooling, tests, and `vendor/` (the updater) from a wordpress.org build, while keeping every runtime dir (`src/`, `build/`, `assets/`, `languages/`).
- **`readme.txt`** (wordpress.org format) — leads with SEO-plugin coexistence + plug-and-play (no API keys) positioning.

No functional change to any plugin feature.

## [1.22.0] - 2026-06-14

### Added — SEO-plugin coexistence
- **One canonical SEO-plugin detector** (`Zehoro\Compat\SeoPlugin`), replacing the per-module, inconsistent checks `ArticleSchema` and `FAQ` each carried (different plugin lists; one checked `SureRank\SureRank`, the other `SureRank\Core\SureRank`). Now recognises **Yoast, SEOPress, Rank Math, AIOSEO, SureRank, Slim SEO, The SEO Framework, Squirrly, Schema Pro** — extensible via the `zehoro/seo_plugins` filter.
- **Coexist by default:** when a dedicated SEO plugin is active, Zehoro pauses its own structured-data (schema) output to avoid duplicate markup — the principle that *Zehoro is a content-business toolkit, not an SEO plugin*: it owns the loop / entity / conversion features, never the SEO-output plumbing, and its one overlap (JSON-LD) yields to the specialist.
- **Visible + overridable:** a new admin notice (on Zehoro screens) names the detected plugin and offers a one-click **"Use Zehoro's schema instead"** (option `zehoro_schema_output` = `auto`/`always`/`never`) + a persistent dismiss. The editor schema meta box now names the plugin too. Dev override: the `zehoro/emit_schema` filter (and the legacy `zehoro_article_schema_force`, still honoured).

### Changed
- Regenerated `languages/zehoro-toolkit.pot` (169 strings) for the new copy.

## [1.21.2] - 2026-06-14

### Internationalization (translation-ready)
- **Fixed the one un-extractable string.** The module-grid category labels were translated through a variable (`__( $label, … )`), which i18n tooling can't extract — so they'd never be translatable. They now come from `Plugin::group_labels()`, which maps each group key to a **literal** `__()` call (falling back to the English label for any future key). Every user-facing string is now wrapped with the correct, literal `zehoro-toolkit` text domain (220 call sites verified).
- **Added the `Domain Path: /languages` header**, a bundled **`languages/zehoro-toolkit.pot`** (161 strings) for translators, and moved `load_plugin_textdomain()` to the `init` hook (avoids the WP 6.7+ "just-in-time textdomain" notice). On wordpress.org, translations are also served automatically from translate.wordpress.org.

## [1.21.1] - 2026-06-14

### Fixed
- **The GitHub-token lookup for auto-updates is now consistent with Pro.** The Free updater read the legacy `lkst_pro_github_token` option, while Pro reads/writes the canonical `zehoro_pro_github_token` — so a token set the canonical way left Free's updater unauthenticated (GitHub API rate-limit / private-repo update failures). Free now reads `zehoro_pro_github_token` first, falling back to the legacy `lkst_pro_github_token`. *(External review.)*

## [1.21.0] - 2026-06-14

### Added
- **`zehoro_landing` extension point** — the top-level Zehoro menu page now delegates to whoever hooks `zehoro_landing` (Pro's "Start Here" home claims it), falling back to the **Modules grid** when nothing does. So a Free-only install is unchanged; with Pro active, the landing becomes the Start-Here dashboard and the Modules grid moves to its own **Modules** submenu (its filter/REST assets follow it). `Dashboard::render_landing()` + a conditional menu in `register_menus()`.

## [1.20.2] - 2026-06-12

### Changed
- **Content Box folds into Content Stream for Pro users (IA pass).** When Content Stream is active, the Content Box card is hidden from the Modules grid — Stream owns box composition and keeps Content Box active as its renderer (so the injected forms can't break). Content Box's settings stay reachable from the Content Stream page.

## [1.20.1] - 2026-06-12

### Changed
- **Sidebar curation (IA pass).** The Zehoro admin sidebar now surfaces only daily-driver surfaces; everything else stays in the Modules grid (reachable via each card's *Configure* link). **Content Box** and **Disclaimer** settings moved out of the sidebar (Content Box is being folded into Content Stream for Pro users). No settings were removed — only the menu placement changed.

## [1.20.0] - 2026-06-12

### Added
- **Module cards now carry type + capability pills.** Each card shows a `Block` or `Tool` pill (plain behaviour modules stay unbadged to avoid badge-soup), an `AI` pill on modules that use a BYOK LLM (Rewrite with Context, AI Visibility, EntityMap), and a `GSC` pill on the Search-Console-fed modules (CTR Rescue, Cannibalisation, Refresh Trigger, Orphan Check). **Topical Gap deliberately carries no AI pill** — it's deterministic crawl + token-diff. The registry auto-derives `type`/`needs` from the slug (each overridable per module via `register_module()`), and the pills are part of the card search index. The existing `PRO` tier badge already covered tier, so the redundant "(Pro)" suffix was dropped from Pro module titles (Pro 1.47.1).

### Fixed
- **The Table of Contents rendered but wouldn't open** — clicking the bar did nothing. `toc.js` (the click-to-expand handler) was only enqueued when the `[zehoro_toc]` *shortcode* was present, so every auto-injected TOC loaded styled but inert (same bug class as the v1.19.0 stylesheet gate, one layer down). The script now loads whenever a TOC will actually render, via a shared `toc_will_render()` check used by both the style gate and the script enqueue so they can't disagree. (Enqueue moved out of `Plugin::enqueue_assets` into `TableOfContents`.)

## [1.19.1] - 2026-06-12

### Fixed
- **Mid-post CTA card rendered with an invisible heading** (cream text on white). The CTA wrapper had already been renamed to `zehoro-midpost-cta` but its child elements and *all* the CSS still used `lkst-midpost-cta*`, so the dark card background (which the cream heading depends on) never applied. Completed the rename — `lkst-midpost-cta*`, `lkst-cta-image*`, and the `lkst-sidebar-cta` modifier → `zehoro-*` — across `ContentBox.php` render and `style.css` in lockstep. (Surfaced by the v1.19.0 stylesheet-gate fix, which finally loaded the CSS that exposed the half-rename.)

## [1.19.0] - 2026-06-12

### Fixed
- **Table of Contents rendered unstyled** on posts where it auto-injects. The global stylesheet gate scans *stored* post content, so it never saw the runtime-injected TOC and skipped loading `style.css` + the CSS variables. The TOC now hooks the `zehoro/load_global_styles` filter and forces the stylesheet when a ≥2-entry TOC is actually coming.

### Changed
- **TOC markup renamed `lkst-toc-*` → `zehoro-toc-*`** (wrapper, header, title, list, items, depth classes, `data-zehoro-toc` attribute) across the rendered HTML, `style.css`, and `toc.js` in lockstep. The `[lkst_toc]` shortcode alias and `lkst_toc_settings` option fallback are unchanged, so existing posts keep working.

## [1.18.0] - 2026-06-10
- Live module counts, recommended setups, shortcode copy fixes

## [1.17.0] - 2026-06-10
- Bulk module toggles

## [1.16.0] - 2026-06-10
- Sidebar rename Site Toolkit → Zehoro + back-to-Modules links

## [1.15.1] - 2026-06-09
- Flat alphabetical grid (fix v1.14.0 redundant category sections)

## [1.15.0] - 2026-06-09
- Conditional enqueue gating (49 KB saved per page)

## [1.14.0] - 2026-06-09
- Modules page sidebar group nav + URL state

## [1.13.0] - 2026-06-09
- Per-module submenus hidden from Site Toolkit sidebar

## [1.12.0] - 2026-06-09
- REST toggle endpoint (no Save button)

## [1.11.0] - 2026-06-09
- Modules JS + CSS extracted to assets/

## [1.10.0] - 2026-06-09
- Module metadata: tier, group, order, keywords, badges

## [1.9.0] - 2026-06-09
- Modules page: category grouping

## [1.8.0] - 2026-06-09
- Search + status filter on Modules page

## [1.7.0] - 2026-06-08
- Full lkst → zehoro rename

## [1.6.1] - 2026-06-06

### Changed — renamed Leokoo Site Toolkit → Zehoro Toolkit
- Plugin renamed to **Zehoro Toolkit** (the free base for Zehoro Toolkit Pro). Namespace `LK\SiteToolkit\` → `Zehoro\`, constants `LKST_*` → `ZEHORO_*`, slug/folder/text-domain/block-category/handles/PUC → `zehoro-toolkit`, GitHub repo → `leokoo/zehoro-toolkit`.
- Filter `lkst_article_schema` → `zehoro_article_schema` (the old name still fires via `apply_filters_deprecated`).
- Removed 4 modules native WordPress core already provides / that add no value: **PostNav** (`core/post-navigation-link`), **ReadingTime** (`core/post-time-to-read`), **ReadingProgress**, **NewsTicker**.
- Stored `lkst_` data (options, post/user meta, shortcodes, CSS classes, `lkst/` theme filters) intentionally unchanged — no migration.

### Internal — test coverage backfill

- **AuthorBox: 3 → 21 tests** (`tests/integration/AuthorBoxTest.php`). Original 3 tests covered the v1.5.2 empty-default-URL regression on the primary CTA — `render_box`'s secondary-CTA path and the entire `render_socials` standalone shortcode (166 untested lines) were not covered. Expanded to add: secondary CTA empty-URL hide / configured render / filter override / independence from primary; identity rendering (tagline, bio with nl2br, chips 1–3 in source order, partial chips); in-box socials section markup; `[lkst_author_socials]` shortcode end-to-end (no URLs → empty, single platform, all four platforms with correct dashicon mapping, `esc_url` drops `javascript:` protocol, unconfigured platforms omitted).
- **TableOfContents: 0 → 21 tests** (`tests/integration/TableOfContentsTest.php`). Same regression class as ContentStream (Pro): `preg_replace_callback` over `the_content`, builder-preview short-circuits, structural injection point. Covers `sanitize_settings` (non-array fallback, unknown post types dropped, invalid `insertion` falls back to `auto`); auto-insertion (TOC prepended only when ≥ 2 headings, anchor IDs injected for headings without one, existing IDs preserved, h2 vs h3 depth class); shortcode-mode (`[lkst_toc]` placeholder replaced when valid, stripped when under 2 headings per the module's Bug 4 comment, no auto-inject); short-circuits (post-type filter, Bricks/Etchwp/Elementor previews, global processing-flag re-entry guard); `preparse_toc_headings` populates global `$lkst_toc_items`; `render_shortcode` returns empty under 2 items and renders when ≥ 2.
- No shipped behavior changed. New tests run against the current v1.6.0 surface via `composer test`.

## [1.6.0] - 2026-05-27

### Added
- **Home Filter Pills module** (`HomeFilterPills.php`, slug `home_filter_pills`) — new shortcode `[lkst_home_filter_pills]` that renders a cross-CPT navigation pill bar. Each pill is a real anchor that navigates to a category or CPT archive (clean SEO URL, shareable, no JS, no AJAX, no custom taxonomy required). Site-specific destinations are configured via the `lkst/home_filter_pills/items` filter so the module stays generic across sites.
  - Pill item shape: `[ 'label' => ..., 'url' => ..., 'count' => int|array|null ]`
  - Count spec supports three resolver types: `category` (by slug), `tax` (any taxonomy by slug), `cpt` (post type publish count)
  - Active state derived from current URL path (with `aria-current="page"` for screen readers)
  - Two visual schemes via shortcode attr: `scheme="dark"` (default, white-on-dark) or `scheme="light"` (dark-text-on-cream for use on light sections)
  - Reuses `.lkst-cat-pill` shape from `CategoryPills.php`, adds `.lkst-cat-pills--light` and `.is-active` rules to `assets/style.css`
- **`.is-active` state styling** (`assets/style.css`) for `.lkst-cat-pill` — navy bg + cream text. Used by both modules.
- **`.lkst-cat-pills--light` variant** (`assets/style.css`) — cream-bg pill style for sections with light backgrounds. Uses CSS `color-mix()` so the pill auto-tracks the site's `--lkst-primary-contrast` and `--lkst-bg-light` settings.

### Rationale
Replaces an ill-fitting Bricks `filter-radio` + AJAX pattern that had been deployed on leokoo.com home page (v1.9 of that site). The Bricks filter required a custom taxonomy + backfill across all existing posts + a `save_post` auto-tagger + `?brx_lkflr=` URL pollution + DOM-order surgery (filter must render after loop, see Bricks build-system gotcha G32) + ~80 lines of CSS to hide native radio inputs. Replaced with this anchor-based pattern (matches FGB Malaysia's `fgb_category_pills` shape and the standard WordPress archive-navigation idiom).

## [1.5.2] - 2026-05-23

### Fixed (footgun removal)
- **Author Box CTA defaults** — previous default URLs (`/blog/` for "Read the articles" and `#newsletter` for "Get the newsletter") silently rendered broken links on any site that didn't set the options. Defaults are now **empty** — both buttons hide unless the site owner explicitly configures them via:
  - **wp_options**: `lkst_cta_primary_url`, `lkst_cta_secondary_url` (set via `update_option()` or wp-cli)
  - **filters**: `lkst/author_box/cta_primary`, `lkst/author_box/cta_secondary`
- Default primary CTA label updated from `Read the articles` → `Read more articles` (more conventional phrasing).

### Migration notes
- Sites that **were relying on the default `/blog/` button**: that URL was always broken on sites whose post archive isn't literally `/blog/`. The button now hides instead of misleading. To restore: set `lkst_cta_primary_url` to your actual archive URL (often `/news/`, `/articles/`, or whatever the site uses).
- Sites that **set the option themselves**: no impact — your saved value is honoured.

## [1.5.1] - 2026-05-23

### Changed (visual — heads up if you rely on the legacy badge look)
- **Last Updated badge — unstyled by default.** The `[lkst_last_updated]` shortcode previously rendered with hard-coded inline styles (small uppercase pill, cream background, dark text). That meant the badge looked the same no matter where it was placed and was extremely difficult to override from a theme or builder context. The default output is now an unstyled `<span class="lkst-last-updated">Updated: <time>...</time></span>` that inherits surrounding typography.
- **Pill look is now opt-in.** Pass `variant="pill"` to restore the legacy editorial-pill styling: `[lkst_last_updated variant="pill"]`. Or add the `.lkst-last-updated--pill` modifier class manually in custom markup. The stylesheet still ships the pill rules, just under the new modifier class.
- **`label` attribute** added — `[lkst_last_updated label="Last edited:"]` to customise the prefix. Pass an empty string to omit the label entirely (just the date).
- **Markup is now semantic** — wrapper changed from `<div>` to `<span>` (inline), and the date is wrapped in a `<time datetime="ISO-8601">` element for screen readers and structured-data crawlers.
- **`lkst-editorial-block` class removed from the wrapper.** It was misleading: the badge isn't an editorial block, it's a freshness signal. Sites that styled against this class should switch to `.lkst-last-updated` or `.lkst-last-updated--pill`.

### Migration notes
- Sites with **auto-inject enabled** will see the badge change from a styled pill to plain inline text at the top of single posts. To restore the pill: disable auto-inject in *Site Toolkit → Last Updated*, then place `[lkst_last_updated variant="pill"]` manually.
- Sites with **theme/builder overrides targeting `.lkst-last-updated { ... }`**: those rules now apply only to the unstyled default. To target the pill, switch your selector to `.lkst-last-updated--pill`.
- **No data migration is needed** — the change is purely presentational.

## [1.5.0] - 2026-05-22

### Added
- **CTA Swap module:** progressive-disclosure pattern for swapping CTA-button groups inline with hidden forms. Data-attribute API — `data-lkst-swap-group`, `data-lkst-swap-target`, `data-lkst-swap-back`. No shortcode, no PHP rendering — author marks up the buttons and form however they want, the module ships only the ~50-line vanilla-JS swap behaviour + minimal hidden-state CSS. Common pattern used by Substack, Mailchimp, ConvertKit etc. for newsletter signups, donation flows, multi-step CTAs. Disabled by default — enable via **Site Toolkit → Modules**.
  - ESC key closes an open swap
  - Focus is moved to the first focusable element in the form when opened
  - Focus is restored to the originating trigger when closed
  - Vanilla JS, zero dependencies, footer-loaded, respects `prefers-reduced-motion`

## [1.4.1] - 2026-05-22
### Added
- **Reading Time Bricks dynamic tag:** `{lkst_read_time}` is now registered as a Bricks dynamic data tag, mirroring the existing `[lkst_read_time]` shortcode. Use either inside a Bricks element setting to render the estimated reading time for the current post.

### Fixed
- **ReadingTime docblock:** Replaced an escaped apostrophe (`\'`) in the file header comment with a straight apostrophe — purely cosmetic, no behavioural change.

## [1.4.0] - 2026-05-13
### Added
- **Steps / Process Block:** SSR Gutenberg block for numbered how-to steps. Emits HowTo JSON-LD schema automatically (defers to SEO plugins when active).
- **Testimonial Block:** Static testimonial card with avatar, quote, name, role, and company. Three layout variants: card, minimal, highlight.
- **Stat Callout Block:** Large-number callout block for B2B/SaaS content. Supports centred, left-aligned, and highlighted-box layouts with optional source citation.
- **Inline Product Mention Block:** Compact horizontal product card for mid-content affiliate references. Image, name, one-liner, and CTA button with configurable `rel`.

### Changed
- **Build pipeline:** Migrated all four new block editors from vanilla JS (`wp.element.createElement`) to JSX via `@wordpress/scripts`. Source in `src/blocks/`; compiled output in `build/`. Legacy blocks (callout, pros/cons, tldr) are unaffected.

### Fixed
- **ArticleSchema:** Detects WP Review Pro (`MTS_WP_REVIEW_DB_TABLE`) and suppresses duplicate JSON-LD output when that plugin is active. Filterable via `lkst_article_schema_suppress_wp_review`.

## [1.3.1] - 2026-05-13
### Fixed
- **MODULES.md:** Corrected module reference — marked all four Stage 1 blocks as built.

## [1.3.0] - 2026-05-13
### Added
- **Article Schema Module:** Automatically outputs valid JSON-LD Article/BlogPosting schema for single posts, including author sameAs social links and dateModified signals.
- **Reading Progress Bar:** Added a lightweight, high-performance scroll progress bar at the top of the viewport.
- **Disclaimer Presets:** Refactored the Affiliate Disclosure module into a global Disclaimer module with standard presets for Medical and Legal sites.

## [1.2.1] - 2026-05-13
### Added
- **Plugin Update Checker:** Integrated the PUC library to enable native WordPress dashboard updates directly from GitHub.
- **Custom Block Category:** Registered a dedicated "Zehoro Toolkit" category in the Gutenberg editor to group all native toolkit blocks together.

### Fixed
- **TOC Regex:** Fixed an inverted regex capture group that was causing the Table of Contents anchor IDs to include unwanted quotation marks.
- **Block Assets:** Corrected the path mapping in `block.json` for the Callout and Pros & Cons blocks so they properly load their compiled assets in the editor.

## [1.2.0] - 2026-05-11
### Changed
- **Pro Refactoring:** Extracted resource-intensive features (Intelligent Content CTAs, CTA Admin, Inline Posts, Freshness Log, Pros/Cons Schema) and moved them to the new Zehoro Toolkit Pro add-on to maintain a lightweight core.

## [1.10.0] - 2026-05-06

### Added
- **Wirecutter-Style TOC:** A highly optimized, builder-agnostic Table of Contents module that automatically parses content and generates a sticky header with a mobile bottom-sheet dropdown.
- **Seamless TOC Marquee:** Long TOC headings are now elegantly constrained to a single line. If the text overflows the screen, it automatically triggers a continuous, seamless horizontal scrolling marquee.
- **TOC Settings Page:** Added a dedicated dashboard for the TOC to select supported post types and toggle between 'Auto-inject' or 'Shortcode Only' insertion methods.
- **Plugin Meta:** Added the Plugin URI (`https://leokoo.com`) to the WordPress plugins list.

### Changed
- **Typography Refinements:** Tuned TOC typography to strictly follow Wirecutter's hierarchy (12px bold label, 15px normal heading).

### Fixed
- **Layout Spacing:** Removed aggressive, hardcoded margins (`3rem`) from the Author Box and Content CTAs, allowing Bricks Builder (or any active theme) to naturally dictate structural spacing.
- **CTA Grid & Gaps:** Fixed the 200px image-width stretching bug in the Content CTAs and injected specific overrides to remove stubborn `15px` gaps generated by Fluent Forms.
- **Mobile Stacking:** Forced Content CTAs into a single-column layout on screens under 900px to prevent input field crushing.
- **Injection Guards:** Loosened overly strict WordPress loop guards that were preventing CTAs from rendering properly within the Bricks Builder DOM structure.


## [1.0.0] - 2026-05-06

### Added
- **Initial Release:** Complete architectural rewrite and rebranding from the legacy "Bricks Site Toolkit".
- **OOP Architecture:** Refactored all features into isolated, strictly-typed module classes under the `LK\SiteToolkit` namespace.
- **Builder Agnosticism:** Replaced all Bricks-specific `{echo:}` functions with standard WordPress shortcodes (e.g., `[lkst_read_time]`, `[lkst_post_nav]`).
- **Self-Contained Output:** Updated the News Ticker to output its own DOM wrappers (`.lkst-ticker`, `.lkst-ticker__wrap`), making it compatible with any builder or text area.
- **Flexible CTAs:** Content CTAs no longer strictly require a form shortcode to inject. A heading-only CTA will now successfully render.

### Fixed
- **Taxonomy Lock-in:** The "deepest match" category override logic now dynamically queries all hierarchical taxonomies attached to a post type (fixing custom post type compatibility).
- **Injection Safety:** Added strict `in_the_loop()` and `is_main_query()` guards to the CTA injection engine to prevent leaks into sidebars, widgets, or nested shortcodes.
- **Bottom CTA Math:** Fixed an edge case where the Bottom Power CTA would fail to calculate its correct injection position if the Middle CTA was disabled.
- **Data Integrity:** Rewrote the CTA settings parser to strictly allowlist valid keys, preventing internal logic flags (like `bottom_enabled`) from corrupting renderer data.

### Security
- **Sanitization:** Added strict, core-native sanitization callbacks (`sanitize_text_field`, `esc_url_raw`, `sanitize_hex_color`) to all dashboard options upon registration.
- **Error Handling:** Added `is_wp_error()` guards to all term lookups to prevent fatal crashes if a custom taxonomy goes offline.
