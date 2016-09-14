<?php

class MainWP_WP_Stream_Connector_Backupwordpress extends MainWP_WP_Stream_Connector {

	public static $name = 'backupwordpress_backups';

	public static $actions = array(
		'backupwordpress_backup',                
	);

	public static function get_label() {
		return __( 'BackupWordPress', 'default' );
	}

	public static function get_action_labels() {
            return array(
                'backupwordpress_backup'    => __( 'BackupWordPress Backup', 'default' ),			
            );
	}

	public static function get_context_labels() {
            return array(
                'backupwordpress_backups' => __( 'BackupWordPress Backups', 'mainwp-child-reports' ),
            );
	}

	public static function action_links( $links, $record ) {
            if (isset($record->object_id)) {
            }
            return $links;
	}
        
        public static function callback_backupwordpress_backup($destination, $message, $status, $type, $backup_time = 0) {                                                             
            self::log(
                $message,
                compact('destination', 'status', 'type', 'backup_time'),
                0,
                array( 'backupwordpress_backups' => 'backupwordpress_backup' )
            );
        }    
}
