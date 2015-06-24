<?php

class MainWP_WP_Stream_Connector_Comments extends MainWP_WP_Stream_Connector {

	public static $name = 'comments';

	public static $actions = array(		
		'wp_insert_comment',
		'edit_comment',
		'delete_comment',
		'trash_comment',
		'untrash_comment',
		'spam_comment'
	);

	public static function get_label() {
		return __( 'Comments', 'default' );
	}

	public static function get_action_labels() {
		return array(
			'created'    => __( 'Created', 'mainwp-child-reports' ),
			'edited'     => __( 'Edited', 'mainwp-child-reports' ),
			'replied'    => __( 'Replied', 'mainwp-child-reports' ),
			'approved'   => __( 'Approved', 'mainwp-child-reports' ),
			'unapproved' => __( 'Unapproved', 'mainwp-child-reports' ),
			'trashed'    => __( 'Trashed', 'mainwp-child-reports' ),
			'untrashed'  => __( 'Restored', 'mainwp-child-reports' ),
			'spammed'    => __( 'Marked as Spam', 'mainwp-child-reports' ),
//			'unspammed'  => __( 'Unmarked as Spam', 'mainwp-child-reports' ),
			'deleted'    => __( 'Deleted', 'mainwp-child-reports' ),
//			'duplicate'  => __( 'Duplicate', 'mainwp-child-reports' ),
//			'flood'      => __( 'Throttled', 'mainwp-child-reports' ),
		);
	}

	public static function get_context_labels() {
		return array(
			'comments' => __( 'Comments', 'default' ),
		);
	}

	public static function get_comment_type_labels() {
		return apply_filters(
			'mainwp_wp_stream_comment_type_labels',
			array(
				'comment'   => __( 'Comment', 'default' ),
				'trackback' => __( 'Trackback', 'default' ),
				'pingback'  => __( 'Pingback', 'default' ),
			)
		);
	}

	public static function get_comment_type_label( $comment_id ) {
		$comment_type = get_comment_type( $comment_id );

		if ( empty( $comment_type ) ) {
			$comment_type = 'comment';
		}

		$comment_type_labels = self::get_comment_type_labels();

		$label = isset( $comment_type_labels[ $comment_type ] ) ? $comment_type_labels[ $comment_type ] : $comment_type;

		return $label;
	}

	public static function action_links( $links, $record ) {
		if ( $record->object_id ) {
			if ( $comment = get_comment( $record->object_id ) ) {
				$del_nonce     = wp_create_nonce( "delete-comment_$comment->comment_ID" );
				$approve_nonce = wp_create_nonce( "approve-comment_$comment->comment_ID" );

				$links[ __( 'Edit', 'default' ) ] = admin_url( "comment.php?action=editcomment&c=$comment->comment_ID" );

				if ( 1 === $comment->comment_approved ) {
					$links[ __( 'Unapprove', 'mainwp-child-reports' ) ] = admin_url(
						sprintf(
							'comment.php?action=unapprovecomment&c=%s&_wpnonce=%s',
							$record->object_id,
							$approve_nonce
						)
					);
				} elseif ( empty( $comment->comment_approved ) ) {
					$links[ __( 'Approve', 'mainwp-child-reports' ) ] = admin_url(
						sprintf(
							'comment.php?action=approvecomment&c=%s&_wpnonce=%s',
							$record->object_id,
							$approve_nonce
						)
					);
				}
			}
		}

		return $links;
	}

	public static function get_comment_author( $comment, $field = 'id' ) {
		$comment = is_object( $comment ) ? $comment : get_comment( absint( $comment ) );

		$req_name_email = get_option( 'require_name_email' );
		$req_user_login = get_option( 'comment_registration' );

		$user_id   = 0;
		$user_name = __( 'Guest', 'mainwp-child-reports' );

		if ( $req_name_email && isset( $comment->comment_author_email ) && isset( $comment->comment_author ) ) {
			$user      = get_user_by( 'email', $comment->comment_author_email );
			$user_id   = isset( $user->ID ) ? $user->ID : 0;
			$user_name = isset( $user->display_name ) ? $user->display_name : $comment->comment_author;
		}

		if ( $req_user_login ) {
			$user      = wp_get_current_user();
			$user_id   = $user->ID;
			$user_name = $user->display_name;
		}

		if ( 'id' === $field ) {
			$output = $user_id;
		} elseif ( 'name' === $field ) {
			$output = $user_name;
		}

		return $output;
	}

	public static function callback_wp_insert_comment( $comment_id, $comment ) {
		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id        = self::get_comment_author( $comment, 'id' );
		$user_name      = self::get_comment_author( $comment, 'name' );
		$post_id        = $comment->comment_post_ID;
		$post_type      = get_post_type( $post_id );
		$post_title     = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'mainwp-child-reports' );
		$comment_status = ( 1 == $comment->comment_approved ) ? __( 'approved automatically', 'mainwp-child-reports' ) : __( 'pending approval', 'mainwp-child-reports' );
		$is_spam        = false;
		// Auto-marked spam comments
		if ( class_exists( 'Akismet' ) && Akismet::matches_last_comment( $comment ) ) {
			$ak_last_comment = Akismet::get_last_comment();
			if ( 'true' == $ak_last_comment['akismet_result'] ) {
				$is_spam        = true;
				$comment_status = __( 'automatically marked as spam by Akismet', 'mainwp-child-reports' );
			}
		}
		$comment_type   = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		if ( $comment->comment_parent ) {
			$parent_user_id   = get_comment_author( $comment->comment_parent, 'id' );
			$parent_user_name = get_comment_author( $comment->comment_parent, 'name' );

			self::log(
				_x(
					'Reply to %1$s\'s %5$s by %2$s on %3$s %4$s',
					"1: Parent comment's author, 2: Comment author, 3: Post title, 4: Comment status, 5: Comment type",
					'mainwp_child_reports'
				),
				compact( 'parent_user_name', 'user_name', 'post_title', 'comment_status', 'comment_type', 'post_id', 'parent_user_id' ),
				$comment_id,
				array( $post_type => 'replied' ),
				$user_id
			);
		} else {
			self::log(
				_x(
					'New %4$s by %1$s on %2$s %3$s',
					'1: Comment author, 2: Post title 3: Comment status, 4: Comment type',
					'mainwp_child_reports'
				),
				compact( 'user_name', 'post_title', 'comment_status', 'comment_type', 'post_id', 'is_spam' ),
				$comment_id,
				array( $post_type => $is_spam ? 'spammed' : 'created' ),
				$user_id
			);
		}
	}

	public static function callback_edit_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'mainwp-child-reports' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s edited',
				'1: Comment author, 2: Post title, 3: Comment type',
				'mainwp_child_reports'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			array( $post_type => 'edited' )
		);
	}

	public static function callback_delete_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'mainwp-child-reports' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s deleted permanently',
				'1: Comment author, 2: Post title, 3: Comment type',
				'mainwp_child_reports'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			array( $post_type => 'deleted' )
		);
	}

	public static function callback_trash_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'mainwp-child-reports' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s trashed',
				'1: Comment author, 2: Post title, 3: Comment type',
				'mainwp_child_reports'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			array( $post_type => 'trashed' )
		);
	}

	public static function callback_untrash_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'mainwp-child-reports' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s restored',
				'1: Comment author, 2: Post title, 3: Comment type',
				'mainwp_child_reports'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			array( $post_type => 'untrashed' )
		);
	}

	public static function callback_spam_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'mainwp-child-reports' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s marked as spam',
				'1: Comment author, 2: Post title, 3: Comment type',
				'mainwp_child_reports'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			array( $post_type => 'spammed' )
		);
	}

	public static function get_ignored_comment_types() {
		return apply_filters(
			'mainwp_wp_stream_comment_exclude_comment_types',
			array()
		);
	}

}
