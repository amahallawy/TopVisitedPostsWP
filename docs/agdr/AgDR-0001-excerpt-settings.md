# AgDR-0001: Excerpt word count & preserve-line-breaks settings

- **Date:** 2026-06-21
- **Status:** Accepted
- **Issue:** amahallawy/TopVisitedPostsWP#7
- **Spec:** docs/superpowers/specs/2026-06-21-excerpt-settings-design.md

## Context

The excerpt element was trimmed to a hardcoded 12 words with line breaks
collapsed. We need a configurable length and an option to preserve line
breaks, with the controls disabled when the excerpt element is off.

## Decisions

1. **Word-based trim (configurable), not character-based.** Keeps parity
   with the existing `wp_trim_words` behavior and avoids mid-word cuts.
   Default 20 words. Alternative (character count) rejected as more
   surprising for content with multibyte text.

2. **Preserve line breaks via `nl2br( esc_html( ... ) )` + CSS
   `white-space: pre-line`.** A custom word-trim helper preserves `\n`
   because `wp_trim_words` collapses all whitespace. Alternative
   (CSS-only) rejected: WP excerpt processing already strips raw breaks,
   so PHP must keep them.

3. **No DB migration.** Settings stored in the existing `tvp_settings`
   array; missing keys read with runtime defaults. Avoids the migration
   gate and keeps upgrades zero-touch.

4. **Absent-key fallback to prior saved value in `sanitize_settings()`.**
   Disabled inputs are not POSTed; falling back to the stored value (not
   the hardcoded default) prevents disabling the fields from wiping the
   user's saved configuration. A hidden companion `0` input lets the
   preserve checkbox be turned off explicitly when the excerpt element is
   enabled.

## Consequences

- Preserve-on drops the 2-line CSS clamp so breaks are visible.
- Output remains escaped before `nl2br`; no XSS regression.
