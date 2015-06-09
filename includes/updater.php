<?php
/**
 * Updater Class
 *
 */

if ( ! class_exists( 'MainWP_WP_Stream_Updater_0_1' ) ) {
	class MainWP_WP_Stream_Updater_0_1 {

		const VERSION = 0.1;

		static $instance;

		public $plugins = array();

		private $api_url = 'https://wp-stream.com/api/';

		public static function instance() {
			if ( empty( self::$instance ) ) {
				$class = get_called_class();
				self::$instance = new $class;
			}
			return self::$instance;
		}

		public function __construct() {
			$this->api_url = apply_filters( 'mainwp_wp_stream_update_api_url', $this->api_url );
			$this->setup();
		}

		public function setup() {
			// Override requests for plugin information
			//add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
			// Check for updates
			//add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ), 20, 3 );

			// License validation and storage
			//add_action( 'wp_ajax_mainwp-stream-license-check', array( $this, 'license_check' ) );
			//add_action( 'wp_ajax_mainwp-stream-license-remove', array( $this, 'license_remove' ) );
		}

		public function register( $plugin_file ) {
			$this->plugins[ $plugin_file ] = preg_match( '#([a-z\-]+).php#', $plugin_file, $match ) ? $match[1] : null;

			// Plugin activation link
			$plugin_basename = plugin_basename( $plugin_file );
			add_action( 'plugin_action_links_' . $plugin_basename, array( $this, 'plugin_action_links' ) );			
		}

		public function info( $result, $action = null, $args = null ) {
			if ( $action != 'plugin_information' || ! in_array( $args->slug, $this->plugins )  ) {
				return $result;
			}

			$url     = apply_filters( 'mainwp_wp_stream_update_api_url', $this->api_url . $action, $action );
			$options = array(
				'body' => array(
					'slug'   => $args->slug,
				),
			);
			$response = wp_remote_post( $url, $options );

			if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
				wp_die( __( 'Could not connect to MainWP Child Reports update center.', 'mainwp-child-reports' ) );
			}

			$body = wp_remote_retrieve_body( $response );
			do_action( 'mainwp_wp_stream_update_api_response', $body, $response, $url, $options );

			$info = (object) json_decode( $body, true );
			return $info;
		}

		public function check( $transient ) {
//			if ( empty( $transient->checked ) || ! $this->plugins ) {
//				return $transient;
//			}
//
//			$response = (array) $this->request( array_intersect_key( $transient->checked, $this->plugins ) );
//
//			$license = get_site_option( MainWP_WP_Stream_Updater::LICENSE_KEY );
//			$site    = esc_url_raw( parse_url( get_option( 'siteurl' ), PHP_URL_HOST ) );
//			if ( $response ) {
//				foreach ( $response as $key => $value ) {
//					if ( $license ) {
//						$value->package = add_query_arg(
//							array(
//								'key'     => 'update',
//								'license' => $license,
//								'site'    => $site,
//							),
//							esc_url_raw( $value->package )
//						);
//					} else {
//						$value->package = '';
//					}
//				}
//				$transient->response = array_merge( $transient->response, $response );
//			}
//
//			
//			return $transient;
		}

		public function request( $plugins ) {
//			$license = get_site_option( MainWP_WP_Stream_Updater::LICENSE_KEY );
//
//			if ( ! $license ) {
//				return;
//			}
//
//			$action  = 'update';
//			$url     = apply_filters( 'mainwp_wp_stream_update_api_url', $this->api_url . $action, $action );
//			$options = array(
//				'body' => array(
//					'a'       => $action,
//					'plugins' => $plugins,
//					'name'    => get_bloginfo( 'name' ),
//					'url'     => get_bloginfo( 'url' ),
//					'license' => $license,
//				),
//			);
//
//			$response = wp_remote_post( $url, $options );
//
//			if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
//				$error = __( 'Could not connect to Stream update center.', 'mainwp-child-reports' );
//				add_action( 'all_admin_notices', function() use ( $error ) { echo wp_kses_post( $error ); } );
//				return;
//			}
//
//			$body = wp_remote_retrieve_body( $response );
//			do_action( 'mainwp_wp_stream_update_api_response', $body, $response, $url, $options );
//
//			$body = json_decode( $body );
//
//			if ( empty( $body ) ) {
//				return;
//			}
//
//			return $body;
		}

		public function license_check() {
//			$license = mainwp_wp_stream_filter_input( INPUT_POST, 'license' );
//
//			if ( ! wp_verify_nonce( mainwp_wp_stream_filter_input( INPUT_POST, 'nonce' ), 'license_check' ) ) {
//				wp_die( __( 'Invalid security check.', 'mainwp-child-reports' ) );
//			}
//
//			$action = 'license-verify';
//			$args   = array(
//				'body' => array(
//					'a' => $action,
//					'l' => $license,
//				),
//			);
//
//			$url      = apply_filters( 'mainwp_wp_stream_update_api_url', $this->api_url . $action, $action );
//			$response = wp_remote_post( $url, $args );
//
//			if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
//				wp_send_json_error( __( 'Could not connect to Stream license server to verify license details.', 'mainwp-child-reports' ) );
//			}
//
//			$data = json_decode( wp_remote_retrieve_body( $response ) );
//			if ( ! $data->success ) {
//				wp_send_json_error( $data );
//			}
//
//			update_site_option( MainWP_WP_Stream_Updater::LICENSE_KEY, $license );
//			update_site_option( MainWP_WP_Stream_Updater::LICENSEE_KEY, $data->data->user );
//
//			// Invalidate plugin-update transient so we can check for updates
//			// and restore package urls to existing updates
//			delete_site_transient( 'update_plugins' );
//
//			wp_send_json( $data );
		}

		public function license_remove() {
//			if ( ! wp_verify_nonce( mainwp_wp_stream_filter_input( INPUT_POST, 'nonce' ), 'license_remove' ) ) {
//				wp_die( __( 'Invalid security check.', 'mainwp-child-reports' ) );
//			}
//
//			delete_site_option( MainWP_WP_Stream_Updater::LICENSE_KEY );
//			delete_site_option( MainWP_WP_Stream_Updater::LICENSEE_KEY );
//
//			// Invalidate plugin-update transient so we can check for updates
//			// and restore package urls to existing updates
//			delete_site_transient( 'update_plugins' );
//
//			wp_send_json_success( array( 'message' => __( 'Site disconnected successfully from your Stream account.', 'mainwp-child-reports' ) ) );
		}

		public function plugin_action_links( $links ) {			
                        $links['activation'] = __( 'Activated', 'mainwp-child-reports' );			
			return $links;
		}

		public function get_api_url() {
			return $this->api_url;
		}

		

	}
}

if ( ! class_exists( 'MainWP_WP_Stream_Updater' ) ) {
	class MainWP_WP_Stream_Updater {
		const LICENSE_KEY  = 'mainwp_wp_stream_license';
		const LICENSEE_KEY = 'mainwp_wp_stream_licensee';

		private static $versions = array();

		public static function instance() {
			$latest = max( array_keys( self::$versions ) );
			return new self::$versions[ $latest ];
		}

		public static function register( $class ) {
			self::$versions[ $class::VERSION ] = $class;
		}
	}
}

MainWP_WP_Stream_Updater::register( 'MainWP_WP_Stream_Updater_0_1' );
