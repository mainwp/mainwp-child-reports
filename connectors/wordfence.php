<?php

class MainWP_WP_Stream_Connector_Wordfence extends MainWP_WP_Stream_Connector {

	public static $name = 'wordfence_scan';

	public static $actions = array(
		'mainwp_reports_wordfence_scan',                
	);

	public static function get_label() {
		return __( 'Wordfence', 'default' );
	}

	public static function get_action_labels() {
            return array(
                'mainwp_reports_wordfence_scan'    => __( 'Wordfence scan', 'default' ),			
            );
	}

	public static function get_context_labels() {
            return array(
                'wordfence_scans' => __( 'Wordfence scan', 'mainwp-child-reports' ),
            );
	}

	public static function action_links( $links, $record ) {
            if (isset($record->object_id)) {
            }
            return $links;
	}
       
        public static function callback_mainwp_reports_wordfence_scan( $message, $scan_time, $details, $result = '') {                                                             
            self::log(
                $message,
                compact('scan_time', 'result', 'details'),
                0,
                array( 'wordfence_scans' => 'mainwp_reports_wordfence_scan' )
            );
        }    
}
