<?php

class MainWP_WP_Stream_Connector_Maintenance extends MainWP_WP_Stream_Connector {

	public static $name = 'mainwp_maintenance';

	public static $actions = array(
		'mainwp_reports_maintenance',                
	);

	public static function get_label() {
		return __( 'Maintenance', 'default' );
	}

	public static function get_action_labels() {
            return array(
                'mainwp_reports_maintenance'    => __( 'Maintenance', 'default' ),			
            );
	}

	public static function get_context_labels() {
            return array(
                'mainwp_maintenances' => __( 'Maintenance', 'default' ),
            );
	}

	public static function action_links( $links, $record ) {
            if (isset($record->object_id)) {
            }
            return $links;
	}
       
        public static function callback_mainwp_reports_maintenance( $message, $log_time, $details, $result = '') {
            self::log(
                $message,
                compact('log_time', 'details' , 'result'),
                0,
                array( 'mainwp_maintenances' => 'mainwp_reports_maintenance' )
            );
        }    
}
