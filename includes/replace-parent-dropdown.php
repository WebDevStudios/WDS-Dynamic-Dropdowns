<?php
class WDSDD_Replace_Parent_Dropdown {
	/**
	 * Parent plugin class
	 *
	 * @var WDS_Dynamic_Dropdowns
	 * @since  0.1.0
	 */
	protected $plugin = null;

	/**
	 * Determine if we are loading the parent select dropdown
	 *
	 * @since    0.1.0
	 *
	 * @var      bool
	 */
	protected $is_parent_select_dropdown = false;

	/**
	 * Constructor - add our hooks
	 *
	 * @since     0.1.0
	 *
	 * @param WDS_Dynamic_Dropdowns $plugin Main plugin class
	 * @return    null
	 */
	public function __construct( $plugin ) {
		// allow access to main plugin class
		$this->plugin = $plugin;

		// initiate hooks
		$this->hooks();
	}

	/**
	 * Initiate hooks
	 *
	 * @since  0.1.0
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'dropdown_pages_pseudo_cpt' ) );
		add_filter( 'quick_edit_dropdown_pages_args', array( $this, 'dropdown_pages_pseudo_cpt' ) );
		add_filter( 'wp_dropdown_pages', array( $this, 'replace_default_parent_dropdown' ) );

		add_action( 'wp_ajax_wds_replace_page_dropdown', array( $this, 'ajax_get_pages' ) );
		add_action( 'wp_ajax_nopriv_wds_replace_page_dropdown', array( $this, 'ajax_get_pages' ) );
	}

	/**
	 * Override the default parent dropdown with a fake post_type to short-circuit the query
	 *
	 * @since     0.1.0
	 *
	 * @return    array    $dropdown_args	arguments to be used in get_posts call
	 */
	public function dropdown_pages_pseudo_cpt( $dropdown_args ) {
		$this->is_parent_select_dropdown = true;

		$dropdown_args['post_type'] = 'fake_post_type';

		return $dropdown_args;
	}

	/**
	 * Replace default Parent dropdown with our own
	 *
	 * @since     0.1.0
	 *
	 * @return    string	 new HTML structure
	 */
	function replace_default_parent_dropdown( $output ) {
		if ( $this->is_parent_select_dropdown ) {
			$output = $this->get_replacement_output();
		}

		$this->is_parent_select_dropdown = false;

		return $output;
	}

	/**
	 * Enqueue select2 scripts and styles and output the <input>
	 *
	 * @since     0.1.0
	 *
	 * @return    string    HTML for the <input>
	 */
	public function get_replacement_output() {
		global $post;

		// enqueue select 2
		$this->enqueue_scripts_and_styles();

		// Do search w/ Ajax and populate these fields dynamically when a person starts typing
		return '
			<input type="text" name="parent_id" id="wds-page-search" value="'. ( isset( $post->post_parent ) ? $post->post_parent : '' ) .'"/>
		';
	}

	/**
	 * Enqueues Scripts and Styles and localize script data
	 *
	 * @since     0.1.0
	 * 
	 * @return    void
	 */
	protected function enqueue_scripts_and_styles() {
		global $post;

		wp_enqueue_script( 'select2', $this->plugin->url . 'assets/js//select2-3.5.0/select2.min.js', array( 'jquery' ), '3.5.0', true );
		wp_enqueue_style( 'select2', $this->plugin->url . 'assets/js/select2-3.5.0/select2.css', array(), '3.5.0' );
		wp_enqueue_script( 'wds-replace-page-dropdown', $this->plugin->url . 'assets/js/replace-parent-dropdown.js', array( 'jquery', 'select2' ), $this->plugin->version, true );

		$parent = isset( $post->post_parent ) ? $post->post_parent : '';
		$post_type_object = get_post_type_object( $post->post_type );

		$data = array(
			'ajax_callback'    => 'wds_replace_page_dropdown',
			'parent_id'        => $parent,
			'parent_title'     => $parent ? get_the_title( $parent ) : '',
			'post_type'        => $post->post_type,
			'nonce'            => wp_create_nonce( 'wds-replace-page-dd-nonce' ),
			'placeholder_text' => sprintf( __( 'Select a Parent %s', 'wds-replace-page-dropdown' ), $post_type_object->labels->singular_name ),
		);

		wp_localize_script( 'wds-replace-page-dropdown', 'wds_rpd_config', $data );
	}

	/**
	 * Search for posts using post_title
	 *
	 * @since     0.1.0
	 *
	 * @return    null    outputs a JSON string to be consumed by an AJAX call
	 */
	public function ajax_get_pages() {
		$security_check_passes = (
			! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& 'xmlhttprequest' === strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& isset( $_GET['nonce'], $_GET['q'],  $_GET['post_type'] )
			&& wp_verify_nonce( $_GET['nonce'],  'wds-replace-page-dd-nonce' )
		);

		if ( ! $security_check_passes ) {
			wp_send_json_error( $_GET );
		}

		$search = sanitize_text_field( $_GET['q'] );
		$post_type = sanitize_text_field( $_GET['post_type'] );

		global $wpdb;
		$query = "
			SELECT
				`ID`, `post_title`
			FROM
				`$wpdb->posts`
			WHERE
				`post_title` LIKE '%%%s%%'
				AND `post_type` = '%s'
			LIMIT 20
		";

		$query = $wpdb->prepare( $query, $search, $post_type );
		$results = $wpdb->get_results( $query );

		// sanity check
		if ( is_wp_error( $results ) ) {
			wp_send_json_error( $results->get_error_message() );
		}

		if ( ! is_array( $results ) ) {
			wp_send_json_error( $_GET );
		}

		$response = array();

		foreach ( $results as $result ) {
			$response[] = array(
				'id'   => $result->ID,
				'text' => $result->post_title,
			);
		}

		wp_send_json_success( $response );
	}
}
