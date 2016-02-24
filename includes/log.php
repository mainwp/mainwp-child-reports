<?php

class MainWP_WP_Stream_Log {

	public static $instance = null;

	public $prev_record;

	public static function load() {
		
		$log_handler = apply_filters( 'mainwp_wp_stream_log_handler', __CLASS__ );

		self::$instance = new $log_handler;
	}

	public static function get_instance() {
		if ( ! self::$instance ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

	public function log( $connector, $message, $args, $object_id, $contexts, $user_id = null , $created_timestamp = null) {
		global $wpdb;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		require_once MAINWP_WP_STREAM_INC_DIR . 'class-wp-stream-author.php';

		$user  = new WP_User( $user_id );
		$roles = get_option( $wpdb->get_blog_prefix() . 'user_roles' );

		if ( ! isset( $args['author_meta'] ) ) {
			$args['author_meta'] = array(
				'user_email'      => $user->user_email,
				'display_name'    => ( defined( 'WP_CLI' ) && empty( $user->display_name ) ) ? 'WP-CLI' : $user->display_name,
				'user_login'      => $user->user_login,
				'user_role_label' => ! empty( $user->roles ) ? $roles[ $user->roles[0] ]['name'] : null,
				'agent'           => MainWP_WP_Stream_Author::get_current_agent(),
			);

			if ( ( defined( 'WP_CLI' ) ) && function_exists( 'posix_getuid' ) ) {
				$uid       = posix_getuid();
				$user_info = posix_getpwuid( $uid );

				$args['author_meta']['system_user_id']   = $uid;
				$args['author_meta']['system_user_name'] = $user_info['name'];
			}
		}

		// Remove meta with null values from being logged
		$meta = array_filter(
			$args,
			function ( $var ) {
				return ! is_null( $var );
			}
		);

		$recordarr = array(
			'object_id'   => $object_id,
			'site_id'     => is_multisite() ? get_current_site()->id : 1,
			'blog_id'     => apply_filters( 'blog_id_logged', is_network_admin() ? 0 : get_current_blog_id() ),
			'author'      => $user_id,
			'author_role' => ! empty( $user->roles ) ? $user->roles[0] : null,
			'created'     => !empty($created_timestamp) ? gmdate("Y-m-d H:i:s", $created_timestamp) : current_time( 'mysql', 1 ),
			'summary'     => vsprintf( $message, $args ),
			'parent'      => self::$instance->prev_record,
			'connector'   => $connector,
			'contexts'    => $contexts,
			'meta'        => $meta,
			'ip'          => mainwp_wp_stream_filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP ),
		);

		$record_id = MainWP_WP_Stream_DB::get_instance()->insert( $recordarr );

		return $record_id;
	}

	public function get_log( $agrs = array()) {
		return MainWP_WP_Stream_DB::get_instance()->get_report( $agrs );		
	}
		
}
