# HANDOFF — Key Takeaways block (autonomous session 2026-07-12)

*Branch: `feat/key-takeaways-block`. Nothing was deployed to any live site; no release cut. This is a
branch-scoped note — delete it before merging to `main`.*

## TL;DR

Built launch block #1 — **Key Takeaways** (`zehoro/key-takeaways`) — to the "perfected" bar you asked for,
and did the `lkst/tldr → zehoro/key-takeaways` rename **bulletproof**. Free suite green (**212 tests, 625
assertions**), and verified end-to-end on a real local WordPress 6.9.4 install (front-end render, the legacy
safety net on a live page, the migrator moving real data, and the block working in the Gutenberg editor with
no console errors). Version bumped **1.28.0 → 1.29.0** with the CHANGELOG in the same commit.

## What's in this branch

**New**
- `src/blocks/key-takeaways/{block.json,index.js,style.scss,editor.scss,render.php}` — the block source.
- `build/key-takeaways/*` — compiled output (this plugin commits `build/`).
- `src/Modules/KeyTakeaways.php` — module: registers the block, holds the **single render seam**
  `KeyTakeaways::render_html()`, the legacy `render_block` safety net, and the DOM-based legacy-content
  extractor.
- `src/Cli/MigrateBlocksCommand.php` — `wp zehoro migrate-blocks` (dry-run by default; `--execute` to write).
- `tests/integration/KeyTakeawaysRenderTest.php` (16 tests) + `KeyTakeawaysCompatTest.php` (13 tests).
- `specs/blocks/key-takeaways.md` — the STEP-0 design doc.

**Changed / removed**
- Retired `src/Modules/TLDR.php` + all `build/tldr*` assets.
- Module slug `tldr` → `key_takeaways`; updated the 3 references (group map + block-type list in
  `src/Core/Plugin.php`, curation list in `src/Admin/Dashboard.php`).
- `zehoro-toolkit.php` version 1.28.0 → 1.29.0; `CHANGELOG.md` entry; `webpack.config.js` comment.

## The design (so you can sanity-check the calls I made)

- **Dynamic block, one render seam.** `save()` returns `null`; all front-end markup comes from
  `KeyTakeaways::render_html($attributes, $wrapper)`. render.php is a thin delegate. This is the Tier-2 clean
  seam so the future connected/smart version intercepts the source in one place, no block deprecation.
- **Two modes** via a block-toolbar toggle: bulleted list (default) or short TL;DR paragraph. Heading text +
  level (H2–H4) configurable. `html:false`, `anchor:true` (deep-linkable), nothing else — dumb by design.
- **Escaping = the seam is the only boundary.** `esc_html()` on the heading, `wp_kses()` (tight inline
  allowlist) on the body. XSS-tested: `<script>` tags, `javascript:` hrefs, and `onclick` all stripped.
- **Empty renders nothing** (no empty `<section>`), better for the document outline + a11y.
- **Theme-neutral CSS**: accent from `currentColor`, low-alpha surfaces, `prefers-color-scheme` dark override.
  No brand colour imposed.
- **No schema markup** — deliberate. There is no valid schema type for "key takeaways"; the value is the
  visible, quote-ready passage (matches the STEP-0 + the wedge doctrine).

## Backward-compat for `lkst/tldr` (the part that touches your content)

Two layers, so nothing can break:
1. **Safety net** — a `render_block` filter re-renders any un-migrated `lkst/tldr` through the new seam, so it
   shows styled + correct (never dead `lkst-*` markup), even if you never run the migrator.
2. **Migrator** — `wp zehoro migrate-blocks` rewrites `lkst/tldr` → `zehoro/key-takeaways` in `post_content`
   (heading + content carried across). **Dry-run by default**; `--execute` to apply.

You said only the **dev site** uses the old block. I did **not** touch any of your sites. When you're ready:
run `wp zehoro migrate-blocks` (preview) then `--execute` on the dev site with me awake. Live sites have no
`lkst/tldr` per your note, so there's nothing to migrate there — and the safety net covers any stray anyway.

## Verification done

- **Tests:** `composer test` → 212 passed / 625 assertions. My 29 new tests cover both render modes, the
  empty contract, heading clamping, XSS, wrapper passthrough, the legacy extractor, the safety net, the
  migrator, and a `do_blocks()` round-trip.
- **Real WordPress 6.9.4** (throwaway install at `/tmp/zehoro-dev`, served on `http://localhost:8899`):
  - Front-end page with two new blocks **and** a legacy `lkst/tldr` block → all render correctly; the legacy
    block came through the safety net as `zehoro-key-takeaways` with **zero `lkst-tldr` leaks**.
  - `wp zehoro migrate-blocks` dry-run wrote nothing; `--execute` converted the post to 3
    `zehoro/key-takeaways` blocks, **0 `lkst/tldr` remaining**.
  - Block CSS confirmed loading (WP 6.9 inlines it) and the file resolves HTTP 200.
  - Gutenberg editor: block registers, inserts, renders WYSIWYG, toolbar + Inspector controls all work,
    **no console errors**.
  - **axe-core (WCAG 2 A + AA): 0 violations** on the rendered blocks (after the fix below).

## 🐛 Bug caught + fixed during verification (the axe scan earned its keep)

The a11y scan flagged a `serious` `list` violation: a migrated `<ul>` held **literal `u003cli…` text**
instead of real `<li>` elements. Root cause: `wp_update_post()` internally `wp_unslash()`es its input, so the
migrator's `serialize_blocks()` output (which JSON-escapes HTML as `<`) lost its backslashes on write,
corrupting any HTML-bearing attribute (`items`, `text`, links). **Fixed** by `wp_slash()`ing the serialized
content before `wp_update_post()`, extracted into a testable `MigrateBlocksCommand::migrate_post()`, and locked
with a regression test that crosses the real write boundary (the earlier round-trip test used
`serialize_blocks()`/`do_blocks()` directly and couldn't catch it). Re-verified live: 0 axe violations, real
`<li>` elements. **Editor-created blocks were never affected** — only the CLI migrator's write path.

## 🔍 Formal Refuter audit (the process you asked about) — BLOCK → fixed

After you asked whether I used the dev-team, I ran the real thing: a 5-lens read-only Refuter panel + a
synthesis pass on the KT diff. Verdict **BLOCK, 6 confirmed** — and two were serious enough that my "212 green
+ verified" was self-consistency, not correctness (the tests encoded the same proxy the bugs did):

- **CRITICAL — module shipped dark on every upgrading site.** The slug rename wasn't migrated inside the
  stored `zehoro_active_modules` allowlist, so `Plugin::init`'s `in_array('key_takeaways',$active)` gate was
  false: block unregistered, safety net never hooked, CLI never registered. Fresh installs (my whole
  verification) were fine — which is why it stayed green. **Fixed** + proven live (seed `['tldr']` → next
  request → block **registers**).
- **HIGH — permanent data loss.** Legacy content was force-mapped to paragraph mode + an inline-only
  `wp_kses`, collapsing multi-paragraph/list TL;DRs to a run-on line, and `--execute` wrote it. **Fixed:**
  lossless `rich` render path (safety net) + the migrator now *skips + reports* block-structured content
  instead of flattening it. Proven live (multi-paragraph legacy → 2 separate `<p>`).
- **MEDIUM — self-confirming tests** (bypassed the activation gate; asserted a bare `'Hello'`). Replaced with
  real-path tests: a seeded-allowlist migration test, multi-paragraph/list fixtures, sibling byte-integrity.

Plus should-fix items (dropped `target=_blank` reverse-tabnabbing; moved these trade-offs into the CHANGELOG so
they aren't silent debt when this file is deleted). Suite now **222 green**. Full run:
`~/.claude/.../workflows/wf_5c42800d-ad0`.

## ⚠️ Known follow-ups (flagged, not blockers)

1. **`RichText multiline="li"` deprecation warning** (editor console). It still works fully in WP 6.9 (the
   "removed in 6.3" notice is stale — 8 minor versions later it's alive), which is why I kept it: it gives the
   best type-and-Enter list UX and keeps the render seam attribute-driven. The clean long-term path is
   InnerBlocks (`core/list`), but that breaks the single-seam model — worth a deliberate decision before
   wp.org, not a rushed change. **This is the one thing I'd want your call on.**
2. **`readme.txt` Stable tag is 1.25.3** — already 3 minors behind before I started; it isn't kept in sync
   pre-launch. Needs a full readme sync pass before wp.org submission (out of scope tonight).
3. ~~Module-slug rename impact~~ — **RESOLVED.** The formal audit (below) found this was a CRITICAL
   ship-dark regression, not the "near-zero" reset I'd written. Fixed by migrating the slug inside
   `zehoro_active_modules`; recorded durably in the CHANGELOG, not just here.
4. **Dark mode** uses `prefers-color-scheme` (the OS setting, not the theme's). A light-OS user on a dark
   theme gets the light box; the `currentColor` border keeps it visible regardless. Acceptable for a
   theme-neutral default; noting it.
5. Pre-existing `StepsSchemaXssTest` emits a `setAccessible` deprecation on PHP 8.5 only (not my code, not on
   CI's 8.1–8.3). Left as-is.

## Explicitly NOT done (your guardrails)

- **No deploys, no releases** — build/test/commit/push to the branch only.
- **No module reorg** (parking ~15 modules Free→Pro) — you chose blocks-only; it's a coordinated two-repo
  change for a session you're awake for.
- Only Key Takeaways was built (you chose "Key Takeaways only, perfected").

## Review it yourself

```bash
# Tests
cd ~/Code/Zorasi/zehoro-toolkit && composer test          # (local WP test env now provisioned at /tmp/wordpress)

# Recreate the demo site if /tmp was cleared (ephemeral):
#   see the commands in this session's transcript, or just `composer test` for the automated proof.
# Dev site (if still up): http://localhost:8899  (admin / admin) — post id 4 (front-end), 7 (editor demo)
```

## Suggested next steps

- Your call on follow-up #1 (multiline → InnerBlocks or keep).
- Then block #2 when you're ready — the Author box (the differentiator), reusing this block as the template:
  the render-seam pattern, the `lkst-*`→`zehoro-*` rename recipe, and the test shape all transfer directly.
