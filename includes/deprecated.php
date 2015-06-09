<?php

/**
 * Handle deprecated filters
 */

global $mainwp_wp_stream_deprecated_filters;

$mainwp_wp_stream_deprecated_filters = array(
	array(
		'old'     => 'stream_query_args',
		'new'     => 'mainwp_wp_stream_query_args',
		'version' => '1.3.2',
	),
	array(
		'old'     => 'stream_toggle_filters',
		'new'     => 'mainwp_wp_stream_toggle_filters',
		'version' => '1.3.2',
	),
);

foreach ( $mainwp_wp_stream_deprecated_filters as $filter ) {
	add_filter( $filter['new'], 'mainwp_wp_stream_deprecated_filter_mapping' );
}

function mainwp_wp_stream_deprecated_filter_mapping( $data ) {
	global $mainwp_wp_stream_deprecated_filters;

	$new_filter = current_filter();
	$old_filter = false;
	$version    = false;

	foreach ( $mainwp_wp_stream_deprecated_filters as $key => $filter ) {
		if ( $new_filter === $filter['new'] ) {
			$old_filter = $filter['old'];
			$version    = $filter['version'];
			break;
		}
	}

	if ( ! $old_filter || ! has_filter( $old_filter ) ) {
		return $data;
	}

	$filter_args = array_merge(
		array(
			$old_filter,
		),
		func_get_args()
	);

	$data = call_user_func_array( 'apply_filters', $filter_args );

	_deprecated_function(
		sprintf( __( 'The %s filter', 'mainwp-child-reports' ), $old_filter ),
		$version,
		$new_filter
	);

	return $data;
}

/**
 * stream_query()
 *
 * @deprecated 1.3.2
 * @deprecated Use mainwp_wp_stream_query()
 * @see mainwp_wp_stream_query()
 */
//function stream_query( $args = array() ) {
//	_deprecated_function( __FUNCTION__, '1.3.2', 'mainwp_wp_stream_query()' );
//
//	return mainwp_wp_stream_query( $args );
//}

/**
 * get_stream_meta()
 *
 * @deprecated 1.3.2
 * @deprecated Use mainwp_wp_stream_get_meta
 * @see mainwp_wp_stream_get_meta()
 */
//function get_stream_meta( $record_id, $key = '', $single = false ) {
//	_deprecated_function( __FUNCTION__, '1.3.2', 'mainwp_wp_stream_get_meta()' );
//
//	return mainwp_wp_stream_get_meta( $record_id, $key, $single );
//}

/**
 * update_stream_meta()
 *
 * @deprecated 1.3.2
 * @deprecated Use mainwp_wp_stream_update_meta
 * @see mainwp_wp_stream_update_meta()
 */
//function update_stream_meta( $record_id, $meta_key, $meta_value, $prev_value = '' ) {
//	_deprecated_function( __FUNCTION__, '1.3.2', 'mainwp_wp_stream_update_meta()' );
//
//	return mainwp_wp_stream_update_meta( $record_id, $meta_key, $meta_value, $prev_value );
//}

/**
 * existing_records()
 *
 * @deprecated 1.3.2
 * @deprecated Use mainwp_wp_stream_existing_records
 * @see mainwp_wp_stream_existing_records()
 */
//function existing_records( $column, $table = '' ) {
//	_deprecated_function( __FUNCTION__, '1.3.2', 'mainwp_wp_stream_existing_records()' );
//
//	return mainwp_wp_stream_existing_records( $column, $table );
//}
