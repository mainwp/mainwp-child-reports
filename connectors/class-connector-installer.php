<?php
namespace WP_MainWP_Stream;

class Connector_Installer extends Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'installer';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'upgrader_pre_install', // use to the current version of all plugins, before they are upgraded ( Net-Concept - Xavier NUEL )
		'upgrader_process_complete', // plugins::installed | themes::installed
		'activate_plugin', // plugins::activated
		'deactivate_plugin', // plugins::deactivated
		'switch_theme', // themes::activated
		'delete_site_transient_update_themes', // themes::deleted
		'pre_option_uninstall_plugins', // plugins::deleted
		'pre_set_site_transient_update_plugins',
		'_core_updated_successfully',
		'mainwp_child_installPluginTheme',
		'mainwp_child_plugin_action',
		'mainwp_child_theme_action',
        'mainwp_child_upgradePluginTheme'
	);

	public $old_plugins = array();
	
	/**
	 * Register connector in the WP Frontend
	 *
	 * @var bool
	 */
	public $register_frontend = false;

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html__( 'Installer', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'installed'   => esc_html__( 'Installed', 'mainwp-child-reports' ),
			'activated'   => esc_html__( 'Activated', 'mainwp-child-reports' ),
			'deactivated' => esc_html__( 'Deactivated', 'mainwp-child-reports' ),
			'deleted'     => esc_html__( 'Deleted', 'mainwp-child-reports' ),
			'updated'     => esc_html__( 'Updated', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'plugins'   => esc_html__( 'Plugins', 'mainwp-child-reports' ),
			'themes'    => esc_html__( 'Themes', 'mainwp-child-reports' ),
			'wordpress' => esc_html__( 'WordPress', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}
	 *
	 * @param  array  $links     Previous links registered
	 * @param  object $record    Stream record
	 *
	 * @return array             Action links
	 */
	public function action_links( $links, $record ) {
		if ( 'WordPress' === $record->context && 'updated' === $record->action ) {
			global $wp_version;

			$version = $record->get_meta( 'new_version', true );

			if ( $version === $wp_version ) {
				$links[ esc_html__( 'About', 'mainwp-child-reports' ) ] = admin_url( 'about.php?updated' );
			}

			$links[ esc_html__( 'View Release Notes', 'mainwp-child-reports' ) ] = esc_url( sprintf( 'http://codex.wordpress.org/Version_%s', $version ) );
		}

		return $links;
	}

	/**
	 * Wrapper method for calling get_plugins()
	 *
	 * @return array
	 */
	public function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins();
	}

	/**
	 * Log plugin installations
	 *
	 * @action transition_post_status
	 *
	 * @param \WP_Upgrader $upgrader
	 * @param array $extra
	 *
	 * @return bool
	 */
	public function callback_upgrader_process_complete( $upgrader, $extra ) {
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
		
		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			return false;
		}

		if ( 'install' === $action ) {
			if ( 'plugin' === $type ) {
				$path = $upgrader->plugin_info();

				if ( ! $path ) {
					return false;
				}

				$data    = get_plugin_data( $upgrader->skin->result['local_destination'] . '/' . $path );
				$slug    = $upgrader->result['destination_name'];
				$name    = $data['Name'];
				$version = $data['Version'];
			} else { // theme
				$slug = $upgrader->theme_info();

				if ( ! $slug ) {
					return false;
				}

				wp_clean_themes_cache();

				$theme   = wp_get_theme( $slug );
				$name    = $theme->name;
				$version = $theme->version;
			}

			$action = 'installed';
			// translators: Placeholders refer to a plugin/theme type, a plugin/theme name, and a plugin/theme version (e.g. "plugin", "Stream", "4.2")
			$message = _x(
				'Installed %1$s: %2$s %3$s',
				'Plugin/theme installation. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'mainwp-child-reports'
			);

			$logs[] = compact( 'slug', 'name', 'version', 'message', 'action' );
		} elseif ( 'update' === $action ) {
			$action = 'updated';
			// translators: Placeholders refer to a plugin/theme type, a plugin/theme name, and a plugin/theme version (e.g. "plugin", "Stream", "4.2")
			$message = _x(
				'Updated %1$s: %2$s %3$s',
				'Plugin/theme update. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'mainwp-child-reports'
			);

			if ( 'plugin' === $type ) {
				if ( isset( $extra['bulk'] ) && true === $extra['bulk'] ) {
					$slugs = $extra['plugins'];
				} else {
					$slugs = array( $upgrader->skin->plugin );
				}

				//$_plugins = $this->get_plugins();

				foreach ( $slugs as $slug ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug );
					$name        = $plugin_data['Name'];
					$version     = $plugin_data['Version'];
					//$old_version = $_plugins[ $slug ]['Version'];
					
					//( Net-Concept - Xavier NUEL ) : get old versions
					if (isset($this->old_plugins[$slug])) {
						$old_version = $this->old_plugins[$slug]['Version'];
					} else {
                      //$old_version = ''; // Hummm... will this happen ?
						$old_version = $upgrader->skin->plugin_info['Version']; // to fix old version
					}                    
					
                    if (version_compare($version, $old_version, '>')) {
						$logs[] = compact('slug', 'name', 'old_version', 'version', 'message', 'action');
					}
					

					//$logs[] = compact( 'slug', 'name', 'old_version', 'version', 'message', 'action' );
				}
			} else { // theme
				if ( isset( $extra['bulk'] ) && true === $extra['bulk'] ) {
					$slugs = $extra['themes'];
				} else {
					$slugs = array( $upgrader->skin->theme );
				}

				foreach ( $slugs as $slug ) {
					$theme       = wp_get_theme( $slug );
					$stylesheet  = $theme['Stylesheet Dir'] . '/style.css';
					$theme_data  = get_file_data(
						$stylesheet, array(
							'Version' => 'Version',
						)
					);
					$name        = $theme['Name'];
					$old_version = $theme['Version'];
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

			$this->log(
				$message,
				compact( 'type', 'name', 'version', 'slug', 'success', 'error', 'old_version' ),
				null,
				$context,
				$action
			);
		}

		return true;
	}

	public function callback_activate_plugin( $slug, $network_wide ) {
		$_plugins     = $this->get_plugins();
		$name         = $_plugins[ $slug ]['Name'];
		$network_wide = $network_wide ? esc_html__( 'network wide', 'mainwp-child-reports' ) : null;

		$this->log(
			// translators: Placeholders refer to a plugin name, and whether it is on a single site or network wide (e.g. "Stream", "network wide") (a single site results in a blank string)
			_x(
				'"%1$s" plugin activated %2$s',
				'1: Plugin name, 2: Single site or network wide',
				'mainwp-child-reports'
			),
			compact( 'name', 'network_wide', 'slug' ),
			null,
			'plugins',
			'activated'
		);
	}

	public function callback_deactivate_plugin( $slug, $network_wide ) {
		$_plugins     = $this->get_plugins();		
		$name         = $_plugins[ $slug ]['Name'];
		$network_wide = $network_wide ? esc_html__( 'network wide', 'mainwp-child-reports' ) : null;

		$this->log(
			// translators: Placeholders refer to a plugin name, and whether it is on a single site or network wide (e.g. "Stream", "network wide") (a single site results in a blank string)
			_x(
				'"%1$s" plugin deactivated %2$s',
				'1: Plugin name, 2: Single site or network wide',
				'mainwp-child-reports'
			),
			compact( 'name', 'network_wide', 'slug' ),
			null,
			'plugins',
			'deactivated'
		);
	}

	public function callback_switch_theme( $name, $theme ) {
		unset( $theme );
		$this->log(
			// translators: Placeholder refers to a theme name (e.g. "Twenty Seventeen")
			__( '"%s" theme activated', 'mainwp-child-reports' ),
			compact( 'name' ),
			null,
			'themes',
			'activated'
		);
	}

	/**
	 * @todo Core needs a delete_theme hook
	 */
	public function callback_delete_site_transient_update_themes() {
		$backtrace = debug_backtrace(); // @codingStandardsIgnoreLine This is used as a hack to determine a theme was deleted.
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
		// @todo Can we get the name of the theme? Or has it already been eliminated

		$this->log(
			// translators: Placeholder refers to a theme name (e.g. "Twenty Seventeen")
			__( '"%s" theme deleted', 'mainwp-child-reports' ),
			compact( 'name' ),
			null,
			'themes',
			'deleted'
		);
	}

	/**
	 * @todo Core needs an uninstall_plugin hook
	 * @todo This does not work in WP-CLI
	 */
	public function callback_pre_option_uninstall_plugins() {
		if (
			'delete-selected' !== wp_mainwp_stream_filter_input( INPUT_GET, 'action' )
			&&
			'delete-selected' !== wp_mainwp_stream_filter_input( INPUT_POST, 'action2' )
		) {
			return false;
		}

		// @codingStandardsIgnoreStart
		$type = isset( $_POST['action2'] ) ? INPUT_POST : INPUT_GET;
		// @codingStandardsIgnoreEnd

		$plugins  = wp_mainwp_stream_filter_input( $type, 'checked' );
		$_plugins = $this->get_plugins();

		$plugins_to_delete = array();

		foreach ( (array) $plugins as $plugin ) {
			$plugins_to_delete[ $plugin ] = $_plugins[ $plugin ];
		}

		update_option( 'wp_mainwp_stream_plugins_to_delete', $plugins_to_delete );

		return false;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 * @todo Core needs a delete_plugin hook
	 * @todo This does not work in WP-CLI
	 */
	public function callback_pre_set_site_transient_update_plugins( $value ) {
		$plugins_to_delete = get_option( 'wp_mainwp_stream_plugins_to_delete' );
		if ( ! wp_mainwp_stream_filter_input( INPUT_POST, 'verify-delete' ) || ! $plugins_to_delete ) {
			return $value;
		}

		foreach ( $plugins_to_delete as $plugin => $data ) {
			$name         = $data['Name'];
			$network_wide = $data['Network'] ? esc_html__( 'network wide', 'mainwp-child-reports' ) : '';

			$this->log(
				// translators: Placeholder refers to a plugin name (e.g. "Stream")
				__( '"%s" plugin deleted', 'mainwp-child-reports' ),
				compact( 'name', 'plugin', 'network_wide' ),
				null,
				'plugins',
				'deleted'
			);
		}

		delete_option( 'wp_mainwp_stream_plugins_to_delete' );

		return $value;
	}

	public function callback__core_updated_successfully( $new_version ) {
		global $pagenow, $wp_version;

		$old_version  = $wp_version;
		$auto_updated = ( 'update-core.php' !== $pagenow );

		if ( $auto_updated ) {
			// translators: Placeholder refers to a version number (e.g. "4.2")
			$message = esc_html__( 'WordPress auto-updated to %s', 'mainwp-child-reports' );
		} else {
			// translators: Placeholder refers to a version number (e.g. "4.2")
			$message = esc_html__( 'WordPress updated to %s', 'mainwp-child-reports' );
		}

		$this->log(
			$message,
			compact( 'new_version', 'old_version', 'auto_updated' ),
			null,
			'WordPress',
			'updated'
		);
	}
	
	public function callback_mainwp_child_installPluginTheme( $args ) {
		
		$logs = array();
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
			$this->log(
				$message,
				compact( 'type', 'name', 'version', 'slug', 'success', 'error', 'old_version' ),
				null,
				$context,
				$action				
			);
		}
	}       
        
        
	public function callback_mainwp_child_plugin_action( $args ) {	
		if (!is_array($args) || !isset($args['action']))
			return;            
            $action = $args['action'];
            if ($action == 'delete') {
                $name         = $args['Name'];
                $network_wide =  '';
                $this->log(
					__( '"%s" plugin deleted', 'mainwp-child-reports' ),
					compact( 'name', 'plugin' ),
					null,
					'plugins',
					'deleted'                        
                );
            }
	}
        
    public function callback_mainwp_child_theme_action($args) {
            if (!is_array($args) || !isset($args['action']))
                return;
            $action = $args['action'];
            $name = $args['Name'];
            if ($action == 'delete') {
                $this->log(
					__( '"%s" theme deleted', 'mainwp-child-reports' ),
					compact( 'name' ),
					null,
					'themes',
					'deleted'                        
                );
            }
	}
 
	// ( Net-Concept - Xavier NUEL ) : save all plugins versions before upgrade
	public function callback_upgrader_pre_install() {        
		$this->old_plugins = $this->get_plugins();        
	}
	
    public function callback_mainwp_child_upgradePluginTheme( $extra ) {
		$logs    = array();
		
		if ( ! isset( $extra['type'] ) ) {
			return false;
		}

		$type   = $extra['type'];
		$action = $extra['action'];

		if ( ! in_array( $type, array( 'plugin', 'theme' ) ) ) {
			return;
		}

		if ( 'update' === $action ) {
            if ( 'plugin' === $type ) {				
				$slug    = $extra['slug'];
				$name    = $extra['name'];
				$version = $extra['version'];  
                $old_version = $extra['old_version'];
			} else { // theme
				$name    = $extra['name'];
				$version = $extra['version'];
                $old_version = $extra['old_version'];
			}
            
			$action  = 'updated';
			$message = _x(
				'Updated %1$s: %2$s %3$s',
				'Plugin/theme update. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'mainwp_child_reports'
			);
            $logs[] = compact( 'slug', 'name', 'old_version', 'version', 'message', 'action' );			
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
			$this->log(
				$message,
				compact( 'type', 'name', 'version', 'slug', 'success', 'error', 'old_version' ),
				null,
				$context,
				$action				
			);
		}
	}

	
}
