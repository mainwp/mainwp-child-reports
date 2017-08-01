<?php
if ( class_exists( 'MainWP_WP_Stream_Connector' ) ) {
	class MainWP_WP_Stream_Connector_Wptimecapsule extends MainWP_WP_Stream_Connector {

		/**
		 * Connector slug
		 *
		 * @var string
		 */
		public static $name = 'wptimecapsule_backups';

		/**
		 * Actions registered for this connector
		 *
		 * @var array
		 */
		public static $actions = array(
			'mainwp_wptimecapsule_backup',
		);

		/**
		 * Return translated connector label
		 *
		 * @return string Translated connector label
		 */
		public static function get_label() {
			return __( 'WP Time Capsule', 'mainwp-child' );
		}

		/**
		 * Return translated action labels
		 *
		 * @return array Action label translations
		 */
		public static function get_action_labels() {
			return array(
				'mainwp_wptimecapsule_backup' => __( 'WP Time Capsule Backup', 'mainwp-child' ),
			);
		}

		/**
		 * Return translated context labels
		 *
		 * @return array Context label translations
		 */
		public static function get_context_labels() {
			return array(
				'wptimecapsule_backups' => __( 'WP Time Capsule Backups', 'mainwp-child' ),
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
		
		public static function callback_mainwp_wptimecapsule_backup( $message, $type, $backup_time ) {
			self::log(
				$message,
				compact( 'type', 'backup_time' ),
				0,
				array( 'wptimecapsule_backups' => 'mainwp_wptimecapsule_backup' )
			);
		}
	}
}

