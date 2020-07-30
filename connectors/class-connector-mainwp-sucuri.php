<?php
/** MainWP Sucuri Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_MainWP_Sucuri
 * @package WP_MainWP_Stream
 */
class Connector_MainWP_Sucuri extends Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'mainwp_sucuri';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'mainwp_reports_sucuri_scan',
	);

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return __( 'MainWP Sucuri', 'default' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'sucuri_scan' => __( 'Sucuri Scan', 'default' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'sucuri_scan' => __( 'Sucuri scan', 'default' ),
		);
	}

    /**
     * Register connector with parent class.
     */
    public function register() {
		parent::register();
	}
	
	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array $links Previous links registered
	 * @param  int $record  Stream record
	 *
	 * @return array        Action links
	 */
	public function action_links( $links, $record ) {
		return $links;
	}

    /**
     * Callback for MainWP reports Sucuri Scan.
     *
     * @param array $data         Scan Results array.
     * @param string $scan_status Status of current scan.
     * @param array $scan_data    Scan data.
     * @param int $scan_time      The current time of scan.
     */
    public function callback_mainwp_reports_sucuri_scan($data, $scan_status, $scan_data, $scan_time = 0) {
		
		$message = '';
		if ( 'success' === $scan_status ) {
			$message     = __( 'Sucuri scan successful!', 'mainwp-child' );
			$scan_status = 'success';
		} else {
			$message     = __( 'Sucuri scan failed!', 'mainwp-child' );
			$scan_status = 'failed';
		}

		$scan_result = maybe_unserialize( base64_decode( $data ) );
		$status      = $webtrust = '';
		if ( is_array( $scan_result ) ) {
			$status   = isset( $scan_result['status'] ) ? $scan_result['status'] : '';
			$webtrust = isset( $scan_result['webtrust'] ) ? $scan_result['webtrust'] : '';
		}
		
		if ( empty($scan_time))
			$scan_time = time();
		
		$this->log(
			$message,
			compact( 'scan_status', 'status', 'webtrust', 'scan_data', 'scan_time' ),
			0,
			'sucuri_scan',
			'sucuri_scan'			
		);
	}
}


