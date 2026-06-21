# Excerpt Word Count & Preserve-Line-Breaks Settings — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two display settings to the Top Visited Posts excerpt element — a configurable word count and a preserve-line-breaks toggle — with the fields disabled in admin when the excerpt element isn't selected.

**Architecture:** Both settings live in the existing `tvp_settings` option array (no DB migration). Admin registers two new Display-Settings fields; `sanitize_settings()` validates them and falls back to the previously saved value when a key is absent (so the cosmetic disabling never wipes config). The frontend `case 'excerpt'` reads both settings; a new isolated static helper trims by word count while preserving newlines for the preserve-on path.

**Tech Stack:** PHP 7.4+ (WordPress Settings API), jQuery (admin), CSS. CI is lint-only (phpcs/WPCS + ESLint); there is **no PHPUnit/JS test harness** in this repo, so tasks use lint + scripted manual verification instead of automated tests. Standing up a test harness is out of scope (tracked separately).

**Spec:** `docs/superpowers/specs/2026-06-21-excerpt-settings-design.md`
**Issue:** amahallawy/TopVisitedPostsWP#7
**Branch:** `feature/#7-excerpt-word-count-preserve-breaks` (already created)

**Conventions reminder:** stage specific files only (never `git add -A`); commits end with the Claude trailer; never commit to `main`.

---

### Task 1: Record the AgDR for the design decisions

**Files:**
- Create: `docs/agdr/AgDR-0001-excerpt-settings.md`

- [ ] **Step 1: Write the AgDR**

```markdown
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
   user's saved configuration.

## Consequences

- Preserve-on drops the 2-line CSS clamp so breaks are visible.
- Output remains escaped before `nl2br`; no XSS regression.
```

- [ ] **Step 2: Commit**

```bash
git add docs/agdr/AgDR-0001-excerpt-settings.md
git commit -m "docs: add AgDR-0001 for excerpt settings decisions

Refs #7

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Add activation defaults

**Files:**
- Modify: `top-visited-posts.php:67-77` (the `$defaults` array in `tvp_activate()`)

- [ ] **Step 1: Add the two new keys to `$defaults`**

Change the `$defaults` array so it reads:

```php
	$defaults = array(
		'category'                => 0,
		'page_id'                 => 0,
		'num_posts'               => 5,
		'section_title'           => __( 'Top Visited Posts', 'top-visited-posts' ),
		'layout'                  => 'list',
		'columns'                 => 3,
		'show_rank'               => 1,
		'order_by'                => array( 'most_views' ),
		'elements'                => array( 'thumbnail', 'title', 'excerpt', 'date', 'views' ),
		'excerpt_words'           => 20,
		'excerpt_preserve_breaks' => 0,
	);
```

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l top-visited-posts.php`
Expected: `No syntax errors detected in top-visited-posts.php`

- [ ] **Step 3: Commit**

```bash
git add top-visited-posts.php
git commit -m "feat: add excerpt_words and excerpt_preserve_breaks defaults

Refs #7

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Register the two new admin fields + render callbacks

**Files:**
- Modify: `admin/class-tvp-admin.php` — add two `add_settings_field()` calls after the `tvp_elements` field (after line 199), and add two render callbacks (after `render_elements_field()`'s helper, e.g. after line 513).

- [ ] **Step 1: Register the fields**

Immediately after the `tvp_elements` `add_settings_field(...)` block (ends line 199) and before the `tvp_layout` block, insert:

```php
		add_settings_field(
			'tvp_excerpt_words',
			__( 'Excerpt Length (words)', 'top-visited-posts' ),
			array( $this, 'render_excerpt_words_field' ),
			'top-visited-posts',
			'tvp_display_section'
		);

		add_settings_field(
			'tvp_excerpt_preserve_breaks',
			__( 'Preserve Line Breaks', 'top-visited-posts' ),
			array( $this, 'render_excerpt_preserve_breaks_field' ),
			'top-visited-posts',
			'tvp_display_section'
		);
```

- [ ] **Step 2: Add the render callbacks**

After the `render_element_item()` method (closes line 513), insert:

```php
	/**
	 * Excerpt word-count field.
	 *
	 * Disabled in the UI when the excerpt element is not selected (handled
	 * by admin.js). The wrapper row carries a class so the JS can target it.
	 */
	public function render_excerpt_words_field() {
		$options = get_option( self::OPTION_KEY );
		$words   = isset( $options['excerpt_words'] ) ? absint( $options['excerpt_words'] ) : 20;

		printf(
			'<input type="number" id="tvp_excerpt_words" class="tvp-excerpt-dependent" min="1" max="100" step="1" name="%s[excerpt_words]" value="%d" />',
			esc_attr( self::OPTION_KEY ),
			(int) $words
		);
		echo '<p class="description">' . esc_html__( 'Number of words shown in the excerpt (1–100). Only applies when the Excerpt element is enabled.', 'top-visited-posts' ) . '</p>';
	}

	/**
	 * Excerpt preserve-line-breaks checkbox.
	 *
	 * Disabled in the UI when the excerpt element is not selected (handled
	 * by admin.js).
	 */
	public function render_excerpt_preserve_breaks_field() {
		$options  = get_option( self::OPTION_KEY );
		$preserve = isset( $options['excerpt_preserve_breaks'] ) ? (int) $options['excerpt_preserve_breaks'] : 0;

		printf(
			'<label><input type="checkbox" id="tvp_excerpt_preserve_breaks" class="tvp-excerpt-dependent" name="%s[excerpt_preserve_breaks]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION_KEY ),
			checked( $preserve, 1, false ),
			esc_html__( 'Keep line breaks and blank lines from the post excerpt', 'top-visited-posts' )
		);
	}
```

- [ ] **Step 3: Verify PHP syntax**

Run: `php -l admin/class-tvp-admin.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add admin/class-tvp-admin.php
git commit -m "feat: register excerpt word-count and preserve-breaks admin fields

Refs #7

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Sanitize the two new settings (with absent-key fallback)

**Files:**
- Modify: `admin/class-tvp-admin.php` — inside `sanitize_settings()`, before `return $sanitized;` (line 287).

- [ ] **Step 1: Add sanitization with prior-value fallback**

Insert just before `return $sanitized;`:

```php
		// Excerpt sub-settings. Because the fields are disabled in the UI
		// when the excerpt element is off (and disabled inputs are not
		// submitted), fall back to the previously saved value rather than
		// the hardcoded default so disabling never wipes the config.
		$existing = get_option( self::OPTION_KEY );

		if ( isset( $input['excerpt_words'] ) ) {
			$words = absint( $input['excerpt_words'] );
		} elseif ( is_array( $existing ) && isset( $existing['excerpt_words'] ) ) {
			$words = absint( $existing['excerpt_words'] );
		} else {
			$words = 20;
		}
		if ( $words < 1 ) {
			$words = 1;
		}
		if ( $words > 100 ) {
			$words = 100;
		}
		$sanitized['excerpt_words'] = $words;

		if ( isset( $input['excerpt_preserve_breaks'] ) ) {
			$sanitized['excerpt_preserve_breaks'] = empty( $input['excerpt_preserve_breaks'] ) ? 0 : 1;
		} elseif ( is_array( $existing ) && isset( $existing['excerpt_preserve_breaks'] ) ) {
			$sanitized['excerpt_preserve_breaks'] = (int) $existing['excerpt_preserve_breaks'] ? 1 : 0;
		} else {
			$sanitized['excerpt_preserve_breaks'] = 0;
		}
```

Note: the checkbox absent-key fallback intentionally preserves the prior
value instead of treating "absent" as unchecked, because the field is
disabled (not POSTed) when the excerpt element is off. When the excerpt
element IS on, the checkbox is enabled and an unchecked box sends no value
— in that case `$input['excerpt_preserve_breaks']` is absent and we keep
the prior value. To allow turning it OFF, see Task 5 Step 2 which keeps
the checkbox enabled whenever excerpt is on, plus a hidden companion
input is NOT used; instead unchecking is handled by the enabled-state +
the fact that users uncheck then the prior value persists. **Decision:**
to make unchecking work reliably, Task 3's checkbox is paired with a
hidden fallback input below.

- [ ] **Step 2: Add a hidden companion input so unchecking persists**

Unchecking a checkbox sends nothing, which our fallback would treat as
"keep prior value" — making it impossible to turn off. Fix by adding a
hidden `0` input *before* the checkbox so an explicit `0` is always sent
when the field is enabled. In `render_excerpt_preserve_breaks_field()`
(Task 3 Step 2), replace the single `printf(...)` for the label with:

```php
		printf(
			'<input type="hidden" class="tvp-excerpt-dependent-hidden" name="%1$s[excerpt_preserve_breaks]" value="0" />' .
			'<label><input type="checkbox" id="tvp_excerpt_preserve_breaks" class="tvp-excerpt-dependent" name="%1$s[excerpt_preserve_breaks]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_KEY ),
			checked( $preserve, 1, false ),
			esc_html__( 'Keep line breaks and blank lines from the post excerpt', 'top-visited-posts' )
		);
```

With the hidden `0` present: when excerpt is ON, both inputs are enabled
— checked sends `1` (checkbox wins, later in DOM), unchecked sends `0`.
When excerpt is OFF, admin.js disables BOTH inputs (Task 5), nothing is
sent, and the sanitizer keeps the prior value. This makes Step 1's
checkbox branch behave correctly for both on and off states.

- [ ] **Step 3: Verify PHP syntax**

Run: `php -l admin/class-tvp-admin.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add admin/class-tvp-admin.php
git commit -m "feat: sanitize excerpt settings with prior-value fallback

Refs #7

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Admin JS — disable the fields when excerpt is not selected

**Files:**
- Modify: `admin/js/admin.js` — add logic inside the existing `$( function () { ... } )` ready handler (after the `.each(...)` block, before line 32's closing `});`).

- [ ] **Step 1: Add the dependency toggle logic**

Inside the document-ready callback, after the existing
`$( '#tvp-elements-sortable, #tvp-orderby-sortable' ).each( ... );`
block, insert:

```javascript
		// Enable/disable the excerpt-dependent fields based on whether the
		// excerpt element is checked in the elements list.
		var $excerptCheckbox = $( '#tvp-elements-sortable .tvp-element-checkbox[value="excerpt"]' );
		var $dependents = $( '.tvp-excerpt-dependent, .tvp-excerpt-dependent-hidden' );

		function syncExcerptDependents() {
			var enabled = $excerptCheckbox.length ? $excerptCheckbox.is( ':checked' ) : false;
			$dependents.prop( 'disabled', ! enabled );
		}

		// Initial state on page load.
		syncExcerptDependents();

		// Update whenever the excerpt checkbox changes.
		$excerptCheckbox.on( 'change', syncExcerptDependents );
```

- [ ] **Step 2: Verify ESLint passes**

Run: `npm run lintjs`
Expected: no errors for `admin/js/admin.js`

- [ ] **Step 3: Commit**

```bash
git add admin/js/admin.js
git commit -m "feat: disable excerpt settings fields when excerpt element is off

Refs #7

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Frontend — configurable word count + preserve-breaks rendering

**Files:**
- Modify: `public/class-tvp-public.php` — read settings in `build_section()` (after line 212), replace the `case 'excerpt'` block (lines 343-347), and add a static helper method.

- [ ] **Step 1: Read the two settings in `build_section()`**

After the `$elements = ...` line (line 212), insert:

```php
		$excerpt_words    = isset( $options['excerpt_words'] ) ? absint( $options['excerpt_words'] ) : 20;
		$excerpt_preserve = isset( $options['excerpt_preserve_breaks'] ) ? (int) $options['excerpt_preserve_breaks'] : 0;
		if ( $excerpt_words < 1 ) {
			$excerpt_words = 1;
		}
		if ( $excerpt_words > 100 ) {
			$excerpt_words = 100;
		}
```

- [ ] **Step 2: Replace the `case 'excerpt'` block**

Replace lines 343-347 (the current `case 'excerpt':` ... `break;`) with:

```php
									case 'excerpt':
										if ( $excerpt_preserve ) {
											$excerpt_text = self::trim_words_keep_breaks( get_the_excerpt(), $excerpt_words, '…' );
											?>
											<span class="tvp-post-excerpt tvp-post-excerpt--preserve-breaks"><?php echo nl2br( esc_html( $excerpt_text ) ); ?></span>
											<?php
										} else {
											?>
											<span class="tvp-post-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), $excerpt_words, '…' ) ); ?></span>
											<?php
										}
										break;
```

- [ ] **Step 3: Add the newline-preserving trim helper**

Add this static method to the `TVP_Public` class (e.g. immediately after
`build_section()` closes, or near the other helpers). It mirrors
`wp_trim_words` semantics but preserves `\n`:

```php
	/**
	 * Trim text to a word count while preserving line breaks.
	 *
	 * Unlike wp_trim_words(), which collapses all whitespace (including
	 * newlines) to single spaces, this keeps `\n` characters so the
	 * excerpt can be rendered with line breaks intact.
	 *
	 * @param string $text      Source text (may contain HTML/newlines).
	 * @param int    $num_words Maximum number of words to keep.
	 * @param string $more      Appended when the text is truncated.
	 * @return string Trimmed plain text with newlines preserved.
	 */
	public static function trim_words_keep_breaks( $text, $num_words, $more = '…' ) {
		$text      = wp_strip_all_tags( $text );
		$num_words = (int) $num_words;

		// Normalise CRLF/CR to LF; collapse spaces/tabs but keep newlines.
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );

		// Split into tokens, treating runs of spaces and newlines as
		// separators but keeping newline tokens so they can be re-emitted.
		$tokens = preg_split( '/( |\n)/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
		if ( empty( $tokens ) ) {
			return '';
		}

		$out       = '';
		$word_seen = 0;
		$truncated = false;

		foreach ( $tokens as $token ) {
			if ( "\n" === $token || ' ' === $token ) {
				$out .= $token;
				continue;
			}
			if ( $word_seen >= $num_words ) {
				$truncated = true;
				break;
			}
			$out .= $token;
			++$word_seen;
		}

		$out = rtrim( $out );

		if ( $truncated ) {
			$out .= $more;
		}

		return $out;
	}
```

- [ ] **Step 4: Verify PHP syntax**

Run: `php -l public/class-tvp-public.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add public/class-tvp-public.php
git commit -m "feat: render excerpt with configurable word count and preserved breaks

Refs #7

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: CSS for the preserve-breaks variant

**Files:**
- Modify: `public/css/public.css` — add a rule near the existing
  `.tvp-post-excerpt` styles (around lines 150-160).

- [ ] **Step 1: Add the preserve-breaks rule**

Append after the base `.tvp-post-excerpt` block:

```css
.tvp-post-excerpt--preserve-breaks {
	white-space: pre-line; /* honour preserved line breaks */
	-webkit-line-clamp: none; /* show all lines, not just 2 */
	display: block;
}
```

- [ ] **Step 2: Commit**

```bash
git add public/css/public.css
git commit -m "style: add preserve-breaks excerpt variant CSS

Refs #7

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: Version bump, changelog, readme & uninstall note

**Files:**
- Modify: `top-visited-posts.php` (plugin header `Version:` and `TVP_VERSION`)
- Modify: `readme.txt` (Stable tag + changelog)
- Modify: `README.md` (feature list + changelog)
- Check: `uninstall.php` — confirm it deletes the whole `tvp_settings` option (no per-key change needed); only touch if it deletes specific keys.

- [ ] **Step 1: Bump version to 0.2.0**

In `top-visited-posts.php`, set the header `Version: 0.2.0` and
`define( 'TVP_VERSION', '0.2.0' );`.

- [ ] **Step 2: Add changelog entries**

In `readme.txt`, set `Stable tag: 0.2.0` and add under `== Changelog ==`:

```
= 0.2.0 =
* Add configurable excerpt word count (default 20).
* Add option to preserve line breaks in the excerpt.
* Excerpt length/preserve fields are disabled when the Excerpt element is off.
```

In `README.md`, add the matching changelog entry and mention the two new
options in the features/settings section.

- [ ] **Step 3: Confirm uninstall cleanup**

Run: `grep -n "tvp_settings" uninstall.php`
Expected: it deletes the `tvp_settings` option wholesale (e.g.
`delete_option( 'tvp_settings' )`). If so, no change needed — the new
keys are removed with the option. If it deletes specific sub-keys, add
the two new keys.

- [ ] **Step 4: Verify PHP syntax**

Run: `php -l top-visited-posts.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add top-visited-posts.php readme.txt README.md
git commit -m "chore: bump to 0.2.0 and document excerpt settings

Refs #7

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 9: Full lint pass + manual verification

**Files:** none (verification only)

- [ ] **Step 1: PHP lint / WPCS**

Run: `composer lint`
Expected: no phpcs errors. Fix any reported issues (alignment, escaping,
Yoda conditions) and amend the relevant task's commit or add a fixup
commit.

- [ ] **Step 2: JS lint**

Run: `npm run lintjs`
Expected: clean.

- [ ] **Step 3: Manual verification checklist (sandbox-wp or local WP)**

Verify each acceptance criterion from issue #7:
- Display Settings shows "Excerpt Length (words)" number field + "Preserve Line Breaks" checkbox.
- Uncheck "Excerpt" in the elements list → both fields become disabled; re-check → enabled. Correct on initial page load too.
- With excerpt OFF, save settings, re-open → word count & preserve values retained (not reset).
- With excerpt ON, set words to e.g. 5, uncheck preserve, save → frontend excerpt shows ~5 words on one line.
- Enter words = 0 or 999 → saved value clamps to 1 / 100.
- Check preserve, save → frontend excerpt renders line breaks (`pre-line`), no 2-line clamp; output is escaped (insert a post excerpt containing `<b>` and confirm it shows literally, not bold).
- On a site whose `tvp_settings` predates this change (no new keys), the widget renders with defaults and no PHP notices.

- [ ] **Step 4: Push the branch**

```bash
git push -u origin feature/#7-excerpt-word-count-preserve-breaks
```

---

### Task 10: Open the PR (stop at merge gate)

**Files:** none

- [ ] **Step 1: Create the PR**

```bash
gh pr create --repo amahallawy/TopVisitedPostsWP \
  --title "feat(#7): configurable excerpt word count and preserve line breaks" \
  --body "<see PR body below>"
```

PR body must include a **Glossary** section (workspace rule), a summary,
the acceptance-criteria checklist, the AgDR link, and `Closes #7`.

- [ ] **Step 2: STOP — merge gate**

Do **not** merge. Per workspace rules, merging requires both markers
(automated review green + explicit per-PR author authorization). Note:
`security.yml` is known-misconfigured in this repo and may show red,
which blocks Marker 1 — surface this to the owner rather than working
around it. Hand back to the user for review/approval.

---

## Self-Review notes

- **Spec coverage:** word count (Tasks 2,3,4,6), preserve breaks (Tasks 3,4,6,7), disable-when-off (Task 5), absent-key fallback (Task 4), no-migration/runtime defaults (Tasks 2,6), AgDR (Task 1), governance/PR (Tasks 9,10). All spec sections covered.
- **Checkbox off-state gotcha:** resolved in Task 4 Step 2 via the hidden companion `0` input so unchecking persists when excerpt is on, while disabling-both (Task 5) preserves prior value when excerpt is off.
- **Type/name consistency:** helper named `trim_words_keep_breaks` and class `tvp-post-excerpt--preserve-breaks` / `tvp-excerpt-dependent` used consistently across Tasks 3, 5, 6, 7.
- **No test harness:** TDD steps replaced with `php -l`, `composer lint`, `npm run lintjs`, and a manual checklist — explicitly noted; harness is out of scope.
