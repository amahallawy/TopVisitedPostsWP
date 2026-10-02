# AgDR-0005: View tracking without a nonce, rate limit without per-view rows

- **Date:** 2026-10-02
- **Status:** Accepted
- **Issue:** amahallawy/TopVisitedPostsWP#14

## Context

`tracker.js` posted to `admin-ajax.php` with a nonce printed into the page,
and `track_view()` rejected the request when `check_ajax_referer()` failed.

1. **Cached pages stopped counting.** WordPress nonces expire after 12–24
   hours. A page cache (plugin, host or CDN) serves the same HTML for longer,
   so every visitor to a stale cached page sent an expired nonce, got a
   `-1`, and the view was lost. `tracker.js` never checked the response, so
   nobody saw it fail.
2. **`wp_options` filled up.** The 30-minute rate limit wrote one transient
   per IP and post: two option rows each. Without a persistent object cache,
   a busy site gathers thousands of rows, all queried through the options
   table.
3. The transient key used `md5( $ip )`. Unsalted, it could be reversed for
   IPv4 by hashing all 2³² addresses.

## Decisions

1. **No nonce on the tracking request.** A nonce protects a logged-in user
   from being tricked into an action (CSRF). This request is anonymous, its
   nonce is public in the page HTML, and its only effect is +1 on a public
   counter, so the nonce added no protection and broke cached pages. The
   endpoint still accepts only published posts of type `post`, and the rate
   limit is what actually controls inflation.

   Alternatives rejected:
   - **Refresh the nonce from an uncached endpoint first.** Doubles the
     requests per view, for no security gain.
   - **Move to a REST route.** Same nonce question, plus a larger change.

2. **The rate limit uses the object cache when there is one, otherwise one
   transient per post.**
   - With a persistent object cache (`wp_using_ext_object_cache()`):
     `wp_cache_add()` per visitor and post, with a 30-minute expiry. It is
     atomic and never touches the database.
   - Without one: a single transient `tvp_view_<post_id>` holds a map of
     visitor keys to view times. Expired entries are pruned on each write,
     and the map is capped at 1,000 visitors per post (oldest dropped). The
     row count is bounded by the posts viewed in the last 30 minutes, not by
     visitors × posts.
   - Known trade-off: two simultaneous first views of the same post can each
     read the map before the other writes it, so one visitor may be
     remembered twice or dropped. At worst that is one extra count, which is
     acceptable for a view counter.

3. **Visitor keys are `wp_hash()` of the IP, truncated to 16 characters.**
   They are salted with the site's auth salts, so they can't be matched back
   to an IP.

4. **`tracker.js` records "viewed" in sessionStorage only after a successful
   JSON response**, so a failed request is retried on the next page view in
   the same session.

## Consequences

- Existing per-IP transients from older versions expire within 30 minutes
  and are removed by WordPress's expired-transient cleanup. No migration is
  needed.
- A visitor rotating IPs can still add views, as before. This is a
  popularity signal, not analytics-grade data.
