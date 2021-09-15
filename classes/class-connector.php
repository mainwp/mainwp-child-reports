<?php
/** MainWP Child Reports connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector.
 *
 * @package WP_MainWP_Stream
 */
abstract class Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = null;

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array();

	/**
	 * Store delayed logs
	 *
	 * @var array
	 */
	public $delayed = array();

	/**
	 * Previous Stream entry in same request
	 *
	 * @var int
	 */
	public $prev_stream = null;

	/**
	 * Register connector in the WP Admin
	 *
	 * @var bool
	 */
	public $register_admin = true;

	/**
	 * Register connector in the WP Frontend
	 *
	 * @var bool
	 */
	public $register_frontend = true;

	/**
	 * Register connector in the WP CRON
	 *
	 * @var bool
	 */
	public $register_cron = false;

	/**
	 * Register all context hooks
	 */
	public function register() {
		foreach ( $this->actions as $action ) {
			add_action( $action, array( $this, 'callback' ), 10, 99 );
		}

		add_filter( 'wp_mainwp_stream_action_links_' . $this->name, array( $this, 'action_links' ), 10, 2 );
	}

	/**
	 * Callback for all registered hooks throughout Stream
	 * Looks for a class method with the convention: "callback_{action name}"
	 */
	public function callback() {
		$action   = current_filter();
		$callback = array( $this, 'callback_' . preg_replace( '/[^A-Za-z0-9_\-]/', '_', $action ) ); // to fix A-Z charater in callback name

		// For the sake of testing, trigger an action with the name of the callback
		if ( defined( 'WP_MAINWP_STREAM_TESTS' ) && WP_MAINWP_STREAM_TESTS ) {
			/**
			 * Action fires during testing to test the current callback
			 *
			 * @param  array  $callback  Callback name
			 */
			do_action( 'wp_mainwp_stream_test_' . $callback[1] );
		}

		// Call the real function
		if ( is_callable( $callback ) ) {
			return call_user_func_array( $callback, func_get_args() );
		}
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @param array  $links   Previous links registered
	 * @param object $record Stream record
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		unset( $record );
		return $links;
	}

	/**
	 * Log handler
	 *
	 * @param string $message sprintf-ready error message string
	 * @param array  $args     sprintf (and extra) arguments to use
	 * @param int    $object_id  Target object id
	 * @param string $context Context of the event
	 * @param string $action  Action of the event
	 * @param int    $user_id    User responsible for the event
	 *
	 * @return bool
	 */
	public function log( $message, $args, $object_id, $context, $action, $user_id = null, $forced_log = false ) {
		$connector = $this->name;

		$data = apply_filters(
			'wp_mainwp_stream_log_data',
			compact( 'connector', 'message', 'args', 'object_id', 'context', 'action', 'user_id', 'forced_log' )
		);

		if ( ! $data ) {
			return false;
		} else {
			$connector  = $data['connector'];
			$message    = $data['message'];
			$args       = $data['args'];
			$object_id  = $data['object_id'];
			$context    = $data['context'];
			$action     = $data['action'];
			$user_id    = $data['user_id'];
			$forced_log = $data['forced_log'];
		}

		$created_timestamp = null;

		if ( ! empty( $context ) && is_array( $args ) ) {
			if ( $context == 'plugins' ) {

				if ( isset( $args['slug'] ) && ( $args['slug'] == 'mainwp-child/mainwp-child.php' || $args['slug'] == 'mainwp-child-reports/mainwp-child-reports.php' ) ) {
					$options = (array) get_option( 'wp_mainwp_stream', array() );
					if ( ! empty( $options['general_hide_child_plugins'] ) ) {
						return false; // return, do not log child/reports plugin
					}
					$branding_text = wp_mainwp_stream_get_instance()->child_helper->get_branding_title();
					if ( ! empty( $branding_text ) ) {
						if ( $args['slug'] == 'mainwp-child/mainwp-child.php' ) {
							$args['name'] = $branding_text;
						} else {
							$args['name'] = $branding_text . ' Reports';
						}
					}
				}
			}

			$addition_connector = '';

			$mainwp_addition_connector = array(
				'mainwp_backups',
				'mainwp_maintenances',
				'mainwp_sucuri',
				'mainwp_wordfence',
			);

			if ( in_array( $connector, $mainwp_addition_connector ) ) {
				$addition_connector = $connector;
			}

			$created_timestamp = 0;
			if ( ! empty( $addition_connector ) ) {

				if ( is_array( $args ) ) {
					if ( isset( $args['backup_time'] ) ) {
						$created_timestamp = $args['backup_time'];
					} elseif ( isset( $args['scan_time'] ) ) {
						$created_timestamp = $args['scan_time'];
					}
				}

				if ( empty( $created_timestamp ) ) {
					return;
				}

				$query_args = array(
					'connector' => $addition_connector,
					'created'   => date( 'Y-m-d H:i:s', $created_timestamp ),
				);

				$created_item = wp_mainwp_stream_get_instance()->db->get_records( $query_args );

				if ( $created_item ) {
					return;
				}
			}
		}

		return call_user_func_array( array( wp_mainwp_stream_get_instance()->log, 'log' ), compact( 'connector', 'message', 'args', 'object_id', 'context', 'action', 'user_id', 'created_timestamp', 'forced_log' ) );
	}

	/**
	 * Save log data till shutdown, so other callbacks would be able to override
	 *
	 * @param string $handle Special slug to be shared with other actions
	 * @note param mixed $arg1 Extra arguments to sent to log()
	 * @note param param mixed $arg2, etc..
	 */
	public function delayed_log( $handle ) {
		$args = func_get_args();

		array_shift( $args );

		$this->delayed[ $handle ] = $args;

		add_action( 'shutdown', array( $this, 'delayed_log_commit' ) );
	}

	/**
	 * Commit delayed logs saved by @delayed_log
	 */
	public function delayed_log_commit() {
		foreach ( $this->delayed as $handle => $args ) {
			call_user_func_array( array( $this, 'log' ), $args );
		}
	}

	/**
	 * Compare two values and return changed keys if they are arrays
	 *
	 * @param  mixed    $old_value Value before change
	 * @param  mixed    $new_value Value after change
	 * @param  bool|int $deep   Get array children changes keys as well, not just parents
	 *
	 * @return array
	 */
	public function get_changed_keys( $old_value, $new_value, $deep = false ) {
		if ( ! is_array( $old_value ) && ! is_array( $new_value ) ) {
			return array();
		}

		if ( ! is_array( $old_value ) ) {
			return array_keys( $new_value );
		}

		if ( ! is_array( $new_value ) ) {
			return array_keys( $old_value );
		}

		$diff = array_udiff_assoc(
			$old_value,
			$new_value,
			function( $value1, $value2 ) {
				// Compare potentially complex nested arrays
				return wp_json_encode( $value1 ) !== wp_json_encode( $value2 );
			}
		);

		$result = array_keys( $diff );

		// find unexisting keys in old or new value
		$common_keys     = array_keys( array_intersect_key( $old_value, $new_value ) );
		$unique_keys_old = array_values( array_diff( array_keys( $old_value ), $common_keys ) );
		$unique_keys_new = array_values( array_diff( array_keys( $new_value ), $common_keys ) );

		$result = array_merge( $result, $unique_keys_old, $unique_keys_new );

		// remove numeric indexes
		$result = array_filter(
			$result,
			function( $value ) {
				// @codingStandardsIgnoreStart
				// check if is not valid number (is_int, is_numeric and ctype_digit are not enough)
				return (string) (int) $value !== (string) $value;
				// @codingStandardsIgnoreEnd
			}
		);

		$result = array_values( array_unique( $result ) );

		if ( false === $deep ) {
			return $result; // Return an numerical based array with changed TOP PARENT keys only
		}

		$result = array_fill_keys( $result, null );

		foreach ( $result as $key => $val ) {
			if ( in_array( $key, $unique_keys_old, true ) ) {
				$result[ $key ] = false; // Removed
			} elseif ( in_array( $key, $unique_keys_new, true ) ) {
				$result[ $key ] = true; // Added
			} elseif ( $deep ) { // Changed, find what changed, only if we're allowed to explore a new level
				if ( is_array( $old_value[ $key ] ) && is_array( $new_value[ $key ] ) ) {
					$inner  = array();
					$parent = $key;
					$deep--;
					$changed = $this->get_changed_keys( $old_value[ $key ], $new_value[ $key ], $deep );
					foreach ( $changed as $child => $change ) {
						$inner[ $parent . '::' . $child ] = $change;
					}
					$result[ $key ] = 0; // Changed parent which has a changed children
					$result         = array_merge( $result, $inner );
				}
			}
		}

		return $result;
	}

	/**
	 * Allow connectors to determine if their dependencies is satisfied or not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		return true;
	}
}
