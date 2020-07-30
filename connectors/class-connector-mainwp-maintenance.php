<?php

namespace WP_MainWP_Stream;

class Connector_MainWP_Maintenance extends Connector {

	public $name = 'mainwp_maintenance';

	public $actions = array(
		'mainwp_reports_maintenance',                
	);

	public function get_label() {
		return __( 'Maintenance', 'default' );
	}

	public function get_action_labels() {
            return array(
                'maintenance'    => __( 'Maintenance', 'default' ),			
            );
	}

	public function get_context_labels() {
            return array(
                'mainwp_maintenance' => __( 'Maintenance', 'default' ),
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
       
	public function callback_mainwp_reports_maintenance( $message, $log_time, $details, $result = '', $revisions = 0) {
		$this->log(
			$message,
			compact('log_time', 'details' , 'result', 'revisions'),
			0,
			'mainwp_maintenance',
			'maintenance'			
		);
	}    
}
