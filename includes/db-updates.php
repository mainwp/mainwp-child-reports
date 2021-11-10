<?php
/**
 * MainWP WP Stream Database Update Version 3.5.2
 *
 * To fix meta data.
 */

/**
 * Version 3.0.8
 *
 * Force update for older versions to call \dbdelta in install() method to fix column widths.
 *
 * @param string $db_version
 * @param string $current_version
 *
 * @return string
 */
function wp_mainwp_stream_update_auto_308( $db_version, $current_version ) {
	$plugin = wp_mainwp_stream_get_instance();
	$plugin->install->install( $current_version );

	return $current_version;
}

