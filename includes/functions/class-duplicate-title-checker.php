<?php

namespace VoxelToolkit\Functions;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Duplicate_Title_Checker {

	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->add_hooks();
	}

	private function add_hooks() {
		// AJAX handler for checking duplicate titles
		add_action( 'wp_ajax_voxel_toolkit_check_duplicate_title', array( $this, 'ajax_check_duplicate_title' ) );
		add_action( 'wp_ajax_nopriv_voxel_toolkit_check_duplicate_title', array( $this, 'ajax_check_duplicate_title' ) );

		// Enqueue scripts on frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue JavaScript for real-time duplicate checking
	 */
	public function enqueue_scripts() {
		// Only enqueue on frontend, not admin
		if ( is_admin() ) {
			return;
		}

		$settings = $this->get_settings();

		wp_enqueue_script(
			'voxel-toolkit-duplicate-checker',
			VOXEL_TOOLKIT_URL . 'assets/js/duplicate-title-checker.js',
			array( 'jquery' ),
			VOXEL_TOOLKIT_VERSION,
			true
		);

		wp_localize_script( 'voxel-toolkit-duplicate-checker', 'voxelToolkitDuplicateChecker', array(
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'voxel_toolkit_duplicate_title_check' ),
			'debug'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'block_duplicate' => ! empty( $settings['block_duplicate'] ),
		) );
	}

	/**
	 * Check if current page is a Voxel post creation/edit page
	 */
	private function is_voxel_post_page() {
		global $wp;

		// Check if we're on a create or edit page
		$current_url = home_url( $wp->request );

		// Common Voxel URL patterns for post creation
		$patterns = array(
			'/create-',
			'/edit-',
			'/submit-',
		);

		foreach ( $patterns as $pattern ) {
			if ( strpos( $current_url, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * AJAX handler to check for duplicate titles
	 */
	public function ajax_check_duplicate_title() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'voxel_toolkit_duplicate_title_check' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed.', 'voxel-toolkit' ),
			) );
		}

		// Get parameters
		$title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
		$current_post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		// Validate title
		if ( empty( $title ) ) {
			wp_send_json_success( array(
				'has_duplicate' => false,
				'message' => '',
			) );
		}

		// Check for minimum title length to avoid checking very short titles
		if ( strlen( $title ) < 3 ) {
			wp_send_json_success( array(
				'has_duplicate' => false,
				'message' => '',
			) );
		}

		// Query for duplicate titles
		$duplicates = $this->find_duplicate_titles( $title, $post_type, $current_post_id );

		if ( ! empty( $duplicates ) ) {
			$count = count( $duplicates );

			// Build response message
			$message = sprintf(
				_n(
					'Warning: %d post with a similar title already exists.',
					'Warning: %d posts with similar titles already exist.',
					$count,
					'voxel-toolkit'
				),
				$count
			);

			// Add links to existing posts (limit to 3)
			$links = array();
			$max_links = 3;
			foreach ( array_slice( $duplicates, 0, $max_links ) as $duplicate ) {
				$links[] = array(
					'id' => $duplicate->ID,
					'title' => $duplicate->post_title,
					'url' => get_permalink( $duplicate->ID ),
					'edit_url' => get_edit_post_link( $duplicate->ID ),
					'status' => $duplicate->post_status,
				);
			}

			wp_send_json_success( array(
				'has_duplicate' => true,
				'message' => $message,
				'count' => $count,
				'duplicates' => $links,
			) );
		} else {
			wp_send_json_success( array(
				'has_duplicate' => false,
				'message' => '',
			) );
		}
	}

	/**
	 * Find posts with duplicate or similar titles
	 *
	 * @param string $title The title to check
	 * @param string $post_type The post type to search in
	 * @param int $exclude_post_id Post ID to exclude from search (for edits)
	 * @return array Array of WP_Post objects with matching titles
	 */
	private function find_duplicate_titles( $title, $post_type, $exclude_post_id = 0 ) {
		global $wpdb;

		// Sanitize inputs
		$title = sanitize_text_field( $title );
		$post_type = sanitize_key( $post_type );
		$exclude_post_id = absint( $exclude_post_id );

		// Search for exact match first
		$query = $wpdb->prepare(
			"SELECT ID, post_title, post_status
			FROM {$wpdb->posts}
			WHERE post_title = %s
			AND post_type = %s
			AND post_status IN ('publish', 'draft', 'pending', 'private')
			AND ID != %d
			ORDER BY post_status DESC, post_date DESC
			LIMIT 10",
			$title,
			$post_type,
			$exclude_post_id
		);

		$results = $wpdb->get_results( $query );

		// If no exact matches, try case-insensitive search
		if ( empty( $results ) ) {
			$query = $wpdb->prepare(
				"SELECT ID, post_title, post_status
				FROM {$wpdb->posts}
				WHERE LOWER(post_title) = LOWER(%s)
				AND post_type = %s
				AND post_status IN ('publish', 'draft', 'pending', 'private')
				AND ID != %d
				ORDER BY post_status DESC, post_date DESC
				LIMIT 10",
				$title,
				$post_type,
				$exclude_post_id
			);

			$results = $wpdb->get_results( $query );
		}

		return $results;
	}

	/**
	 * Get settings for duplicate title checker
	 */
	private function get_settings() {
		$options = get_option( 'voxel_toolkit_options', array() );
		$defaults = array(
			'enabled' => true,
			'block_duplicate' => false,
			'min_title_length' => 3,
			'check_delay' => 500, // milliseconds
			'post_types' => array(), // empty = all post types
		);

		if ( isset( $options['duplicate_title_checker'] ) ) {
			return wp_parse_args( $options['duplicate_title_checker'], $defaults );
		}

		return $defaults;
	}

	/**
	 * Render settings for this function
	 */
	public function render_settings( $current_settings ) {
		$settings = isset( $current_settings['duplicate_title_checker'] ) ? $current_settings['duplicate_title_checker'] : array();
		$block_duplicate = isset( $settings['block_duplicate'] ) ? $settings['block_duplicate'] : false;
		?>
		<div class="voxel-toolkit-setting">
			<h3><?php _e( 'Duplicate Title Checker Settings', 'voxel-toolkit' ); ?></h3>
			<p class="description">
				<?php _e( 'Configure how duplicate title checking behaves on post creation forms.', 'voxel-toolkit' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="duplicate_title_checker_block_duplicate">
							<?php _e( 'Block Duplicate Submissions', 'voxel-toolkit' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input
								type="checkbox"
								name="voxel_toolkit_options[duplicate_title_checker][block_duplicate]"
								id="duplicate_title_checker_block_duplicate"
								value="1"
								<?php checked( $block_duplicate, true ); ?>
							/>
							<?php _e( 'Prevent users from submitting posts with duplicate titles', 'voxel-toolkit' ); ?>
						</label>
						<p class="description">
							<?php _e( 'When enabled, the publish button will be disabled if a duplicate title is detected. When disabled, users will see a warning but can still publish.', 'voxel-toolkit' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Check if duplicate checking is enabled for a specific post type
	 */
	private function is_enabled_for_post_type( $post_type ) {
		$settings = $this->get_settings();

		if ( ! $settings['enabled'] ) {
			return false;
		}

		// If post_types is empty, enable for all
		if ( empty( $settings['post_types'] ) ) {
			return true;
		}

		return in_array( $post_type, $settings['post_types'], true );
	}
}
