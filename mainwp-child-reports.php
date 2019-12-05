<?php
/*
  Plugin Name: MainWP Child Reports
  Plugin URI: https://mainwp.com/
  Description: The MainWP Child Report plugin tracks Child sites for the MainWP Client Reports Extension. The plugin is only useful if you are using MainWP and the Client Reports Extension.
  Author: MainWP
  Author URI: https://mainwp.com
  Version: 1.9.3
 */

/**
 * Copyright (c) 2015 XWP.Co Pty Ltd. (https://xwp.co/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

if ( ! version_compare( PHP_VERSION, '5.3', '>=' ) ) {
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


