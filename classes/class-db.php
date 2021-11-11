<?php
/** MainWP Child Reports Database. */

namespace WP_MainWP_Stream;

/**
 * Class DB.
 * @package WP_MainWP_Stream
 */
class DB {
	/**
	 * Hold the Driver class
	 *
	 * @var DB_Driver
	 */
	public $driver;

	/**
	 * Number of records in last request
	 *
	 * @var int
	 */
	protected $found_records_count = 0;

	/** @var $wpdb wpdb */
	private $wpdb;
	
	/**
	 * DB constructor.
	 *
	 * Run each time the class is called.
	 *
	 * @param DB_Driver $driver Driver we want to use.
	 */
	public function __construct( $driver ) {
		$this->driver = $driver;

		/** @global object $wpdb WordPress Database instance. */
		global $wpdb;

		$this->wpdb	= &$wpdb;
		
	}

	/**
	 * Insert a record
	 *
	 * @param array $record
	 *
	 * @return int
	 */
	public function insert( $record ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return false;
		}

		/**
		 * Filter allows modification of record information
		 *
		 * @param array $record
		 *
		 * @return array
		 */
		$record = apply_filters( 'wp_mainwp_stream_record_array', $record );

		array_walk(
			$record, function( &$value ) {
				if ( ! is_array( $value ) ) {
					$value = strip_tags( $value );
				}
			}
		);

		if ( empty( $record ) ) {
			return false;
		}

		$fields = array( 'object_id', 'site_id', 'blog_id', 'user_id', 'user_role', 'created', 'summary', 'ip', 'connector', 'context', 'action', 'meta' );
		$data   = array_intersect_key( $record, array_flip( $fields ) );

		$record_id = $this->driver->insert_record( $data );

		if ( ! $record_id ) {
			/**
			 * Fires on a record insertion error
			 *
			 * @param array $record
			 * @param mixed $result
			 */
			do_action( 'wp_mainwp_stream_record_insert_error', $record, false );

			return false;
		}

		/**
		 * Fires after a record has been inserted
		 *
		 * @param int   $record_id
		 * @param array $record
		 */
		do_action( 'wp_mainwp_stream_record_inserted', $record_id, $record );

		return absint( $record_id );
	}

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * GROUP BY allows query to find just the first occurrence of each value in the column,
	 * increasing the efficiency of the query.
	 *
	 * @see assemble_records
	 * @since 1.0.4
	 *
	 * @param string $column
	 *
	 * @return array
	 */
	public function existing_records( $column ) {
		// Sanitize column
		$allowed_columns = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'created', 'summary', 'connector', 'context', 'action', 'ip' );
		if ( ! in_array( $column, $allowed_columns, true ) ) {
			return array();
		}

		$rows = $this->driver->get_column_values( $column );

		if ( is_array( $rows ) && ! empty( $rows ) ) {
			$output_array = array();

			foreach ( $rows as $row ) {
				foreach ( $row as $cell => $value ) {
					$output_array[ $value ] = $value;
				}
			}

			return (array) $output_array;
		}

		$column = sprintf( 'stream_%s', $column );

		$term_labels = wp_mainwp_stream_get_instance()->connectors->term_labels;
		return isset( $term_labels[ $column ] ) ? $term_labels[ $column ] : array();
	}

	/**
	 * Get stream records
	 *
	 * @param array Query args
	 *
	 * @return array Stream Records
	 */
	public function get_records( $args ) {
		$defaults = array(
			// Search param
			'search'           => null,
			'search_field'     => 'summary',
			'record_after'     => null, // Deprecated, use date_after instead
			// Date-based filters
			'date'             => null, // Ex: 2015-07-01
			'date_from'        => null, // Ex: 2015-07-01
			'date_to'          => null, // Ex: 2015-07-01
			'date_after'       => null, // Ex: 2015-07-01T15:19:21+00:00
			'date_before'      => null, // Ex: 2015-07-01T15:19:21+00:00
			// Record ID filters
			'record'           => null,
			'record__in'       => array(),
			'record__not_in'   => array(),
			// Pagination params
			'records_per_page' => get_option( 'posts_per_page', 20 ),
			'paged'            => 1,
			// Order
			'order'            => 'desc',
			'orderby'          => 'date',
			// Fields selection
			'fields'           => array(),
			'created'          => null,
		);

		// Additional property fields
		$properties = array(
			'user_id'   => null,
			'user_role' => null,
			'ip'        => null,
			'object_id' => null,
			'site_id'   => null,
			'blog_id'   => null,
			'connector' => null,
			'context'   => null,
			'action'    => null,
		);

		/**
		 * Filter allows additional query properties to be added
		 *
		 * @return array Array of query properties
		 */
		$properties = apply_filters( 'wp_mainwp_stream_query_properties', $properties );

		// Add property fields to defaults, including their __in/__not_in variations
		foreach ( $properties as $property => $default ) {
			if ( ! isset( $defaults[ $property ] ) ) {
				$defaults[ $property ] = $default;
			}

			$defaults[ "{$property}__in" ]     = array();
			$defaults[ "{$property}__not_in" ] = array();
		}

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter allows additional arguments to query $args
		 *
		 * @return array  Array of query arguments
		 */
		$args = apply_filters( 'wp_mainwp_stream_query_args', $args );
		
		$result                    = (array) $this->driver->get_records( $args );
		$this->found_records_count = isset( $result['count'] ) ? $result['count'] : 0;
		
		return empty( $result['items'] ) ? array() : $result['items'];
	}

	/**
	 * Helper function, backwards compatibility
	 *
	 * @param array $args Query args
	 *
	 * @return array Stream Records
	 */
	public function query( $args ) {
		return $this->get_records( $args );
	}

	/**
	 * Return the number of records found in last request
	 *
	 * return int
	 */
	public function get_found_records_count() {
		return $this->found_records_count;
	}

	/**
	 * Public getter to return table names
	 *
	 * @return array
	 */
	public function get_table_names() {
		return $this->driver->get_table_names();
	}
	
//	public static function _query( $query, $link ) {
//		if ( self::use_mysqli() ) {
//			return mysqli_query( $link, $query );
//		} else {
//			return mysql_query( $query, $link );
//		}
//	}
	
//	public static function num_rows( $result ) {
//		if ( $result === false ) {
//			return 0;
//		}
//
//		if ( self::use_mysqli() ) {
//			return mysqli_num_rows( $result );
//		} else {
//			return mysql_num_rows( $result );
//		}
//	}
	
//	public function db_query( $sql ) {
//		if ( $sql == null ) {
//			return false;
//		}
//
//		$result = @self::_query( $sql, $this->wpdb->dbh );
//
//		if ( !$result || ( @self::num_rows( $result ) == 0 ) ) {
//			return false;
//		}
//
//		return $result;
//	}
	
	
//	public function fetch_object( $result ) {
//		if ( $result === false ) {
//			return false;
//		}
//
//		if ( self::use_mysqli() ) {
//			return mysqli_fetch_object( $result );
//		} else {
//			return mysql_fetch_object( $result );
//		}
//	}
	
//	public function free_result( $result ) {
//		if ( $result === false ) {
//			return false;
//		}
//
//		if ( self::use_mysqli() ) {		
//			return mysqli_free_result( $result );
//		} else {
//			return mysql_free_result( $result );
//		}
//	}
	
//	public static function use_mysqli() {		
//		if ( function_exists( 'mysqli_connect' ) ) {
//			return true;
//		}		
//		return false;
//	}								
}
