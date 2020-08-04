<?php
/** MainWP Child Reports helper. */

namespace WP_MainWP_Stream;

/**
 * Class MainWP_Child_Report_Helper.
 * @package WP_MainWP_Stream
 */
class MainWP_Child_Report_Helper {

    /**
     * Public static variable to hold the single instance of the class.
     *
     * @var object $instance
     */
    public static $instance;

    /**
     * @var null Child Site branding options.
     */
    public $branding_options = null;

    /** @var string Child Site branding title. */
    public $branding_title = null;

    /** @var array Settings fields. */
    public $setting_fields = array()

    /**  @var string Plugin slug. */
    public $plugin;

    /** @var array List table array. */
    public $list_table = null;

    /**
     * MainWP_Child_Report_Helper constructor.
     * @param null $plugin
     */
    function __construct( $plugin = null ) {
		$this->plugin = $plugin;
		
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );				
		add_filter( 'mainwp_wp_stream_settings_form_action', array( $this, 'settings_form_action' ) );
		add_filter('updraftplus_save_last_backup', array( __CLASS__, 'hook_updraftplus_save_last_backup' ));
		// hmbkp_backup_complete
		add_action('mainwp_child_reports_log', array( __CLASS__, 'hook_reports_log' ), 10, 1);
		add_filter( 'all_plugins', array( $this, 'modify_plugin_header' ) );
		add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 10, 2 );
		add_filter( 'wp_mainwp_stream_settings_option_fields', array( $this, 'get_hide_child_report_fields' ) );					
		$this->init_branding_options();		
	}

    /**
     * Method get_instance
     *
     * Create a public static instance.
     *
     * @static
     * @return MainWP_Child_Report_Helper
     */
    public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

    /**
     * Initiate admin menu.
     */
    public function admin_menu() {
		$opts = $this->branding_options;		
        $hide = is_array($opts) && isset( $opts['hide_child_reports']) && ($opts['hide_child_reports'] == 'hide');
        if ( ! $hide ) {
            // Register settings page
            add_filter( 'mainwp-child-init-subpages', array( $this, 'init_subpages' ) );
		}
	}

    /**
     * Form settings action.
     *
     * @param array $action Action to perform.
     * @return array $action Action to perform.
     */
    function settings_form_action($action ) {
		if ( is_network_admin() ) {
			$current_page = wp_mainwp_stream_filter_input( INPUT_GET, 'page' );
			$action       = add_query_arg( array( 'action' => $current_page ), 'edit.php' );
		}
		return $action;
	}

    /**
     * Initiate branding options.
     *
     * @return array|null Return branding options or Null.
     */
    public function init_branding_options() {
        return $this->get_branding_options();
    }

    /**
     * Get branding options.
     *
     * @return array|null Return branding options or Null.
     */
    public function get_branding_options() {
        if ( $this->branding_options === null ) {

            $opts = get_option( 'mainwp_child_branding_settings' ); // settings from mainwp-child plugin
            // this is new update
            if ( is_array($opts) ) {
                if (isset($opts['cancelled_branding'])) { // if it was set
                    $cancelled_branding = $opts['cancelled_branding'];
                } else {
                    $disconnected = isset( $opts['branding_disconnected'] ) ? $opts['branding_disconnected'] : '';
                    $preserve_branding = isset( $opts['preserve_branding'] ) ? $opts['preserve_branding'] : '';
                    $cancelled_branding = ( $disconnected === 'yes' ) && ! $preserve_branding;
                    $opts['cancelled_branding'] = $cancelled_branding;
                }
                $branding_header = isset( $opts['branding_header'] ) ? $opts['branding_header'] : '';
            } else { // to compatible will old code
                $opts = array();
                $opts['hide'] = get_option( 'mainwp_branding_child_hide' );
                $opts['branding_header'] = get_option( 'mainwp_branding_plugin_header' );
                $cancelled_branding = ( get_option( 'mainwp_child_branding_disconnected' ) === 'yes' ) && ! get_option( 'mainwp_branding_preserve_branding' );
                $opts['cancelled_branding'] = $cancelled_branding;
                $branding_header = $opts['branding_header'];
            }

            if ( ! $cancelled_branding && ( is_array( $branding_header ) && ! empty( $branding_header['name'] ) ) ) {
                $this->branding_title = stripslashes( $branding_header['name'] );
            } else {
                $this->branding_title = '';
            }

            $this->branding_options = $opts;
        }

        return $this->branding_options;
    }


    /**
     * Modify Plugin header.
     *
     * @param array $plugins Plugins array.
     * @return array Modified plugins array.
     */
    public function modify_plugin_header( $plugins ) {
        $_opts = $this->branding_options;
        $is_hide = isset( $_opts['hide'] ) ? $_opts['hide'] : '';
        $cancelled_branding = isset( $_opts['cancelled_branding'] ) ? $_opts['cancelled_branding'] : false;
        $branding_header = isset( $_opts['branding_header'] ) ? $_opts['branding_header'] : '';

        if ( $cancelled_branding ) {
            return $plugins;
        }

		if ( 'T' === $is_hide ) {
			foreach ( $plugins as $key => $value ) {
				$plugin_slug = basename( $key, '.php' );
				if ( 'mainwp-child-reports' === $plugin_slug ) {
					unset( $plugins[ $key ] );
				}
			}
			return $plugins;
		}

		if ( is_array( $branding_header ) && ! empty( $branding_header['name'] ) ) {
			return $this->update_plugin_header( $plugins, $branding_header );
		} else {
			return $plugins;
		}
	}

    /**
     * Update plugins header.
     *
     * @param array $plugins Plugins array.
     * @param string $header Header to update.
     * @return array Modified plugins array.
     */
    public function update_plugin_header($plugins, $header ) {
		$plugin_key = '';
		foreach ( $plugins as $key => $value ) {
			$plugin_slug = basename( $key, '.php' );
			if ( 'mainwp-child-reports' === $plugin_slug ) {
				$plugin_key  = $key;
				$plugin_data = $value;
			}
		}

		if ( ! empty( $plugin_key ) ) {
			$plugin_data['Name']        = stripslashes( $header['name'] . " reports" );
            $plugin_data['Description'] = stripslashes( $header['description'] );
			$plugin_data['Author']      = stripslashes( $header['author'] );
			$plugin_data['AuthorURI']   = stripslashes( $header['authoruri'] );
			if ( ! empty( $header['pluginuri'] ) ) {
				$plugin_data['PluginURI'] = stripslashes( $header['pluginuri'] );
			}
			$plugins[ $plugin_key ] = $plugin_data;
		}

		return $plugins;
	}

    /**
     * Check if branding is enabled.
     *
     * @return bool TRUE|FALSE.
     */
    public function is_branding() {

        $_opts = $this->branding_options;
        $is_hide = isset( $_opts['hide'] ) ? $_opts['hide'] : '';
        $cancelled_branding = isset( $_opts['cancelled_branding'] ) ? $_opts['cancelled_branding'] : false;
        $branding_header = isset( $_opts['branding_header'] ) ? $_opts['branding_header'] : array();

        if ( $cancelled_branding ) {
            return false;
        }
        // hide.
        if ( 'T' === $is_hide ) {
            return true;
        }
        if ( is_array( $branding_header ) && !empty( $branding_header['name'] ) ) {
            return true;
        }
        return false;

    }


    /**
     * Plugin row meta.
     *
     * @param array $plugin_meta Plugin meta data.
     * @param string $plugin_file Plugin file.
     * @return array $plugin_meta Return pugin meta data array.
     */
    public function plugin_row_meta($plugin_meta, $plugin_file ) {
        if ( WP_MAINWP_STREAM_PLUGIN !== $plugin_file ) {
			return $plugin_meta;
		}

		if ( ! $this->is_branding() ) {
			return $plugin_meta;
		}
                // hide View details links
		$meta_total = count( $plugin_meta );
		for ( $i = 0; $i < $meta_total; $i++ ) {
			$str_meta = $plugin_meta[ $i ];
			if ( strpos( $str_meta, 'plugin-install.php?tab=plugin-information' ) ) {
				unset( $plugin_meta[ $i ] );
				break;
			}
		}

		return $plugin_meta;
	}

    /**
     * Initiate subpages.
     *
     * @param array $subPages Subpages array.
     * @return array Return subpages array, or
     */
    public function init_subpages($subPages = array() ) {

        if ( is_network_admin() && ! is_plugin_active_for_network( WP_MAINWP_STREAM_PLUGIN ) ) {
            return $subPages;
        }

        $branding_text = $this->branding_title;

        if (empty($branding_text)) {
                $branding_text = 'Child Reports';
        } else {
                $branding_text = $branding_text . ' Reports';
        }
		
        $subPages[] = array('title' => $branding_text, 'slug' => 'reports-page' , 'callback' => array( $this, 'render_reports_page' ) , 'load_callback' => array( $this, 'register_list_table' ));
        $subPages[] = array('title' => $branding_text . ' Settings', 'slug' => 'reports-settings' , 'callback' => array( $this, 'render_settings_page' ) );

        return $subPages;
    }

    /**
     * Save UpdraftPlus last backup hook.
     *
     * @param array $last_backup last backup meta data.
     * @return array $last_backup last backup meta data.
     */
    public static function hook_updraftplus_save_last_backup( $last_backup ) {

		if (!is_array($last_backup))
			return $last_backup;

		if (isset($last_backup['backup_time'])) {
				if (empty($last_backup['success']))
					return $last_backup;

				$backup_time = $last_backup['backup_time'];
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

				// to logging updraftplus backup
				do_action("updraftplus_backup", $destination , $message, __('Finished', 'mainwp-child-reports'), $backup_type, $backup_time);
		}
		return $last_backup;
	}

    /**
     * Reports log hook.
     *
     * @param string $ext_name Extension name.
     */
    public static function hook_reports_log( $ext_name = '') {
		do_action('mainwp_child_log', $ext_name);
	}

    /**
     * Hide Child Reports Fields.
     *
     * @param array $fields Fields array.
     * @return array $fields Return modified fields array.
     */
    public function get_hide_child_report_fields( $fields ) {
		
		$branding_text = $this->get_branding_title();
		$branding_name = !empty($branding_text) ? $branding_text : 'MainWP Child';
		$chkbox_label = 'Hide ' . $branding_name . ' Reports from reports';
		$chkbox_desc = 'If selected, the ' . $branding_name . ' Reports plugin will be left out from reports for this site.';
		
		$new_fields['general']['fields'][] = array(
			'name'        => 'hide_child_plugins',
			'title'       => $chkbox_label,
			'after_field' => __( 'Enabled', 'mainwp-child-reports' ),
			'default'     => 0,
			'desc'        => $chkbox_desc,
			'type'        => 'checkbox',
		);

		$fields = array_merge_recursive( $new_fields, $fields );
		return $fields;
			
	}

    /**
     * Register list table.
     */
    public function register_list_table() {
		$this->list_table = new List_Table(
			$this->plugin, array(
				'screen' => 'settings_page_' . $this->plugin->admin->records_page_slug,
			)
		);		
	}

    /**
     * Render list table.
     */
    public function render_list_table() {
		$this->list_table->prepare_items();
		echo '<div class="mainwp_child_reports_wrap">';
		$this->list_table->display();
		echo '</div>';
	}

    /**
     * Render reports page.
     */
    public function render_reports_page() {
		do_action('mainwp-child-pageheader', 'reports-page');		
		$this->render_list_table();		
		do_action('mainwp-child-pagefooter', 'reports-page');
	}

    /**
     * Render settings page.
     */
    public function render_settings_page() {
		$option_key  = $this->plugin->settings->option_key;
		$form_action = apply_filters( 'mainwp_wp_stream_settings_form_action', admin_url( 'options.php' ) );		
		do_action('mainwp-child-pageheader', 'reports-settings');		
		?>
		<div class="postbox">
			<div class="inside">
				<form method="post" action="<?php echo esc_attr( $form_action ) ?>" enctype="multipart/form-data">
					<?php					
					settings_fields( $option_key );
					do_settings_sections( $option_key );					
					submit_button();
					?>
				</form>
			</div>
		</div>
	<?php
		do_action('mainwp-child-pagefooter', 'reports-settings');
	}

    /**
     * Get branding title.
     *
     * @return string|null Return branding title or Null
     */
    public function get_branding_title() {
		return $this->branding_title;
	}
}