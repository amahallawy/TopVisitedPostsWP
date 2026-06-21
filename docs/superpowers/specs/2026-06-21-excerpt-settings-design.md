# Design: Configurable excerpt word count & preserve-line-breaks settings

- **Date:** 2026-06-21
- **Issue:** amahallawy/TopVisitedPostsWP#7
- **Status:** Approved (brainstorming)

## Problem

The Top Visited Posts widget renders an optional post excerpt. Today the
excerpt is trimmed to a **hardcoded 12 words** via
`wp_trim_words( get_the_excerpt(), 12, '…' )` in
`public/class-tvp-public.php`, and line breaks are visually collapsed
(`esc_html` output with a 2-line CSS clamp). Site owners cannot control
excerpt length or keep paragraph breaks.

## Goal

Add two new display settings for the excerpt element:

1. **Excerpt word count** — configurable number of words (replaces the
   hardcoded 12). Default **20**.
2. **Preserve white spaces / line breaks** — when enabled, the excerpt
   keeps newlines (rendered with `nl2br` + `white-space: pre-line`).
   Default **off**.

Both fields must be **disabled in the admin UI when "Excerpt" is not a
selected element**.

## Non-goals

- No character-based trimming (kept word-based per decision).
- No DB schema change / migration — settings live in the existing
  `tvp_settings` option array.
- No new test harness (the plugin has none yet); tracked separately.

## Settings

Stored in the existing `tvp_settings` option array.

| Key | Type | Default | Sanitization |
|---|---|---|---|
| `excerpt_words` | int | `20` | `absint`, clamped to 1–100 |
| `excerpt_preserve_breaks` | bool | `0` | normalized to `0`/`1` |

Existing installs that lack these keys read them with the runtime
defaults above — no migration required.

## Components & changes

### 1. Activation defaults — `top-visited-posts.php`
Add `excerpt_words => 20` and `excerpt_preserve_breaks => 0` to the
`$defaults` array used on activation.

### 2. Admin settings — `admin/class-tvp-admin.php`
- Register two new fields in the **Display Settings** section:
  - `excerpt_words`: number input, label "Excerpt length (words)".
  - `excerpt_preserve_breaks`: checkbox, label "Preserve line breaks".
- Add render callbacks for both fields.
- Extend `sanitize_settings()`:
  - `excerpt_words`: `absint`, clamp 1–100, fall back to 20 if invalid.
  - `excerpt_preserve_breaks`: boolean to `0`/`1`.
  - **Absent-key rule:** when either key is missing from the submitted
    payload, fall back to the **previously saved value** (via
    `get_option`), not the hardcoded default. This protects config when
    the fields are disabled in the UI (disabled inputs are not POSTed).

### 3. Admin JS — `admin/js/admin.js`
- On change of the excerpt checkbox in the elements sortable, toggle the
  `disabled` attribute on both new fields.
- Set the correct initial disabled state on page load (based on whether
  excerpt is currently enabled).

### 4. Frontend render — `public/class-tvp-public.php`
In the `case 'excerpt':` block, read both settings:
- **Preserve off:**
  `esc_html( wp_trim_words( get_the_excerpt(), $words, '…' ) )`
  (current behavior, configurable count).
- **Preserve on:** a small helper trims by word count **while keeping
  newlines** (`wp_trim_words` collapses them), then output is
  `nl2br( esc_html( $trimmed ) )` and the element gets the extra class
  `tvp-post-excerpt--preserve-breaks`.

Word count and preserve flag are read with runtime defaults and
re-validated on render (consistent with how `order_by` / `elements` are
already double-validated).

#### Newline-preserving trim helper
A pure static helper (e.g. `TVP_Public::trim_words_keep_breaks( $text,
$num_words, $more )`):
- Strip tags (`wp_strip_all_tags`).
- Split into words on spaces/tabs **but preserve `\n`**.
- Reassemble up to `$num_words` words, append `$more` (`…`) if truncated.
- Isolated and side-effect free so it is unit-testable later.

### 5. CSS — `public/css/public.css`
Add:
```css
.tvp-post-excerpt--preserve-breaks {
    white-space: pre-line;
    -webkit-line-clamp: none; /* show all preserved lines */
}
```

## Data flow

`tvp_settings` option → `TVP_Public::build_section()` reads
`excerpt_words` + `excerpt_preserve_breaks` → per-post `case 'excerpt'`
branch chooses preserve vs. plain path → escaped HTML output.

## Error handling / edge cases

- Invalid / empty word count → default 20.
- Missing keys (old installs, disabled-field saves) → runtime defaults /
  prior saved value.
- Output always escaped (`esc_html`) **before** `nl2br`; no raw HTML
  reaches the page (no XSS regression).
- Preserve-on still truncates to the word limit; clamp removed so
  preserved breaks are visible.

## Testing

No automated test harness exists in this repo (CI is lint-only). For
this change:
- Keep the trim logic in an isolated static helper for future unit
  tests.
- Manual verification: toggle excerpt on/off (fields enable/disable),
  save with excerpt off (values retained), vary word count, toggle
  preserve and confirm line breaks render on the frontend.
- `composer lint` (phpcs) and `npm run lintjs` must pass.

Standing up PHPUnit + the WP test harness is out of scope and tracked as
a separate issue.

## Decisions (AgDR)

Key decisions (word-based vs character-based trim, newline preservation
via `nl2br`, absent-key fallback strategy) are recorded in
`docs/agdr/AgDR-0001-excerpt-settings.md`.
