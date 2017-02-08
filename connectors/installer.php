<?php

class MainWP_WP_Stream_Connector_Installer extends MainWP_WP_Stream_Connector {

	public static $name = 'installer';

	public static $actions = array(
		'upgrader_process_complete', // plugins::installed | themes::installed
		'activate_plugin', // plugins::activated
		'deactivate_plugin', // plugins::deactivated
		'switch_theme', // themes::activated
		'delete_site_transient_update_themes', // themes::deleted
		'pre_option_uninstall_plugins', // plugins::deleted
		'pre_set_site_transient_update_plugins',
		'wp_redirect',
		'_core_updated_successfully',
		'mainwp_child_installPluginTheme',
		'mainwp_child_plugin_action',
		'mainwp_child_theme_action'
	);

	public static function get_label() {
		return __( 'Installer', 'mainwp-child-reports' );
	}

	public static function get_action_labels() {
		return array(
			'installed'   => __( 'Installed', 'mainwp-child-reports' ),
			'activated'   => __( 'Activated', 'mainwp-child-reports' ),
			'deactivated' => __( 'Deactivated', 'mainwp-child-reports' ),
			'deleted'     => __( 'Deleted', 'mainwp-child-reports' ),
			'edited'      => __( 'Edited', 'mainwp-child-reports' ),
			'updated'     => __( 'Updated', 'mainwp-child-reports' ),
		);
	}

	public static function get_context_labels() {
		return array(
			'plugins'   => __( 'Plugins', 'default' ),
			'themes'    => __( 'Themes', 'default' ),
			'wordpress' => __( 'WordPress', 'default' ),
		);
	}

	public static function action_links( $links, $record ) {
		if ( 'wordpress' === $record->context && 'updated' === $record->action ) {
			global $wp_version;
			$version = mainwp_wp_stream_get_meta( $record->ID, 'new_version', true );
			if ( $version === $wp_version ) {
				$links[ __( 'About', 'mainwp-child-reports' ) ] = admin_url( 'about.php?updated' );
			}
			$links[ __( 'View Release Notes', 'mainwp-child-reports' ) ] = esc_url( sprintf( 'http://codex.wordpress.org/Version_%s', $version ) );
		}
		return $links;
	}        
 
        
        public static function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins();
	}
        
        
        public static function callback_mainwp_child_installPluginTheme($args ) {                
                $logs    = array();
		$success = isset($args['success']) ? $args['success'] : 0;
		$error   = null;

		if ( ! $success ) {
			$errors = $args['errors'];;
		}

		// This would have failed down the road anyway
		if ( ! isset( $args['type'] ) ) {
			return false;
		}

		$type   = $args['type'];
		$action = $args['action'];

		if ( ! in_array( $type, array( 'plugin', 'theme' ) ) ) {
			return;
		}

		if ( 'install' === $action ) {
			if ( 'plugin' === $type) {				
                                if ( !isset($args['Name']) || empty($args['Name']))
                                    return;
				$slug    = $args['slug'];
				$name    = $args['Name'];
				$version = $args['Version'];
			} else { // theme
				$slug    = $args['slug'];
				if ( ! $slug ) {
					return;
				}
				wp_clean_themes_cache();
				$theme   = wp_get_theme( $slug );
				$name    = $theme->name;
				$version = $theme->version;
			}
			$action  = 'installed';
			$message = _x(
				'Installed %1$s: %2$s %3$s',
				'Plugin/theme installation. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'mainwp_child_reports'
			);
			$logs[]  = compact( 'slug', 'name', 'version', 'message', 'action' );
		} else {
			return false;
		}

		$context = $type . 's';

		foreach ( $logs as $log ) {
			$name        = isset( $log['name'] ) ? $log['name'] : null;
			$version     = isset( $log['version'] ) ? $log['version'] : null;
			$slug        = isset( $log['slug'] ) ? $log['slug'] : null;
			$old_version = isset( $log['old_version'] ) ? $log['old_version'] : null;
			$message     = isset( $log['message'] ) ? $log['message'] : null;
			$action      = isset( $log['action'] ) ? $log['action'] : null;
			self::log(
				$message,
				compact( 'type', 'name', 'version', 'slug', 'success', 'error', 'old_version' ),
				null,
				array( $context => $action )
			);
		}
        }       
        
        
        public static function callback_mainwp_child_plugin_action( $args ) {	
            if (!is_array($args) || !isset($args['action']))
                return;            
            $action = $args['action'];
            if ($action == 'delete') {
                $name         = $args['Name'];
                $network_wide =  '';
                self::log(
                        __( '"%s" plugin deleted', 'mainwp-child-reports' ),
                        compact( 'name', 'plugin', 'network_wide' ),
                        null,
                        array( 'plugins' => 'deleted' )
                );
            }
	}
        
        public static function callback_mainwp_child_theme_action($args) {
            if (!is_array($args) || !isset($args['action']))
                return;
            $action = $args['action'];
            $name = $args['Name'];
            if ($action == 'delete') {
                self::log(
                        __( '"%s" theme deleted', 'mainwp-child-reports' ),
                        compact( 'name' ),
                        null,
                        array( 'themes' => 'deleted' )
                );
            }
	}
        
	public static function callback_upgrader_process_complete( $upgrader, $extra ) {
		$logs    = array();
		$success = ! is_wp_error( $upgrader->skin->result );
		$error   = null;

		if ( ! $success ) {
			$errors = $upgrader->skin->result->errors;
			list( $error ) = reset( $errors );
		}

		// This would have failed down the road anyway
		if ( ! isset( $extra['type'] ) ) {
			return false;
		}

		$type   = $extra['type'];
		$action = $extra['action'];

		if ( ! in_array( $type, array( 'plugin', 'theme' ) ) ) {
			return;
		}

		if ( 'install' === $action ) {
			if ( 'plugin' === $type ) {
				$path = $upgrader->plugin_info();
				if ( ! $path ) {
					return;
				}
				$data    = get_plugin_data( $upgrader->skin->result['local_destination'] . '/' . $path );
				$slug    = $upgrader->result['destination_name'];
				$name    = $data['Name'];
				$version = $data['Version'];
			} else { // theme
				$slug = $upgrader->theme_info();
				if ( ! $slug ) {
					return;
				}
				wp_clean_themes_cache();
				$theme   = wp_get_theme( $slug );
				$name    = $theme->name;
				$version = $theme->version;
			}
			$action  = 'installed';
			$message = _x(
				'Installed %1$s: %2$s %3$s',
				'Plugin/theme installation. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'mainwp_child_reports'
			);
			$logs[]  = compact( 'slug', 'name', 'version', 'message', 'action' );
		} elseif ( 'update' === $action ) {
			$action  = 'updated';
			$message = _x(
				'Updated %1$s: %2$s %3$s',
				'Plugin/theme update. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'mainwp_child_reports'
			);
			if ( 'plugin' === $type ) {
				if ( isset( $extra['bulk'] ) && true == $extra['bulk'] ) {
					$slugs = $extra['plugins'];
				} else {
					$slugs = array( $upgrader->skin->plugin );
				}
                                                                
				foreach ( $slugs as $slug ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug );
					$name        = $plugin_data['Name'];
					$version     = $plugin_data['Version'];
					$old_version = $upgrader->skin->plugin_info['Version']; // to fix old version 
					$logs[] = compact( 'slug', 'name', 'old_version', 'version', 'message', 'action' );
				}
			} else { // theme
				if ( isset( $extra['bulk'] ) && true == $extra['bulk'] ) {
					$slugs = $extra['themes'];
				} else {
					$slugs = array( $upgrader->skin->theme );
				}
				foreach ( $slugs as $slug ) {
					$theme       = wp_get_theme( $slug );
					$stylesheet  = $theme['Stylesheet Dir'] . '/style.css';
					$theme_data  = get_file_data( $stylesheet, array( 'Version' => 'Version' ) );
					$name        = $theme['Name'];
                                        $old_version = $upgrader->skin->theme_info->get('Version'); // to fix old version  //$theme['Version'];
					$version     = $theme_data['Version'];

					$logs[] = compact( 'slug', 'name', 'old_version', 'version', 'message', 'action' );
				}                                                             
			}
		} else {
			return false;
		}

		$context = $type . 's';

		foreach ( $logs as $log ) {
			$name        = isset( $log['name'] ) ? $log['name'] : null;
			$version     = isset( $log['version'] ) ? $log['version'] : null;
			$slug        = isset( $log['slug'] ) ? $log['slug'] : null;
			$old_version = isset( $log['old_version'] ) ? $log['old_version'] : null;
			$message     = isset( $log['message'] ) ? $log['message'] : null;
			$action      = isset( $log['action'] ) ? $log['action'] : null;
			self::log(
				$message,
				compact( 'type', 'name', 'version', 'slug', 'success', 'error', 'old_version' ),
				null,
				array( $context => $action )
			);
		}
	}

	public static function callback_activate_plugin( $slug, $network_wide ) {
		$plugins      = self::get_plugins();                
		$name         = $plugins[ $slug ]['Name'];
		$network_wide = $network_wide ? __( 'network wide', 'mainwp-child-reports' ) : null;                
		self::log(
			_x(
				'"%1$s" plugin activated %2$s',
				'1: Plugin name, 2: Single site or network wide',
				'mainwp_child_reports'
			),
			compact( 'name', 'network_wide', 'slug' ),
			null,
			array( 'plugins' => 'activated' )
		);
	}

	public static function callback_deactivate_plugin( $slug, $network_wide ) {
		$plugins      = self::get_plugins();
		$name         = $plugins[ $slug ]['Name'];
		$network_wide = $network_wide ? __( 'network wide', 'mainwp-child-reports' ) : null;
		self::log(
			_x(
				'"%1$s" plugin deactivated %2$s',
				'1: Plugin name, 2: Single site or network wide',
				'mainwp_child_reports'
			),
			compact( 'name', 'network_wide', 'slug' ),
			null,
			array( 'plugins' => 'deactivated' )
		);
	}

	public static function callback_switch_theme( $name, $theme ) {
		$stylesheet = $theme->get_stylesheet();

		self::log(
			__( '"%s" theme activated', 'mainwp-child-reports' ),
			compact( 'name' ),
			null,
			array( 'themes' => 'activated' )
		);
	}

	public static function callback_delete_site_transient_update_themes() {

		$backtrace = debug_backtrace();
		$delete_theme_call = null;
		foreach ( $backtrace as $call ) {
			if ( isset( $call['function'] ) && 'delete_theme' === $call['function'] ) {
				$delete_theme_call = $call;
				break;
			}
		}

		if ( empty( $delete_theme_call ) ) {
			return;
		}

		$name = $delete_theme_call['args'][0];

		self::log(
			__( '"%s" theme deleted', 'mainwp-child-reports' ),
			compact( 'name' ),
			null,
			array( 'themes' => 'deleted' )
		);
	}

	public static function callback_pre_option_uninstall_plugins() {
		global $plugins;

		if ( 'delete-selected' !== mainwp_wp_stream_filter_input( INPUT_GET, 'action' ) && 'delete-selected' !== mainwp_wp_stream_filter_input( INPUT_POST, 'action2' ) ) {
			return false;
		}

		$_plugins = self::get_plugins();

		foreach ( $plugins as $plugin ) {
			$plugins_to_delete[ $plugin ] = $_plugins[ $plugin ];
		}

		update_option( 'mainwp_wp_stream_plugins_to_delete', $plugins_to_delete );

		return false;
	}

	public static function callback_pre_set_site_transient_update_plugins( $value ) {
		if ( ! mainwp_wp_stream_filter_input( INPUT_POST, 'verify-delete' ) || ! ( $plugins_to_delete = get_option( 'mainwp_wp_stream_plugins_to_delete' ) ) ) {
			return $value;
		}

		foreach ( $plugins_to_delete as $plugin => $data ) {
			$name         = $data['Name'];
			$network_wide = $data['Network'] ? __( 'network wide', 'mainwp-child-reports' ) : '';

			self::log(
				__( '"%s" plugin deleted', 'mainwp-child-reports' ),
				compact( 'name', 'plugin', 'network_wide' ),
				null,
				array( 'plugins' => 'deleted' )
			);
		}

		delete_option( 'mainwp_wp_stream_plugins_to_delete' );

		return $value;
	}

	public static function callback_wp_redirect( $location ) {
		if ( ! preg_match( '#(plugin)-editor.php#', $location, $match ) ) {
			return $location;
		}

		$type = $match[1];

		list( $url, $query ) = explode( '?', $location );

		$query = wp_parse_args( $query );
		$file  = $query['file'];

		if ( empty( $query['file'] ) ) {
			return $location;
		}

		if ( 'theme' === $type ) {
			if ( empty( $query['updated'] ) ) {
				return $location;
			}
			$theme = wp_get_theme( $query['theme'] );
			$name  = $theme['Name'];
		}
		elseif ( 'plugin' === $type ) {
			global $plugin, $plugins;
			$plugin_base = current( explode( '/', $plugin ) );
			foreach ( $plugins as $key => $plugin_data ) {
				if ( $plugin_base === current( explode( '/', $key ) ) ) {
					$name = $plugin_data['Name'];
					break;
				}
			}
		}

		self::log(
			_x(
				'Edited %1$s: %2$s',
				'Plugin/theme editing. 1: Type (plugin/theme), 2: Plugin/theme name',
				'mainwp_child_reports'
			),
			compact( 'type', 'name', 'file' ),
			null,
			array( $type . 's' => 'edited' )
		);

		return $location;
	}

	public static function callback__core_updated_successfully( $new_version ) {
		global $pagenow, $wp_version;

		$old_version  = $wp_version;
		$auto_updated = ( 'update-core.php' !== $pagenow && !isset($_POST['mainwpsignature']));

		if ( $auto_updated ) {
			$message = __( 'WordPress auto-updated to %s', 'mainwp-child-reports' );
		} else {
			$message = __( 'WordPress updated to %s', 'mainwp-child-reports' );
		}

		self::log(
			$message,
			compact( 'new_version', 'old_version', 'auto_updated' ),
			null,
			array( 'wordpress' => 'updated' )
		);
	}

}
