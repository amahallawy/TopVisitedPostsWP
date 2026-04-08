# Top Visited Posts

A WordPress plugin that tracks post views and displays a ranked, configurable section of the most visited posts in a category — with a smooth scroll-to-post navigation when visitors click through to the target page.

Built for sites that organize content by category on dedicated pages (e.g. a "News" or "Articles" page rendered with Spectra Post Grid or a theme template). Instead of opening the individual post, clicking a top-posts item takes the visitor to the category page and scrolls the matching card into view with a brief highlight.

- **Version:** 1.0.0
- **Requires WordPress:** 5.8 or newer
- **Requires PHP:** 7.4 or newer
- **License:** GPL-2.0-or-later

---

## Features

### Tracking
- Automatic view counting on single post pages
- AJAX-based increment (one count per session per post)
- View data stored as post meta (`tvp_view_count`) — no extra tables
- Cleanly removed on uninstall

### Ordering (multi-layer, draggable)
Pick any combination of criteria and drag to set priority. The first criterion is the primary sort; subsequent criteria break ties.
- Most views first
- Least views first
- Newest first
- Oldest first
- Sticky posts first (uses native WordPress sticky flag)

### Display
- Single category picker (any WordPress category)
- Post count from 1 to 50
- Customizable section heading (leave blank to hide)
- Optional numbered rank badges (1, 2, 3…)
- Two layouts:
  - **List** — compact vertical rows, good for sidebars and narrow containers
  - **Grid** — card layout with prominent thumbnails, 2–4 columns on desktop

### Post elements (toggleable + reorderable)
Each of the following can be enabled/disabled and dragged to change the rendering order inside each post card:
- Thumbnail (featured image)
- Title
- Excerpt (first 12 words, truncated to 2 lines)
- Relative date ("2 hours ago", "5 days ago")
- View count

### Navigation
- Target page setting: the category listing page that visitors land on
- Clicking a top-posts item navigates to the target page and smoothly scrolls to the matching post card
- Highlight animation draws attention to the target card
- Works with Spectra Post Grid blocks and generic theme templates via permalink-to-anchor mapping

### Accessibility & responsiveness
- Mobile-first responsive breakpoints (480px / 768px / 1024px / 1280px)
- RTL support (auto-detected from WordPress locale)
- Dark mode via `prefers-color-scheme`
- Reduced motion via `prefers-reduced-motion`
- Print styles (backgrounds and hover effects stripped)
- Keyboard focus styles on post links

### Security
- Nonces on all AJAX requests
- Capability checks (`manage_options`) on settings and docs pages
- Input sanitization on every field (allowlists for enums, `absint` for integers, `sanitize_text_field` for strings)
- Output escaping throughout templates
- Validated against stored option data on every render (re-validates enums against allowlists in case DB was tampered with)

### Admin UX
- Dedicated top-level menu with Dashicon
- Settings API-based form with Content and Display sections
- Drag-to-reorder via jQuery UI Sortable
- Built-in **CSS Documentation** subpage (see below)

---

## Installation

1. Download or clone this repository:
   ```bash
   git clone https://github.com/amahallawy/TopVisitedPostsWP.git
   ```
2. Copy or symlink the `top-visited-posts` folder into `wp-content/plugins/` on your WordPress site.
3. Activate **Top Visited Posts** from the WordPress Plugins screen.
4. A new **Top Visited Posts** menu item appears in the admin sidebar.

Alternatively, download a ZIP from the [releases page](https://github.com/amahallawy/TopVisitedPostsWP/releases) and upload it via **Plugins → Add New → Upload Plugin**.

---

## Usage

### 1. Configure the plugin
Open **Top Visited Posts → Settings** in the WordPress admin.

**Content Settings:**
- **Post Category** — the category to pull posts from
- **Target Page** — the page visitors land on when they click a top-posts item (typically a category listing page)
- **Number of Posts** — how many posts to display (1–50)
- **Order By** — check the criteria you want, then drag the list to set priority

**Display Settings:**
- **Section Title** — the heading above the posts (leave empty to hide)
- **Show Rank Numbers** — toggle the numbered rank badges
- **Post Elements** — check which elements to show, drag to reorder
- **Layout** — list or grid
- **Grid Columns** — 2, 3, or 4 (grid layout only)

Save settings.

### 2. Place the shortcode
Paste the shortcode anywhere you want the section to render:

```
[top_visited_posts]
```

Common places:
- Homepage (via the block editor Shortcode block or a Custom HTML block)
- A sidebar widget (Shortcode widget)
- Inside a page or post
- Inside a theme template using `do_shortcode('[top_visited_posts]')`

### 3. Scroll-to-post navigation
When a visitor clicks a top-posts item, the plugin takes them to the configured **Target Page** and appends `#tvp-post-{id}` to the URL. On that page, `scroll.js` looks up the post card by permalink (using a map injected via `wp_localize_script`), assigns the matching anchor ID to it, smoothly scrolls the card into view, and briefly applies the `.tvp-scroll-highlight` class for visual feedback.

This works out of the box with Spectra Post Grid blocks and any theme template that links each card to its permalink.

---

## Shortcode reference

| Shortcode | Description |
|---|---|
| `[top_visited_posts]` | Renders the configured section. All appearance and content options come from the settings page — no attributes needed. |

---

## CSS Documentation

The plugin ships with a complete CSS reference accessible inside the WordPress admin at **Top Visited Posts → CSS Docs**. It lists every selector, CSS custom property, layout modifier, and responsive breakpoint the plugin uses, along with an example theme override.

The same reference is reproduced below for convenience.

### Customizing from your theme

All styling hooks are CSS classes and custom properties — no `!important` gymnastics required. Add overrides to your theme stylesheet or to **Appearance → Customize → Additional CSS**.

### CSS Custom Properties

Set these on `.tvp-section` to re-theme the entire section. They cascade to all child elements.

| Variable | Default | Description |
|---|---|---|
| `--tvp-bg` | `#f9fafb` | Section background color |
| `--tvp-border` | `#e5e7eb` | Border and separator color |
| `--tvp-title-color` | `#111827` | Section heading color |
| `--tvp-text-color` | `#1f2937` | Post title text color |
| `--tvp-meta-color` | `#6b7280` | Meta text color (excerpt, date, views, rank badge) |
| `--tvp-accent` | `#4f46e5` | Accent color (view count, focus ring) |
| `--tvp-hover-bg` | `#eef2ff` | Post item background on hover / focus |
| `--tvp-featured-bg` | `#fffbeb` | Background for sticky post items |
| `--tvp-featured-border` | `#f59e0b` | Left border color for sticky post items |
| `--tvp-radius` | `8px` | Border radius for the section and grid cards |
| `--tvp-columns` | `3` | Number of grid columns on desktop (set automatically from plugin settings) |

### Section container

| Selector | Description |
|---|---|
| `.tvp-section` | Outermost wrapper. Receives layout modifiers and the `dir="rtl"` attribute when WordPress is in RTL mode. Apply custom properties here to re-theme. |
| `.tvp-section-title` | The `<h2>` section heading. Hidden if the Section Title setting is empty. |

### Post list & items

| Selector | Description |
|---|---|
| `.tvp-post-list` | The `<ul>` that contains all post items. Becomes a CSS grid in grid layout. |
| `.tvp-post-item` | Each `<li>` wrapping a single post. Receives `.tvp-post-featured` if the post is sticky. |
| `.tvp-post-featured` | Modifier added to sticky posts. Adds a left border (`border-inline-start`) and warm background. |
| `.tvp-post-link` | The `<a>` element wrapping all post content. Flex container for layout. Receives hover/focus styles. |

### Rank badge

| Selector | Description |
|---|---|
| `.tvp-rank-badge` | Circular badge showing the post rank number (1, 2, 3…). In grid layout it's positioned absolutely over the top-start corner of the card. Only rendered when **Show Rank Numbers** is enabled. |

### Thumbnail

| Selector | Description |
|---|---|
| `.tvp-post-thumb` | Thumbnail container. Fixed size in list layout, full-width 16:9 ratio in grid layout. |
| `.tvp-post-thumb img` | The `<img>` inside the thumbnail. Uses `object-fit: cover` for consistent sizing. |

### Post content elements

Each element is a `<span>` rendered in the order configured in settings. Elements can be toggled and reordered from the admin.

| Selector | Description |
|---|---|
| `.tvp-post-title` | Post title. Truncated to 2 lines with text-overflow ellipsis. Uses `--tvp-text-color`. |
| `.tvp-post-excerpt` | Post excerpt (first 12 words). Truncated to 2 lines. Hidden in list layout on mobile, visible on tablet and up. |
| `.tvp-post-date` | Relative date ("2 hours ago", "5 days ago"). Uses `--tvp-meta-color`. |
| `.tvp-post-views` | View count display ("42 views"). |
| `.tvp-post-views strong` | The numeric view count. Styled with `--tvp-accent`. |

### Layout modifiers

Added to `.tvp-section` to switch between layouts.

| Selector | Description |
|---|---|
| `.tvp-layout-list` | List layout. Items stack vertically separated by border lines. Excerpt hidden on mobile. |
| `.tvp-layout-grid` | Grid layout. Items display as cards in a CSS Grid: 1 column on mobile, 2 columns on tablet, configurable columns on desktop. |

### Grid-specific overrides

These selectors apply only when `.tvp-layout-grid` is active.

| Selector | Description |
|---|---|
| `.tvp-layout-grid .tvp-post-list` | CSS grid container. Columns controlled by `--tvp-columns` at the desktop breakpoint. |
| `.tvp-layout-grid .tvp-post-item` | Each card gets a border, rounded corners, and white background. |
| `.tvp-layout-grid .tvp-post-link` | Switches to `flex-direction: column` so content stacks vertically inside the card. |
| `.tvp-layout-grid .tvp-rank-badge` | Positioned absolutely at the top-start of the card with a subtle shadow. |
| `.tvp-layout-grid .tvp-post-thumb` | Full-width with 16:9 aspect ratio (`padding-bottom: 56.25%`). Image fills absolutely. |

### Scroll highlight animation

| Selector | Description |
|---|---|
| `.tvp-scroll-highlight` | Added to the target post element after scroll. Triggers a 2-second yellow pulse animation. Removed after `animationend`. |

### Responsive breakpoints

| Breakpoint | Description |
|---|---|
| `@media (min-width: 480px)` | Small phones: slightly larger padding, thumbnails 56px, grid → 2 columns. |
| `@media (min-width: 768px)` | Tablets: larger spacing, thumbnails 64px, list excerpt visible, grid → 2 columns. |
| `@media (min-width: 1024px)` | Desktop: full padding, thumbnails 72px, grid → configured column count. |
| `@media (min-width: 1280px)` | Wide screens: slightly larger title, grid card aspect ratio adjusted. |

### User / system preference queries

| Media query | Description |
|---|---|
| `@media (prefers-reduced-motion: reduce)` | Disables scroll highlight animation and hover transitions. |
| `@media (prefers-color-scheme: dark)` | Overrides all CSS custom properties for dark backgrounds, lighter text, and muted accent. |
| `@media print` | Removes backgrounds, shadows, and hover effects for clean printing. |

### RTL support

Activated automatically when WordPress uses an RTL locale. No settings needed.

| Selector | Description |
|---|---|
| `[dir="rtl"] .tvp-section` | Sets `text-align: right` on the section. |
| `[dir="rtl"] .tvp-post-link` | Reverses flex direction to `row-reverse` so items flow right-to-left. |
| `.tvp-section[dir="rtl"]` | Matches when `dir` is set directly on the section element. |

### Example: custom theme override

```css
/* Change the color scheme */
.tvp-section {
    --tvp-bg: #ffffff;
    --tvp-accent: #0073aa;
    --tvp-hover-bg: #f0f6fc;
    --tvp-radius: 12px;
}

/* Make the section title larger */
.tvp-section-title {
    font-size: 1.5em;
    color: #1d2327;
}

/* Style the rank badges */
.tvp-rank-badge {
    background: #0073aa;
    color: #ffffff;
}

/* Hide excerpt everywhere */
.tvp-post-excerpt {
    display: none !important;
}

/* Custom featured post style */
.tvp-post-featured {
    border-inline-start: 4px solid #d63638;
}
.tvp-post-featured .tvp-post-link {
    background-color: #fcf0f1;
}
```

---

## File structure

```
top-visited-posts/
├── top-visited-posts.php          Plugin bootstrap, constants, init, activation hook
├── uninstall.php                  Cleanup of options and view count meta
├── readme.txt                     WordPress.org style readme
├── README.md                      This file
├── includes/
│   └── class-tvp-tracker.php      AJAX view counter + get_views() helper
├── admin/
│   ├── class-tvp-admin.php        Settings page (Settings API)
│   ├── class-tvp-docs.php         CSS documentation page
│   ├── css/admin.css              Admin settings and docs styling
│   └── js/admin.js                jQuery UI Sortable wiring for drag-to-reorder
└── public/
    ├── class-tvp-public.php       Shortcode handler + multi-sort + HTML template
    ├── css/public.css             Frontend styling (variables, layouts, responsive, RTL, dark mode)
    └── js/
        ├── scroll.js              Scroll-to-post + highlight on target page
        └── tracker.js             AJAX view counter on single post pages
```

---

## Uninstall

Deactivating the plugin leaves everything intact. **Deleting** the plugin (via Plugins → Delete) runs `uninstall.php`, which:

- Removes the `tvp_settings` option
- Deletes all `tvp_view_count` post meta entries

No orphaned data is left behind.

---

## Changelog

### 1.0.0 — Initial release
- Automatic post view tracking (AJAX, session-deduped)
- Configurable category, target page, post count (1–50)
- Multi-layer draggable ordering with 5 criteria
- List and grid layouts with 2–4 grid columns
- Toggleable and reorderable post elements (thumbnail, title, excerpt, date, views)
- Rank badges
- Shortcode `[top_visited_posts]`
- Scroll-to-post navigation with highlight animation
- Responsive, RTL, dark mode, reduced motion, and print support
- Built-in CSS documentation admin page
- Clean uninstall (options + post meta)

---

## License

GPL-2.0-or-later. See <https://www.gnu.org/licenses/gpl-2.0.html>.

## Author

**Ahmed Mahallawy** ([@amahallawy](https://github.com/amahallawy))
