<?php

class MainWP_WP_Stream_Dashboard_Widget {

	public static function load() {
		
	}

	/**
	 * Handles Live Updates for Stream Activity Dashboard Widget.
	 *
	 * @uses gather_updated_items
	 *
	 * @param  array  Response to heartbeat
	 * @param  array  Response from heartbeat
	 * @return array  Data sent to heartbeat
	 */
	public static function live_update( $response, $data ) {
		if ( ! isset( $data['wp-mainwp-stream-heartbeat-last-id'] ) ) {
			return;
		}

		$send = array();

		$last_id = intval( $data['wp-mainwp-stream-heartbeat-last-id'] );

		$updated_items = self::gather_updated_items( $last_id );

		if ( ! empty( $updated_items ) ) {
			ob_start();
			foreach ( $updated_items as $item ) {
				echo self::widget_row( $item ); //xss okay
			}

			$send = ob_get_clean();
		}

		return $send;
	}

        /**
	 * Sends Updated Actions to the List Table View
	 *
	 * @param       int    Timestamp of last update
	 * @param array $query
	 *
	 * @return array  Array of recently updated items
	 */
	public static function gather_updated_items( $last_id, $query = array() ) {
		if ( false === $last_id ) {
			return '';
		}

		$default = array(
			'record_greater_than' => (int) $last_id,
		);

		// Filter default
		$query = wp_parse_args( $query, $default );

		// Run query
		$items = mainwp_wp_stream_query( $query );

		return $items;
	}

}
