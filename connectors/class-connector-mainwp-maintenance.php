<?php
/** MainWP Maintenaince Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_MainWP_Maintenance
 *
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_MainWP_Maintenance extends Connector {

	/** @var string Connector slug. */
	public $name = 'mainwp_maintenance';

	/** @var string[] Actions registered for this connector. */
	public $actions = array(
		'mainwp_reports_maintenance',
	);

	/**
	 * Return translated connector label.
	 *
	 * @return string Translated connector label.
	 */
	public function get_label() {
		return __( 'Maintenance', 'default' );
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array Action label translations.
	 */
	public function get_action_labels() {
			return array(
				'maintenance' => __( 'Maintenance', 'default' ),
			);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
			return array(
				'mainwp_maintenance' => __( 'Maintenance', 'default' ),
			);
	}

	/**
	 * Register with parent class.
	 *
	 * @uses \WP_MainWP_Stream\Connector::register()
	 */
	public function register() {
		parent::register();
	}

	/**
	 * Add action links to Stream drop row in admin list screen.
	 *
	 * @param  array  $links Previous links registered.
	 * @param  object $record Stream record.
	 *
	 * @return array Action links.
	 */
	public function action_links( $links, $record ) {
		if ( isset( $record->object_id ) ) {
		}
			return $links;
	}

	/**
	 * Record MainWP Maintenance reports.
	 *
	 * @param string $message Error messages.
	 * @param string $log_time Maintenance Log Time.
	 * @param array  $details Maintenance details array.
	 * @param string $result Maintenance results.
	 * @param string $revisions Maintenance revisions.
	 */
	public function callback_mainwp_reports_maintenance( $message, $log_time, $details, $result = '', $revisions = 0 ) {
		$this->log(
			$message,
			compact( 'log_time', 'details', 'result', 'revisions' ),
			0,
			'mainwp_maintenance',
			'maintenance'
		);
	}
}
