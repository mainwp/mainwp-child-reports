<?php
/** MainWP Child Reports uninstall. */

namespace WP_MainWP_Stream;

/**
 * Class Uninstall.
 * @package WP_MainWP_Stream
 */
class Uninstall {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Hold the array of option keys to uninstall
	 *
	 * @var array
	 */
	public $options;

	/**
	 * Hold the array of user meta keys to uninstall
	 *
	 * @var array
	 */
	public $user_meta;

    /**
     * Uninstall constructor.
     *
     * Run each time the class is called.
     *
     * @param object $plugin Plugin class.
     */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->user_meta = array(
			'edit_mainwp_stream_per_page',
			'stream_last_read', // Deprecated
			'stream_unread_count', // Deprecated
			'stream_user_feed_key', // Deprecated
		);
	}

	/**
	 * Uninstall Stream by deleting its data
	 */
	public function ajax_uninstall() {
		check_ajax_referer( 'child_reports_uninstall_nonce', 'wp_mainwp_stream_nonce' );

		$this->options = array(
			$this->plugin->install->option_key,
			$this->plugin->settings->option_key,
			$this->plugin->settings->network_options_key,
		);

		// Verify current user's permissions before proceeding
		if ( ! current_user_can( $this->plugin->admin->settings_cap ) ) {
			wp_die(
				esc_html__( "You don't have sufficient privileges to do this action.", 'mainwp-child-reports' )
			);
		}

		// Prevent this action from firing
		remove_action( 'deactivate_plugin', array( 'Connector_Installer', 'callback' ), null );

		// Just in case
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Drop everything on single site installs or when network activated
		// Otherwise only delete data relative to the current blog
		if ( ! is_multisite() || is_plugin_active_for_network( $this->plugin->locations['plugin'] ) ) {
			$this->delete_all_records();
			$this->delete_all_options();
			$this->delete_all_user_meta();
		} else {
			$blog_id = get_current_blog_id();

			$this->delete_blog_records( $blog_id );
			$this->delete_blog_options( $blog_id );
			$this->delete_blog_user_meta( $blog_id );
		}

		$this->delete_all_cron_events();

		$this->deactivate();
	}

	/**
	 * Delete the Stream database tables
	 */
	private function delete_all_records() {

		/** @global object $wpdb WordPress Database instance. */
		global $wpdb;

		$wpdb->query( "DROP TABLE {$wpdb->mainwp_stream}" );
		$wpdb->query( "DROP TABLE {$wpdb->mainwp_streammeta}" );
	}

	/**
	 * Delete records and record meta from a specific blog
	 *
	 * @param int $blog_id (optional)
	 */
	private function delete_blog_records( $blog_id = 1 ) {
		if ( empty( $blog_id ) || ! is_int( $blog_id ) ) {
			return;
		}

		/** @global object $wpdb WordPress Database instance. */
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE `records`, `meta`
				FROM {$wpdb->mainwp_stream} AS `records`
				LEFT JOIN {$wpdb->mainwp_streammeta} AS `meta`
				ON `meta`.`record_id` = `records`.`ID`
				WHERE blog_id = %d;",
				$blog_id
			)
		);
	}

	/**
	 * Delete all options
	 */
	private function delete_all_options() {

		/** @global object $wpdb WordPress Database instance. */
		global $wpdb;

		// Wildcard matches
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wp_mainwp_stream%';" );

		// Specific options
		foreach ( $this->options as $option ) {
			delete_site_option( $option ); // Supports both multisite and single site installs
		}

		// Single site installs can stop here
		if ( ! is_multisite() ) {
			return;
		}

		// Wildcard matches on network options
		$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%wp_mainwp_stream%';" );

		// Delete options from each blog on network
		foreach ( wp_mainwp_stream_get_sites() as $blog ) {
			$this->delete_blog_options( absint( $blog->blog_id ) );
		}
	}

	/**
	 * Delete options from a specific blog
	 *
	 * @param int $blog_id (optional)
	 */
	private function delete_blog_options( $blog_id = 1 ) {
		if ( empty( $blog_id ) || ! is_int( $blog_id ) ) {
			return;
		}

		/** @global object $wpdb WordPress Database instance. */
		global $wpdb;

		// Wildcard matches
		$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '%wp_mainwp_stream%';" );

		// Specific options
		foreach ( $this->options as $option ) {
			delete_blog_option( $blog_id, $option );
		}
	}

	/**
	 * Delete all user meta
	 */
	private function delete_all_user_meta() {

		/** @global object $wpdb WordPress Database instance. */
		global $wpdb;

		// Wildcard matches
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '%wp_mainwp_stream%';" );

		// Specific user meta
		foreach ( $this->user_meta as $meta_key ) {
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s;", $meta_key )
			);
		}
	}

	/**
	 * Delete user meta from a specific blog
	 *
	 * @param int $blog_id (optional)
	 */
	private function delete_blog_user_meta( $blog_id = 1 ) {
		if ( empty( $blog_id ) || ! is_int( $blog_id ) ) {
			return;
		}

		/** @global object $wpdb WordPress Database instance. */
		global $wpdb;

		// Wildcard matches
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '{$wpdb->prefix}%wp_mainwp_stream%';" );

		// Specific user meta
		foreach ( $this->user_meta as $meta_key ) {
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = {$wpdb->prefix}%s;", $meta_key )
			);
		}
	}

	/**
	 * Delete scheduled cron event hooks
	 */
	private function delete_all_cron_events() {
		wp_clear_scheduled_hook( 'wp_mainwp_stream_auto_purge' );
	}

	/**
	 * Deactivate the plugin and redirect to the plugins screen
	 */
	private function deactivate() {
		deactivate_plugins( $this->plugin->locations['plugin'] );

		wp_safe_redirect(
			add_query_arg(
				array(
					'deactivate' => true,
				),
				self_admin_url( 'plugins.php' )
			)
		);

		exit;
	}
}
