# AgDR-0004: Rank from ordered candidate queries, cache the ranked IDs

- **Date:** 2026-10-02
- **Status:** Accepted
- **Issue:** amahallawy/TopVisitedPostsWP#13

## Context

`build_section()` loaded the 100 newest posts in the category and sorted them
in PHP by the configured criteria (most/least views, newest/oldest, sticky
first, stackable in any order). In a category with more than 100 posts, an
older post could never rank, whatever its views. The query and sort also
ran on every page load, and the target-page scroll map had the same
100-post cap.

## Decisions

1. **Candidate queries ordered by the database, then the existing PHP
   multi-sort.** The PHP sort stays the single source of truth for ordering.
   The database only has to return a candidate set guaranteed to contain the
   true top N:
   - **Viewed posts** (`meta_key` + `meta_value_num`), ordered by the
     criteria translated to SQL, limited to N + S.
   - **Never-viewed posts** (`NOT EXISTS` on the meta key), ordered by the
     same criteria minus the view criteria, limited to N + S.
   - **Sticky posts in the category**, only when "Sticky first" is one of the
     criteria. S is the number of sticky posts.

   Within each set, the SQL order matches the PHP order restricted to that
   set, so each set's top N + S is a superset of its share of the true top N.
   Sticky posts can push up to S others down, which is why the limit is
   N + S. A final tie-break on date, then ID, is added to both sides, which
   also makes the PHP sort deterministic on PHP 7.4, where `usort` is not
   stable.

   Alternatives rejected:
   - **Fetch every post in the category.** Correct, but loads every post
     object (content included) on large categories.
   - **One query with an `EXISTS OR NOT EXISTS` meta clause and orderby.**
     WordPress joins `postmeta` without the key filter for never-viewed posts,
     so they get ordered by an arbitrary other meta value.
   - **A custom `$wpdb` query.** The code standards forbid direct queries
     where a WordPress API exists.

2. **The ranked IDs are cached in one transient (`tvp_ranked_ids`) for
   5 minutes**, keyed by a hash of category, criteria and count. The cache is
   cleared when the settings are saved, when a post enters or leaves
   `publish`, when a post is deleted, and when the sticky list changes. View
   counts in the output stay live; only the order can be up to 5 minutes
   stale. The `tvp_ranking_cache_ttl` filter changes the lifetime, and 0
   disables the cache. One option row in total, not one per view.

3. **The scroll map uses the same ranked IDs.** The section only links to
   ranked posts, so those are the only cards that need anchors. It no longer
   maps the 100 newest posts.

## Consequences

- Rendering does at most three small queries on a cache miss and one option
  read on a hit, regardless of category size.
- A post whose views overtake another's moves up within 5 minutes, or at
  once when the cache is cleared.
