<?php
/** MainWP iThemes Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_MainWP_iThemes.
 *
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_MainWP_iThemes extends Connector {

	/** @var string Connector slug. */
	public $name = 'mainwp_ithemes';

	public $register_cron = true;

	/** @var string[] Actions registered for this connector. */
	public $actions = array(
		'itsec_site_scanner_scan_complete'
	);

	public function __construct() {
	}

	/**
	 * Get translated connector label.
	 *
	 * @return mixed Translated connector label.
	 */
	public function get_label() {
		return __( 'iThemes', 'default' );
	}

	/**
	 * Get translated action labels.
	 *
	 * @return array Return translated action labels.
	 */
	public function get_action_labels() {
			return array(
				'ithemes_scan' => __( 'iThemes scan', 'default' ),
			);
	}

	/**
	 * Get Context label translations.
	 *
	 * @return array Context label translations.
	 */
	public function get_context_labels() {
			return array(
				'ithemes_scan' => __( 'iThemes scan', 'mainwp-child-reports' ),
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

	public function callback_itsec_site_scanner_scan_complete( $scan, $site_id, $cached ) {
		if ( ! is_wp_error( $scan ) ) {

			$scan_time = $scan->get_time()->getTimestamp();

			$count_issues = 0;
			foreach ( $scan->get_entries() as $entry ) {
				if ( ! empty( $entry ) ) {
					if ( 'warn' == $entry->get_status() ) {
						$count_issues++;
					}
				}
			}

			$result  = $count_issues;
			$details = $count_issues;
			$message = 'iThemes scan completed';

			$fields = compact( 'scan_time', 'result', 'details' );
			if ( $scan_time ) {
				// log scan info.
				$this->log(
					$message,
					$fields,
					0,
					'ithemes_scan',
					'ithemes_scan'
				);
			}
		}
	}

	/**
	 * Add action links.
	 *
	 * @param array  $links  Previous links registered.
	 * @param Record $record Stream record.
	 *
	 * @return array Action links.
	 */
	public function action_links( $links, $record ) {
		if ( isset( $record->object_id ) ) {
		}
			return $links;
	}
}
