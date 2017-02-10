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

	public static $import_connectors;
	
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
		self::$import_connectors = array('comment', 'editor', 'installer', 'media', 'menus', 'posts', 'users', 'widgets');
		self::check();
	}

	private static function check() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}   
		
		global $wpdb;	
                
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "mainwp_stream'" ) !== $wpdb->prefix . "mainwp_stream" ||
                        $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "mainwp_stream_meta'" ) !== $wpdb->prefix . "mainwp_stream_meta" 
                        )  {
			self::$db_version = false;	
                } else {                    
                    if (false === get_option('mainwp_creport_first_time_activated')) {                        
                        $sql = "SELECT MIN( created ) AS first_time " .
                                "FROM {$wpdb->prefix}mainwp_stream " . 
                                "WHERE created != '0000-00-00 00:00:00'";                                
                        $result = $wpdb->get_results( $sql, ARRAY_A );                 
                        $time = time();
                        if (isset($result[0]) && !empty($result[0]['first_time'])) {
                            $time = strtotime( $result[0]['first_time'] );                            
                        } 
                        update_option('mainwp_creport_first_time_activated', $time);
                    }
                }
		
		if ( empty( self::$db_version ) ) {
			self::install( self::$current );		
			self::copy_stream_db();	
		} elseif ( version_compare( self::$db_version, self::$current, '!=') ) {                    
                        self::check_updates();
			update_site_option( self::KEY, self::$current );			
		}	
		
		if ('yes' == get_option('mainwp_child_reports_check_to_copy_data', false)) {
			add_action( 'all_admin_notices', array( __CLASS__, 'update_notice_hook' ) );						
		}
	}
    
	public static function check_to_copy_data() {		
		$stream_db_version = get_site_option( 'wp_stream_db' ); // store db version of the plugin stream 1.4.9					
		update_option('mainwp_child_reports_check_to_copy_data', 'yes');
		return;
	}
	
	public static function copy_stream_db() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}				
		$stream_db_version = get_site_option( 'wp_stream_db' ); // store db version of the plugin stream 1.4.9			
		if (version_compare($stream_db_version, '1.4.9', '='))					                
			self::copy_stream_149_db();
		else if (version_compare($stream_db_version, '3.0' , '>=')) {
			self::copy_stream_300_db();
		}
		update_option('mainwp_child_reports_copied_data_ok', 'yes');
		update_option('mainwp_child_reports_check_to_copy_data', '');
	}
	
	public static function copy_stream_149_db() {
            global $wpdb;            
            if ( is_multisite() ) {
                return;
            }            
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
        
	public static function copy_stream_300_db() {
		global $wpdb;
		if ( is_multisite() ) {
			return;
		}
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "stream'" ) !== $wpdb->prefix . "stream" ) 
			return;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "stream_meta'" ) !== $wpdb->prefix . "stream_meta" ) 
			return;

		$timeout = 20 * 60 * 60; /*20 minutes*/
		$mem           = '512M';
		// @codingStandardsIgnoreStart
		@ini_set( 'memory_limit', $mem );
		@set_time_limit( $timeout );
		@ini_set( 'max_execution_time', $timeout );
		
		$sql = "SELECT * FROM {$wpdb->prefix}stream";

		$blog_stream = $wpdb->get_results( $sql, ARRAY_A );
		$printed_connector = array();
		
		foreach ( $blog_stream as $key => $stream_entry ) {
			
				if (!in_array($stream_entry['connector'], self::$import_connectors)) {
					continue;
				}
				
				if ('users' == $stream_entry['connector'] && 'login' == $stream_entry['action']) {
					continue;
				}
					
				$prev_entry_id = $stream_entry['ID'];				
				
				$insert_entry = array(
					'site_id' => $stream_entry['site_id'], 
					'blog_id' => $stream_entry['blog_id'],
					'object_id' => $stream_entry['object_id'],
					'author' => $stream_entry['user_id'],
					'author_role' => $stream_entry['user_role'],
					'author_role' => $stream_entry['user_role'],
					'summary' => $stream_entry['summary'],
					'visibility' => 'publish',
					'parent' => 0,
					'type' => 'stream',
					'created' => $stream_entry['created'],
					'ip' => $stream_entry['ip']
				);
				
				$wpdb->insert( $wpdb->prefix . 'mainwp_stream', $insert_entry );
				$stream_entry_id = $wpdb->insert_id;
				
				$insert_context = array(										
					'record_id'	=> $stream_entry_id,
					'context'	=> $stream_entry['context'],
					'action'	=> $stream_entry['action'],
					'connector'	=> $stream_entry['connector']
				);
				$wpdb->insert( $wpdb->prefix . 'mainwp_stream_context', $insert_context );				

				$sql = "SELECT * FROM {$wpdb->prefix}stream_meta WHERE record_id = $prev_entry_id";

				$blog_stream_meta = $wpdb->get_results( $sql, ARRAY_A );

				foreach ( $blog_stream_meta as $key => $stream_meta ) {
						unset( $stream_meta['meta_id'] );
						$stream_meta['record_id'] = $stream_entry_id;
						$wpdb->insert( $wpdb->prefix . 'mainwp_stream_meta', $stream_meta );
				}
		}

	}

	public static function update_notice_hook() {
//		if ( ! current_user_can( WP_Stream_Admin::VIEW_CAP ) ) {
//			return;
//		}
		
		if ( ! isset( $_REQUEST['mainwp_wp_stream_update'] ) ) {
			self::prompt_copy_data();
		} else { 
			check_admin_referer( 'mainwp_wp_stream_update_db' );
			if ( isset( $_REQUEST['mainwp_reports_copy_db_submit'] ) ) {						
				self::copy_stream_db();	
				
			} else if ( isset( $_REQUEST['mainwp_reports_continue_submit'] ) ) {
				update_option('mainwp_child_reports_check_to_copy_data', '');
			}
		}
		
		if ('yes' == get_option('mainwp_child_reports_copied_data_ok')) {
			self::prompt_copy_data_status();			
		}
	}
	
	public static function prompt_copy_data() {
		?>
		<div class="updated">
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'message' ) ) ?>">
				<?php wp_nonce_field( 'mainwp_wp_stream_update_db' ) ?>
				<input type="hidden" name="mainwp_wp_stream_update" value="not_update_and_continue"/>
				<p><strong><?php esc_html_e( 'Do you want to import logs from the Stream plugin?', 'mainwp-child-reports' ) ?></strong></p>				
				<p class="submit">
					<?php submit_button( esc_html__( 'Yes', 'mainwp-child-reports' ), 'primary', 'mainwp_reports_copy_db_submit', false ) ?>
					<?php submit_button( esc_html__( 'No', 'mainwp-child-reports' ), 'primary', 'mainwp_reports_continue_submit', false ) ?>
				</p
			</form>
		</div>
		<?php
	}
	
	public static function prompt_copy_data_status() {		
		printf( '<div class="updated"><p>%s</p></div>', __( 'Logs have been successfully imported.', 'mainwp-child-reports' ) );
		delete_option('mainwp_child_reports_copied_data_ok');		
	}
	
	public static function get_db_version() {

		$version = get_site_option( self::KEY );
		
		return $version;
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
			meta_value text NOT NULL,
			PRIMARY KEY  (meta_id),
			KEY record_id (record_id),
			KEY meta_key (meta_key(100))
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

        public static function check_updates() {
            global $wpdb;
            
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $current_version = self::get_db_version();
            
            $prefix = self::$table_prefix;
                
            if (version_compare($current_version, '0.0.4', '<')) {
                $wpdb->query( "ALTER TABLE {$prefix}mainwp_stream_meta CHANGE `meta_value` `meta_value` TEXT " . ( !empty($wpdb->charset) ? "CHARACTER SET " . $wpdb->charset : "" ) . ( !empty($wpdb->collate) ? " COLLATE " . $wpdb->collate : "" ) . " NOT NULL;");                
                if ( $wpdb->get_var( "SHOW INDEX FROM {$prefix}mainwp_stream_meta WHERE column_name = 'meta_value'")) {
                    $wpdb->query( "ALTER TABLE {$prefix}mainwp_stream_meta DROP INDEX meta_value");
                }
            }  
            
        }        
}
