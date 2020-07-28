<?php
/** MainWP WordFence Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_MainWP_Wordfence.
 * @package WP_MainWP_Stream
 */
class Connector_MainWP_Wordfence extends Connector {

    /** @var string Connector slug. */
    public $name = 'mainwp_wordfence';

    /** @var string[] Actions registered for this connector. */
    public $actions = array(
		'mainwp_reports_wordfence_scan',                
	);

    /**
     * Get translated connector label.
     *
     * @return mixed Translated connector label.
     */
    public function get_label() {
		return __( 'Wordfence', 'default' );
	}

    /**
     * Get translated action labels.
     *
     * @return array Return translated action labels.
     */
    public function get_action_labels() {
            return array(
                'wordfence_scan'    => __( 'Wordfence scan', 'default' ),			
            );
	}

    /**
     * Get Context label translations.
     *
     * @return array Context label translations.
     */
    public function get_context_labels() {
            return array(
                'wordfence_scan' => __( 'Wordfence scan', 'mainwp-child-reports' ),
            );
	}

    /** Register with parent class. */
    public function register() {
		parent::register();
	}

    /**
     * Add action links.
     *
     * @param array  $links  Previous links registered.
     * @param Record $record Stream record.
     *
     * @return array Action links.
     */
    public function action_links($links, $record ) {
            if (isset($record->object_id)) {
            }
            return $links;
	}

    /**
     * Record Wordfence scan data.
     *
     * @param $message
     * @param string $scan_time Wordfence scan time.
     * @param array $details Scan details array.
     * @param string $result Scan result.
     */
    public function callback_mainwp_reports_wordfence_scan($message, $scan_time, $details, $result = '') {
		$this->log(
			$message,
			compact('scan_time', 'result', 'details'),
			0,
			'wordfence_scan',
			'wordfence_scan'			
		);
	}    
}
