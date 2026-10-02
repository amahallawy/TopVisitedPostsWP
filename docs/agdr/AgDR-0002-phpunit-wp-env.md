# AgDR-0002: PHPUnit integration tests run through wp-env

- **Date:** 2026-10-02
- **Status:** Accepted
- **Issue:** amahallawy/TopVisitedPostsWP#11

## Context

The plugin had no automated tests. Almost every behaviour worth testing goes
through WordPress itself: the AJAX tracker (`check_ajax_referer`, post meta,
transients, a direct `$wpdb` increment), the Settings API sanitiser and the
shortcode, which builds a `WP_Query` and renders posts. The ranking fix
planned in #13 changes that query, so the tests must run it against a real
database to prove anything.

The maintainer's machine has Docker and Node but no local PHP or Composer.

## Decisions

1. **WordPress integration tests (`WP_UnitTestCase`, `WP_Ajax_UnitTestCase`),
   not mocked unit tests.** Alternative: Brain Monkey or WP_Mock with plain
   PHPUnit. Rejected because mocking `WP_Query`, post meta and `$wpdb` would
   test the mocks, not the plugin, and could not catch the query-ordering bug
   in #13.

2. **Run the suite inside `@wordpress/env` (wp-env), locally and in CI.**
   wp-env starts WordPress, MySQL and the WordPress test library in Docker
   from one `.wp-env.json`. Alternative: the classic `install-wp-tests.sh`
   with a MySQL service in CI. Rejected because it needs local PHP, MySQL and
   SVN, which this machine lacks, and the two environments would drift.
   Cost: CI takes about a minute longer to start containers.

3. **PHPUnit 9.6 with `yoast/phpunit-polyfills` 1.x.** PHPUnit 9 is the
   newest major that the WordPress test library supports and that still runs
   on PHP 7.4, the plugin's minimum. The polyfills are required by the
   WordPress test bootstrap.

4. **The plugin is mounted at `wp-content/plugins/top-visited-posts` through a
   `mappings` entry, not `plugins: ["."]`.** `plugins` names the folder after
   the checkout directory, which is `TopVisitedPostsWP` in CI. A fixed mapping
   keeps paths identical everywhere.

## Consequences

- New dev dependencies: `@wordpress/env` (npm), `phpunit/phpunit` and
  `yoast/phpunit-polyfills` (Composer). None ship in the release zip.
- `npm run test:php` is the one command to run the suite; it calls
  `composer test` inside the tests container. Docker must be running.
- A new `tests.yml` workflow runs the suite on every PR and push to `main`.
