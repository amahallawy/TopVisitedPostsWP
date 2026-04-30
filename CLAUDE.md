# Plugin: top-visited-posts

Inherits workspace rules from `Company/CLAUDE.md`. This file defines the
plugin-specific concretions.

**Published to:** https://github.com/amahallawy/TopVisitedPostsWP
**Default branch:** `main`

## Merge policy — concrete markers

**Marker 1 (automated review) — all of:**

- `cs.yml` (WPCS via phpcs) — green
- `lint.yml` (PHP syntax across 7.4–8.3) — green
- `lintjs.yml` (ESLint) — green
- `security.yml` (TruffleHog + CodeQL) — green

**Marker 2 (explicit authorization):**

- `lgtm` / `approved` comment on the PR by the repo owner.

### Current state: active

PR-first workflow is binding. Direct pushes and commits to `main` are
blocked by `.claude/hooks/block-main-push.sh` for Claude-initiated
commands.

**Action required on your side (server-side enforcement):**

- Enable GitHub branch protection on `main` in `amahallawy/TopVisitedPostsWP`
  requiring: pull request before merge, status checks passing.
- Set required checks to: `cs.yml`, `lint.yml`, `lintjs.yml`, `security.yml`
  (all five workflows green).
- Consider installing apexyard's `block-unreviewed-merge.sh` to enforce
  the two markers at `gh pr merge` time (future tier).

## Plugin-specific conventions

- **Prefix**: `tvp_` for meta keys, options, hooks; `TVP_` for PHP class
  names. Prevents collisions with other plugins.
- **Storage**: view counts live in post meta (`tvp_view_count`), not a
  custom table. See future ADR if this changes.
- **Release zip structure**: the release workflow produces a zip with
  `top-visited-posts/` as the top-level folder so it installs directly
  into `wp-content/plugins/`.
