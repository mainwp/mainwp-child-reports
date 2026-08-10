<?php
/** MainWP Backups Connector. */

namespace WP_MainWP_Stream;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Class Connector_MainWP_Backups
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_MainWP_Backups extends Connector {

    /**
     * Option prefix for atomically reserving a backup fingerprint.
     */
	const FINGERPRINT_LOCK_PREFIX = 'mainwp_reports_backup_fingerprint_lock_';

    /**
     * Fingerprint lock lease duration in seconds.
     */
	const FINGERPRINT_LOCK_TTL = 300;

	/**
	 * @var array<string, bool> Fingerprint logging results for this request.
	 */
	private static $fingerprint_logged_results = array();

	/** @var string Connector slug. */
	public $name = 'mainwp_backups';

	public $register_cron = true; // to fix cron backups.

	/** @var array Actions registered for this connector. */
	public $actions = array(
		'mainwp_backup',
		'mainwp_reports_backupbuddy_backup',
		'mainwp_reports_backupwordpress_backup',
		'mainwp_reports_backwpup_backup',
		'updraftplus_backup', // backup action from updraftplus
		'mainwp_reports_wptimecapsule_backup',
        'wpvivid_backup'
	);

	/**
	 * Return translated connector label.
	 *
	 * @return string Translated connector label.
	 */
	public function get_label() {
		return __( 'MainWP Backups', 'default' );
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array Action label translations.
	 */
	public function get_action_labels() {
		return array(
			'mainwp_backup' => esc_html__( 'MainWP Backup', 'mainwp-child-reports' ),
			'backupbuddy_backup' =>  esc_html__( 'BackupBuddy Backup', 'mainwp-child-reports' ),
			'backupwordpress_backup' => esc_html__( 'BackupWordPress Backup', 'mainwp-child-reports' ),
			'backwpup_backup' => __( 'BackWPup Backup', 'mainwp-child-reports' ),
			'updraftplus_backup' => __( 'Updraftplus Backup', 'mainwp-child-reports' ),
			'wptimecapsule_backup' => __( 'WP Time Capsule Backup', 'mainwp-child-reports' ),
            'wpvivid_backup' => __( 'WPvivid Backup', 'mainwp-child-reports' )
		);
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array Context label translations.
	 */
	public function get_context_labels() {
		return array(
			'backups' => __( 'Backups', 'mainwp-child-reports' ),
		);
	}

    /**
     * Register with parent class.
     *
     * @uses \WP_MainWP_Stream\Connector::register()
     */
	public function register() {
		parent::register();
		// Reuse the Report Settings "Keep Records for" purge lifecycle.
		add_action( 'wp_mainwp_stream_auto_purge', array( __CLASS__, 'cleanup_expired_fingerprint_locks' ) );
	}


	/**
	 * Add action links to Stream drop row in admin list screen.
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array $links Previous links registered.
	 * @param  int $record Stream record.
	 *
	 * @return array             Action links.
	 */
	public function action_links( $links, $record ) {
		return $links;
	}

    /**
     * Rord MainWP Backup log.
     *
     * @param string $destination Backup destination.
     * @param string $message Log message.
     * @param string $size Size of backup.
     * @param string $status Backup status.
     * @param string $type Type of backup.
     */
    public function callback_mainwp_backup($destination, $message, $size, $status, $type ) {
		$this->log(
			$message,
			compact( 'destination', 'status', 'type', 'size' ),
			0,
			'backups',
			'mainwp_backup'
		);
	}

    /**
     * Record MainWP BackupBuddy backup log.
     *
     * @param $message Log message.
     * @param string $type Type of backup.
     * @param int $backup_time Backup time.
     */
	public function callback_mainwp_reports_backupbuddy_backup($message, $type , $backup_time = 0) {
		$fingerprint = func_num_args() > 3 ? func_get_arg( 3 ) : '';
		$this->log_backup(
			$message,
			compact('type', 'backup_time'),
			0,
			'backups',
			'backupbuddy_backup',
			$fingerprint
		);
	}

    /**
     * Record MainWP BackupWordpress backup log.
     *
     * @param string $destination Backup destination.
     * @param string $message Log message.
     * @param string $status Backup status.
     * @param string $type Type of backup.
     * @param int $backup_time Bakcup time.
     */
	public function callback_mainwp_reports_backupwordpress_backup($destination, $message, $status, $type, $backup_time = 0) {
		$fingerprint = func_num_args() > 5 ? func_get_arg( 5 ) : '';
		$this->log_backup(
			$message,
			compact('destination', 'status', 'type', 'backup_time'),
			0,
			'backups',
			'backupwordpress_backup',
			$fingerprint
		);
	}


    /**
     * Record MainWP BackupWPup backup log.
     *
     * @param string $message Log message.
     * @param string $type Type of backup.
     * @param int $backup_time Bakcup time.
     * @param string $fingerprint Backup fingerprint to use for deduplication.
     */
    public function callback_mainwp_reports_backwpup_backup($message, $type, $backup_time, $fingerprint = '' ) {
		$this->log_backup(
			$message,
			compact( 'type', 'backup_time' ),
			0,
			'backups',
			'backwpup_backup',
			$fingerprint
		);
	}

    /**
     * Record MainWP UpdraftPlus backups log.
     *
	 * Not used.
	 *
     * @param string $destination Backup destination.
     * @param string $message Log message.
     * @param string $status Backup status.
     * @param string $type Backup type.
     * @param int $backup_time Backup time.
     * @param string $fingerprint Backup fingerprint to use for deduplication.
     */
    public function callback_updraftplus_backup($destination, $message, $status, $type, $backup_time, $fingerprint = '') {
		$this->log_backup(
			$message,
			compact('destination', 'status', 'type', 'backup_time'),
			0,
			'backups',
			'updraftplus_backup',
			$fingerprint
		);
	}

    /**
     * Record MainWP WPTimeCapsule backups log.
     *
     * @param string $message Log message.
     * @param string $type Backup type.
     * @param int $backup_time Backup time.
     * @param string $fingerprint Backup fingerprint to use for deduplication.
     */
    public function callback_mainwp_reports_wptimecapsule_backup($message, $type, $backup_time, $fingerprint = '' ) {
		$this->log_backup(
			$message,
			compact( 'type', 'backup_time' ),
			0,
			'backups',
			'wptimecapsule_backup',
			$fingerprint
		);
	}

    /**
     * Record MainWP WPvivid backup log.
     *
     * @param string $destination Backup destination.
     * @param string $message Log message.
     * @param string $status Backup status.
     * @param string $type Backup type.
     * @param int $backup_time Backup time.
     * @param string $fingerprint Backup fingerprint to use for deduplication.
     */
    public function callback_wpvivid_backup($destination, $message, $status, $type, $backup_time, $fingerprint = ''){
        $this->log_backup(
            $message,
            compact( 'destination', 'status', 'type', 'backup_time' ),
            0,
            'backups',
            'wpvivid_backup',
            $fingerprint
        );
    }

	/**
	 * Log a backup once, using a stable provider fingerprint.
	 *
	 * The fingerprint is stored as Stream metadata. The metadata lookup also
	 * keeps fingerprints created before deduplication was introduced from
	 * being imported a second time.
     *
     * @param string $message sprintf-ready error message string.
     * @param array  $args sprintf (and extra) arguments to use.
     * @param int    $object_id Target object id.
     * @param string $context Context of the event.
     * @param string $action Action of the event.
     * @param string $fingerprint Backup fingerprint to use for deduplication.
	 *
	 * @return bool|int False when the record was skipped or could not be inserted.
	 */
	private function log_backup( $message, $args, $object_id, $context, $action, $fingerprint = '' ) {
		$fingerprint = sanitize_text_field( $fingerprint );
		$lock_option = '';
		$lock_value  = '';

		if ( '' !== $fingerprint ) {
			$lock_option = self::FINGERPRINT_LOCK_PREFIX . md5( $fingerprint );
			$lock_value = ( time() + self::FINGERPRINT_LOCK_TTL ) . ':' . wp_generate_uuid4();
			if ( ! add_option( $lock_option, $lock_value, '', false ) ) {
				$existing_lock = get_option( $lock_option, '' );
				$lock_parts    = explode( ':', (string) $existing_lock, 2 );

				if ( 2 !== count( $lock_parts ) || (int) $lock_parts[0] > time() ) {
					return false;
				}

				global $wpdb;
				$reclaimed = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
						$lock_value,
						$lock_option,
						$existing_lock
					)
				);
				if ( 1 !== $reclaimed ) {
					return false;
				}
				wp_cache_delete( $lock_option, 'options' );
			}

			if ( self::fingerprint_exists( $fingerprint ) ) {
				self::$fingerprint_logged_results[ $fingerprint ] = true;
				self::release_fingerprint_lock( $lock_option, $lock_value );
				return false;
			}

			register_shutdown_function(
				function() use ( $lock_option, $lock_value ) {
					self::release_fingerprint_lock( $lock_option, $lock_value );
				}
			);
		}

		if ( '' !== $fingerprint ) {
			$args['backup_fingerprint'] = $fingerprint;
		}

		$result = $this->log( $message, $args, $object_id, $context, $action );
		if ( '' !== $lock_option ) {
			self::release_fingerprint_lock( $lock_option, $lock_value );
			self::$fingerprint_logged_results[ $fingerprint ] = (bool) $result;
		}

		return $result;
	}

	/**
	 * Release a fingerprint lock only when it still belongs to this request.
	 *
	 * @param string $lock_option Lock option name.
	 * @param string $lock_value Lock owner value.
	 */
	private static function release_fingerprint_lock( $lock_option, $lock_value ) {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$lock_option,
				$lock_value
			)
		);
		if ( $deleted ) {
			wp_cache_delete( $lock_option, 'options' );
		}
	}

	/**
	 * Remove a batch of locks left behind by terminated requests.
	 *
	 * This runs from the existing periodic maintenance cron, not from the
	 * backup logging path. The batch limit keeps maintenance bounded.
	 */
	public static function cleanup_expired_fingerprint_locks() {
		global $wpdb;
		$like = $wpdb->esc_like( self::FINGERPRINT_LOCK_PREFIX ) . '%';
		$expired_options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) <= %d LIMIT 100",
				$like,
				time()
			)
		);

		if ( empty( $expired_options ) ) {
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $expired_options ), '%s' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name IN ({$placeholders}) AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) <= %d",
				array_merge( $expired_options, array( time() ) )
			)
		);

		foreach ( $expired_options as $option_name ) {
			wp_cache_delete( $option_name, 'options' );
		}
	}

	/**
	 * Check whether a backup fingerprint was already logged.
     * @param string $fingerprint Backup fingerprint to check.
     *
     * @return bool True if the fingerprint was already logged, false otherwise.
	 */
	public static function fingerprint_exists( $fingerprint ) {
		$fingerprint = sanitize_text_field( $fingerprint );
		if ( '' === $fingerprint ) {
			return false;
		}

		global $wpdb;
		$prefix = apply_filters( 'wp_mainwp_stream_db_tables_prefix', $wpdb->base_prefix );
		$meta_table = $prefix . 'mainwp_stream_meta';
		$stream_table = $prefix . 'mainwp_stream';
		$record_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta.record_id FROM {$meta_table} AS meta INNER JOIN {$stream_table} AS stream ON stream.ID = meta.record_id WHERE meta.meta_key = %s AND meta.meta_value = %s AND stream.site_id = %d AND stream.blog_id = %d LIMIT 1",
				'backup_fingerprint',
				$fingerprint,
				(int) is_multisite() ? \get_current_site()->id : 1,
				(int) apply_filters( 'wp_mainwp_stream_blog_id_logged', get_current_blog_id() )
			)
		);

		return ! empty( $record_id );
	}

	/**
	 * Public bridge for Child Sync code, which must not insert Stream rows itself.
     * @param  $fingerprint Backup fingerprint to check.
     *
     * @return bool True if the fingerprint was already logged, false otherwise.
	 */
	public static function was_fingerprint_logged( $fingerprint ) {
		return self::fingerprint_exists( $fingerprint );
	}

	/**
	 * Return whether this request logged the supplied fingerprint.
	 *
	 * @param string $fingerprint Backup fingerprint.
	 * @return bool True when the current request inserted or recognized the record.
	 */
	public static function was_fingerprint_logged_this_request( $fingerprint ) {
		$fingerprint = sanitize_text_field( $fingerprint );
		return ! empty( self::$fingerprint_logged_results[ $fingerprint ] );
	}
}
