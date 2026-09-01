# Zehoro Toolkit — Module Reference

**Plugin:** Zehoro Toolkit (Free)
**Namespace:** `Zehoro\Modules`
**Source:** `src/Modules/`

Free is deliberately lean — the wordpress.org acquisition funnel: the five launch
blocks plus Table of Contents, Article schema, and Visual Styles. Everything else
lives in **Zehoro Toolkit Pro** (see "Moved to Pro" below).

---

## Launch blocks (native Gutenberg)

All five are dynamic blocks rendered through a single server-side seam
(`save()` returns null; `render.php` delegates to the module's render method),
so markup improvements never invalidate stored content.

| Module file | Slug | Block(s) | Description |
|---|---|---|---|
| `KeyTakeaways.php` | `key_takeaways` | `zehoro/key-takeaways` | Scannable summary box for the top of a post. Supersedes the legacy `lkst/tldr` block (render safety-net + `wp zehoro migrate-blocks` migrator). |
| `ProsCons.php` | `pros_cons` | `zehoro/pros-cons` | Two-column (or single-list) Pros & Cons box with a `show` toggle. Consolidates the retired `lkst/pros-cons` + `lkst/pros` + `lkst/cons` set. No schema. |
| `FAQ.php` | `faq` | `zehoro/faq` + `zehoro/faq-item` | Accessible question-and-answer accordion on native `details`/`summary`, rich answers via inner blocks. No FAQPage JSON-LD (Google retired FAQ rich results 2026-05). Legacy `[zehoro_faq]` shortcode kept. |
| `AuthorBox.php` | `author_box` | `zehoro/author-box` | E-E-A-T trust card auto-filled from the post author (avatar, bio, credential chips, socials) with an Organization mode. Emits no schema of its own — ArticleSchema owns the author Person. Legacy `[zehoro_author_box]` shortcode kept. |
| `Evaluation.php` | `evaluation` | `zehoro/evaluation` | "We Tested" scorecard: methodology note, per-criteria scores (auto-averaged), pros/cons, verdict. Emits Review JSON-LD **only for third-party subjects** — a hard self-serving guardrail suppresses reviews of your own site/brand. |

---

## Content & schema

| Module file | Slug | Description |
|---|---|---|
| `TableOfContents.php` | `table_of_contents` | Wirecutter-style collapsible TOC. Parses H2/H3 from `post_content`, injects anchor IDs, auto-inject or `[zehoro_toc]`. Settings page: `zehoro-toc-settings`. |
| `ArticleSchema.php` | `article_schema` | Post-type-aware JSON-LD (`BlogPosting`, `Recipe`, `Review`, `Service`, `Product`, `WebPage`, `Article`). Author Person enriched with `worksFor` (publisher Org by `@id`) + social `sameAs`. Stands down automatically when a dedicated SEO plugin is active. |
| `VisualStyles.php` | `styles` | Brand colour customisation via CSS custom properties (`--lkst-*`), inlined with the global stylesheet. |

---

## Moved to Pro (lean-Free reorg, 2026-07)

Thirteen modules relocated to **Zehoro Toolkit Pro** across reorg stages A–C —
block names, shortcodes, option keys, and stored data unchanged, so a Free+Pro
site is unaffected:

`callout`, `stat_callout`, `steps`, `testimonial`, `inline_product`,
`content_box`, `cta_swap`, `disclosure` (Disclaimer), `last_updated`,
`category_pills`, `home_filter_pills`, `rss_support`, `archive_cleanup`.

See `zehoro-toolkit-pro/MODULES.md` for their reference entries.

---

## Cut modules (no longer anywhere)

Four modules from earlier "Leokoo Site Toolkit" builds were **removed on
2026-06-06** — each is covered by native Gutenberg core: **Reading Time**
(`core/post-time-to-read`), **Post Navigation** (`core/post-navigation-link`),
**Reading Progress**, and **News Ticker**. Their old `[lkst_*]` shortcodes now
render nothing; re-create the effect with the core blocks.
