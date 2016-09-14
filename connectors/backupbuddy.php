<?php

class MainWP_WP_Stream_Connector_Backupbuddy extends MainWP_WP_Stream_Connector {

	public static $name = 'backupbuddy_backups';

	public static $actions = array(
		'mainwp_reports_backupbuddy_backup',                
	);

	public static function get_label() {
		return __( 'BackupBuddy', 'default' );
	}

	public static function get_action_labels() {
            return array(
                'mainwp_reports_backupbuddy_backup'    => __( 'BackupBuddy Backup', 'default' ),			
            );
	}

	public static function get_context_labels() {
            return array(
                'backupbuddy_backups' => __( 'BackupBuddy Backups', 'mainwp-child-reports' ),
            );
	}

	public static function action_links( $links, $record ) {
            if (isset($record->object_id)) {
            }
            return $links;
	}
        
        public static function callback_mainwp_reports_backupbuddy_backup( $message, $type , $backup_time = 0) {                                                             
            self::log(
                $message,
                compact('type', 'backup_time'),
                0,
                array( 'backupbuddy_backups' => 'mainwp_reports_backupbuddy_backup' )
            );
        }    
}
