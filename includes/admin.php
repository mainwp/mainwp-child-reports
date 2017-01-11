<?php

class MainWP_WP_Stream_Admin {

	public static $screen_id = array();

	public static $list_table = null;

	public static $disable_access = false;
	public static $brandingTitle = null;

	const ADMIN_BODY_CLASS     = 'mainwp_wp_stream_screen';
	const RECORDS_PAGE_SLUG    = 'mainwp-reports-page';
	const SETTINGS_PAGE_SLUG   = 'mainwp_wp_stream_settings';	
	const ADMIN_PARENT_PAGE    = 'options-general.php';
	const VIEW_CAP             = 'view_stream';
	const SETTINGS_CAP         = 'manage_options';
	const PRELOAD_AUTHORS_MAX  = 50;

	public static function load() {
		// User and role caps
		add_filter( 'user_has_cap', array( __CLASS__, '_filter_user_caps' ), 10, 4 );
		add_filter( 'role_has_cap', array( __CLASS__, '_filter_role_caps' ), 10, 3 );

		self::$disable_access = apply_filters( 'mainwp_wp_stream_disable_admin_access', false );

		// Register settings page
		if (get_option('mainwp_creport_branding_stream_hide') !== "hide") {
			add_filter( 'mainwp-child-init-subpages', array( __CLASS__, 'init_subpages' ) );						
		}

		// Admin notices
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		// Add admin body class
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );

		// Load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_menu_css' ) );

		// Reset MainWP Reports database
		add_action( 'wp_ajax_mainwp_wp_stream_reset', array( __CLASS__, 'ajax_reset_reports' ) );

		// Reset MainWP Reports settings
		add_action( 'wp_ajax_mainwp_wp_stream_defaults', array( __CLASS__, 'wp_ajax_defaults' ) );


		// Auto purge setup
		add_action( 'wp_loaded', array( __CLASS__, 'purge_schedule_setup' ) );
		add_action( 'mainwp_wp_stream_auto_purge', array( __CLASS__, 'purge_scheduled_action' ) );

		// Admin notices
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		// Ajax authors list
		add_action( 'wp_ajax_mainwp_wp_stream_filters', array( __CLASS__, 'ajax_filters' ) );

		// Ajax author's name by ID
		add_action( 'wp_ajax_mainwp_wp_stream_get_filter_value_by_id', array( __CLASS__, 'get_filter_value_by_id' ) );
                
		add_filter('updraftplus_save_last_backup', array( __CLASS__, 'hookUpdraftplusSaveLastBackup' ));                
                // hmbkp_backup_complete
		add_action('mainwp_child_reports_log', array( __CLASS__, 'hook_reports_log' ), 10, 1);                                
	}
	
	public static function get_branding_title() {
		if (self::$brandingTitle  === null) {
			$cancelled_branding = ( get_option( 'mainwp_child_branding_disconnected' ) === 'yes' ) && ! get_option( 'mainwp_branding_preserve_branding' );
			$branding_header = get_option( 'mainwp_branding_plugin_header' );
			if ( ! $cancelled_branding && ( is_array( $branding_header ) && ! empty( $branding_header['name'] ) ) ) {
				self::$brandingTitle   = stripslashes( $branding_header['name'] );
			} else {
				self::$brandingTitle = '';
			}	
		}
		return self::$brandingTitle;
	}

	public static function admin_notices() {
		$message = mainwp_wp_stream_filter_input( INPUT_GET, 'message' );

		switch ( $message ) {
			case 'child_reports_data_erased':
				printf( '<div class="updated"><p>%s</p></div>', __( 'All records have been successfully erased.', 'mainwp-child-reports' ) );
				break;
			case 'child_reports_settings_reset':
				printf( '<div class="updated"><p>%s</p></div>', __( 'All site settings have been successfully reset.', 'mainwp-child-reports' ) );
				break;			
		}		
	}
        
        public static function hookUpdraftplusSaveLastBackup($last_backup) {                 
            
            if (!is_array($last_backup))
                return $last_backup;

            if (isset($last_backup['backup_time'])) {                                      
                    $date = $last_backup['backup_time'];
                    $backup = $last_backup['backup_array'];
                    
                    $message = "";
                    $backup_type = "";
                    if (isset($backup['db'])) {
                        $message .= "database, ";
                        $backup_type .= "database, ";
                    }
                    if (isset($backup['plugins'])) {
                        $message .= "plugins, ";
                        $backup_type .= "plugins, ";
                    }

                    if (isset($backup['themes'])) {
                        $message .= "themes, ";
                        $backup_type .= "themes, ";
                    }

                    $message = rtrim($message, ', ');                                
                    $message = "Updraftplus backup " . $message ." finished"; 

                    $backup_type = rtrim($backup_type, ', ');

                    $size = "N/A";
                    if (isset($backup['db-size'])) {
                        $size = $backup['db-size'];
                    } else if (isset($backup['themes-size'])) {
                        $size = $backup['themes-size'];
                    }
                    $destination = "";                          
                    do_action("updraftplus_backup", $destination , $message, __('Finished', 'mainwp-child-reports'), $backup_type, $date);                    
            }
            return $last_backup;
        }
        
        public static function hook_reports_log($ext_name = '') {            
            do_action('mainwp_child_log', $ext_name);
        }

        static function get_record_meta_data($record, $meta_key) { 
        
            if (empty($record))
                return "";
            $value = "";
            if (isset($record->meta)) {
                $meta = $record->meta;
                if (isset($meta[$meta_key])) {
                    $value = $meta[$meta_key];
                    $value = current($value); 
                    if ($meta_key == "author_meta") {
                        $value = unserialize($value); 
                        $value = $value['display_name'];                    
                    }

                }             
            }
            return $value;            
        }
	
        public static function init_subpages($subPages = array()) {
                if ( is_network_admin() && ! is_plugin_active_for_network( MAINWP_WP_STREAM_PLUGIN ) ) {
                        return $subPages;
                }	

                $branding_text = MainWP_WP_Stream_Admin::get_branding_title();			
                if (empty($branding_text)) {
                        $branding_text = 'Child Reports';
                } else {
                        $branding_text = $branding_text . ' Reports';
                }

                $subPages[] = array('title' => $branding_text, 'slug' => 'reports-page' , 'callback' => array( __CLASS__, 'render_reports_page' ) , 'load_callback' => array( __CLASS__, 'register_list_table' ));
                $subPages[] = array('title' => $branding_text . ' Settings', 'slug' => 'reports-settings' , 'callback' => array( __CLASS__, 'render_reports_settings' ) );
                return $subPages;			
        }
		
	public static function admin_enqueue_scripts( $hook ) {
		wp_register_script( 'select2', MAINWP_WP_STREAM_URL . 'ui/select2/select2.min.js', array( 'jquery' ), '3.4.5', true );
		wp_register_style( 'select2', MAINWP_WP_STREAM_URL . 'ui/select2/select2.css', array(), '3.4.5' );
		wp_register_script( 'timeago', MAINWP_WP_STREAM_URL . 'ui/timeago/jquery.timeago.js', array(), '1.4.1', true );

		$locale    = strtolower( substr( get_locale(), 0, 2 ) );
		$file_tmpl = 'ui/timeago/locales/jquery.timeago.%s.js';

		if ( file_exists( MAINWP_WP_STREAM_DIR . sprintf( $file_tmpl, $locale ) ) ) {
			wp_register_script( 'timeago-locale', MAINWP_WP_STREAM_URL . sprintf( $file_tmpl, $locale ), array( 'timeago' ), '1' );
		} else {
			wp_register_script( 'timeago-locale', MAINWP_WP_STREAM_URL . sprintf( $file_tmpl, 'en' ), array( 'timeago' ), '1' );
		}

		wp_enqueue_style( 'mainwp-wp-stream-admin', MAINWP_WP_STREAM_URL . 'ui/admin.css', array(), MainWP_WP_Stream::VERSION );
		
		$script_screens = array( 'plugins.php', 'user-edit.php', 'user-new.php', 'profile.php' );

		if ( 'index.php' === $hook ) {
			
		} elseif ( in_array( $hook, self::$screen_id ) || in_array( $hook, $script_screens ) || $hook == 'settings_page_mainwp-reports-page' ) {
			
			wp_enqueue_script( 'select2' );
			wp_enqueue_style( 'select2' );

			wp_enqueue_script( 'timeago' );
			wp_enqueue_script( 'timeago-locale' );

			wp_enqueue_script( 'mainwp-wp-stream-admin', MAINWP_WP_STREAM_URL . 'ui/admin.js', array( 'jquery', 'select2', 'heartbeat' ), MainWP_WP_Stream::VERSION );			
			wp_localize_script(
				'mainwp-wp-stream-admin',
				'mainwp_wp_stream',
				array(
					'i18n'            => array(
						'confirm_purge'     => __( 'Are you sure you want to delete all MainWP Child Reports activity records from the database? This cannot be undone.', 'mainwp-child-reports' ),
						'confirm_defaults'  => __( 'Are you sure you want to reset all site settings to default? This cannot be undone.', 'mainwp-child-reports' ),
						'confirm_uninstall' => __( 'Are you sure you want to uninstall and deactivate MainWP Child Reports? This will delete all MainWP Child Reports tables from the database and cannot be undone.', 'mainwp-child-reports' ),
					),
					'gmt_offset'     => get_option( 'gmt_offset' ),
					'current_screen' => $hook,
					'current_page'   => isset( $_GET['paged'] ) ? esc_js( $_GET['paged'] ) : '1',
					'current_order'  => isset( $_GET['order'] ) ? esc_js( $_GET['order'] ) : 'desc',
					'current_query'  => json_encode( $_GET ),
					'filters'        => self::$list_table ? self::$list_table->get_filters() : false,
					'locale'     => esc_js( $locale )					
				)
			);	
		}
	}

	
	public static function admin_body_class( $classes ) {
		if ( isset( $_GET['page'] ) && false !== strpos( $_GET['page'], self::RECORDS_PAGE_SLUG ) ) {
			$classes .= sprintf( ' %s ', self::ADMIN_BODY_CLASS );
		}

		return $classes;
	}

	public static function admin_menu_css() {
		wp_register_style( 'jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/base/jquery-ui.css', array(), '1.10.1' );
		wp_register_style( 'mainwp-wp-stream-datepicker', MAINWP_WP_STREAM_URL . 'ui/datepicker.css', array( 'jquery-ui' ), MainWP_WP_Stream::VERSION );

		// Make sure we're working off a clean version
		include( ABSPATH . WPINC . '/version.php' );
	}

	public static function register_update_hook( $file, $callback, $version ) {
		if ( ! is_admin() ) {
			return;
		}

		$plugin = plugin_basename( $file );

		if ( is_plugin_active_for_network( $plugin ) ) {
			$current_versions = get_site_option( MainWP_WP_Stream_Install::KEY . '_connectors', array() );
			$network          = true;
		} elseif ( is_plugin_active( $plugin ) ) {
			$current_versions = get_option( MainWP_WP_Stream_Install::KEY . '_connectors', array() );
			$network          = false;
		} else {
			return;
		}

		if ( version_compare( $version, $current_versions[ $plugin ], '>' ) ) {
			call_user_func( $callback, $current_versions[ $plugin ], $network );
			$current_versions[ $plugin ] = $version;
		}

		if ( $network ) {
			update_site_option( MainWP_WP_Stream_Install::KEY . '_registered_connectors', $current_versions );
		} else {
			update_option( MainWP_WP_Stream_Install::KEY . '_registered_connectors', $current_versions );
		}

		return;
	}
	
	public static function register_list_table() {
		require_once MAINWP_WP_STREAM_INC_DIR . 'list-table.php';
                $param = array();
                if (isset(self::$screen_id['main'])) {
                    $param['screen'] = self::$screen_id['main'];
                }
		self::$list_table = new MainWP_WP_Stream_List_Table( $param );
	}

	public static function render_reports_page() {	
		do_action('mainwp-child-pageheader', 'reports-page');
		self::$list_table->prepare_items();
		echo '<div class="mainwp_child_reports_wrap">';
		self::$list_table->display();		
		echo '</div>';
		do_action('mainwp-child-pagefooter', 'reports-page');
	}

	public static function render_reports_settings() {

		$option_key  = MainWP_WP_Stream_Settings::$option_key;
		$form_action = apply_filters( 'mainwp_wp_stream_settings_form_action', admin_url( 'options.php' ) );
		$sections   = MainWP_WP_Stream_Settings::get_fields();
		//settings_errors();			
		do_action('mainwp-child-pageheader', 'reports-settings')
		?>
		<div class="postbox">
			<div class="inside">
				<form method="post" action="<?php echo esc_attr( $form_action ) ?>" enctype="multipart/form-data">
					<?php
					$i = 0;
					foreach ( $sections as $section => $data ) {
						$i++;
						settings_fields( $option_key );
						do_settings_sections( $option_key );						
					}
					submit_button();
					?>
				</form>
			</div>
		</div>
		
	<?php
		do_action('mainwp-child-pagefooter', 'reports-settings');
	}
	
	public static function ajax_reset_reports() {
		check_ajax_referer( 'stream_nonce', 'mainwp_wp_stream_nonce' );

		if ( current_user_can( self::SETTINGS_CAP ) ) {
			self::erase_stream_records();					
			MainWP_WP_Stream_Install::check_to_copy_data();			
			wp_redirect(					
				add_query_arg(
					array(
						'page'    => 'mainwp-reports-settings',						
						'message' => 'child_reports_data_erased'
					),
					admin_url( 'options-general.php' )					
				)
			);
			exit;
		} else {
			wp_die( "You don't have sufficient privileges to do this action." );
		}
	}

	private static function erase_stream_records() {
		global $wpdb;

		$where = '';
		if ( is_multisite() && ! is_plugin_active_for_network( MAINWP_WP_STREAM_PLUGIN ) ) {
			$where .= $wpdb->prepare( ' AND `blog_id` = %d', get_current_blog_id() );
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE `stream`, `context`, `meta`
				FROM {$wpdb->mainwp_reports} AS `stream`
				LEFT JOIN {$wpdb->mainwp_reportscontext} AS `context`
				ON `context`.`record_id` = `stream`.`ID`
				LEFT JOIN {$wpdb->mainwp_reportsmeta} AS `meta`
				ON `meta`.`record_id` = `stream`.`ID`
				WHERE `stream`.`type` = %s
				$where;",
				'stream'
			)
		);
	}

	public static function wp_ajax_defaults() {
		check_ajax_referer( 'stream_nonce', 'mainwp_wp_stream_nonce' );

		if ( ! is_plugin_active_for_network( MAINWP_WP_STREAM_PLUGIN ) ) {
			wp_die( "You don't have sufficient privileges to do this action." );
		}

		if ( current_user_can( self::SETTINGS_CAP ) ) {
			self::reset_stream_settings();
			wp_redirect(
				add_query_arg(
					array(
						'page'    => is_network_admin() ? 'mainwp_wp_stream_network_settings' : 'mainwp_wp_stream_settings',
						'message' => 'child_reports_settings_reset',
					),
					is_plugin_active_for_network( MAINWP_WP_STREAM_PLUGIN ) ? network_admin_url( self::ADMIN_PARENT_PAGE ) : admin_url( self::ADMIN_PARENT_PAGE )
				)
			);
			exit;
		} else {
			wp_die( "You don't have sufficient privileges to do this action." );
		}
	}

	private static function reset_stream_settings() {
		global $wpdb;

		$blogs = wp_get_sites();

		if ( $blogs ) {
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				delete_option( MainWP_WP_Stream_Settings::KEY );
			}
			restore_current_blog();
		}
	}

	public static function purge_schedule_setup() {
		if ( ! wp_next_scheduled( 'mainwp_wp_stream_auto_purge' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'mainwp_wp_stream_auto_purge' );
		}
	}

	public static function purge_scheduled_action() {
		global $wpdb;

		// Don't purge if in Network Admin if Stream isn't network enabled
		if ( is_network_admin() && is_multisite() && ! is_plugin_active_for_network( MAINWP_WP_STREAM_PLUGIN ) ) {
			return;
		}

		if ( is_multisite() && is_plugin_active_for_network( MAINWP_WP_STREAM_PLUGIN ) ) {
			$options = (array) get_site_option( MainWP_WP_Stream_Settings::NETWORK_KEY, array() );
		} else {
			$options = MainWP_WP_Stream_Settings::get_options();
		}

		$days = $options['general_records_ttl'];
		$date = new DateTime( 'now', $timezone = new DateTimeZone( 'UTC' ) );

		$date->sub( DateInterval::createFromDateString( "$days days" ) );

		$where = $wpdb->prepare( ' AND `stream`.`created` < %s', $date->format( 'Y-m-d H:i:s' ) );

		if ( is_multisite() && ! is_plugin_active_for_network( MAINWP_WP_STREAM_PLUGIN ) ) {
			$where .= $wpdb->prepare( ' AND `blog_id` = %d', get_current_blog_id() );
		}
                        
		$wpdb->query(
			$wpdb->prepare(
				"DELETE `stream`, `context`, `meta`
				FROM {$wpdb->mainwp_reports} AS `stream`
				LEFT JOIN {$wpdb->mainwp_reportscontext} AS `context`
				ON `context`.`record_id` = `stream`.`ID`
				LEFT JOIN {$wpdb->mainwp_reportsmeta} AS `meta`
				ON `meta`.`record_id` = `stream`.`ID`
				WHERE `stream`.`type` = %s
				$where;",
				'stream',
				$date->format( 'Y-m-d H:i:s' )
			)
		);
	}

	private static function _role_can_view_stream( $role ) {
		if ( in_array( $role, array('administrator')) ) {
			return true;
		}

		return false;
	}

	public static function _filter_user_caps( $allcaps, $caps, $args, $user = null ) {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		$user = is_a( $user, 'WP_User' ) ? $user : wp_get_current_user();

		$roles = array_unique(
			array_merge(
				$user->roles,
				array_filter(
					array_keys( $user->caps ),
					array( $wp_roles, 'is_role' )
				)
			)
		);

		foreach ( $caps as $cap ) {
			if ( self::VIEW_CAP === $cap ) {
				foreach ( $roles as $role ) {
					if ( self::_role_can_view_stream( $role ) ) {
						$allcaps[ $cap ] = true;
						break 2;
					}
				}
			}
		}

		return $allcaps;
	}

	public static function _filter_role_caps( $allcaps, $cap, $role ) {
		if ( self::VIEW_CAP === $cap && self::_role_can_view_stream( $role ) ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	public static function ajax_filters() {
                if ( ! defined( 'DOING_AJAX' ) ) {
			wp_die( '-1' );
		}

		check_ajax_referer( 'mainwp_creport_filters_user_search_nonce', 'nonce' );
                  
		switch ( mainwp_wp_stream_filter_input( INPUT_GET, 'filter' ) ) {
			case 'author':
				$users = array_merge(
					array( 0 => (object) array( 'display_name' => 'WP-CLI' ) ),
					get_users()
				);

				// `search` arg for get_users() is not enough
				$users = array_filter(
					$users,
					function ( $user ) {
						return false !== mb_strpos( mb_strtolower( $user->display_name ), mb_strtolower( mainwp_wp_stream_filter_input( INPUT_GET, 'q' ) ) );
					}
				);

				if ( count( $users ) > self::PRELOAD_AUTHORS_MAX ) {
					$users = array_slice( $users, 0, self::PRELOAD_AUTHORS_MAX );
					// @todo $extra is not used
					$extra = array(
						'id'       => 0,
						'disabled' => true,
						'text'     => sprintf( _n( 'One more result...', '%d more results...', $results_count - self::PRELOAD_AUTHORS_MAX, 'mainwp-child-reports' ), $results_count - self::PRELOAD_AUTHORS_MAX ),
					);
				}

				// Get gravatar / roles for final result set
				$results = self::get_authors_record_meta( $users );

				break;
		}
		if ( isset( $results ) ) {
			echo json_encode( array_values( $results ) );
		}
		die();
	}

	public static function get_filter_value_by_id() {
		$filter = mainwp_wp_stream_filter_input( INPUT_POST, 'filter' );
		switch ( $filter ) {
			case 'author':
				$id = mainwp_wp_stream_filter_input( INPUT_POST, 'id' );
				if ( $id === '0' ) {
					$value = 'WP-CLI';
					break;
				}
				$user = get_userdata( $id );
				if ( ! $user || is_wp_error( $user ) ) {
					$value = '';
				} else {
					$value = $user->display_name;
				}
				break;
			default:
				$value = '';
				break;
		}
		echo json_encode( $value );
		wp_die();
	}

	public static function get_authors_record_meta( $authors ) {
		require_once MAINWP_WP_STREAM_INC_DIR . 'class-wp-stream-author.php';

		$authors_records = array();

		foreach ( $authors as $user_id => $args ) {
			$author   = new MainWP_WP_Stream_Author( $user_id );
			$disabled = isset( $args['disabled'] ) ? $args['disabled'] : null;

			$authors_records[ $user_id ] = array(
				'text'     => $author->get_display_name(),
				'id'       => $user_id,
				'label'    => $author->get_display_name(),
				'icon'     => $author->get_avatar_src( 32 ),
				'title'    => '',
				'disabled' => $disabled,
			);
		}

		return $authors_records;
	}
}
