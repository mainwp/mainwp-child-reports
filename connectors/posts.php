<?php

class MainWP_WP_Stream_Connector_Posts extends MainWP_WP_Stream_Connector {

	public static $name = 'posts';

	public static $actions = array(
		'transition_post_status',
		'deleted_post',
	);

	public static function get_label() {
		return __( 'Posts', 'default' );
	}

	public static function get_action_labels() {
		return array(
			'updated'   => __( 'Updated', 'mainwp-child-reports' ),
                        'autosave'   => __( 'Auto save', 'mainwp-child-reports' ),
			'created'   => __( 'Created', 'mainwp-child-reports' ),
			'trashed'   => __( 'Trashed', 'mainwp-child-reports' ),
			'untrashed' => __( 'Restored', 'mainwp-child-reports' ),
			'deleted'   => __( 'Deleted', 'mainwp-child-reports' ),
		);
	}

	public static function get_context_labels() {
		global $wp_post_types;
		$post_types = wp_filter_object_list( $wp_post_types, array(), null, 'label' );
		$post_types = array_diff_key( $post_types, array_flip( self::get_ignored_post_types() ) );

		add_action( 'registered_post_type', array( __CLASS__, '_registered_post_type' ), 10, 2 );

		return $post_types;
	}

	public static function action_links( $links, $record ) {
		$post = get_post( $record->object_id );

		if ( $post && $post->post_status === mainwp_wp_stream_get_meta( $record->ID, 'new_status', true ) ) {
			$post_type_name = self::get_post_type_name( get_post_type( $post->ID ) );

			if ( 'trash' === $post->post_status ) {
				$untrash = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'untrash',
							'post'   => $post->ID,
						),
						admin_url( 'post.php' )
					),
					sprintf( 'untrash-post_%d', $post->ID )
				);

				$delete = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'delete',
							'post'   => $post->ID,
						),
						admin_url( 'post.php' )
					),
					sprintf( 'delete-post_%d', $post->ID )
				);

				$links[ sprintf( esc_html_x( 'Restore %s', 'Post type singular name', 'mainwp-child-reports' ), $post_type_name ) ] = $untrash;
				$links[ sprintf( esc_html_x( 'Delete %s Permenantly', 'Post type singular name', 'mainwp-child-reports' ), $post_type_name ) ] = $delete;
			} else {
				$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'mainwp-child-reports' ), $post_type_name ) ] = get_edit_post_link( $post->ID );

				if ( $view_link = get_permalink( $post->ID ) ) {
					$links[ esc_html__( 'View', 'default' ) ] = $view_link;
				}

				if ( $revision_id = mainwp_wp_stream_get_meta( $record->ID, 'revision_id', true ) ) {
					$links[ esc_html__( 'Revision', 'default' ) ] = get_edit_post_link( $revision_id );
				}
			}
		}

		return $links;
	}

	public static function _registered_post_type( $post_type, $args ) {
		$post_type_obj = get_post_type_object( $post_type );
		$label         = $post_type_obj->label;

		MainWP_WP_Stream_Connectors::$term_labels['stream_context'][ $post_type ] = $label;
	}

	public static function callback_transition_post_status( $new, $old, $post ) {
		if ( in_array( $post->post_type, self::get_ignored_post_types() ) ) {
			return;
		}

		if ( in_array( $new, array( 'auto-draft', 'inherit' ) ) ) {
			return;
		} elseif ( $old == 'auto-draft' && $new == 'draft' ) {
			$message = _x(
				'"%1$s" %2$s drafted',
				'1: Post title, 2: Post type singular name',
				'mainwp_child_reports'
			);
			$action  = 'created';
		} elseif ( $old == 'auto-draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = _x(
				'"%1$s" %2$s published',
				'1: Post title, 2: Post type singular name',
				'mainwp_child_reports'
			);
			$action  = 'created';
		} elseif ( $old == 'draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = _x(
				'"%1$s" %2$s published',
				'1: Post title, 2: Post type singular name',
				'mainwp_child_reports'
			);
		} elseif ( $old == 'publish' && ( in_array( $new, array( 'draft' ) ) ) ) {
			$message = _x(
				'"%1$s" %2$s unpublished',
				'1: Post title, 2: Post type singular name',
				'mainwp_child_reports'
			);
		} elseif ( $new == 'trash' ) {
			$message = _x(
				'"%1$s" %2$s trashed',
				'1: Post title, 2: Post type singular name',
				'mainwp_child_reports'
			);
			$action  = 'trashed';
		} elseif ( $old == 'trash' && $new != 'trash' ) {
			$message = _x(
				'"%1$s" %2$s restored from trash',
				'1: Post title, 2: Post type singular name',
				'mainwp_child_reports'
			);
			$action  = 'untrashed';
		} else {
                    $message = _x(
				'"%1$s" %2$s updated',
				'1: Post title, 2: Post type singular name',
				'mainwp_child_reports'
			);  
                    if (defined( 'DOING_AUTOSAVE' ) ) 		
                        $action = 'autosave';                    
		}

		if ( empty( $action ) ) {
			$action = 'updated';
		}

		$revision_id = null;

		if ( wp_revisions_enabled( $post ) ) {
			$revision = get_children(
				array(
					'post_type'      => 'revision',
					'post_status'    => 'inherit',
					'post_parent'    => $post->ID,
					'posts_per_page' => 1,
					'order'          => 'desc',
					'fields'         => 'ids',
				)
			);
			if ( $revision ) {
				$revision_id = $revision[0];
			}
		}

		$post_type_name = strtolower( self::get_post_type_name( $post->post_type ) );
                
                if ($action == 'updated' && ($post->post_type == 'page' || $post->post_type == 'post')) {
                        $report_settings = get_option('mainwp_wp_stream', array());                    
                        $minutes = is_array($report_settings) && isset($report_settings['general_period_of_time']) ?  $report_settings['general_period_of_time'] : 30;                        
                        if (!empty($minutes) && intval($minutes) > 0) {                    
                            $args = array();
                            $args['object_id'] =  $post->ID;
                            $date_from = time() - $minutes * 60;
                            $args['datetime_from'] = date( 'Y-m-d H:i:s', $date_from );   
                            $args['context'] =  $post->post_type;
                            $args['action'] = 'updated';                    
                            $args['records_per_page'] = 9999;   
                            $args['orderby'] = 'created';
                            $args['order'] = 'desc';   
                            $items = mainwp_wp_stream_query( $args );                            
                            if (count($items) > 0)
                                return;
                        }
                }
                
		self::log(
			$message,
			array(
				'post_title'    => $post->post_title,
				'singular_name' => $post_type_name,
				'new_status'    => $new,
				'old_status'    => $old,
				'revision_id'   => $revision_id,
			),
			$post->ID,
			array( $post->post_type => $action )
		);
	}

	public static function callback_deleted_post( $post_id ) {
		$post = get_post( $post_id );

		// We check if post is an instance of WP_Post as it doesn't always resolve in unit testing
		if ( ! ( $post instanceof WP_Post ) || in_array( $post->post_type, self::get_ignored_post_types() )  ) {
			return;
		}

		// Ignore auto-drafts that are deleted by the system, see issue-293
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$post_type_name = strtolower( self::get_post_type_name( $post->post_type ) );

		self::log(
			_x(
				'"%1$s" %2$s deleted from trash',
				'1: Post title, 2: Post type singular name',
				'mainwp_child_reports'
			),
			array(
				'post_title'    => $post->post_title,
				'singular_name' => $post_type_name,
			),
			$post->ID,
			array( $post->post_type => 'deleted' )
		);
	}

	public static function get_ignored_post_types() {
		return apply_filters(
			'mainwp_wp_stream_post_exclude_post_types',
			array(
				'nav_menu_item',
				'attachment',
				'revision',
			)
		);
	}

	private static function get_post_type_name( $post_type_slug ) {
		$name = __( 'Post', 'default' ); // Default

		if ( post_type_exists( $post_type_slug ) ) {
			$post_type = get_post_type_object( $post_type_slug );
			$name      = $post_type->labels->singular_name;
		}

		return $name;
	}

}
