<?php
/**
 * MainWP Child Reports.
 *
 * Plugin Name: MainWP Child Reports
 * Plugin URI: https://mainwp.com/
 * Description: The MainWP Child Report plugin tracks Child sites for the MainWP Client Reports Extension. The plugin is only useful if you are using MainWP and the Client Reports Extension.
 * Author: MainWP
 * Author URI: https://mainwp.com
 * Version: 2.0.5
 * Requires at least: 3.6
 * Text Domain: mainwp-child-reports
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

 /**
  * Credit to the Stream Plugin which the MainWP Child Reports plugin is built on.
  *
  * Plugin Name: Stream
  * Plugin-URI: https://wp-stream.com/
  * Description: Stream tracks logged-in user activity so you can monitor every change made on your WordPress site in beautifully organized detail. All activity is organized by context, action and IP address for easy filtering. Developers can extend Stream with custom connectors to log any kind of action.
  * Author: XWP
  * Author URI: https://xwp.co/
  * License: GPLv2+
  */

if ( ! version_compare( PHP_VERSION, '5.6', '>=' ) ) {
	load_plugin_textdomain( 'mainwp-child-reports', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	add_action( 'shutdown', 'wp_mainwp_stream_fail_php_version' );
} else {
	require __DIR__ . '/classes/class-plugin.php';
	$plugin_class_name = 'WP_MainWP_Stream\Plugin';
	if ( class_exists( $plugin_class_name ) ) {
		define( 'WP_MAINWP_STREAM_PLUGIN', plugin_basename( __FILE__ ) );
		$GLOBALS['wp_mainwp_stream'] = new $plugin_class_name();
	}
}

/**
 * Invoked when the PHP version check fails
 * Load up the translations and add the error message to the admin notices.
 */
function wp_mainwp_stream_fail_php_version() {
	load_plugin_textdomain( 'mainwp-child-reports', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	$message      = esc_html__( 'MainWP Child Reports requires PHP version 5.3+, plugin is currently NOT ACTIVE.', 'stream' );
	$html_message = sprintf( '<div class="error">%s</div>', wpautop( $message ) );

	echo wp_kses_post( $html_message );
}

/**
 * Helper for external plugins which wish to use Stream
 *
 * @return WP_MainWP_Stream\Plugin
 */
function wp_mainwp_stream_get_instance() {
	return $GLOBALS['wp_mainwp_stream'];
}
