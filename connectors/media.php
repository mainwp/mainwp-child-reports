<?php

class MainWP_WP_Stream_Connector_Media extends MainWP_WP_Stream_Connector {

	public static $name = 'media';

	public static $actions = array(		
		'edit_attachment',
		'delete_attachment',
		'wp_save_image_editor_file',
		'wp_save_image_file',
	);

	public static function get_label() {
		return __( 'Media', 'default' );
	}

	public static function get_action_labels() {
		return array(			
			'uploaded'   => __( 'Uploaded', 'mainwp-child-reports' ),
			'updated'    => __( 'Updated', 'mainwp-child-reports' ),
			'deleted'    => __( 'Deleted', 'mainwp-child-reports' )			
		);
	}

	public static function get_context_labels() {
		return array(
			'image'       => __( 'Image', 'default' ),
			'audio'       => __( 'Audio', 'default' ),
			'video'       => __( 'Video', 'default' ),
			'document'    => __( 'Document', 'mainwp-child-reports' ),
			'spreadsheet' => __( 'Spreadsheet', 'mainwp-child-reports' ),
			'interactive' => __( 'Interactive', 'mainwp-child-reports' ),
			'text'        => __( 'Text', 'default' ),
			'archive'     => __( 'Archive', 'default' ),
			'code'        => __( 'Code', 'default' ),
		);
	}

	public static function get_attachment_type( $file_uri ) {
		$extension      = pathinfo( $file_uri, PATHINFO_EXTENSION );
		$extension_type = wp_ext2type( $extension );

		if ( empty( $extension_type ) ) {
			$extension_type = 'document';
		}

		$context_labels = self::get_context_labels();

		if ( ! isset( $context_labels[ $extension_type ] ) ) {
			$extension_type = 'document';
		}

		return $extension_type;
	}

	public static function action_links( $links, $record ) {
		if ( $record->object_id ) {
			if ( $link = get_edit_post_link( $record->object_id ) ) {
				$links[ __( 'Edit Media', 'default' ) ] = $link;
			}
			if ( $link = get_permalink( $record->object_id ) ) {
				$links[ __( 'View', 'default' ) ] = $link;
			}
		}

		return $links;
	}

	public static function callback_edit_attachment( $post_id ) {
		$post            = get_post( $post_id );
		$message         = __( 'Updated "%s"', 'mainwp-child-reports' );
		$name            = $post->post_title;
		$attachment_type = self::get_attachment_type( $post->guid );

		self::log(
			$message,
			compact( 'name' ),
			$post_id,
			array( $attachment_type => 'updated' )
		);
	}

	public static function callback_delete_attachment( $post_id ) {
		$post            = get_post( $post_id );
		$parent          = $post->post_parent ? get_post( $post->post_parent ) : null;
		$parent_id       = $parent ? $parent->ID : null;
		$message         = __( 'Deleted "%s"', 'mainwp-child-reports' );
		$name            = $post->post_title;
		$url             = $post->guid;
		$attachment_type = self::get_attachment_type( $post->guid );

		self::log(
			$message,
			compact( 'name', 'parent_id', 'url' ),
			$post_id,
			array( $attachment_type => 'deleted' )
		);
	}

	public static function callback_wp_save_image_editor_file( $dummy, $filename, $image, $mime_type, $post_id ) {
		$name            = basename( $filename );
		$attachment_type = self::get_attachment_type( $post->guid );

		self::log(
			__( 'Edited image "%s"', 'mainwp-child-reports' ),
			compact( 'name', 'filename', 'post_id' ),
			$post_id,
			array( $attachment_type => 'edited' )
		);
	}

	public static function callback_wp_save_image_file( $dummy, $filename, $image, $mime_type, $post_id ) {
		return self::callback_wp_save_image_editor_file( $dummy, $filename, $image, $mime_type, $post_id );
	}

}
