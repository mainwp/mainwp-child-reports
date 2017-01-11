<?php

class MainWP_WP_Stream_Live_Update {

	public static $list_table = null;

	public static function load() {
		// Heartbeat live update
		add_filter( 'heartbeat_received', array( __CLASS__, 'heartbeat_received' ), 10, 2 );

		// Enable/Disable live update per user
		add_action( 'wp_ajax_mainwp_stream_enable_live_update', array( __CLASS__, 'enable_live_update' ) );

	}

	public static function enable_live_update() {
		check_ajax_referer( 'mainwp_creport_live_update_records_nonce', 'nonce' );

		$input = array(
			'checked' => FILTER_SANITIZE_STRING,
			'user'    => FILTER_SANITIZE_STRING,
		);

		$input = filter_input_array( INPUT_POST, $input );

		if ( false === $input ) {
			wp_send_json_error( 'Error in live update checkbox' );
		}

		$checked = ( 'checked' === $input['checked'] ) ? 'on' : 'off';

		$user = (int) $input['user'];

		$success = update_user_meta( $user, 'stream_live_update_records', $checked );

		if ( $success ) {
			wp_send_json_success( 'Live Updates Enabled' );
		} else {
			wp_send_json_error( 'Live Updates checkbox error' );
		}
	}

	public static function live_update( $response, $data ) {		
		if ( ! isset( $data['wp-mainwp-stream-heartbeat-last-id'] ) ) {
			return;
		}
		
		if ( ! isset( $data['wp-mainwp-stream-heartbeat-last-created'] ) ) {
			return;
		}

		$last_id = intval( $data['wp-mainwp-stream-heartbeat-last-id'] );
		$last_created = intval( $data['wp-mainwp-stream-heartbeat-last-created'] );
		
		$query   = $data['wp-mainwp-stream-heartbeat-query'];
		if ( empty( $query ) ) {
			$query = array();
		}

		// Decode the query
		$query = json_decode( wp_kses_stripslashes( $query ) );
                
		$updated_items = MainWP_WP_Stream_Dashboard_Widget::gather_updated_items( $last_id, $query, $last_created );
                
		if ( ! empty( $updated_items ) ) {
			ob_start();
			foreach ( $updated_items as $item ) {
				self::$list_table->single_row( $item );
			}

			$send = ob_get_clean();
		} else {
			$send = '';
		}

		return $send;
	}

	public static function heartbeat_received( $response, $data ) {
		$option                  = get_option( 'dashboard_mainwp_stream_activity_options' );
		$enable_stream_update    = true; //( 'off' !== get_user_meta( get_current_user_id(), 'stream_live_update_records', true ) );
		$enable_dashboard_update = false; //( 'off' !== ( $option['live_update'] ) );

		// Register list table
		require_once MAINWP_WP_STREAM_INC_DIR . 'list-table.php';
		self::$list_table = new MainWP_WP_Stream_List_Table( array( 'screen' => 'mainwp-child_page_' . MainWP_WP_Stream_Admin::RECORDS_PAGE_SLUG ) );
		self::$list_table->prepare_items();

		$total_items = isset( self::$list_table->_pagination_args['total_items'] ) ? self::$list_table->_pagination_args['total_items'] : null;
		$total_pages = isset( self::$list_table->_pagination_args['total_pages'] ) ? self::$list_table->_pagination_args['total_pages'] : null;
		$per_page    = isset( self::$list_table->_pagination_args['per_page'] ) ? self::$list_table->_pagination_args['per_page'] : null;

		if ( isset( $data['wp-mainwp-stream-heartbeat'] ) && isset( $total_items ) ) {
			$response['total_items']      = $total_items;
			$response['total_items_i18n'] = sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) );
		}

		if ( isset( $data['wp-mainwp-stream-heartbeat'] ) && 'live-update' === $data['wp-mainwp-stream-heartbeat'] && $enable_stream_update ) {

			if ( ! empty( $data['wp-mainwp-stream-heartbeat'] ) ) {
				if ( isset( $total_pages ) ) {
					$response['total_pages']      = $total_pages;
					$response['total_pages_i18n'] = number_format_i18n( $total_pages );

					$query_args          = json_decode( $data['wp-mainwp-stream-heartbeat-query'], true );
					$query_args['paged'] = $total_pages;

					$response['last_page_link'] = add_query_arg( $query_args, admin_url( 'admin.php' ) );
				} else {
					$response['total_pages'] = 0;
				}
			}

			$response['wp-mainwp-stream-heartbeat'] = self::live_update( $response, $data );

		} elseif ( isset( $data['wp-mainwp-stream-heartbeat'] ) && 'dashboard-update' === $data['wp-mainwp-stream-heartbeat'] && $enable_dashboard_update ) {

			$per_page = isset( $option['records_per_page'] ) ? absint( $option['records_per_page'] ) : 5;

			if ( isset( $total_items ) ) {
				$total_pages = ceil( $total_items / $per_page );
				$response['total_pages'] = $total_pages;
				$response['total_pages_i18n'] = number_format_i18n( $total_pages );

				$query_args['page']  = MainWP_WP_Stream_Admin::RECORDS_PAGE_SLUG;
				$query_args['paged'] = $total_pages;				

				$response['last_page_link'] = add_query_arg( $query_args, admin_url( 'admin.php' ) );
			}

			$response['per_page'] = $per_page;
			$response['wp-mainwp-stream-heartbeat'] = MainWP_WP_Stream_Dashboard_Widget::live_update( $response, $data );

		} else {
			$response['log'] = 'fail';
		}
               
		return $response;
	}

}