<?php
/**
 * Version 3.5.0
 *
 * To fix connector.
 *
 * @param string $db_version
 * @param string $current_version
 *
 * @return string
 */
function wp_mainwp_stream_update_auto_350($db_version, $current_version ) {
	
	global $wpdb;
	
	$stream_entries = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}mainwp_stream" );
	foreach ( $stream_entries as $entry ) {
		$connector = $entry->connector;	
		$context = $entry->context;	
		$action = $entry->action;	
		
		if ( in_array( $connector, array('updraftplus_backups', 'backupbuddy_backups', 'backupwordpress_backups', 'backwpup_backups', 'wptimecapsule_backups') )) {			
			$context = 'backups';
			$action = rtrim($connector, 's');
			$connector = 'mainwp_backups';
		} 
		else  if ($connector == 'mainwp_maintenance') 
		{
			$action = 'maintenance';
			$context = 'mainwp_maintenance';						
		}
		else  if ($connector == 'mainwp_sucuri') 
		{
			$action = 'sucuri_scan';
			$context = 'sucuri_scan';						
		}
		else  if ($connector == 'wordfence_scan') 
		{
			$action = 'wordfence_scan';
			$context = 'wordfence_scan';						
			$connector = 'mainwp_wordfence';
		}		
		else 
		{
			continue;
		}
		
		$wpdb->update(
			$wpdb->base_prefix . 'mainwp_stream', array(
				'connector' => $connector,
				'context'   => $context,
				'action'    => $action,
			), array(
				'ID' => $entry->ID,
			)
		);
		
	}

	return $current_version;
	
}

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

/**
 * Version 3.0.2
 *
 * @param string $db_version
 * @param string $current_version
 *
 * @return string
 */
function wp_mainwp_stream_update_302( $db_version, $current_version ) {
	global $wpdb;

	$stream_entries = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}mainwp_stream" );
	foreach ( $stream_entries as $entry ) {
		$class = 'Connector_' . $entry->context;
		if ( class_exists( $class ) ) {
			$connector = new $class();
			$wpdb->update(
				$wpdb->base_prefix . 'mainwp_stream', array(
					'connector' => $connector->name,
				), array(
					'ID' => $entry->ID,
				)
			);
		} else {			
			$wpdb->update(
				$wpdb->base_prefix . 'mainwp_stream', array(
					'connector' => strtolower( $entry->connector ),
				), array(
					'ID' => $entry->ID,
				)
			);
		}
	}

	return $current_version;
}

/**
 * Version 3.0.0
 *
 * Update from 1.4.9
 *
 * @param string $db_version
 * @param string $current_version
 *
 * @return string
 */
function wp_mainwp_stream_update_auto_300( $db_version, $current_version ) {	
	global $wpdb;

	// Get only the author_meta values that are double-serialized
	$wpdb->query( "RENAME TABLE {$wpdb->base_prefix}mainwp_stream TO {$wpdb->base_prefix}mainwp_stream_tmp, {$wpdb->base_prefix}mainwp_stream_context TO {$wpdb->base_prefix}mainwp_stream_context_tmp" );

	$plugin = wp_mainwp_stream_get_instance();
	$plugin->install->install( $current_version );
	
	$date = new DateTime( '2019-8-31 23:59:59', $timezone = new DateTimeZone( 'UTC' ) ); // fixed value, around 3 months ago	
		
	$where = " AND `created` > STR_TO_DATE(" . $wpdb->prepare('%s', $date->format( 'Y-m-d H:i:s' )) . ", '%Y-%m-%d %H:%i:%s') ";	
	$orderby = ' ORDER BY ID ASC '; // ASC importance
	 
	$starting_row   = 0;
	$rows_per_round = 5000;	
	
	$stream_entries = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}mainwp_stream_tmp WHERE 1 = 1 " . $where . $orderby . $wpdb->prepare( " LIMIT %d, %d", $starting_row, $rows_per_round ) );

	while ( ! empty( $stream_entries ) ) {
		foreach ( $stream_entries as $entry ) {
			$context = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}mainwp_stream_context_tmp WHERE record_id = %s LIMIT 1", $entry->ID )
			);
			$new_entry = array(
				'site_id'   => $entry->site_id,
				'blog_id'   => $entry->blog_id,
				'user_id'   => $entry->author,
				'user_role' => $entry->author_role,
				'summary'   => $entry->summary,
				'created'   => $entry->created,
				'connector' => $context->connector,
				'context'   => $context->context,
				'action'    => $context->action,
				'ip'        => $entry->ip,
			);

			if ( $entry->object_id && 0 !== $entry->object_id ) {
				$new_entry['object_id'] = $entry->object_id;
			}
			
			$wpdb->insert( $wpdb->base_prefix . 'mainwp_stream', $new_entry );			
			
			if ( $new_insert_id = $wpdb->insert_id ) {						
				$wpdb->update(
					$wpdb->base_prefix . 'mainwp_stream_meta', array(
						'record_id' => $new_insert_id,						
					), array(
						'record_id' => $entry->ID,
					)
				);								
			}
		}
		
		$starting_row += $rows_per_round;

		$stream_entries = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}mainwp_stream_tmp WHERE 1 = 1 " . $where . $orderby . $wpdb->prepare( "LIMIT %d, %d", $starting_row, $rows_per_round ) );
	}
	//$wpdb->query( "DROP TABLE {$wpdb->base_prefix}mainwp_stream_tmp, {$wpdb->base_prefix}mainwp_stream_context_tmp" );
	return $current_version;
}
