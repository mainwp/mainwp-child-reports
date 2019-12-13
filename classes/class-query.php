<?php
namespace WP_MainWP_Stream;

class Query {
	/**
	 * Hold the number of records found
	 *
	 * @var int
	 */
	public $found_records = 0;

	/**
	 * Query records
	 *
	 * @param array Query args
	 *
	 * @return array Stream Records
	 */
	public function query( $args ) {
		global $wpdb;

		$join  = '';
		$where = '';

		/**
		 * PARSE CORE PARAMS
		 */
		if ( is_numeric( $args['site_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.site_id = %d", $args['site_id'] );
		}

		if ( is_numeric( $args['blog_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.blog_id = %d", $args['blog_id'] );
		}

		if ( is_numeric( $args['object_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.object_id = %d", $args['object_id'] );
		}

		if ( is_numeric( $args['user_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.user_id = %d", $args['user_id'] );
		}

		if ( ! empty( $args['user_role'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.user_role = %s", $args['user_role'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$field = ! empty( $args['search_field'] ) ? $args['search_field'] : 'summary';

			// Sanitize field
			$allowed_fields = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'created', 'summary', 'connector', 'context', 'action', 'ip' );
			if ( in_array( $field, $allowed_fields, true ) ) {
				$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.{$field} LIKE %s", "%{$args['search']}%" ); // @codingStandardsIgnoreLine can't prepare column name
			}
		}

		if ( ! empty( $args['connector'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.connector = %s", $args['connector'] );
		}

		if ( ! empty( $args['context'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.context = %s", $args['context'] );
		}

		if ( ! empty( $args['action'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.action = %s", $args['action'] );
		}

		if ( ! empty( $args['ip'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.ip = %s", wp_mainwp_stream_filter_var( $args['ip'], FILTER_VALIDATE_IP ) );
		}

		/**
		 * PARSE DATE PARAM FAMILY
		 */
		if ( ! empty( $args['date'] ) ) {
			$args['date_from'] = $args['date'];
			$args['date_to']   = $args['date'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $args['date_from'] . ' 00:00:00' ) ) );
			//$where .= $wpdb->prepare( " AND DATE($wpdb->mainwp_stream.created) >= %s", $date );
			$where .= " AND ($wpdb->mainwp_stream.created >= STR_TO_DATE(" . $wpdb->prepare('%s', $date) . ", '%Y-%m-%d %H:%i:%s'))";
		}

		if ( ! empty( $args['date_to'] ) ) {
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $args['date_to'] . ' 23:59:59' ) ) );
			//$where .= $wpdb->prepare( " AND DATE($wpdb->mainwp_stream.created) <= %s", $date );
			$where .= " AND ($wpdb->mainwp_stream.created <= STR_TO_DATE(" . $wpdb->prepare('%s', $date) . ", '%Y-%m-%d %H:%i:%s'))";
		}

		if ( ! empty( $args['date_after'] ) ) {
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $args['date_after'] ) ) );
			//$where .= $wpdb->prepare( " AND DATE($wpdb->mainwp_stream.created) > %s", $date );
			$where .= " AND ($wpdb->mainwp_stream.created > STR_TO_DATE(" . $wpdb->prepare('%s', $date) . ", '%Y-%m-%d %H:%i:%s'))";
		}

		if ( ! empty( $args['date_before'] ) ) {
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $args['date_before'] ) ) );
			//$where .= $wpdb->prepare( " AND DATE($wpdb->mainwp_stream.created) < %s", $date );
			$where .= " AND ($wpdb->mainwp_stream.created < STR_TO_DATE(" . $wpdb->prepare('%s', $date) . ", '%Y-%m-%d %H:%i:%s'))";
		}
		
		
		// mainwp custom parameter
		if ( ! empty( $args['created'] ) ) {
			$created = strtotime( $args['created'] );
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', $created + 5 ) );
			//$where .= $wpdb->prepare( " AND DATE($wpdb->mainwp_stream.created) <= %s", $date );
			$where .= " AND ($wpdb->mainwp_stream.created <= STR_TO_DATE(" . $wpdb->prepare('%s', $date) . ", '%Y-%m-%d %H:%i:%s'))";
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', $created - 5 ) );
			//$where .= $wpdb->prepare( " AND DATE($wpdb->mainwp_stream.created) >= %s", $date );
			$where .= " AND ($wpdb->mainwp_stream.created >= STR_TO_DATE(" . $wpdb->prepare('%s', $date) . ", '%Y-%m-%d %H:%i:%s'))";
		}

		/**
		 * PARSE __IN PARAM FAMILY
		 */
		$ins = array();

		foreach ( $args as $arg => $value ) {
			if ( '__in' === substr( $arg, -4 ) ) {
				$ins[ $arg ] = $value;
			}
		}

		if ( ! empty( $ins ) ) {
			foreach ( $ins as $key => $value ) {
				if ( empty( $value ) || ! is_array( $value ) ) {
					continue;
				}

				$field = str_replace( array( 'record_', '__in' ), '', $key );
				$field = empty( $field ) ? 'ID' : $field;
				$type  = is_numeric( array_shift( $value ) ) ? '%d' : '%s';

				if ( ! empty( $value ) ) {
					$format = '(' . join( ',', array_fill( 0, count( $value ), $type ) ) . ')';
					$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.%s IN {$format}", $field, $value ); // @codingStandardsIgnoreLine prepare okay
				}
			}
		}

		/**
		 * PARSE __NOT_IN PARAM FAMILY
		 */
		$not_ins = array();

		foreach ( $args as $arg => $value ) {
			if ( '__not_in' === substr( $arg, -8 ) ) {
				$not_ins[ $arg ] = $value;
			}
		}

		if ( ! empty( $not_ins ) ) {
			foreach ( $not_ins as $key => $value ) {
				if ( empty( $value ) || ! is_array( $value ) ) {
					continue;
				}

				$field = str_replace( array( 'record_', '__not_in' ), '', $key );
				$field = empty( $field ) ? 'ID' : $field;
				$type  = is_numeric( array_shift( $value ) ) ? '%d' : '%s';

				if ( ! empty( $value ) ) {
					$format = '(' . join( ',', array_fill( 0, count( $value ), $type ) ) . ')';
					$where .= $wpdb->prepare( " AND $wpdb->mainwp_stream.%s NOT IN {$format}", $field, $value ); // @codingStandardsIgnoreLine prepare okay
				}
			}
		}
		
		// exclude child/report plugins from log results
		if (isset($args['hide_child_reports']) && $args['hide_child_reports']) {
			$child_record_ids = array();
			$sql_meta = "SELECT record_id FROM $wpdb->mainwp_streammeta WHERE meta_key = 'slug' AND (meta_value = 'mainwp-child/mainwp-child.php' OR meta_value = 'mainwp-child-reports/mainwp-child-reports.php')";
			$ret  = $wpdb->get_results( $sql_meta, 'ARRAY_A' );

			if ( is_array($ret) && count($ret)> 0 ) {
				foreach($ret as $val) {
					$child_record_ids[] = $val['record_id'];
				}
			}
			if (count($child_record_ids) > 0) {
				$where .= " AND $wpdb->mainwp_stream.ID NOT IN (" . implode(",", $child_record_ids). ") ";
			}
		}


		/**
		 * PARSE PAGINATION PARAMS
		 */
		$limits   = '';
		$page     = absint( $args['paged'] );
		$per_page = absint( $args['records_per_page'] );

		if ( $per_page >= 0 ) {
			$offset = absint( ( $page - 1 ) * $per_page );
			$limits = "LIMIT {$offset}, {$per_page}";
		}

		/**
		 * PARSE ORDER PARAMS
		 */
		$order     = esc_sql( $args['order'] );
		$orderby   = esc_sql( $args['orderby'] );
		
		// to fix order by created
		if ($orderby == 'date')  { 
			$orderby = 'created';
		}
			
		$orderable = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'summary', 'created', 'connector', 'context', 'action' );

		if ( in_array( $orderby, $orderable, true ) ) {
			$orderby = sprintf( '%s.%s', $wpdb->mainwp_stream, $orderby );
		} elseif ( 'meta_value_num' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->mainwp_streammeta.meta_value AS SIGNED)";
		} elseif ( 'meta_value' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "$wpdb->mainwp_streammeta.meta_value";
		} else {
			$orderby = "$wpdb->mainwp_stream.ID";
		}

		$orderby = "ORDER BY {$orderby} {$order}";
		
		/**
		 * PARSE FIELDS PARAMETER
		 */
		$fields  = (array) $args['fields'];
		$selects = array();

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				// We'll query the meta table later
				if ( 'meta' === $field ) {
					continue;
				}

				$selects[] = sprintf( "$wpdb->mainwp_stream.%s", $field );
			}
		} else {
			$selects[] = "$wpdb->mainwp_stream.*";
		}

		$select = implode( ', ', $selects );

		/**
		 * BUILD THE FINAL QUERY
		 */
		$query = "SELECT SQL_CALC_FOUND_ROWS {$select}
		FROM $wpdb->mainwp_stream
		{$join}
		WHERE 1=1 {$where}
		{$orderby}
		{$limits}";
		
		/**
		 * Filter allows the final query to be modified before execution
		 *
		 * @param string $query
		 * @param array  $args
		 *
		 * @return string
		 */
		
		$query = apply_filters( 'wp_mainwp_stream_db_query', $query, $args );
				
		$items = $wpdb->get_results( $query ); // @codingStandardsIgnoreLine $query already prepared		
				
		$found_row = $items ? absint( $wpdb->get_var( 'SELECT FOUND_ROWS()' ) ) : 0;
		
		// mainwp-child custom query
		if ( isset( $args['with-meta'] ) && $args['with-meta'] && is_array( $items ) && $items ) {
			$ids      = array_map( 'absint', wp_list_pluck( $items, 'ID' ) );
            // to fix issue long query
            $start_slice = 0;
            $max_slice = 100;
            
            while( $start_slice <=  count($ids)) {
                $slice_ids = array_slice($ids, $start_slice, $max_slice);
                $start_slice += $max_slice;

                if (!empty($slice_ids)) {
                    $sql_meta = sprintf(
                        "SELECT * FROM $wpdb->mainwp_streammeta WHERE record_id IN ( %s )",
                        implode( ',', $slice_ids )
                    );

                    $meta_records  = $wpdb->get_results( $sql_meta );
                    $ids_flip = array_flip( $ids );

                    foreach ( $meta_records as $meta_record ) {							
						if ( !empty($meta_record->meta_value) ) {
							$items[ $ids_flip[ $meta_record->record_id ] ]->meta[ $meta_record->meta_key ][] = $meta_record->meta_value;						
						}
                    }
                }
            }            
		}
		

		$result = array();
		/**
		 * QUERY THE DATABASE FOR RESULTS
		 */
		$result['items'] = $items;
		$result['count'] = $found_row;

		return $result;
	}
}
