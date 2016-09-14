<?php

class MainWP_WP_Stream_Connector_Updraftplus extends MainWP_WP_Stream_Connector {

	public static $name = 'updraftplus_backups';

	public static $actions = array(
		'updraftplus_backup',                
	);

	public static function get_label() {
		return __( 'Updraftplus', 'default' );
	}

	public static function get_action_labels() {
            return array(
                'updraftplus_backup'    => __( 'Updraftplus Backup', 'default' ),			
            );
	}

	public static function get_context_labels() {
            return array(
                'updraftplus_backups' => __( 'Updraftplus Backups', 'mainwp-child-reports' ),
            );
	}

	public static function action_links( $links, $record ) {
            if (isset($record->object_id)) {
            }
            return $links;
	}
        
        public static function callback_updraftplus_backup($destination, $message, $status, $type, $backup_date) {                                                          
            self::log(
                $message,
                compact('destination', 'status', 'type', 'backup_date'),
                0,
                array( 'updraftplus_backups' => 'updraftplus_backup' )
            );
        }    
}
