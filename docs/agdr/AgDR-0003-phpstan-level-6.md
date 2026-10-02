# AgDR-0003: PHPStan at level 6 with WordPress stubs, no baseline

- **Date:** 2026-10-02
- **Status:** Accepted
- **Issue:** amahallawy/TopVisitedPostsWP#12

## Context

Nothing statically analysed the plugin's PHP: CodeQL has no PHP support, and
phpcs checks style, not types. `.claude/rules/code-standards.md` in the
Company workspace expects PHPStan or Psalm.

A probe of the current code gave these error counts per PHPStan level:
5 → 10, 6 → 53, 7 → 56, 8 → 58, 9 → 120, max → 161.

## Decisions

1. **PHPStan, not Psalm.** PHPStan has the maintained WordPress extension
   (`szepeviktor/phpstan-wordpress`, which pulls in `php-stubs/wordpress-stubs`
   and understands hooks and `apply_filters` return types). Psalm's WordPress
   plugin lags behind current core.

2. **Level 6, fixed to zero, with no baseline.** Of the 53 level-6 findings,
   32 were missing `@return void` tags and 11 were arrays with no value type.
   Both are docblock-only fixes. The rest were 6 unknown constants and 4 real
   type mismatches: an int passed as `option_none_value` where WordPress
   expects a string, and ints passed to `esc_attr()`/`esc_html()`. All are
   fixed here, so there is nothing to baseline. Level 5 would have skipped
   the docblock work but taught nothing new about return types. Levels 7 and
   up mostly add mixed-type noise from `get_option()` arrays and would need a
   baseline, which tends to rot.

3. **Plugin constants come from a bootstrap file.** `TVP_PLUGIN_URL` and its
   siblings are `define()`d at runtime, so PHPStan can't see them.
   `tests/phpstan-bootstrap.php` declares placeholders, and
   `dynamicConstantNames` stops PHPStan from assuming those placeholder
   values.

4. **`phpstan/extension-installer` loads the WordPress extension** instead
   of an `includes:` path into `vendor/`.

## Consequences

- New dev dependencies: `phpstan/phpstan` ^2.1, `phpstan/extension-installer`
  ^1.4, `szepeviktor/phpstan-wordpress` ^2.0. None ship in the release zip.
- `composer stan` runs locally (with PHP, or through the `composer:2` Docker
  image) and in a new `PHPStan` workflow on every PR and push to `main`.
- New code must keep level 6 clean: every function needs a return type
  (native or `@return`), and arrays need value types.
- Raising the level later is its own issue.
