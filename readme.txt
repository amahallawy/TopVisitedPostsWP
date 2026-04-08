=== Top Visited Posts ===
Contributors: mahallawy
Tags: popular posts, top posts, most viewed, analytics, scroll
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display a configurable section of top visited posts by category with smooth scroll-to-post navigation.

== Description ==

**Top Visited Posts** tracks post views and displays a ranked section of your most visited posts. It's designed for sites that organize content by category on dedicated pages.

**Features:**

* Automatic post view tracking (AJAX-based, one count per session)
* Configurable category filter
* Target page selection — clicking a top post navigates to the page and scrolls to center the post
* Customizable section title and post count
* Shortcode `[top_visited_posts]` for flexible placement
* Auto-insert on your configured target page
* Clean, responsive design with hover effects and highlight animation
* Proper security: nonces, capability checks, input sanitization, output escaping

== Installation ==

1. Upload the `top-visited-posts` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Top Visited Posts** in the admin sidebar to configure settings.
4. Select a category, choose the target page, and set your preferences.

== Frequently Asked Questions ==

= How does view tracking work? =

Each time a visitor views a single post, an AJAX request increments a view counter stored as post meta. Views are counted once per session per post.

= How do I display the top posts section? =

Either configure a target page in settings (it auto-appends) or use the `[top_visited_posts]` shortcode anywhere.

= What happens when I click a top post? =

You're taken to the configured target page, and the browser smoothly scrolls to center that post on screen with a brief highlight animation.

== Changelog ==

= 1.0.0 =
* Initial release.
