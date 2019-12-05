<?php
namespace WP_MainWP_Stream;

class Connector_MainWP_Wordfence extends Connector {

	public $name = 'mainwp_wordfence';

	public $actions = array(
		'mainwp_reports_wordfence_scan',                
	);

	public function get_label() {
		return __( 'Wordfence', 'default' );
	}

	public function get_action_labels() {
            return array(
                'wordfence_scan'    => __( 'Wordfence scan', 'default' ),			
            );
	}

	public function get_context_labels() {
            return array(
                'wordfence_scan' => __( 'Wordfence scan', 'mainwp-child-reports' ),
            );
	}

	public function register() {
		parent::register();
	}
	
	public function action_links( $links, $record ) {
            if (isset($record->object_id)) {
            }
            return $links;
	}
       
	public function callback_mainwp_reports_wordfence_scan( $message, $scan_time, $details, $result = '') {                                                             
		$this->log(
			$message,
			compact('scan_time', 'result', 'details'),
			0,
			'wordfence_scan',
			'wordfence_scan'			
		);
	}    
}
