<?php

class MainWP_WP_Stream_Query {

	public static $instance;

	public static function get_instance() {
		if ( ! self::$instance ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

	public function query( $args ) {
		global $wpdb;

		$defaults = array(
			// Pagination params
			'records_per_page'      => get_option( 'posts_per_page' ),
			'paged'                 => 1,
			// Search param
			'search'                => null,
			// Stream core fields filtering
			'type'                  => 'stream',
			'object_id'             => null,
			'ip'                    => null,
			'site_id'               => is_multisite() ? get_current_site()->id : 1,
			'blog_id'               => is_network_admin() ? null : get_current_blog_id(),
			// Author params
			'author'                => null,
			'author_role'           => null,
			// Date-based filters
			'date'                  => null,
			'date_from'             => null,
			'date_to'               => null,
			// Visibility filters
			'visibility'            => null,
			// __in params
			'record_greater_than'   => null,
			'created_greater_than'   => null,
			'record__in'            => array(),
			'record__not_in'        => array(),
			'record_parent'         => '',
			'record_parent__in'     => array(),
			'record_parent__not_in' => array(),
			'author__in'            => array(),
			'author__not_in'        => array(),
			'author_role__in'       => array(),
			'author_role__not_in'   => array(),
			'ip__in'                => array(),
			'ip__not_in'            => array(),
			// Order
			'order'                 => 'desc',
			'orderby'               => 'created',
			// Meta/Taxonomy sub queries
			'meta_query'            => array(),
			'context_query'         => array(),
			// Fields selection
			'fields'                => '',
			'ignore_context'        => null,
			// Hide records that match the exclude rules
			'hide_excluded'         => ! empty( MainWP_WP_Stream_Settings::$options['exclude_hide_previous_records'] ),
		);

		$args = wp_parse_args( $args, $defaults );
		
		$args = apply_filters( 'mainwp_wp_stream_query_args', $args );

		if ( true === $args['hide_excluded'] ) {
			$args = self::add_excluded_record_args( $args );
		}

		$join  = '';
		$where = '';

		// Only join with context table for correct types of records
		if ( ! $args['ignore_context'] ) {
			$join = sprintf(
				' INNER JOIN %1$s ON ( %1$s.record_id = %2$s.ID )',
				$wpdb->mainwp_reportscontext,
				$wpdb->mainwp_reports
			);
		}

		/**
		 * PARSE CORE FILTERS
		 */
		if ( $args['object_id'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.object_id = %d", $args['object_id'] );
		}

		if ( $args['type'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.type = %s", $args['type'] );
		}

		if ( $args['ip'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.ip = %s", mainwp_wp_stream_filter_var( $args['ip'], FILTER_VALIDATE_IP ) );
		}

		if ( is_numeric( $args['site_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.site_id = %d", $args['site_id'] );
		}

		if ( is_numeric( $args['blog_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.blog_id = %d", $args['blog_id'] );
		}

		if ( $args['search'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.summary LIKE %s", "%{$args['search']}%" );
		}

		if ( $args['author'] || '0' === $args['author'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.author = %d", (int) $args['author'] );
		}

		if ( $args['author_role'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.author_role = %s", $args['author_role'] );
		}

		if ( $args['visibility'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.visibility = %s", $args['visibility'] );
		}
                
                if (isset($args['hide_child_reports']) && $args['hide_child_reports']) {                    
                        $child_record_ids = array();
                        $sql_meta = "SELECT record_id FROM $wpdb->mainwp_reportsmeta WHERE meta_key = 'slug' AND (meta_value = 'mainwp-child/mainwp-child.php' OR meta_value = 'mainwp-child-reports/mainwp-child-reports.php')";                                
                        $ret  = $wpdb->get_results( $sql_meta, 'ARRAY_A' );                         
                        
                        if (is_array($ret) && count($ret)> 0) {
                            foreach($ret as $val) {
                                $child_record_ids[] = $val['record_id'];
                            }
                        }
                        if (count($child_record_ids) > 0) {
                            $where .= " AND $wpdb->mainwp_reports.ID NOT IN (" . implode(",", $child_record_ids). ") ";
                        }                                          
                }
                
		/**
		 * PARSE DATE FILTERS
		 */
		if ( isset( $args['date'] ) && !empty( $args['date'] ) ) {
			$where .= $wpdb->prepare( " AND DATE($wpdb->mainwp_reports.created) = %s", $args['date'] );
		} else {
			if ( isset($args['date_from']) && !empty($args['date_from']) ) {
				$where .= $wpdb->prepare( " AND DATE($wpdb->mainwp_reports.created) >= %s", $args['date_from'] );
			}
			if ( isset($args['date_to']) && !empty($args['date_to']) ) {
				$where .= $wpdb->prepare( " AND DATE($wpdb->mainwp_reports.created) <= %s", $args['date_to'] );
			}                        
                        if ( isset($args['datetime_from']) && !empty($args['datetime_from']) ) {
				$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.created >= %s", $args['datetime_from'] );
			}
		} 

		/**
		 * PARSE __IN PARAM FAMILY
		 */
		if ( $args['record_greater_than'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.ID > %d", (int) $args['record_greater_than'] );
		}

		if ( $args['created_greater_than'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.created > %s", date('Y-m-d H:i:s', $args['created_greater_than'] ) );
		}
		
		if ( $args['record__in'] ) {
			$record__in = array_filter( (array) $args['record__in'], 'is_numeric' );
			if ( ! empty( $record__in ) ) {
				$record__in_format = '(' . join( ',', array_fill( 0, count( $record__in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.ID IN {$record__in_format}", $record__in );
			}
		}

		if ( $args['record__not_in'] ) {
			$record__not_in = array_filter( (array) $args['record__not_in'], 'is_numeric' );
			if ( ! empty( $record__not_in ) ) {
				$record__not_in_format = '(' . join( ',', array_fill( 0, count( $record__not_in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.ID NOT IN {$record__not_in_format}", $record__not_in );
			}
		}

		if ( $args['record_parent'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.parent = %d", (int) $args['record_parent'] );
		}

		if ( $args['record_parent__in'] ) {
			$record_parent__in = array_filter( (array) $args['record_parent__in'], 'is_numeric' );
			if ( ! empty( $record_parent__in ) ) {
				$record_parent__in_format = '(' . join( ',', array_fill( 0, count( $record_parent__in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.parent IN {$record_parent__in_format}", $record_parent__in );
			}
		}

		if ( $args['record_parent__not_in'] ) {
			$record_parent__not_in = array_filter( (array) $args['record_parent__not_in'], 'is_numeric' );
			if ( ! empty( $record_parent__not_in ) ) {
				$record_parent__not_in_format = '(' . join( ',', array_fill( 0, count( $record_parent__not_in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.parent NOT IN {$record_parent__not_in_format}", $record_parent__not_in );
			}
		}

		if ( $args['author__in'] ) {
			$author__in = array_filter( (array) $args['author__in'], 'is_numeric' );
			if ( ! empty( $author__in ) ) {
				$author__in_format = '(' . join( ',', array_fill( 0, count( $author__in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.author IN {$author__in_format}", $author__in );
			}
		}

		if ( $args['author__not_in'] ) {
			$author__not_in = array_filter( (array) $args['author__not_in'], 'is_numeric' );
			if ( ! empty( $author__not_in ) ) {
				$author__not_in_format = '(' . join( ',', array_fill( 0, count( $author__not_in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.author NOT IN {$author__not_in_format}", $author__not_in );
			}
		}

		if ( $args['author_role__in'] ) {
			if ( ! empty( $args['author_role__in'] ) ) {
				$author_role__in = '(' . join( ',', array_fill( 0, count( $args['author_role__in'] ), '%s' ) ) . ')';
				$where          .= $wpdb->prepare( " AND $wpdb->mainwp_reports.author_role IN {$author_role__in}", $args['author_role__in'] );
			}
		}

		if ( $args['author_role__not_in'] ) {
			if ( ! empty( $args['author_role__not_in'] ) ) {
				$author_role__not_in = '(' . join( ',', array_fill( 0, count( $args['author_role__not_in'] ), '%s' ) ) . ')';
				$where              .= $wpdb->prepare( " AND $wpdb->mainwp_reports.author_role NOT IN {$author_role__not_in}", $args['author_role__not_in'] );
			}
		}

		if ( $args['ip__in'] ) {
			if ( ! empty( $args['ip__in'] ) ) {
				$ip__in = '(' . join( ',', array_fill( 0, count( $args['ip__in'] ), '%s' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->mainwp_reports.ip IN {$ip__in}", $args['ip__in'] );
			}
		}

		if ( $args['ip__not_in'] ) {
			if ( ! empty( $args['ip__not_in'] ) ) {
				$ip__not_in = '(' . join( ',', array_fill( 0, count( $args['ip__not_in'] ), '%s' ) ) . ')';
				$where     .= $wpdb->prepare( " AND $wpdb->mainwp_reports.ip NOT IN {$ip__not_in}", $args['ip__not_in'] );
			}
		}

		/**
		 * PARSE META QUERY PARAMS
		 */
		$meta_query = new WP_Meta_Query;
		$meta_query->parse_query_vars( $args );

		if ( ! empty( $meta_query->queries ) ) {
			$mclauses = $meta_query->get_sql( 'mainwp-child-reports', $wpdb->mainwp_reports, 'ID' );
			$join    .= str_replace( 'stream_id', 'record_id', $mclauses['join'] );
			$where   .= str_replace( 'stream_id', 'record_id', $mclauses['where'] );
		}

		/**
		 * PARSE CONTEXT PARAMS
		 */
		if ( ! $args['ignore_context'] ) {
			$context_query = new MainWP_WP_Stream_Context_Query( $args );
			$cclauses      = $context_query->get_sql();
			$join         .= $cclauses['join'];
			$where        .= $cclauses['where'];
		}

		/**
		 * PARSE PAGINATION PARAMS
		 */
		$page    = intval( $args['paged'] );
		$perpage = intval( $args['records_per_page'] );

		if ( $perpage >= 0 ) {
			$offset = ($page - 1) * $perpage;
			$limits = "LIMIT $offset, {$perpage}";
		} else {
			$limits = '';
		}

		/**
		 * PARSE ORDER PARAMS
		 */
		$order     = esc_sql( $args['order'] );
		$orderby   = esc_sql( $args['orderby'] );
		$orderable = array( 'ID', 'site_id', 'blog_id', 'object_id', 'author', 'author_role', 'summary', 'visibility', 'parent', 'type', 'created' );

		if ( in_array( $orderby, $orderable ) ) {
			$orderby = $wpdb->mainwp_reports . '.' . $orderby;
		} elseif ( in_array( $orderby, array( 'connector', 'context', 'action' ) ) ) {
			$orderby = $wpdb->mainwp_reportscontext . '.' . $orderby;
		} elseif ( 'meta_value_num' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->mainwp_reportsmeta.meta_value AS SIGNED)";
		} elseif ( 'meta_value' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "$wpdb->mainwp_reportsmeta.meta_value";
		} else {
			$orderby = "$wpdb->mainwp_reports.ID";
		}
		$orderby = 'ORDER BY ' . $orderby . ' ' . $order;

		/**
		 * PARSE FIELDS PARAMETER
		 */
		$fields = $args['fields'];
		$select = "$wpdb->mainwp_reports.*";

		if ( ! $args['ignore_context'] ) {
			$select .= ", $wpdb->mainwp_reportscontext.context, $wpdb->mainwp_reportscontext.action, $wpdb->mainwp_reportscontext.connector";
		}

		if ( 'ID' === $fields ) {
			$select = "$wpdb->mainwp_reports.ID";
		} elseif ( 'summary' === $fields ) {
			$select = "$wpdb->mainwp_reports.summary, $wpdb->mainwp_reports.ID";
		}

		/**
		 * BUILD UP THE FINAL QUERY
		 */
		$sql = "SELECT SQL_CALC_FOUND_ROWS $select
		FROM $wpdb->mainwp_reports
		$join
		WHERE 1=1 $where
		$orderby
		$limits";
                
		$sql = apply_filters( 'mainwp_wp_stream_query', $sql, $args );
                
                //error_log($sql);
                
		$results = $wpdb->get_results( $sql );

		if ( 'with-meta' === $fields && is_array( $results ) && $results ) {
			$ids      = array_map( 'absint', wp_list_pluck( $results, 'ID' ) );
			$sql_meta = sprintf(
				"SELECT * FROM $wpdb->mainwp_reportsmeta WHERE record_id IN ( %s )",
				implode( ',', $ids )
			);

			$meta  = $wpdb->get_results( $sql_meta );
			$ids_f = array_flip( $ids );

			foreach ( $meta as $meta_record ) {
				$results[ $ids_f[ $meta_record->record_id ] ]->meta[ $meta_record->meta_key ][] = $meta_record->meta_value;
			}
		}

		return $results;
	}

	public static function add_excluded_record_args( $args ) {
		// Remove record of excluded connector
		$args['connector__not_in'] = MainWP_WP_Stream_Settings::get_excluded_by_key( 'connectors' );

		// Remove record of excluded context
		$args['context__not_in'] = MainWP_WP_Stream_Settings::get_excluded_by_key( 'contexts' );

		// Remove record of excluded actions
		$args['action__not_in'] = MainWP_WP_Stream_Settings::get_excluded_by_key( 'actions' );

		// Remove record of excluded author
		$args['author__not_in'] = MainWP_WP_Stream_Settings::get_excluded_by_key( 'authors' );

		// Remove record of excluded author role
		$args['author_role__not_in'] = MainWP_WP_Stream_Settings::get_excluded_by_key( 'roles' );

		// Remove record of excluded ip
		$args['ip__not_in'] = MainWP_WP_Stream_Settings::get_excluded_by_key( 'ip_addresses' );

		return $args;
	}

}

function mainwp_wp_stream_query( $args = array() ) {
	return MainWP_WP_Stream_Query::get_instance()->query( $args );
}

function mainwp_wp_stream_get_meta( $record_id, $key = '', $single = false ) {
	return maybe_unserialize( get_metadata( 'record', $record_id, $key, $single ) );
}

function mainwp_wp_stream_update_meta( $record_id, $meta_key, $meta_value, $prev_value = '' ) {
	return update_metadata( 'record', $record_id, $meta_key, $meta_value, $prev_value );
}

function mainwp_wp_stream_existing_records( $column, $table = '' ) {
	global $wpdb;

	switch ( $table ) {
		case 'stream' :
			$rows = $wpdb->get_results( "SELECT {$column} FROM {$wpdb->mainwp_reports} GROUP BY {$column}", 'ARRAY_A' );
			break;
		case 'meta' :
			$rows = $wpdb->get_results( "SELECT {$column} FROM {$wpdb->mainwp_reportsmeta} GROUP BY {$column}", 'ARRAY_A' );
			break;
		default :
			$rows = $wpdb->get_results( "SELECT {$column} FROM {$wpdb->mainwp_reportscontext} GROUP BY {$column}", 'ARRAY_A' );
	}

	if ( is_array( $rows ) && ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			foreach ( $row as $cell => $value ) {
				$output_array[ $value ] = $value;
			}
		}
		return (array) $output_array;
	} else {
		$column = sprintf( 'stream_%s', $column );
		return isset( MainWP_WP_Stream_Connectors::$term_labels[ $column ] ) ? MainWP_WP_Stream_Connectors::$term_labels[ $column ] : array();
	}
}
