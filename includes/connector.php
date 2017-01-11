<?php

abstract class MainWP_WP_Stream_Connector {

	public static $name = null;
	public static $actions = array();
	public static $prev_stream = null;

	public static function register() {
		$class = get_called_class();

		foreach ( $class::$actions as $action ) {
			add_action( $action, array( $class, 'callback' ), null, 5 );
		}

		add_filter( 'mainwp_wp_stream_action_links_' . $class::$name, array( $class, 'action_links' ), 10, 2 );
	}

	public static function callback() {
		$action   = current_filter();
		$class    = get_called_class();
		$callback = array( $class, 'callback_' . str_replace( '-', '_', $action ) );

		// For the sake of testing, trigger an action with the name of the callback
		if ( defined( 'MAINWP_STREAM_TESTS' ) ) {
			do_action( 'mainwp_wp_stream_test_' . $callback[1] );
		}

		// Call the real function
		if ( is_callable( $callback ) ) {
			return call_user_func_array( $callback, func_get_args() );
		}
	}

	public static function action_links( $links, $record ) {
		return $links;
	}

	public static function log( $message, $args, $object_id, $contexts, $user_id = null ) {
		// Prevent inserting Excluded Context & Actions
		foreach ( $contexts as $context => $action ) {
			if ( ! MainWP_WP_Stream_Connectors::is_logging_enabled( 'contexts', $context ) ) {
				unset( $contexts[ $context ] );
			} else {
				if ( ! MainWP_WP_Stream_Connectors::is_logging_enabled( 'actions', $action ) ) {
					unset( $contexts[ $context ] );
				}
			}
		}

		if ( count( $contexts ) == 0 ){
			return ;
		}
		
		$created_timestamp = null;
                
                if (is_array($contexts) && is_array($args)) {      
                    
                    if (isset($contexts['plugins']) && !empty($contexts['plugins']) ) {
                        if (isset($args['slug']) && ( $args['slug'] == 'mainwp-child/mainwp-child.php' || $args['slug'] ==  'mainwp-child-reports/mainwp-child-reports.php' )) {                            
                            $hide_child_plugins = get_option('mainwp_creport_hide_child_plugins', 'yes');
                            if ($hide_child_plugins == 'yes') {
                                return false;
                            } else {
                                $branding_text = MainWP_WP_Stream_Admin::get_branding_title();                                
                                if (!empty($branding_text)) {                
                                    if ($args['slug'] == 'mainwp-child/mainwp-child.php') {
                                        $args['name'] = $branding_text;
                                    } else {
                                        $args['name'] = $branding_text . ' Reports';
                                    }
                                }
                            }
                        }
                    }
                    
                    $created_timestamp = 0;
                    $child_context = '';                    
                    
                    if ( isset($contexts['backwpup_backups']) ) {                            
                        $child_context = 'backwpup_backups';
                    } elseif ( isset($contexts['backupwordpress_backups']) ) {                        
                        $child_context = 'backupwordpress_backups';                            		
                    } elseif ( isset($contexts['backupbuddy_backups']) ) {                        
                        $child_context = 'backupbuddy_backups';                            		
                    } elseif ( isset($contexts['wordfence_scans']) ) {                        
                        $child_context = 'wordfence_scans';                            		
                    } 
                    
                    if ( !empty($child_context) ) {
                        if (is_array($args)) {
                            if (isset($args['backup_time'])) {
                                $created_timestamp = $args['backup_time'];
                            } else if (isset($args['scan_time'])) {
                                $created_timestamp = $args['scan_time'];
                            }
                        }
                        
                        if (empty($created_timestamp) ) 
                            return;                        
                        
                        $saved_item = MainWP_WP_Stream_Log::get_instance()->get_log( array( 'context' => $child_context, 'created' =>  date("Y-m-d H:i:s", $created_timestamp ) ) );
                        
                        if ($saved_item)
                            return;	                    
                    }
                }
		
		$class = get_called_class();
		
		return MainWP_WP_Stream_Log::get_instance()->log(
			$class::$name,
			$message,
			$args,
			$object_id,
			$contexts,
			$user_id,
			$created_timestamp	
		);
	}

	public static function delayed_log( $handle ) {
		$args = func_get_args();

		array_shift( $args );

		self::$delayed[ $handle ] = $args;

		add_action( 'shutdown', array( __CLASS__, 'delayed_log_commit' ) );
	}

	public static function delayed_log_commit() {
		foreach ( self::$delayed as $handle => $args ) {
			call_user_func_array( array( __CLASS__, 'log' ) , $args );
		}
	}

	public static function get_changed_keys( $old_value, $new_value, $deep = false ) {
		if ( ! is_array( $old_value ) && ! is_array( $new_value ) ) {
			return array();
		}

		if ( ! is_array( $old_value ) ) {
			return array_keys( $new_value );
		}

		if ( ! is_array( $new_value ) ) {
			return array_keys( $old_value );
		}

		$diff = array_udiff_assoc(
			$old_value,
			$new_value,
			function( $value1, $value2 ) {
				return maybe_serialize( $value1 ) !== maybe_serialize( $value2 );
			}
		);

		$result = array_keys( $diff );

		// find unexisting keys in old or new value
		$common_keys     = array_keys( array_intersect_key( $old_value, $new_value ) );
		$unique_keys_old = array_values( array_diff( array_keys( $old_value ), $common_keys ) );
		$unique_keys_new = array_values( array_diff( array_keys( $new_value ), $common_keys ) );
		$result = array_merge( $result, $unique_keys_old, $unique_keys_new );

		// remove numeric indexes
		$result = array_filter(
			$result,
			function( $value ) {
				// check if is not valid number (is_int, is_numeric and ctype_digit are not enough)
				return (string) (int) $value !== (string) $value;
			}
		);

		$result = array_values( array_unique( $result ) );

		if ( false === $deep ) {
			return $result; // Return an numerical based array with changed TOP PARENT keys only
		}

		$result = array_fill_keys( $result, null );

		foreach ( $result as $key => $val ) {
			if ( in_array( $key, $unique_keys_old ) ) {
				$result[ $key ] = false; // Removed
			}
			elseif ( in_array( $key, $unique_keys_new ) ) {
				$result[ $key ] = true; // Added
			}
			elseif ( $deep ) { // Changed, find what changed, only if we're allowed to explore a new level
				if ( is_array( $old_value[ $key ] ) && is_array( $new_value[ $key ] ) ) {
					$inner  = array();
					$parent = $key;
					$deep--;
					$changed = self::get_changed_keys( $old_value[ $key ], $new_value[ $key ], $deep );
					foreach ( $changed as $child => $change ) {
						$inner[ $parent . '::' . $child ] = $change;
					}
					$result[ $key ] = 0; // Changed parent which has a changed children
					$result = array_merge( $result, $inner );
				}
			}
		}

		return $result;
	}

}
