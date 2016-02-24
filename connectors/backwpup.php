<?php
if ( class_exists( 'MainWP_WP_Stream_Connector' ) ) {
	class MainWP_WP_Stream_Connector_Backwpup extends MainWP_WP_Stream_Connector {

		/**
		 * Connector slug
		 *
		 * @var string
		 */
		public static $name = 'backwpup_backups';

		/**
		 * Actions registered for this connector
		 *
		 * @var array
		 */
		public static $actions = array(
			'mainwp_backwpup_backup',
		);

		/**
		 * Return translated connector label
		 *
		 * @return string Translated connector label
		 */
		public static function get_label() {
			return __( 'BackWPup', 'mainwp-child' );
		}

		/**
		 * Return translated action labels
		 *
		 * @return array Action label translations
		 */
		public static function get_action_labels() {
			return array(
				'mainwp_backwpup_backup' => __( 'BackWPup Backup', 'mainwp-child' ),
			);
		}

		/**
		 * Return translated context labels
		 *
		 * @return array Context label translations
		 */
		public static function get_context_labels() {
			return array(
				'backwpup_backups' => __( 'BackWPup Backups', 'mainwp-child' ),
			);
		}

		/**
		 * Add action links to Stream drop row in admin list screen
		 *
		 * @filter wp_stream_action_links_{connector}
		 *
		 * @param  array $links Previous links registered
		 * @param  int $record Stream record
		 *
		 * @return array             Action links
		 */
		public static function action_links( $links, $record ) {
			return $links;
		}
		
		public static function callback_mainwp_backwpup_backup( $message, $type, $backup_time ) {
			self::log(
				$message,
				compact( 'type', 'backup_time' ),
				0,
				array( 'backwpup_backups' => 'mainwp_backwpup_backup' )
			);
		}
	}
}

