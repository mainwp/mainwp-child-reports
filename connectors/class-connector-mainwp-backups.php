<?php
/** MainWP Backups Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_MainWP_Backups
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_MainWP_Backups extends Connector {

	/** @var string Connector slug. */
	public $name = 'mainwp_backups';

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
     * @param sting $type Type of backup.
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
     * @param sting $type Type of backup.
     * @param int $backup_time Backup time.
     */
    public function callback_mainwp_reports_backupbuddy_backup($message, $type , $backup_time = 0) {
		$this->log(
			$message,
			compact('type', 'backup_time'),
			0,
			'backups',
			'backupbuddy_backup'			
		);
	}

    /**
     * Record MainWP BackupWordpress backup log.
     *
     * @param string $destination Backup destination.
     * @param string $message Log message.
     * @param string $status Backup status.
     * @param sting $type Type of backup.
     * @param int $backup_time Bakcup time.
     */
    public function callback_mainwp_reports_backupwordpress_backup($destination, $message, $status, $type, $backup_time = 0) {
		$this->log(
			$message,
			compact('destination', 'status', 'type', 'backup_time'),
			0,
			'backups',
			'backupwordpress_backup'			
		);
	}


    /**
     * Record MainWP BackupWPup backup log.
     *
     * @param string $message Log message.
     * @param sting $type Type of backup.
     * @param int $backup_time Bakcup time.
     */
    public function callback_mainwp_reports_backwpup_backup($message, $type, $backup_time ) {
		$this->log(
			$message,
			compact( 'type', 'backup_time' ),
			0,
			'backups',
			'backwpup_backup'			
		);
	}

    /**
     * Record MainWP UpdraftPlus backups log.
     *
     * @param string $destination Backup destination.
     * @param strign $message Log message.
     * @param string $status Backup status.
     * @param string $type Backup type.
     * @param int $backup_time Backup time.
     */
    public function callback_updraftplus_backup($destination, $message, $status, $type, $backup_time) {
		$this->log(
			$message,
			compact('destination', 'status', 'type', 'backup_time'),
			0,
			'backups',
			'updraftplus_backup'			
		);
	}

    /**
     * Record MainWP WPTimeCapsule backups log.
     *
     * @param strign $message Log message.
     * @param string $type Backup type.
     * @param int $backup_time Backup time.
     */
    public function callback_mainwp_reports_wptimecapsule_backup($message, $type, $backup_time ) {
		$this->log(
			$message,
			compact( 'type', 'backup_time' ),
			0,
			'backups',
			'wptimecapsule_backup'			
		);
	}

    /**
     * Record MainWP WPvivid backup log.
     *
     * @param string $destination Backup destination.
     * @param strign $message Log message.
     * @param string $status Backup status.
     * @param string $type Backup type.
     * @param int $backup_time Backup time.
     */
    public function callback_wpvivid_backup($destination, $message, $status, $type, $backup_time){
        $this->log(
            $message,
            compact( 'destination', 'status', 'type', 'backup_time' ),
            0,
            'backups',
            'wpvivid_backup'
        );
    }
}


