<?php
/**
 * Admin settings page for Top Visited Posts.
 *
 * @package TopVisitedPosts
 */

// Abort if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVP_Admin {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'tvp_settings';

	/**
	 * All available post elements with labels.
	 *
	 * @return array
	 */
	public static function get_available_elements() {
		return array(
			'thumbnail' => __( 'Thumbnail', 'top-visited-posts' ),
			'title'     => __( 'Title', 'top-visited-posts' ),
			'excerpt'   => __( 'Excerpt', 'top-visited-posts' ),
			'date'      => __( 'Date', 'top-visited-posts' ),
			'views'     => __( 'Views', 'top-visited-posts' ),
		);
	}

	/**
	 * All available ordering criteria with labels.
	 *
	 * Each criterion is a single sort dimension. Multiple criteria can be
	 * stacked via drag-to-reorder to create multi-layer sorting.
	 *
	 * @return array
	 */
	public static function get_order_criteria() {
		return array(
			'most_views'  => __( 'Most views first', 'top-visited-posts' ),
			'least_views' => __( 'Least views first', 'top-visited-posts' ),
			'newest'      => __( 'Newest first', 'top-visited-posts' ),
			'oldest'      => __( 'Oldest first', 'top-visited-posts' ),
			'featured'    => __( 'Sticky posts first', 'top-visited-posts' ),
		);
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin styles and scripts on our settings page only.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_top-visited-posts' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'tvp-admin',
			TVP_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			TVP_VERSION
		);
		wp_enqueue_script(
			'tvp-admin-sortable',
			TVP_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			TVP_VERSION,
			true
		);
	}

	/**
	 * Add the top-level admin menu item and rename its first submenu to "Settings".
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Top Visited Posts', 'top-visited-posts' ),
			__( 'Top Visited Posts', 'top-visited-posts' ),
			'manage_options',
			'top-visited-posts',
			array( $this, 'render_settings_page' ),
			'dashicons-chart-bar',
			30
		);

		// Rename the auto-created first submenu item from "Top Visited Posts" to "Settings".
		add_submenu_page(
			'top-visited-posts',
			__( 'Settings', 'top-visited-posts' ),
			__( 'Settings', 'top-visited-posts' ),
			'manage_options',
			'top-visited-posts',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields using the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'tvp_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// --- Content Section ---
		add_settings_section(
			'tvp_content_section',
			__( 'Content Settings', 'top-visited-posts' ),
			array( $this, 'render_content_section_description' ),
			'top-visited-posts'
		);

		add_settings_field(
			'tvp_category',
			__( 'Post Category', 'top-visited-posts' ),
			array( $this, 'render_category_field' ),
			'top-visited-posts',
			'tvp_content_section'
		);

		add_settings_field(
			'tvp_page_id',
			__( 'Target Page', 'top-visited-posts' ),
			array( $this, 'render_page_field' ),
			'top-visited-posts',
			'tvp_content_section'
		);

		add_settings_field(
			'tvp_num_posts',
			__( 'Number of Posts', 'top-visited-posts' ),
			array( $this, 'render_num_posts_field' ),
			'top-visited-posts',
			'tvp_content_section'
		);

		add_settings_field(
			'tvp_order_by',
			__( 'Order By', 'top-visited-posts' ),
			array( $this, 'render_order_by_field' ),
			'top-visited-posts',
			'tvp_content_section'
		);

		// --- Display Section ---
		add_settings_section(
			'tvp_display_section',
			__( 'Display Settings', 'top-visited-posts' ),
			array( $this, 'render_display_section_description' ),
			'top-visited-posts'
		);

		add_settings_field(
			'tvp_section_title',
			__( 'Section Title', 'top-visited-posts' ),
			array( $this, 'render_section_title_field' ),
			'top-visited-posts',
			'tvp_display_section'
		);

		add_settings_field(
			'tvp_show_rank',
			__( 'Show Rank Numbers', 'top-visited-posts' ),
			array( $this, 'render_show_rank_field' ),
			'top-visited-posts',
			'tvp_display_section'
		);

		add_settings_field(
			'tvp_elements',
			__( 'Post Elements', 'top-visited-posts' ),
			array( $this, 'render_elements_field' ),
			'top-visited-posts',
			'tvp_display_section'
		);

		add_settings_field(
			'tvp_layout',
			__( 'Layout', 'top-visited-posts' ),
			array( $this, 'render_layout_field' ),
			'top-visited-posts',
			'tvp_display_section'
		);

		add_settings_field(
			'tvp_columns',
			__( 'Grid Columns', 'top-visited-posts' ),
			array( $this, 'render_columns_field' ),
			'top-visited-posts',
			'tvp_display_section'
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw form values.
	 * @return array Sanitized values.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['category']      = isset( $input['category'] ) ? absint( $input['category'] ) : 0;
		$sanitized['page_id']       = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
		$sanitized['num_posts']     = isset( $input['num_posts'] ) ? absint( $input['num_posts'] ) : 5;
		$sanitized['section_title'] = isset( $input['section_title'] ) ? sanitize_text_field( $input['section_title'] ) : '';
		$sanitized['show_rank']     = ! empty( $input['show_rank'] ) ? 1 : 0;

		// Order by — ordered list of enabled criteria.
		$valid_orders = array_keys( self::get_order_criteria() );
		$sanitized['order_by'] = array();
		if ( isset( $input['order_by'] ) && is_array( $input['order_by'] ) ) {
			foreach ( $input['order_by'] as $criterion ) {
				$criterion = sanitize_text_field( $criterion );
				if ( in_array( $criterion, $valid_orders, true ) ) {
					$sanitized['order_by'][] = $criterion;
				}
			}
		}
		// Ensure at least one criterion.
		if ( empty( $sanitized['order_by'] ) ) {
			$sanitized['order_by'] = array( 'most_views' );
		}

		// Layout.
		$sanitized['layout'] = 'list';
		if ( isset( $input['layout'] ) && in_array( $input['layout'], array( 'list', 'grid' ), true ) ) {
			$sanitized['layout'] = $input['layout'];
		}

		$sanitized['columns'] = isset( $input['columns'] ) ? absint( $input['columns'] ) : 3;
		if ( $sanitized['columns'] < 2 ) {
			$sanitized['columns'] = 2;
		}
		if ( $sanitized['columns'] > 4 ) {
			$sanitized['columns'] = 4;
		}

		// Clamp num_posts between 1 and 50.
		if ( $sanitized['num_posts'] < 1 ) {
			$sanitized['num_posts'] = 1;
		}
		if ( $sanitized['num_posts'] > 50 ) {
			$sanitized['num_posts'] = 50;
		}

		// Elements: ordered list of enabled element keys.
		$available = array_keys( self::get_available_elements() );
		$sanitized['elements'] = array();
		if ( isset( $input['elements'] ) && is_array( $input['elements'] ) ) {
			foreach ( $input['elements'] as $el ) {
				$el = sanitize_text_field( $el );
				if ( in_array( $el, $available, true ) ) {
					$sanitized['elements'][] = $el;
				}
			}
		}
		// Ensure at least title is shown.
		if ( empty( $sanitized['elements'] ) ) {
			$sanitized['elements'] = array( 'title' );
		}

		return $sanitized;
	}

	/**
	 * Content section description callback.
	 */
	public function render_content_section_description() {
		echo '<p>' . esc_html__( 'Configure which posts appear and how they are ordered.', 'top-visited-posts' ) . '</p>';
	}

	/**
	 * Display section description callback.
	 */
	public function render_display_section_description() {
		echo '<p>' . esc_html__( 'Configure the appearance and visible elements of each post item.', 'top-visited-posts' ) . '</p>';
	}

	/**
	 * Category dropdown field.
	 */
	public function render_category_field() {
		$options  = get_option( self::OPTION_KEY );
		$selected = isset( $options['category'] ) ? absint( $options['category'] ) : 0;

		wp_dropdown_categories( array(
			'name'              => self::OPTION_KEY . '[category]',
			'id'                => 'tvp_category',
			'selected'          => $selected,
			'show_option_none'  => __( '— Select Category —', 'top-visited-posts' ),
			'option_none_value' => 0,
			'hide_empty'        => false,
			'class'             => 'tvp-select',
		) );
		echo '<p class="description">' . esc_html__( 'Choose the post category to display in the top posts section.', 'top-visited-posts' ) . '</p>';
	}

	/**
	 * Page dropdown field.
	 */
	public function render_page_field() {
		$options  = get_option( self::OPTION_KEY );
		$selected = isset( $options['page_id'] ) ? absint( $options['page_id'] ) : 0;

		wp_dropdown_pages( array(
			'name'              => self::OPTION_KEY . '[page_id]',
			'id'                => 'tvp_page_id',
			'selected'          => $selected,
			'show_option_none'  => __( '— Select Page —', 'top-visited-posts' ),
			'option_none_value' => 0,
			'class'             => 'tvp-select',
		) );
		echo '<p class="description">' . esc_html__( 'The page that lists posts from the selected category (e.g. using a Spectra Post Grid block). Clicking a top post navigates here and scrolls to it.', 'top-visited-posts' ) . '</p>';
	}

	/**
	 * Number of posts field.
	 */
	public function render_num_posts_field() {
		$options   = get_option( self::OPTION_KEY );
		$num_posts = isset( $options['num_posts'] ) ? absint( $options['num_posts'] ) : 5;

		printf(
			'<input type="number" id="tvp_num_posts" name="%s[num_posts]" value="%d" min="1" max="50" class="small-text" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $num_posts )
		);
		echo '<p class="description">' . esc_html__( 'How many top visited posts to display (1–50).', 'top-visited-posts' ) . '</p>';
	}

	/**
	 * Order by field — draggable multi-layer priority list.
	 */
	public function render_order_by_field() {
		$options   = get_option( self::OPTION_KEY );
		$order_by  = isset( $options['order_by'] ) ? $options['order_by'] : array( 'most_views' );
		$available = self::get_order_criteria();

		// Migrate legacy single-string value to array.
		if ( is_string( $order_by ) ) {
			$order_by = array( $order_by );
		}

		// Separate enabled (in saved order) from disabled.
		$enabled  = array();
		foreach ( $order_by as $key ) {
			if ( isset( $available[ $key ] ) ) {
				$enabled[ $key ] = $available[ $key ];
			}
		}
		$disabled = array_diff_key( $available, $enabled );

		echo '<div class="tvp-elements-wrap">';
		echo '<p class="description" style="margin-bottom:10px;">' . esc_html__( 'Check criteria to use. Drag to set priority (top = primary sort). "Sticky posts first" uses the native WordPress sticky post feature.', 'top-visited-posts' ) . '</p>';
		echo '<ul id="tvp-orderby-sortable" class="tvp-elements-list">';

		foreach ( $enabled as $key => $label ) {
			$this->render_order_item( $key, $label, true );
		}
		foreach ( $disabled as $key => $label ) {
			$this->render_order_item( $key, $label, false );
		}

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Render a single order criterion item for the sortable list.
	 *
	 * @param string $key     Criterion key.
	 * @param string $label   Criterion label.
	 * @param bool   $checked Whether criterion is enabled.
	 */
	private function render_order_item( $key, $label, $checked ) {
		printf(
			'<li class="tvp-element-item" data-key="%s">' .
			'<span class="tvp-drag-handle dashicons dashicons-menu"></span>' .
			'<label>' .
			'<input type="checkbox" class="tvp-element-checkbox" value="%s" %s /> %s' .
			'</label>' .
			'<input type="hidden" name="%s[order_by][]" value="%s" %s />' .
			'</li>',
			esc_attr( $key ),
			esc_attr( $key ),
			checked( $checked, true, false ),
			esc_html( $label ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			$checked ? '' : 'disabled'
		);
	}

	/**
	 * Section title field.
	 */
	public function render_section_title_field() {
		$options       = get_option( self::OPTION_KEY );
		$section_title = isset( $options['section_title'] ) ? $options['section_title'] : '';

		printf(
			'<input type="text" id="tvp_section_title" name="%s[section_title]" value="%s" class="regular-text" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $section_title )
		);
		echo '<p class="description">' . esc_html__( 'The heading displayed above the top posts section.', 'top-visited-posts' ) . '</p>';
	}

	/**
	 * Show rank numbers checkbox.
	 */
	public function render_show_rank_field() {
		$options   = get_option( self::OPTION_KEY );
		$show_rank = isset( $options['show_rank'] ) ? (int) $options['show_rank'] : 1;

		printf(
			'<label><input type="checkbox" name="%s[show_rank]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION_KEY ),
			checked( $show_rank, 1, false ),
			esc_html__( 'Display rank number badges on each post item', 'top-visited-posts' )
		);
	}

	/**
	 * Post elements — drag to reorder, toggle to enable/disable.
	 */
	public function render_elements_field() {
		$options   = get_option( self::OPTION_KEY );
		$elements  = isset( $options['elements'] ) ? $options['elements'] : array_keys( self::get_available_elements() );
		$available = self::get_available_elements();

		// Separate enabled (in order) from disabled.
		$enabled  = array();
		foreach ( $elements as $key ) {
			if ( isset( $available[ $key ] ) ) {
				$enabled[ $key ] = $available[ $key ];
			}
		}
		$disabled = array_diff_key( $available, $enabled );

		echo '<div class="tvp-elements-wrap">';
		echo '<p class="description" style="margin-bottom:10px;">' . esc_html__( 'Check elements to show. Drag to reorder.', 'top-visited-posts' ) . '</p>';
		echo '<ul id="tvp-elements-sortable" class="tvp-elements-list">';

		// Render enabled elements first (in saved order).
		foreach ( $enabled as $key => $label ) {
			$this->render_element_item( $key, $label, true );
		}
		// Render disabled elements after.
		foreach ( $disabled as $key => $label ) {
			$this->render_element_item( $key, $label, false );
		}

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Render a single element item for the sortable list.
	 *
	 * @param string $key     Element key.
	 * @param string $label   Element label.
	 * @param bool   $checked Whether element is enabled.
	 */
	private function render_element_item( $key, $label, $checked ) {
		printf(
			'<li class="tvp-element-item" data-key="%s">' .
			'<span class="tvp-drag-handle dashicons dashicons-menu"></span>' .
			'<label>' .
			'<input type="checkbox" class="tvp-element-checkbox" value="%s" %s /> %s' .
			'</label>' .
			'<input type="hidden" name="%s[elements][]" value="%s" %s />' .
			'</li>',
			esc_attr( $key ),
			esc_attr( $key ),
			checked( $checked, true, false ),
			esc_html( $label ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			$checked ? '' : 'disabled'
		);
	}

	/**
	 * Layout selector field.
	 */
	public function render_layout_field() {
		$options = get_option( self::OPTION_KEY );
		$layout  = isset( $options['layout'] ) ? sanitize_text_field( $options['layout'] ) : 'list';

		$layouts = array(
			'list' => __( 'List — compact rows, best for sidebars and narrow containers', 'top-visited-posts' ),
			'grid' => __( 'Grid — card layout with thumbnails, best for wide content areas', 'top-visited-posts' ),
		);

		echo '<fieldset>';
		foreach ( $layouts as $value => $label ) {
			printf(
				'<label style="display:block;margin-bottom:6px;"><input type="radio" name="%s[layout]" value="%s" %s /> %s</label>',
				esc_attr( self::OPTION_KEY ),
				esc_attr( $value ),
				checked( $layout, $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Grid columns field.
	 */
	public function render_columns_field() {
		$options = get_option( self::OPTION_KEY );
		$columns = isset( $options['columns'] ) ? absint( $options['columns'] ) : 3;

		printf(
			'<select id="tvp_columns" name="%s[columns]" class="tvp-select">',
			esc_attr( self::OPTION_KEY )
		);
		for ( $i = 2; $i <= 4; $i++ ) {
			printf(
				'<option value="%d" %s>%d %s</option>',
				$i,
				selected( $columns, $i, false ),
				$i,
				esc_html__( 'columns', 'top-visited-posts' )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Number of columns on desktop when using Grid layout.', 'top-visited-posts' ) . '</p>';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show success message after save.
		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error(
				'tvp_messages',
				'tvp_updated',
				__( 'Settings saved.', 'top-visited-posts' ),
				'updated'
			);
		}
		?>
		<div class="wrap tvp-settings-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'tvp_messages' ); ?>

			<div class="tvp-settings-card">
				<form action="options.php" method="post">
					<?php
					settings_fields( 'tvp_settings_group' );
					do_settings_sections( 'top-visited-posts' );
					submit_button( __( 'Save Settings', 'top-visited-posts' ) );
					?>
				</form>
			</div>

			<div class="tvp-settings-card tvp-shortcode-info">
				<h2><?php esc_html_e( 'Usage', 'top-visited-posts' ); ?></h2>
				<p><?php esc_html_e( 'Use the following shortcode to display the top visited posts section anywhere:', 'top-visited-posts' ); ?></p>
				<code>[top_visited_posts]</code>
				<p><?php esc_html_e( 'Place this shortcode on any page (e.g. your homepage) to show top posts. Clicking a post will navigate to the Target Page and scroll to it.', 'top-visited-posts' ); ?></p>
			</div>
		</div>
		<?php
	}
}
