# STEP-0 — Key Takeaways / TL;DR block (launch block #1)

*Dev Team RUNLOOP step 0. Approve the shape before code. First launch block: simplest, flagship, fastest
path to the "<5-min verifiable win". Spec source of truth: `../../../roadmaps/specs/product-architecture.md`
+ `free-plugin-spec.md`.*

## Goal

A per-post **Key Takeaways** block: 3–5 scannable bullets (or a short TL;DR paragraph) placed answer-first
near the top of a post, rendering a **quote-ready passage** readers and AI answer engines lift verbatim.

**Why it's #1:** only 1/19 free competitors ships it (census); it sits in the first-30%-of-page zone where
~44% of AI citations originate; the 40–75-word quote-ready shape is the documented AI-extraction pattern.
It is the flagship of the publishers-first wedge and the fastest block to a working demo.

## Non-goals (v1 — protect the wedge + the restaurant rule)

- **No JSON-LD schema.** There is no valid schema type for "key takeaways"; inventing one violates the
  penalty-safe wedge, and there's no evidence markup drives LLM citation. The value is the **visible
  rendered passage**, not schema.
- **No AI/auto-summarize** — that is the SaaS-smart upgrade (uniform law), not the free block.
- **No settings page, no config wizard, no layout/columns/design options** (content-surface scope rule).

## The block

- **Name migration (first-class concern):** the existing block is registered **`lkst/tldr`** and is in use
  on Leo's live posts. Ship as **`zehoro/key-takeaways`** (brand-correct + reframed), with a **backward-
  compat path** so existing `lkst/tldr` content does NOT break: register `lkst/tldr` as a deprecated
  alias that transforms to `zehoro/key-takeaways` (or a `render_block`/block-rename migration on load).
  **Block-name stability is sacred** — verify on a real Simlecco/leokoo post that uses the old block before
  merging. (This is the first instance of the wider `lkst-*→zehoro-*` cleanup — do it right here, reuse the
  pattern for the other blocks.)
- **Two modes:** (a) *Bulleted key takeaways* (default, 3–5 items) · (b) *TL;DR paragraph* (short).
- **Render through ONE server-side render function** (`render_callback`), even though attributes are local
  — this is the Tier-2 clean seam so the future connected/smart version can intercept the source without a
  block-deprecation. (Static-save would also be AI-crawler-visible, but the seam is worth the small cost.)
- **Markup:** `<section class="zehoro-key-takeaways"><h2 class="…__title">Key takeaways</h2><ul><li>…</ul></section>`
  — semantic list, real heading in the document outline, theme-neutral, `zehoro-*` classes (never `lkst-*`).
  Heading text + level configurable (default "Key takeaways" / h2).

## Editor UX (the <5-min win)

Insert → a heading field + a list you type or paste into → done. A variant toggle for bullets vs TL;DR
paragraph. Sensible default placement hint ("best right after your intro"). No required fields, no account,
no wizard. Target: a rendered, front-end-visible block in under 60 seconds.

## Acceptance criteria

- [ ] Renders server-side; semantic `<ul>/<li>`; heading in the outline; theme-neutral in light + dark.
- [ ] a11y: keyboard-navigable in the editor; valid list semantics; passes axe on the rendered output.
- [ ] **Existing `lkst/tldr` content upgrades cleanly** (deprecation/alias) — verified on a real post that
      uses the old block; zero "unexpected/invalid content" editor errors.
- [ ] Zero outbound HTTP, no account, no schema markup, no `lkst-*` classes emitted.
- [ ] i18n: every string translatable.
- [ ] Tests green: a render-function unit test + a block-fixture (serialize→parse→render) test; existing
      Free suite stays green.
- [ ] The "verifiable win": install → add block → see it on the front end, no config, <5 min.

## Invariants (the laws this block must never violate)

- Content stability: a `zehoro/key-takeaways` block already saved must keep rendering across versions
  (use the block deprecation API for any markup change).
- Single render seam: all rendering flows through one function (the connected/smart hook point).
- Zero phone-home; nothing about this block needs the network.

## Uniform law — the dumb-free / smart-SaaS split

- **Free (dumb):** the author writes the takeaways.
- **SaaS-smart (later, same block, on connect):** the loop drafts takeaways from the post's entities + GSC
  queries, flags which ones actually get cited, and A/Bs the phrasing. Same block, gains a brain — the
  render seam above is what makes that a config swap, not a rewrite.

## Build-from

Pattern-reference the 4 clean JS blocks (`src/blocks/{stat-callout,steps,inline-product,testimonial}`) for
the `block.json` + `@wordpress/scripts` setup, and the existing `lkst/tldr` build output for the current
markup/behavior to preserve. Do NOT copy `lkst-*` classes forward.
