<?php

class MainWP_WP_Stream_DB {

	public static $instance;

	public static $table;

	public static $table_meta;

	public static $table_context;

	public function __construct() {
		global $wpdb;

		$prefix = apply_filters( 'mainwp_wp_stream_db_tables_prefix', $wpdb->base_prefix );

		self::$table         = $prefix . 'mainwp_stream';
		self::$table_meta    = $prefix . 'mainwp_stream_meta';
		self::$table_context = $prefix . 'mainwp_stream_context';

		$wpdb->mainwp_reports        = self::$table;
		$wpdb->mainwp_reportsmeta    = self::$table_meta;
		$wpdb->mainwp_reportscontext = self::$table_context;

		// Hack for get_metadata
		$wpdb->recordmeta = self::$table_meta;
	}

	public static function get_instance() {
		if ( ! self::$instance ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

	public function get_table_names() {
		return array(
			self::$table,
			self::$table_meta,
			self::$table_context,
		);
	}

	public function insert( $recordarr ) {
		global $wpdb;

		$recordarr = apply_filters( 'mainwp_wp_stream_record_array', $recordarr );

		// Allow extensions to handle the saving process
		if ( empty( $recordarr ) ) {
			return;
		}

		$fields = array( 'object_id', 'site_id', 'blog_id', 'author', 'author_role', 'created', 'summary', 'parent', 'visibility', 'ip' );
		$data   = array_intersect_key( $recordarr, array_flip( $fields ) );
		$data   = array_filter( $data );

		// TODO: Check/Validate *required* fields

		$result = $wpdb->insert(
			self::$table,
			$data
		);

		if ( 1 === $result ) {
			$record_id = $wpdb->insert_id;
		} else {
			do_action( 'mainwp_wp_stream_post_insert_error', $recordarr );
			return $result;
		}

		self::$instance->prev_record = $record_id;

		$connector = $recordarr['connector'];

		foreach ( (array) $recordarr['contexts'] as $context => $action ) {
			$this->insert_context( $record_id, $connector, $context, $action );
		}

		foreach ( $recordarr['meta'] as $key => $vals ) {
			// If associative array, serialize it, otherwise loop on its members
			if ( is_array( $vals ) && 0 !== key( $vals ) ) {
				$vals = array( $vals );
			}
			foreach ( (array) $vals as $val ) {
				$val = maybe_serialize( $val );
                                if (empty($val))
                                    continue;
				$this->insert_meta( $record_id, $key, $val );
			}
		}

		do_action( 'mainwp_wp_stream_post_inserted', $record_id, $recordarr );

		return $record_id;
	}

	public function insert_context( $record_id, $connector, $context, $action ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::$table_context,
			array(
				'record_id' => $record_id,
				'connector' => $connector,
				'context'   => $context,
				'action'    => $action,
			)
		);

		return $result;
	}

	
	public function get_report( $args = array() ) {
		if (!is_array($args))
			return false;
		global $wpdb;
		$where = "";	
		$left_join = "";		
		if (isset($args['context'])) {
			$left_join = " LEFT JOIN " . self::$table_context . " AS `context` ON `stream`.`ID` = `context`.`record_id` " ;				
		}
		
		foreach ($args as $key => $value) {
			if ($key == 'context') {
				$where .= $wpdb->prepare( ' `context`.`context` = %s AND ', $value); 			
			} else 
				$where .= $wpdb->prepare( ' `stream`.`' . $key . '` = %s AND ', $value); 			
		}		
		
		$where = rtrim($where, "AND ");
		
		if (!empty($where)) {		
			$where .= " AND blog_id = " . apply_filters( 'blog_id_logged', is_network_admin() ? 0 : get_current_blog_id() );									
			$result = $wpdb->get_row( 'SELECT `stream`.* FROM ' . self::$table . ' AS `stream` ' . $left_join . ' WHERE ' . $where );			
			return $result;
		}
		return false;
	}
	
	public function delete_report( $args = array() ) {		
		if (!is_array($args))
			return false;	
		
		global $wpdb;
		$sql = "";
		
		foreach ($args as $key => $value) {
			$sql .= $key ." = " . $value . " AND "; 
		}
					
		$sql = rtrim($sql, "AND ");
		
		if ( ! empty( $sql ) ) {
			$sql .= " AND blog_id = " . apply_filters( 'blog_id_logged', is_network_admin() ? 0 : get_current_blog_id() );
			
			$sql = $wpdb->prepare( 'SELECT ID  FROM ' . self::$table . ' WHERE %s ', $sql );
			$record_id = $wpdb->get_var( $sql );			
			if ($record_id) {			
				$sql = $wpdb->prepare( 'DELETE FROM ' . self::$table . ' WHERE %s ', $sql );
				$wpdb->query( $sql ); 		
				$sql = $wpdb->prepare( 'DELETE FROM ' . self::$table_context . ' WHERE record_id = %d ', $record_id );
				$wpdb->query( $sql ); 
				$sql = $wpdb->prepare( 'DELETE FROM ' . self::$table_meta . ' WHERE record_id = %d ', $record_id );
				$wpdb->query( $sql ); 
				return true;
			}
		}
		return false;
	}
	
	public function insert_meta( $record_id, $key, $val ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::$table_meta,
			array(
				'record_id'  => $record_id,
				'meta_key'   => $key,
				'meta_value' => $val,
			)
		);

		return $result;
	}

}
