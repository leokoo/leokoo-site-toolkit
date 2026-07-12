# STEP-0 — Pros / Cons block (launch block #2)

*Dev Team RUNLOOP step 0. Design approved by Leo 2026-07-12 (consolidate to one block; no schema; built on
the hardened Key Takeaways template). Reuses every lesson from [[key-takeaways]]: single render seam, the
active-modules note (N/A here — module slug `pros_cons` is unchanged), the lossless legacy path, the editor
bridge, and the rename-registry migrator.*

## Goal

One `zehoro/pros-cons` block: a two-column Pros / Cons box for the top of a review or comparison, with a
**show** toggle (`both` | `pros` | `cons`) so it also replaces the standalone pros/cons blocks.

## Decisions (locked)

- **Consolidate the retired 3 blocks into ONE.** The old set was `lkst/pros-cons` (an InnerBlocks *container*
  with `allowedBlocks: [lkst/pros, lkst/cons]`) + standalone `lkst/pros` + `lkst/cons`. All three now map into
  the single `zehoro/pros-cons`.
- **No schema.** A standalone pros/cons list has no valid schema type (positiveNotes/negativeNotes only exist
  inside a `Review`); inventing one is penalty-adjacent. The value is the visible, scannable box.
- **Dynamic block, one render seam** `ProsCons::render_html()` — same Tier-2 clean seam as Key Takeaways.

## The block

- **Attributes:** `prosTitle` (default "Pros"), `consTitle` (default "Cons"), `pros` (inner `<li>` html),
  `cons` (inner `<li>` html), `show` (`both`|`pros`|`cons`), `headingLevel` (2–4, default 3).
- **Markup:** `<div class="zehoro-pros-cons zehoro-pros-cons--{show}"><div class="zehoro-pros-cons__col zehoro-pros-cons__pros"><h3 class="…__title">Pros</h3><ul class="…__list">…</ul></div><div class="…__cons">…</div></div>`
  — semantic lists, theme-neutral, `zehoro-*` classes only, empty renders nothing.
- **Editor:** two RichText lists (multiline `li`, same soft-deprecation DEBT note as KT) + a show toggle in
  the toolbar + heading-level select. WYSIWYG.

## Backward-compat (the rename-registry pattern — generalize the KT machinery)

- Generalise `MigrateBlocksCommand` from one hardcoded name to a **registry**: `[legacy => handler]`, where a
  handler returns the replacement block array or null (skip). Each module registers its renames via a filter.
- **Migrator mappings:**
  - `lkst/pros` → `zehoro/pros-cons` `{show:'pros', pros:<content>}`
  - `lkst/cons` → `zehoro/pros-cons` `{show:'cons', cons:<content>}`
  - `lkst/pros-cons` (container) → read its **inner** `lkst/pros` + `lkst/cons` blocks → `{show:'both', pros, cons}`
- **Safety net:** a `render_block` filter for the 3 legacy names → the new seam (lossless).
- **Editor bridges:** register all 3 legacy blocks client-side reproducing their exact saved markup (verified
  against git main; `lkst/pros-cons` reproduces the InnerBlocks container), each with a `transforms.to`
  `zehoro/pros-cons`; hidden from the inserter.

## Acceptance criteria (mirror KT + the audit lessons)

- [ ] Renders server-side; semantic lists; theme-neutral light+dark; empty renders nothing; zero HTTP; no schema; no `lkst-*` classes emitted.
- [ ] All 3 legacy blocks: front-end renders via the safety net (no `lkst-*` leak); editor loads them `isValid` (no invalid-content warning); one-click transform works — **verified on real posts**.
- [ ] Migrator converts inline/list content losslessly, unpacks the container's inner blocks, and leaves genuinely block-structured content as-is (reported, never flattened); `wp_slash` on write.
- [ ] Module slug `pros_cons` unchanged → no active-modules migration needed (unlike KT); confirm the 3 block-name references are the only rename surface.
- [ ] Tests: render (both/pros/cons/empty/XSS), migrator (3 mappings incl. container-unpack + skip + sibling integrity), safety-net wiring (do_blocks), editor-validity note; full Free suite green.
- [ ] Refuter audit → PASS (re-run until clean or accepted-LOW).

## Invariants

- Block-name stability (the new name is now sacred); single render seam; zero phone-home; non-duplication with the SEO plugin (N/A — no schema here).
