<?php
/**
 * CSS Documentation admin page for Top Visited Posts.
 *
 * @package TopVisitedPosts
 */

// Abort if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVP_Docs {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_submenu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add the documentation submenu page under Top Visited Posts.
	 */
	public function add_submenu_page() {
		add_submenu_page(
			'top-visited-posts',
			__( 'CSS Documentation', 'top-visited-posts' ),
			__( 'CSS Docs', 'top-visited-posts' ),
			'manage_options',
			'tvp-css-docs',
			array( $this, 'render_docs_page' )
		);
	}

	/**
	 * Enqueue admin styles on our docs page.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( 'top-visited-posts_page_tvp-css-docs' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'tvp-admin',
			TVP_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			TVP_VERSION
		);
	}

	/**
	 * Render the CSS documentation page.
	 */
	public function render_docs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap tvp-settings-wrap" style="max-width:960px;">
			<h1><?php esc_html_e( 'CSS Documentation', 'top-visited-posts' ); ?></h1>
			<p class="description" style="font-size:14px;margin-bottom:20px;">
				<?php esc_html_e( 'Reference for all CSS classes, custom properties, and modifiers used by the Top Visited Posts frontend. Use these to customize the appearance in your theme stylesheet or the WordPress Customizer → Additional CSS.', 'top-visited-posts' ); ?>
			</p>

			<?php
			$this->render_section(
				__( 'CSS Custom Properties (Variables)', 'top-visited-posts' ),
				__( 'Override these on .tvp-section to change the entire color scheme. They cascade to all child elements.', 'top-visited-posts' ),
				array(
					array( '--tvp-bg',              '#f9fafb',  __( 'Section background color', 'top-visited-posts' ) ),
					array( '--tvp-border',           '#e5e7eb',  __( 'Border and separator color', 'top-visited-posts' ) ),
					array( '--tvp-title-color',      '#111827',  __( 'Section heading color', 'top-visited-posts' ) ),
					array( '--tvp-text-color',       '#1f2937',  __( 'Post title text color', 'top-visited-posts' ) ),
					array( '--tvp-meta-color',       '#6b7280',  __( 'Meta text color (excerpt, date, views, rank badge)', 'top-visited-posts' ) ),
					array( '--tvp-accent',           '#4f46e5',  __( 'Accent color (view count, focus ring)', 'top-visited-posts' ) ),
					array( '--tvp-hover-bg',         '#eef2ff',  __( 'Post item background on hover / focus', 'top-visited-posts' ) ),
					array( '--tvp-featured-bg',      '#fffbeb',  __( 'Background for sticky post items', 'top-visited-posts' ) ),
					array( '--tvp-featured-border',  '#f59e0b',  __( 'Left border color for sticky post items', 'top-visited-posts' ) ),
					array( '--tvp-radius',           '8px',      __( 'Border radius for the section and grid cards', 'top-visited-posts' ) ),
					array( '--tvp-columns',          '3',        __( 'Number of grid columns on desktop (set via plugin settings)', 'top-visited-posts' ) ),
				),
				true
			);

			$this->render_section(
				__( 'Section Container', 'top-visited-posts' ),
				__( 'The outermost wrapper and section heading.', 'top-visited-posts' ),
				array(
					array( '.tvp-section',       __( 'Outermost wrapper. Receives layout modifiers and dir="rtl" attribute. Apply custom properties here to re-theme.', 'top-visited-posts' ) ),
					array( '.tvp-section-title',  __( 'The &lt;h2&gt; section heading ("Top Visited Posts"). Hidden if the Section Title setting is empty.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'Post List & Items', 'top-visited-posts' ),
				__( 'The list container and each individual post item.', 'top-visited-posts' ),
				array(
					array( '.tvp-post-list',      __( 'The &lt;ul&gt; that contains all post items. Becomes a CSS grid in grid layout.', 'top-visited-posts' ) ),
					array( '.tvp-post-item',       __( 'Each &lt;li&gt; wrapping a single post. Receives .tvp-post-featured if the post is sticky.', 'top-visited-posts' ) ),
					array( '.tvp-post-featured',   __( 'Modifier added to sticky posts (WordPress native sticky). Adds a left border (border-inline-start) and warm background.', 'top-visited-posts' ) ),
					array( '.tvp-post-link',       __( 'The &lt;a&gt; element wrapping all post content. Flex container for layout. Receives hover/focus styles.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'Rank Badge', 'top-visited-posts' ),
				__( 'The numbered circle badge. Only rendered when "Show Rank Numbers" is enabled.', 'top-visited-posts' ),
				array(
					array( '.tvp-rank-badge', __( 'Circular badge showing the post rank number (1, 2, 3…). In grid layout, positioned absolutely over the top-start corner.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'Thumbnail', 'top-visited-posts' ),
				__( 'The post featured image. Only rendered when the "Thumbnail" element is enabled and the post has a featured image.', 'top-visited-posts' ),
				array(
					array( '.tvp-post-thumb',     __( 'Thumbnail container. Fixed size in list layout, full-width 16:9 ratio in grid layout.', 'top-visited-posts' ) ),
					array( '.tvp-post-thumb img',  __( 'The &lt;img&gt; inside the thumbnail. Uses object-fit: cover for consistent sizing.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'Post Content Elements', 'top-visited-posts' ),
				__( 'Each element is a &lt;span&gt; rendered in the order configured in settings. Elements can be enabled/disabled and reordered.', 'top-visited-posts' ),
				array(
					array( '.tvp-post-title',       __( 'Post title. Truncated to 2 lines with text-overflow ellipsis. Uses --tvp-text-color.', 'top-visited-posts' ) ),
					array( '.tvp-post-excerpt',      __( 'Post excerpt (first 12 words). Truncated to 2 lines. Hidden in list layout on mobile, visible on tablet+.', 'top-visited-posts' ) ),
					array( '.tvp-post-date',         __( 'Relative date ("2 hours ago", "5 days ago"). Uses --tvp-meta-color.', 'top-visited-posts' ) ),
					array( '.tvp-post-views',        __( 'View count display ("42 views"). The number is wrapped in &lt;strong&gt; with --tvp-accent color.', 'top-visited-posts' ) ),
					array( '.tvp-post-views strong', __( 'The numeric view count within the views element. Styled with the accent color.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'Layout Modifiers', 'top-visited-posts' ),
				__( 'Added to .tvp-section to switch between list and grid layout.', 'top-visited-posts' ),
				array(
					array( '.tvp-layout-list',  __( 'List layout: items stack vertically separated by border lines. Excerpt hidden on mobile.', 'top-visited-posts' ) ),
					array( '.tvp-layout-grid',  __( 'Grid layout: items display as cards in a CSS Grid. 1 col mobile → 2 cols tablet → configurable cols desktop.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'Grid-Specific Overrides', 'top-visited-posts' ),
				__( 'These selectors apply only when .tvp-layout-grid is active.', 'top-visited-posts' ),
				array(
					array( '.tvp-layout-grid .tvp-post-list',   __( 'Becomes a CSS grid container. Columns controlled by --tvp-columns at desktop breakpoint.', 'top-visited-posts' ) ),
					array( '.tvp-layout-grid .tvp-post-item',    __( 'Each card gets a border, rounded corners, and white background.', 'top-visited-posts' ) ),
					array( '.tvp-layout-grid .tvp-post-link',    __( 'Switches to flex-direction: column so content stacks vertically inside the card.', 'top-visited-posts' ) ),
					array( '.tvp-layout-grid .tvp-rank-badge',   __( 'Positioned absolutely at top-start of the card with a subtle shadow.', 'top-visited-posts' ) ),
					array( '.tvp-layout-grid .tvp-post-thumb',   __( 'Full-width with 16:9 aspect ratio (padding-bottom: 56.25%). Image fills absolutely.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'Scroll Highlight Animation', 'top-visited-posts' ),
				__( 'Applied when a user clicks a TVP item and is scrolled to the post on the target page.', 'top-visited-posts' ),
				array(
					array( '.tvp-scroll-highlight',  __( 'Added to the target post element after scroll. Triggers a 2s yellow pulse animation. Removed after animationend.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'Responsive Breakpoints', 'top-visited-posts' ),
				__( 'Mobile-first breakpoints that adjust sizing, spacing, and grid columns.', 'top-visited-posts' ),
				array(
					array( '@media (min-width: 480px)',   __( 'Small phones: slightly larger padding, thumbnails (56px), grid → 2 columns.', 'top-visited-posts' ) ),
					array( '@media (min-width: 768px)',   __( 'Tablets: larger spacing, thumbnails (64px), list excerpt visible, grid → 2 columns.', 'top-visited-posts' ) ),
					array( '@media (min-width: 1024px)',  __( 'Desktop: full padding, thumbnails (72px), grid → configured column count.', 'top-visited-posts' ) ),
					array( '@media (min-width: 1280px)',  __( 'Wide screens: slightly larger title, grid card aspect ratio adjusted.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'Media Query Modifiers', 'top-visited-posts' ),
				__( 'Automatic adaptations for user/system preferences.', 'top-visited-posts' ),
				array(
					array( '@media (prefers-reduced-motion: reduce)',  __( 'Disables scroll highlight animation and hover transitions.', 'top-visited-posts' ) ),
					array( '@media (prefers-color-scheme: dark)',       __( 'Overrides all CSS custom properties for dark backgrounds, lighter text, and muted accent.', 'top-visited-posts' ) ),
					array( '@media print',                              __( 'Removes backgrounds, shadows, and hover effects for clean printing.', 'top-visited-posts' ) ),
				)
			);

			$this->render_section(
				__( 'RTL Support', 'top-visited-posts' ),
				__( 'Activated automatically when WordPress uses an RTL locale. No settings needed.', 'top-visited-posts' ),
				array(
					array( '[dir="rtl"] .tvp-section',      __( 'Sets text-align: right on the section.', 'top-visited-posts' ) ),
					array( '[dir="rtl"] .tvp-post-link',     __( 'Reverses flex direction to row-reverse so items flow right-to-left.', 'top-visited-posts' ) ),
					array( '.tvp-section[dir="rtl"]',         __( 'Same as above — also matches when dir is on the section element itself.', 'top-visited-posts' ) ),
				)
			);

			// Example usage card.
			?>
			<div class="tvp-settings-card" style="margin-top:24px;">
				<h2 style="margin-top:0;padding-bottom:12px;border-bottom:1px solid #eee;">
					<?php esc_html_e( 'Example: Custom Theme Override', 'top-visited-posts' ); ?>
				</h2>
				<p><?php esc_html_e( 'Add this to your theme CSS or Customizer → Additional CSS to customize the TVP appearance:', 'top-visited-posts' ); ?></p>
				<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow-x:auto;font-size:13px;line-height:1.6;border:1px solid #dcdcde;"><code><?php echo esc_html(
'/* Change the color scheme */
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
}'
				); ?></code></pre>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a documentation section with a table of selectors.
	 *
	 * @param string $title       Section title.
	 * @param string $description Section description.
	 * @param array  $rows        Array of rows: [ selector, description ] or [ variable, default, description ].
	 * @param bool   $is_vars     Whether this is a CSS variables table (3-column).
	 */
	private function render_section( $title, $description, $rows, $is_vars = false ) {
		?>
		<div class="tvp-settings-card" style="margin-top:16px;">
			<h2 style="margin-top:0;padding-bottom:12px;border-bottom:1px solid #eee;">
				<?php echo esc_html( $title ); ?>
			</h2>
			<p style="margin-bottom:12px;"><?php echo esc_html( $description ); ?></p>
			<table class="widefat striped" style="border:1px solid #c3c4c7;">
				<thead>
					<tr>
						<?php if ( $is_vars ) : ?>
							<th style="width:25%;"><?php esc_html_e( 'Variable', 'top-visited-posts' ); ?></th>
							<th style="width:15%;"><?php esc_html_e( 'Default', 'top-visited-posts' ); ?></th>
							<th><?php esc_html_e( 'Description', 'top-visited-posts' ); ?></th>
						<?php else : ?>
							<th style="width:35%;"><?php esc_html_e( 'Selector', 'top-visited-posts' ); ?></th>
							<th><?php esc_html_e( 'Description', 'top-visited-posts' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><code style="font-size:12px;background:#f0f0f1;padding:2px 6px;border-radius:3px;"><?php echo esc_html( $row[0] ); ?></code></td>
							<?php if ( $is_vars ) : ?>
								<td><code style="font-size:12px;background:#f0f0f1;padding:2px 6px;border-radius:3px;"><?php echo esc_html( $row[1] ); ?></code></td>
								<td><?php echo esc_html( $row[2] ); ?></td>
							<?php else : ?>
								<td><?php echo wp_kses( $row[1], array( 'code' => array(), 'strong' => array(), 'em' => array() ) ); ?></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
