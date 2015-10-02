<?php
class WDSDD_Replace_User_Dropdown {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 * Constructor - add our hooks
	 *
	 * @since     1.0.0
	 *
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
	 */
	public function hooks() {
		add_action( 'wp_ajax_wds_replace_user_dropdown', array( $this, 'ajax_get_users' ) );
		add_action( 'wp_ajax_nopriv_wds_replace_user_dropdown', array( $this, 'ajax_get_users' ) );

		add_filter( 'wp_dropdown_users', array( $this, 'dropdown_users_callback' ) );
	}


	public function dropdown_users_callback( $output ) {
		global $post;

		$author_id = isset( $post->post_author ) ? $post->post_author : null;
		$author_data = get_userdata( $author_id );

		$data = array(
			'ajax_callback'	   => 'wds_replace_user_dropdown',
			'post_author'      => $author_id,
			'display_name'     => isset( $author_data->display_name ) ? $author_data->display_name : null,
			'post_type'        => get_post_type(),
			'nonce'            => wp_create_nonce( 'wds-replace-user-dd-nonce' ),
			'placeholder_text' => __( 'Select an Author', 'wds-replace-user-dropdown' ),
		);

		// enqueue select 2
		wp_enqueue_script( 'select2', $this->plugin->url . 'assets/js/select2-3.5.0/select2.min.js', array( 'jquery' ), '3.5.0', true );
		wp_enqueue_style( 'select2', $this->plugin->url . 'assets/js/select2-3.5.0/select2.css', array(), '3.5.0' );
		wp_enqueue_script( 'wds-replace-user-dropdown', $this->plugin->url . 'assets/js/replace-user-dropdown.js', array( 'jquery', 'select2' ), self::VERSION, true );

		wp_localize_script( 'wds-replace-user-dropdown', 'wds_rud_config', $data );

		return '
			<input type="text" name="post_author_override" id="wds-user-search" value="'. $author_id .'"/>
		';
	}

	/**
	 * Search for posts using post_title
	 *
	 * @since     1.0.0
	 *
	 * @return    null    outputs a JSON string to be consumed by an AJAX call
	 */
	public function ajax_get_users() {
		$security_check_passes = (
			! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& 'xmlhttprequest' === strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& isset( $_GET['nonce'], $_GET['q'] )
			&& wp_verify_nonce( $_GET['nonce'],  'wds-replace-user-dd-nonce' )
		);

		if ( ! $security_check_passes ) {
			wp_send_json_error( $_GET );
		}

		// if we have an author id, get the display_name
		if ( isset( $_GET['id'] ) && $_GET['id'] ) {
			$author_data = get_userdata( absint( $_GET['id'] ) );

			$results = array(
				array(
					'id' => $author_data->ID,
					'text' => $author_data->display_name,
				),
			);

			wp_send_json_success( $results );
		}

		$search = sanitize_text_field( $_GET['q'] );

		$user_query = new WP_User_Query(
			array(
				'search' => '*'.$search.'*',
				'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'ID' ),
				'who' => 'authors',
				'number' => 10,
			)
		);

		// bail if we don't have any results
		if ( is_wp_error( $user_query ) ) {
			wp_send_json_error( $_GET );
		}

		$results  = array();
		foreach ( $user_query->results as $user ) {
			$results[] = array(
				'id' => $user->ID,
				'text' => $user->display_name
			);
		}

		wp_send_json_success( $results );
	}
}
