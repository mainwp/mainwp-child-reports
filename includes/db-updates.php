<?php
/**
 * MainWP WP Stream Database Update Version 3.5.2
 *
 * To fix meta data.
 */

/**
 * MainWP WP Stream Database Update Version 3.5.2
 *
 * @param string $db_version New Database version.
 * @param string $current_version Current Database version.
 *
 * @return string $current_version Current WP Stream Database version.
 */
function wp_mainwp_stream_update_auto_352( $db_version, $current_version ) {

    /** @global object $wpdb WordPress Database objcet. */
	global $wpdb;	
	
	$first_correct = $wpdb->get_results(			
			  " SELECT sm.record_id, sm.meta_id
				FROM {$wpdb->prefix}mainwp_stream as s 
				LEFT JOIN {$wpdb->prefix}mainwp_stream_meta as sm
				ON s.ID = sm.record_id
				where sm.meta_key = 'user_meta'
				ORDER BY s.ID ASC LIMIT 1 "
			);
						
	$first_correct_record_id = 0;	
	if ( $first_correct ) {
		$first_correct = current( $first_correct );
		$first_correct_record_id = $first_correct->record_id;		
	}
		
	if ( empty( $first_correct_record_id ) ) {
		return; // this is correct conversion
	}		
	
	// First get correct meta id.
	$sql = $wpdb->prepare( 
		"SELECT * FROM {$wpdb->base_prefix}mainwp_stream_meta 
		WHERE record_id = %d ORDER BY meta_id ASC LIMIT 1", 
		$first_correct_record_id 
	);				
	
	$first_correct_meta_id = 0;
	
	$correct_meta = $wpdb->get_results( $sql );
	if ( $correct_meta ) {
		$correct_meta = current( $correct_meta );
		$first_correct_meta_id = $correct_meta->meta_id;		
	}		
		
	//$date = new DateTime( '2019-8-31 23:59:59', $timezone = new DateTimeZone( 'UTC' ) ); // fixed value, around 3 months ago			
	//$where = " AND ( `created` > STR_TO_DATE(" . $wpdb->prepare('%s', $date->format( 'Y-m-d H:i:s' )) . ", '%Y-%m-%d %H:%i:%s') ) ";	
	$where = " AND ID < " . intval( $first_correct_record_id );	
	$orderby = ' ORDER BY ID DESC ';  // DESC importance
	 
	$starting_row   = 0;
	$rows_per_round = 5000;	
	
	$stream_entries = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}mainwp_stream WHERE 1 = 1 " . 
    $where . $orderby . $wpdb->prepare( " LIMIT %d, %d", $starting_row, $rows_per_round ) );
	$stop = false;

	$fix_what = array(		
		'plugin_updated',
		'plugin_activated_deactivated',
		'theme_updated',
		'theme_activated_deactivated',
		'wordpress_update',		
		'post_page',
		'mainwp_backups',		
		'wordfence_scan',
		'sucuri_scan'
	);

	while ( ! empty( $stream_entries ) ) {
		
		foreach( $fix_what as $fix_value ) {			
			$correct_meta_id = $first_correct_meta_id;		
									
			foreach ( $stream_entries as $entry ) {
				
				if ( $fix_value == 'plugin_updated' ) {
					if ( $entry->connector != 'installer' || $entry->context != 'plugins' || $entry->action !== 'updated') {						
						continue; // next entry
					}
				} else if ( $fix_value == 'plugin_activated_deactivated' ) {
					if ( $entry->connector != 'installer' || $entry->context != 'plugins' || ( $entry->action !== 'activated' && $entry->action !== 'deactivated' ) ) {						
						continue; // next entry
					}
				} else if ( $fix_value == 'theme_updated' ) {
					if ( $entry->connector != 'installer' || $entry->context !== 'themes' || $entry->action !== 'updated' ) {						
						continue; // next entry
					}
				} else if ( $fix_value == 'theme_activated_deactivated' ) {
					if ( $entry->connector != 'installer' || $entry->context != 'themes' || ( $entry->action !== 'activated' && $entry->action !== 'deactivated' ) ) {						
						continue; // next entry
					}
				} else if ($fix_value == 'wordpress_update') {
					if ( $entry->connector != 'installer' || $entry->context !== 'wordpress' || $entry->action != 'updated' ) {						
						continue; // next entry
					}
				} else if ($fix_value == 'post_page') {
					if ( $entry->connector != 'posts' || ( $entry->context !== 'post' && $entry->context !== 'page' ) ) {						
						continue; // next entry
					}
				} else if ($fix_value == 'mainwp_backups') {
					if ( $entry->connector != 'mainwp_backups' || $entry->context != 'backups' ) {						
						continue; // next entry
					}
				} else if ($fix_value == 'wordfence_scan') {
					if ( $entry->context !== 'wordfence_scan' ) {						
						continue; // next entry
					}
				} else if ($fix_value == 'sucuri_scan') {
					if ( $entry->context !== 'sucuri_scan' ) {						
						continue; // next entry
					}
				}
				
				$fix_next_type = false;
				
				// loop meta records
				while ( true ) {					
					
					$fix_next_entry = false;
					
					$sql = $wpdb->prepare( 
						"SELECT * FROM {$wpdb->base_prefix}mainwp_stream_meta 
						WHERE meta_id < %d ORDER BY meta_id DESC LIMIT 20", // get 20 meta items to fix 
						$correct_meta_id 
					);			
					
					$incorrect_metas = $wpdb->get_results( $sql );
					
					if ( empty( $incorrect_metas ) ) {
						
						// valid meta, fix next type	
						$fix_next_type = true;
						break;
					}

					foreach( $incorrect_metas as $incorr) {		
						// guess to fix record_id  
						$update_it = false;						
						if ( $fix_value == 'plugin_updated' ) {							
							if ( $incorr->meta_key == 'type' && $incorr->meta_value === 'plugin') {
								$update_it = true;
							}
						} else if ( $fix_value == 'plugin_activated_deactivated' ) {							
							if ( $incorr->meta_key == 'type' && $incorr->meta_value === 'plugin') { 
								$update_it = true;
							}
						} else if ( $fix_value == 'theme_updated' ) {							
							if ( $incorr->meta_key == 'type' && $incorr->meta_value === 'theme' ) {
								$update_it = true;
							}
						} else if ( $fix_value == 'theme_activated_deactivated' ) {							
							if ( $incorr->meta_key == 'type' && $incorr->meta_value === 'theme') { 
								$update_it = true;
							}
						} else if ($fix_value == 'wordpress_update') {
							if ( $incorr->meta_key == 'auto_updated' ) {
								$update_it = true;
							}							
						} else if ($fix_value == 'post_page') {
							if ( $incorr->meta_key == 'singular_name' && ( $incorr->meta_value == 'page' || $incorr->meta_value == 'post' ) ) {
								$update_it = true;
							}							
						} else if ($fix_value == 'mainwp_backups') {
							if ( $incorr->meta_key == 'backup_time' ) {
								$update_it = true;
							}
						} else if ($fix_value == 'wordfence_scan') {
							if ( $incorr->meta_key == 'result' && strpos( $incorr->meta_value, 'SUM_FINAL' ) !== false ) {
								$update_it = true;
							}
						} else if ($fix_value == 'sucuri_scan') {
							if ( $incorr->meta_key == 'scan_status' ) {
								$update_it = true;
							}
						}
						
						// it's ok
						$correct_meta_id -= 1;  	
						
						if ( $update_it ) {			
														
							$sql = $wpdb->prepare( 
								"SELECT * FROM {$wpdb->base_prefix}mainwp_stream_meta 
								WHERE record_id = %d AND meta_id < %d ",  
								$incorr->record_id, $incorr->meta_id + 10 // + 10 to sure it does not update fixed meta
							);
								
							$fix_metas = $wpdb->get_results( $sql );		
								
							if ( $fix_metas ) {
								// verify one more time
								if ( $fix_value == 'plugin_activated_deactivated' || $fix_value == 'theme_activated_deactivated' ) {							
									if ( count($fix_metas) > 3 )
										continue;
								}		
								
								if ( count($fix_metas) > 20 ) //not valid
										continue;
								
								$fixed_ids = array();
								foreach( $fix_metas as $item ) {
									$fixed_ids[] = $item->meta_id;
								}		
								
								if ($fixed_ids) {		
									$wpdb->query( 'UPDATE ' . $wpdb->base_prefix . 'mainwp_stream_meta' . ' SET record_id=' .  $entry->ID  . ' WHERE meta_id IN (' . implode( ",", $fixed_ids) . ')');
								}															
							}
								
							// found the correct meta so go to fix next entry
							$fix_next_entry = true;
							break;
						}
					}		
					
					$wpdb->flush();
					
					if ( $fix_next_entry )
						break;	
					
				}
				
				if ( $fix_next_type ) {
					break; // to fix next type
				}
			}
		}
		
		$starting_row += $rows_per_round;				
		if ( !$stop )
			$stream_entries = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}mainwp_stream WHERE 1 = 1 " . $where . $orderby . $wpdb->prepare( "LIMIT %d, %d", $starting_row, $rows_per_round ) );
	}	
	
	return $current_version;	
}

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
			
			if (empty($context)) {				
				continue;
			}
			
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
