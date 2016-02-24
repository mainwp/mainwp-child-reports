<?php

class MainWP_WP_Stream_Dashboard_Widget {

	public static function load() {
		
	}

        public static function widget_row( $item, $i = null ) {
		require_once MAINWP_WP_STREAM_INC_DIR . 'class-wp-stream-author.php';

		$author_meta = mainwp_wp_stream_get_meta( $item->ID, 'author_meta', true );
		$author      = new MainWP_WP_Stream_Author( (int) $item->author, $author_meta );

		$time_author = sprintf(
			_x(
				'%1$s ago by <a href="%2$s">%3$s</a>',
				'1: Time, 2: User profile URL, 3: User display name',
				'stream'
			),
			human_time_diff( strtotime( $item->created ) ),
			esc_url( $author->get_records_page_url() ),
			esc_html( $author->get_display_name() )
		);

		if ( $author->get_agent() ) {
			$time_author .= sprintf( ' %s', MainWP_WP_Stream_Author::get_agent_label( $author->get_agent() ) );
		}

		$class = ( isset( $i ) && $i % 2 ) ? 'alternate' : '';

		ob_start()
		?><li class="<?php echo esc_html( $class ) ?>" data-id="<?php echo esc_html( $item->ID ) ?>">
			<div class="record-avatar">
				<a href="<?php echo esc_url( $author->get_records_page_url() ) ?>">
					<?php echo $author->get_avatar_img( 72 ); // xss ok ?>
				</a>
			</div>
			<span class="record-meta"><?php echo $time_author; // xss ok ?></span>
			<br />
			<?php echo esc_html( $item->summary ) ?>
		</li><?php

		return ob_get_clean();
	}

       
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

	public static function gather_updated_items( $last_id, $query = array(), $last_created = null ) {
		if ( false === $last_id ) {
			return '';
		}
		$default = array();
		
		if (!empty($last_created)) {
			 $default['created_greater_than'] = $last_created;
		} else {
			$default['record_greater_than'] = (int) $last_id;
		}

		// Filter default
		$query = wp_parse_args( $query, $default );
		
		// Run query
		$items = mainwp_wp_stream_query( $query );

		return $items;
	}

}
