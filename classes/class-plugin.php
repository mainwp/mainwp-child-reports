<?php
/** MainWP Child Reports plugin. */

namespace WP_MainWP_Stream;

/**
 * Class Plugin.
 * @package WP_MainWP_Stream
 */
class Plugin {

	/** @const string Plugin version number. */
	const VERSION = '3.5.5';

	/** @const string WP-CLI command. */
	const WP_CLI_COMMAND = 'mainwp_stream';

	/** @var \WP_MainWP_Stream\Admin Admin class. */
	public $admin;

	/** @var \WP_MainWP_Stream\Alerts Alerts class. */
	public $alerts;

	/** @var \WP_MainWP_Stream\Alerts_List Alerts_List class. */
	public $alerts_list;

	/** @var \WP_MainWP_Stream\Connectors Connectors class. */
	public $connectors;

	/** @var \WP_MainWP_Stream\DB DB Class. */
	public $db;

	/** @var \WP_MainWP_Stream\Log Log Class. */
	public $log;

	/** @var \WP_MainWP_Stream\Settings Settings class. */
	public $settings;

	/** @var \WP_MainWP_Stream\Install Install class. */
	public $install;

	/** @var array URLs and Paths used by the plugin. */
	public $locations = array();

	
	/** @var \WP_MainWP_Stream\Child_Helper Child_Helper class. */
	public $child_helper;

	/**
	 * Plugin constructor.
	 *
	 * Run each time the class is called.
	 *
	 * @throws \Exception
	 */
	public function __construct() {
		$locate = $this->locate_plugin();

		$this->locations = array(
			'plugin'    => $locate['plugin_basename'],
			'dir'       => $locate['dir_path'],
			'url'       => $locate['dir_url'],
			'inc_dir'   => $locate['dir_path'] . 'includes/',
			'class_dir' => $locate['dir_path'] . 'classes/',
		);

		spl_autoload_register( array( $this, 'autoload' ) );

		// Load helper functions.
		require_once $this->locations['inc_dir'] . 'functions.php';

		// Load DB helper interface/class.
		$driver_class = apply_filters( 'wp_mainwp_stream_db_driver', '\WP_MainWP_Stream\DB_Driver_WPDB' );
		$driver       = null;

		if ( class_exists( $driver_class ) ) {
			$driver   = new $driver_class();
			$this->db = new DB( $driver );
		}

		$error = false;
		if ( ! $this->db ) {
			$error = esc_html__( 'Stream: Could not load chosen DB driver.', 'mainwp-child-reports' );
		} elseif ( ! $driver instanceof DB_Driver ) {
			$error = esc_html__( 'Stream: DB driver must implement DB Driver interface.', 'mainwp-child-reports' );
		}

		if ( $error ) {
			wp_die(
				esc_html( $error ),
				esc_html__( 'Reports DB Error', 'mainwp-child-reports' )
			);
		}

		// Load languages.
		add_action( 'plugins_loaded', array( $this, 'i18n' ) );

		// Load logger class
		$this->log = apply_filters( 'wp_mainwp_stream_log_handler', new Log( $this ) );

		// Load settings and connectors after widgets_init and before the default init priority.
		add_action( 'init', array( $this, 'init' ), 9 );


		// Change DB driver after plugin loaded if any add-ons want to replace.
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 20 );

		// Load admin area classes.
		if ( is_admin() || ( defined( 'WP_MAINWP_STREAM_DEV_DEBUG' ) && WP_MAINWP_STREAM_DEV_DEBUG ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$this->admin   = new Admin( $this );
			$this->install = $driver->setup_storage( $this );
		} elseif ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$this->admin = new Admin( $this, $driver );
		}
		$this->child_helper = new MainWP_Child_Report_Helper( $this );
		
		// Load WP-CLI command.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( self::WP_CLI_COMMAND, 'WP_MainWP_Stream\CLI' );
		}
	}

	/**
	 * Autoloader for classes.
	 *
	 * @param string $class
	 */
	public function autoload( $class ) {
		if ( ! preg_match( '/^(?P<namespace>.+)\\\\(?P<autoload>[^\\\\]+)$/', $class, $matches ) ) {
			return;
		}

		static $reflection;

		if ( empty( $reflection ) ) {
			$reflection = new \ReflectionObject( $this );
		}

		if ( $reflection->getNamespaceName() !== $matches['namespace'] ) {
			return;
		}

		$autoload_name = $matches['autoload'];
		$autoload_dir  = \trailingslashit( $this->locations['class_dir'] );
		$autoload_path = sprintf( '%sclass-%s.php', $autoload_dir, strtolower( str_replace( '_', '-', $autoload_name ) ) );

		if ( is_readable( $autoload_path ) ) {
			require_once $autoload_path;
		}
	}

	/**
	 * Loads the translation files.
	 *
	 * @action plugins_loaded
	 */
	public function i18n() {
		load_plugin_textdomain( 'mainwp-child-reports', false, dirname( $this->locations['plugin'] ) . '/languages/' );
	}

	/**
	 * Load Settings, Notifications, and Connectors.
	 *
	 * @action init
	 */
	public function init() {
		$this->settings    = new Settings( $this );
		$this->connectors  = new Connectors( $this );		
		$this->alerts      = new Alerts( $this );
		$this->alerts_list = new Alerts_List( $this );

	}

	/**
	 * Version of plugin_dir_url() which works for plugins installed in the plugins directory,
	 * and for plugins bundled with themes.
	 *
	 * @throws \Exception
	 *
	 * @return array
	 */
	private function locate_plugin() {
		$dir_url         = trailingslashit( plugins_url( '', dirname( __FILE__ ) ) );
		$dir_path        = plugin_dir_path( dirname( __FILE__ ) );
		$dir_basename    = basename( $dir_path );
		$plugin_basename = trailingslashit( $dir_basename ) . $dir_basename . '.php';

		return compact( 'dir_url', 'dir_path', 'dir_basename', 'plugin_basename' );
	}

	/**
	 * Getter for the version number.
	 *
	 * @return string
	 */
	public function get_version() {
		return self::VERSION;
	}

	/**
	 * Change plugin database driver in case driver plugin loaded after stream.
	 */
	public function plugins_loaded() {
		// Load DB helper interface/class
		$driver_class = apply_filters( 'wp_mainwp_stream_db_driver', '\WP_MainWP_Stream\DB_Driver_WPDB' );

		if ( class_exists( $driver_class ) ) {
			$driver   = new $driver_class();
			$this->db = new DB( $driver );
		}
	}
}
