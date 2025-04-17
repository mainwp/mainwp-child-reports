<?php
/** Installer Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_Installer
 *
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_Installer extends Connector {

	/** @var string Connector slug. */
	public $name = 'installer';

	/** @var array Actions registered for this connector. */
	public $actions = array(
		'upgrader_pre_install', // use to the current version of all plugins, before they are upgraded ( Net-Concept - Xavier NUEL )
		'upgrader_process_complete', // plugins::installed | themes::installed
		'activate_plugin', // plugins::activated
		'deactivate_plugin', // plugins::deactivated
		'switch_theme', // themes::activated
		'delete_site_transient_update_themes', // themes::deleted
		'pre_option_uninstall_plugins', // plugins::deleted
		'deleted_plugin',
		// 'pre_set_site_transient_update_plugins',
		'_core_updated_successfully',
		'mainwp_child_installPluginTheme',
		'mainwp_child_plugin_action',
		'mainwp_child_theme_action',
		'automatic_updates_complete',
	);

	/** @var array Old plugins array. */
	public $current_plugins_info = array();

	public $current_themes_info = array();

	/** @var bool Register connector in the WP Frontend. */
	public $register_frontend = false;

	public $register_cron = true;

	public $register_cli = true;

	/**
	 * Return translated connector label.
	 *
	 * @return string Translated connector label.
	 */
	public function get_label() {
		return esc_html__( 'Installer', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array Action label translations.
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
	 * Return translated context labels.
	 *
	 * @return array Context label translations.
	 */
	public function get_context_labels() {
		return array(
			'plugins'   => esc_html__( 'Plugins', 'mainwp-child-reports' ),
			'themes'    => esc_html__( 'Themes', 'mainwp-child-reports' ),
			'wordpress' => esc_html__( 'WordPress', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen.
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}.
	 *
	 * @param  array  $links     Previous links registered.
	 * @param  object $record    Stream record.
	 *
	 * @return array             Action links.
	 */
	public function action_links( $links, $record ) {
		if ( 'WordPress' === $record->context && 'updated' === $record->action ) {

            $wp_ver = wp_mainwp_stream_get_wordpress_version();

			$version = $record->get_meta( 'new_version', true );

			if ( $version === $wp_ver ) {
				$links[ esc_html__( 'About', 'mainwp-child-reports' ) ] = admin_url( 'about.php?updated' );
			}

			$links[ esc_html__( 'View Release Notes', 'mainwp-child-reports' ) ] = esc_url( sprintf( 'http://codex.wordpress.org/Version_%s', $version ) );
		}

		return $links;
	}

	/**
	 * Register log data.
	 *
	 * @uses \WP_MainWP_Stream\Connector::register()
	 */
	public function register() {
		parent::register();
		add_filter( 'upgrader_pre_install', array( $this, 'upgrader_pre_install' ), 10, 2 );
	}

	public function upgrader_pre_install() {

		if ( empty( $this->current_themes_info ) ) {
			$this->current_themes_info = array();

			if ( ! function_exists( '\wp_get_themes' ) ) {
				require_once ABSPATH . '/wp-admin/includes/theme.php';
			}

			$themes = wp_get_themes();

			if ( is_array( $themes ) ) {
				$theme_name  = wp_get_theme()->get( 'Name' );
				$parent_name = '';
				$parent      = wp_get_theme()->parent();
				if ( $parent ) {
					$parent_name = $parent->get( 'Name' );
				}
				foreach ( $themes as $theme ) {

					$_slug = $theme->get_stylesheet();
					if ( isset( $this->current_themes_info[ $_slug ] ) ) {
						continue;
					}

					$out                  = array();
					$out['name']          = $theme->get( 'Name' );
					$out['title']         = $theme->display( 'Name', true, false );
					$out['version']       = $theme->display( 'Version', true, false );
					$out['active']        = ( $theme->get( 'Name' ) === $theme_name ) ? 1 : 0;
					$out['slug']          = $_slug;
					$out['parent_active'] = ( $parent_name == $out['name'] ) ? 1 : 0;

					$this->current_themes_info[ $_slug ] = $out;
				}
			}
		}
	}


	/**
	 * Wrapper method for calling get_plugins().
	 *
	 * @return array Installed plugins.
	 */
	public function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins();
	}

	/**
	 * Log plugin installations.
	 *
	 * @action transition_post_status.
	 *
	 * @param \WP_Upgrader $upgrader WP_Upgrader class object.
	 * @param array        $extra Extra attributes array.
	 *
	 * @return bool Return TRUE|FALSE.
	 */
	public function callback_upgrader_process_complete( $upgrader, $extra ) {
		$logs    = array();
		$success = ! is_wp_error( $upgrader->skin->result );
		$error   = null;

		if ( ! $success ) {
			$errors = $upgrader->skin->result->errors;

			list( $error ) = reset( $errors );
		}

		// This would have failed down the road anyway.
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
			// translators: Placeholders refer to a plugin/theme type, a plugin/theme name, and a plugin/theme version (e.g. "plugin", "Stream", "4.2").
			$message = _x(
				'Installed %1$s: %2$s %3$s',
				'Plugin/theme installation. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
				'mainwp-child-reports'
			);

			$logs[] = compact( 'slug', 'name', 'version', 'message', 'action' );
		} elseif ( 'update' === $action ) {

			if ( is_object( $upgrader ) && property_exists( $upgrader, 'skin' ) && 'Automatic_Upgrader_Skin' == get_class( $upgrader->skin ) ) {
				return false;
			}

			$action = 'updated';
			// translators: Placeholders refer to a plugin/theme type, a plugin/theme name, and a plugin/theme version (e.g. "plugin", "Stream", "4.2").
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

				// $_plugins = $this->get_plugins();

				foreach ( $slugs as $slug ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug );
					$name        = $plugin_data['Name'];
					$version     = $plugin_data['Version'];
					// $old_version = $_plugins[ $slug ]['Version'];

					// ( Net-Concept - Xavier NUEL ) : get old versions.
					if ( isset( $this->current_plugins_info[ $slug ] ) ) {
						$old_version = $this->current_plugins_info[ $slug ]['Version'];
					} else {
						// $old_version = ''; // Hummm... will this happen ?
						$old_version = $upgrader->skin->plugin_info['Version']; // to fix old version
					}

					if ( version_compare( $version, $old_version, '>' ) ) {
						$logs[] = compact( 'slug', 'name', 'old_version', 'version', 'message', 'action' );
					}

					// $logs[] = compact( 'slug', 'name', 'old_version', 'version', 'message', 'action' );
				}
			} else { // theme
				if ( isset( $extra['bulk'] ) && true === $extra['bulk'] ) {
					$slugs = $extra['themes'];
				} else {
					$slugs = array( $upgrader->skin->theme );
				}

				foreach ( $slugs as $slug ) {
					$theme      = wp_get_theme( $slug );
					$stylesheet = $theme['Stylesheet Dir'] . '/style.css';
					$theme_data = get_file_data(
						$stylesheet,
						array(
							'Version' => 'Version',
						)
					);
					$name       = $theme['Name'];

					$old_version = '';

					if ( isset( $this->current_themes_info[ $slug ] ) ) {
						$old_theme = $this->current_themes_info[ $slug ];

						if ( isset( $old_theme['version'] ) ) {
							$old_version = $old_theme['version'];
						}
					} else {
						$old_version = ! empty( $upgrader->skin ) && ! empty( $upgrader->skin->theme_info ) ? $upgrader->skin->theme_info->get( 'Version' ) : ''; // to fix old version  //$theme['Version'];
					}
					// $old_version = $theme['Version'];
					$version = $theme_data['Version'];

					if ( ! empty( $old_version ) && version_compare( $version, $old_version, '>' ) ) {
						$logs[] = compact( 'slug', 'name', 'old_version', 'version', 'message', 'action' );
					}
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


	/**
	 * Activate plugin callback.
	 *
	 * @param string                             $slug Plugin slug.
	 * @param $network_wide Check if network wide.
	 */
	public function callback_activate_plugin( $slug, $network_wide ) {
		$_plugins     = $this->get_plugins();
		$name         = $_plugins[ $slug ]['Name'];
		$network_wide = $network_wide ? esc_html__( 'network wide', 'mainwp-child-reports' ) : null;

		if ( empty( $name ) ) {
			return;
		}

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

	/** Decativate plugin callback.
	 *
	 * @param string                             $slug Plugin slug.
	 * @param $network_wide Check if network wide.
	 */
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

	/**
	 * Switch theme callback.
	 *
	 * @param string $name Theme name.
	 * @param string $theme Theme slug.
	 */
	public function callback_switch_theme( $name, $theme ) {
		unset( $theme );
		$this->log(
			// translators: Placeholder refers to a theme name (e.g. "Twenty Seventeen").
			__( '"%s" theme activated', 'mainwp-child-reports' ),
			compact( 'name' ),
			null,
			'themes',
			'activated'
		);
	}

	/**
	 * Update theme & transient delete callback.
	 *
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
			// translators: Placeholder refers to a theme name (e.g. "Twenty Seventeen").
			__( '"%s" theme deleted', 'mainwp-child-reports' ),
			compact( 'name' ),
			null,
			'themes',
			'deleted'
		);
	}

	/**
	 * Uninstall plugins callback.
	 *
	 * @todo Core needs an uninstall_plugin hook
	 * @todo This does not work in WP-CLI
	 */
	public function callback_pre_option_uninstall_plugins() {
		if ( ! isset( $_POST['action'] ) || 'delete-plugin' !== $_POST['action'] ) {
			return false;
		}
		$plugin                       = $_POST['plugin'];
		$_plugins                     = $this->get_plugins();
		$plugins_to_delete            = array();
		$plugins_to_delete[ $plugin ] = isset( $_plugins[ $plugin ] ) ? $_plugins[ $plugin ] : array();
		update_option( 'wp_mainwp_stream_plugins_to_delete', $plugins_to_delete );
		return false;
	}

	/**
	 * Uninstall plugins callback.
	 *
	 * @todo Core needs an uninstall_plugin hook
	 * @todo This does not work in WP-CLI
	 */
	public function callback_deleted_plugin( $plugin_file, $deleted ) {
		if ( $deleted ) {

			if ( ! isset( $_POST['action'] ) || 'delete-plugin' !== $_POST['action'] ) {
				return;
			}
			$plugins_to_delete = get_option( 'wp_mainwp_stream_plugins_to_delete' );
			if ( ! $plugins_to_delete ) {
				return;
			}
			foreach ( $plugins_to_delete as $plugin => $data ) {
				if ( $plugin_file == $plugin ) {
					$name         = $data['Name'];
					$network_wide = $data['Network'] ? esc_html__( 'network wide', 'mainwp-child-reports' ) : '';

					$this->log(
						// translators: Placeholder refers to a plugin name (e.g. "Stream").
						__( '"%s" plugin deleted', 'mainwp-child-reports' ),
						compact( 'name', 'plugin', 'network_wide' ),
						null,
						'plugins',
						'deleted'
					);
				}
			}
			delete_option( 'wp_mainwp_stream_plugins_to_delete' );
		}
	}

	/**
	 * Logs WordPress core upgrades
	 *
	 * @action automatic_updates_complete
	 *
	 * @param string $update_results  Update results.
	 * @return void
	 */
	public function callback_automatic_updates_complete( $update_results ) {
		global $pagenow;

        $wp_ver = wp_mainwp_stream_get_wordpress_version();

		if ( ! is_array( $update_results ) || ! isset( $update_results['core'] ) ) {
			$this->automatic_updates_complete_plugin_theme( $update_results );
			return;
		}

		$info = $update_results['core'][0];

		$old_version  = $wp_ver;
		$new_version  = $info->item->version;
		$auto_updated = true;

		$message = esc_html__( 'WordPress auto-updated to %s', 'stream' );

		$this->log(
			$message,
			compact( 'new_version', 'old_version', 'auto_updated' ),
			null,
			'wordpress', // phpcs:ignore -- fix format text.
			'updated',
			null,
			true // forced log - $forced_log.
		);
	}


	/**
	 * Log automatic updates.
	 *
	 * @param array $update_results Update results.
	 */
	public function automatic_updates_complete_plugin_theme( $update_results ) {

		if ( is_array( $update_results ) ) {
			$logs = array();
			foreach ( $update_results as $_type => $result ) {
				if ( is_object( $result ) && property_exists( $result, 'result' ) && true === $result->result ) {
					$type = $_type;
					if ( 'plugin' === $_type ) {
						$action = 'updated';
						// translators: Placeholders refer to a plugin/theme type, a plugin/theme name, and a plugin/theme version (e.g. "plugin", "Stream", "4.2").
						$message = _x(
							'Updated %1$s: %2$s %3$s',
							'Plugin/theme update. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
							'mainwp-child-reports'
						);

						$slug        = $result->item->slug;
						$old_version = $result->item->current_version;

						$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug );
						$name        = $plugin_data['Name'];
						$version     = $plugin_data['Version'];
						if ( version_compare( $version, $old_version, '>' ) ) {
							$logs[] = compact( 'type', 'slug', 'name', 'old_version', 'version', 'message', 'action' );
						}
					} elseif ( 'theme' === $_type ) {
						$action  = 'updated';
						$message = _x(
							'Updated %1$s: %2$s %3$s',
							'Plugin/theme update. 1: Type (plugin/theme), 2: Plugin/theme name, 3: Plugin/theme version',
							'mainwp-child-reports'
						);

						$old_version = $result->item->current_version;
						$slug        = $result->item->theme;
						$theme       = wp_get_theme( $slug );
						$stylesheet  = $theme['Stylesheet Dir'] . '/style.css';
						$theme_data  = get_file_data(
							$stylesheet,
							array(
								'Version' => 'Version',
							)
						);
						$version     = $theme_data['Version'];
						$name        = $theme['Name'];
						if ( ! empty( $old_version ) && version_compare( $version, $old_version, '>' ) ) {
							$logs[] = compact( 'type', 'slug', 'name', 'old_version', 'version', 'message', 'action' );
						}
					}
				}
			}

			if ( ! empty( $logs ) ) {
				foreach ( $logs as $log ) {
					$type = isset( $log['type'] ) ? $log['type'] : null;
					if ( ! empty( $type ) ) {
						$context     = $type . 's';
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
		}
	}


	/**
	 * Core updated successfully callback.
	 *
	 * @param $new_version New WordPress verison.
	 */
	public function callback__core_updated_successfully( $new_version ) {

		/**
		 * @global string $pagenow Current page.
		 */
		global $pagenow;

        $wp_ver = wp_mainwp_stream_get_wordpress_version();

		$old_version  = $wp_ver;
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
			'wordpress', // phpcs:ignore -- fix format text.
			'updated'
		);
	}

	/**
	 * Child Site install Plugin or theme callback.
	 *
	 * @param array $args Success message.
	 * @return bool|void Return FALSE on failure.
	 */
	public function callback_mainwp_child_install_plugin_theme( $args ) {

		$logs    = array();
		$success = isset( $args['success'] ) ? $args['success'] : 0;
		$error   = null;

		if ( ! $success ) {
			$errors = $args['errors'];

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
			if ( 'plugin' === $type ) {
				if ( ! isset( $args['Name'] ) || empty( $args['Name'] ) ) {
					return;
				}
				$slug    = $args['slug'];
				$name    = $args['Name'];
				$version = $args['Version'];
			} else { // theme
				$slug = $args['slug'];
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


	/**
	 * MainWP Plugin Action callback.
	 *
	 * @param $args Action arguments.
	 */
	public function callback_mainwp_child_plugin_action( $args ) {
		if ( ! is_array( $args ) || ! isset( $args['action'] ) ) {
			return;
		}
			$action = $args['action'];
		if ( $action == 'delete' ) {
			$name         = $args['Name'];
			$network_wide = '';
			$this->log(
				__( '"%s" plugin deleted', 'mainwp-child-reports' ),
				compact( 'name', 'plugin' ),
				null,
				'plugins',
				'deleted'
			);
		}
	}

	/**
	 * MainWP Child Theme action callback.
	 *
	 * @param string $args MainWP Child Theme action.
	 */
	public function callback_mainwp_child_theme_action( $args ) {
		if ( ! is_array( $args ) || ! isset( $args['action'] ) ) {
			return;
		}
			$action = $args['action'];
			$name   = $args['Name'];
		if ( $action == 'delete' ) {
			$this->log(
				__( '"%s" theme deleted', 'mainwp-child-reports' ),
				compact( 'name' ),
				null,
				'themes',
				'deleted'
			);
		}
	}

	// ( Net-Concept - Xavier NUEL ) : save all plugins versions before upgrade.

	/**
	 * Upgrader pre-instaler callback.
	 */
	public function callback_upgrader_pre_install() {
		if ( empty( $this->current_plugins_info ) ) {
			$this->current_plugins_info = $this->get_plugins();
		}
	}
}
