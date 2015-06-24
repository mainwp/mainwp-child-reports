<?php

class MainWP_WP_Stream_Install {

	const KEY = 'mainwp_child_reports_db';

	public static $table_prefix;

	public static $db_version;

	public static $current;

	public static $update_versions;

	public static $update_required = false;

	public static $success_db;

	private static $instance = false;

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	function __construct() {
		global $wpdb;

		self::$current    = MainWP_WP_Stream::VERSION;
		self::$db_version = self::get_db_version();
		
		$prefix = $wpdb->base_prefix;

		self::$table_prefix = apply_filters( 'mainwp_wp_stream_db_tables_prefix', $prefix );
		self::check();
	}

	private static function check() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}                
                if ( empty( self::$db_version ) ) {
			self::install( self::$current );
                        self::copy_stream_db_149();
		} elseif ( self::$db_version !== self::$current ) {

		}
	}
        
        public static function copy_stream_db_149() {
            global $wpdb;
            
            if ( is_multisite() ) {
                return;
            }
            
            $stream_db_version = get_site_option( 'wp_stream_db' );
            
            if ($stream_db_version !== '1.4.9')
                return;                        
            
            if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "stream'" ) !== $wpdb->prefix . "stream" ) 
                return;
            if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "stream_context'" ) !== $wpdb->prefix . "stream_context" ) 
                return;
            if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "stream_meta'" ) !== $wpdb->prefix . "stream_meta" ) 
                return;
            
            $sql = "SELECT * FROM {$wpdb->prefix}stream";

            $blog_stream = $wpdb->get_results( $sql, ARRAY_A );

            foreach ( $blog_stream as $key => $stream_entry ) {
                    $prev_entry_id = $stream_entry['ID'];

                    unset( $stream_entry['ID'] );
                    

                    $wpdb->insert( $wpdb->prefix . 'mainwp_stream', $stream_entry );
                    $stream_entry_id = $wpdb->insert_id;

                    $sql = "SELECT * FROM {$wpdb->prefix}stream_context WHERE record_id = $prev_entry_id";

                    $blog_stream_context = $wpdb->get_results( $sql, ARRAY_A );

                    foreach ( $blog_stream_context as $key => $stream_context ) {
                            unset( $stream_context['meta_id'] );
                            $stream_context['record_id'] = $stream_entry_id;

                            $wpdb->insert( $wpdb->prefix . 'mainwp_stream_context', $stream_context );
                    }

                    $sql = "SELECT * FROM {$wpdb->prefix}stream_meta WHERE record_id = $prev_entry_id";

                    $blog_stream_meta = $wpdb->get_results( $sql, ARRAY_A );

                    foreach ( $blog_stream_meta as $key => $stream_meta ) {
                            unset( $stream_meta['meta_id'] );
                            $stream_meta['record_id'] = $stream_entry_id;

                            $wpdb->insert( $wpdb->prefix . 'mainwp_stream_meta', $stream_meta );
                    }
            }
		
        }
        
	public static function get_db_version() {
		global $wpdb;

		$version = get_site_option( self::KEY );

		return $version;
	}

	public static function update_db_option() {
		if ( self::$success_db ) {
			$success_op = update_site_option( self::KEY, self::$current );
		}

		if ( empty( self::$success_db ) || empty( $success_op ) ) {
			wp_die( __( 'There was an error updating the MainWP Child Reports database. Please try again.', 'mainwp-child-reports' ), 'Database Update Error', array( 'response' => 200, 'back_link' => true ) );
		}
	}

	public static function update_notice_hook() {
		if ( ! current_user_can( MainWP_WP_Stream_Admin::VIEW_CAP ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['mainwp_wp_stream_update'] ) ) {
			self::prompt_update();
		} elseif ( 'update_and_continue' === $_REQUEST['mainwp_wp_stream_update'] ) {
			self::prompt_update_status();
		}
	}

	public static function prompt_update() {
		?>
		<div class="error">
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'mainwp_wp_stream_update' ) ) ?>">
				<?php wp_nonce_field( 'mainwp_wp_stream_update_db' ) ?>
				<input type="hidden" name="mainwp_wp_stream_update" value="update_and_continue"/>
				<p><strong><?php esc_html_e( 'MainWP Child Reports Database Update Required', 'mainwp-child-reports' ) ?></strong></p>
				<p><?php esc_html_e( 'MainWP Child Reports has updated! Before we send you on your way, we need to update your database to the newest version.', 'mainwp-child-reports' ) ?></p>
				<p><?php esc_html_e( 'This process could take a little while, so please be patient.', 'mainwp-child-reports' ) ?></p>
				<?php submit_button( esc_html__( 'Update Database', 'mainwp-child-reports' ), 'primary', 'stream-update-db-submit' ) ?>
			</form>
		</div>
		<?php
	}

	public static function prompt_update_status() {
		check_admin_referer( 'mainwp_wp_stream_update_db' );

		self::update_db_option();
		?>
		<div class="updated">
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'mainwp_wp_stream_update' ) ) ?>" style="display:inline;">
				<p><strong><?php esc_html_e( 'Update Complete', 'mainwp-child-reports' ) ?></strong></p>
				<p><?php esc_html_e( sprintf( 'Your MainWP Child Reports database has been successfully updated from %1$s to %2$s!', self::$db_version, self::$current ), 'mainwp-child-reports' ) ?></p>
				<?php submit_button( esc_html__( 'Continue', 'mainwp-child-reports' ), 'secondary', false ) ?>
			</form>
		</div>
		<?php
	}

	public static function db_update_versions() {
		$db_update_versions = array(
			'1.1.4' /* @version 1.1.4 Fix mysql character set issues */,
			'1.1.7' /* @version 1.1.7 Modified the ip column to varchar(39) */,
			'1.2.8' /* @version 1.2.8 Change the context for Media connectors to the attachment type */,
			'1.3.0' /* @version 1.3.0 Backward settings compatibility for old version plugins */,
			'1.3.1' /* @version 1.3.1 Update records of Installer to Theme Editor connector */,
			'1.4.0' /* @version 1.4.0 Add the author_role column and prepare tables for multisite support */,
			'1.4.2' /* @version 1.4.2 Patch to fix rare multisite upgrade not triggering */,
			'1.4.5' /* @version 1.4.5 Patch to fix author_meta broken values */,
		);

		return apply_filters( 'mainwp_wp_stream_db_update_versions', $db_update_versions );
	}

	public static function update( $db_version, $current, $update_args ) {

	}

	public static function install( $current ) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$prefix = self::$table_prefix;

		$sql = "CREATE TABLE {$prefix}mainwp_stream (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL DEFAULT '1',
			blog_id bigint(20) unsigned NOT NULL DEFAULT '0',
			object_id bigint(20) unsigned NULL,
			author bigint(20) unsigned NOT NULL DEFAULT '0',
			author_role varchar(20) NOT NULL DEFAULT '',
			summary longtext NOT NULL,
			visibility varchar(20) NOT NULL DEFAULT 'publish',
			parent bigint(20) unsigned NOT NULL DEFAULT '0',
			type varchar(20) NOT NULL DEFAULT 'stream',
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			ip varchar(39) NULL,
			PRIMARY KEY  (ID),
			KEY site_id (site_id),
			KEY blog_id (blog_id),
			KEY parent (parent),
			KEY author (author),
			KEY created (created)
		)";

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		dbDelta( $sql );

		$sql = "CREATE TABLE {$prefix}mainwp_stream_context (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			context varchar(100) NOT NULL,
			action varchar(100) NOT NULL,
			connector varchar(100) NOT NULL,
			PRIMARY KEY  (meta_id),
			KEY context (context),
			KEY action (action),
			KEY connector (connector)
		)";

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		dbDelta( $sql );

		$sql = "CREATE TABLE {$prefix}mainwp_stream_meta (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			meta_key varchar(200) NOT NULL,
			meta_value varchar(200) NOT NULL,
			PRIMARY KEY  (meta_id),
			KEY record_id (record_id),
			KEY meta_key (meta_key),
			KEY meta_value (meta_value)
		)";

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		dbDelta( $sql );

		update_site_option( self::KEY, self::$current );

		return $current;
	}
}
