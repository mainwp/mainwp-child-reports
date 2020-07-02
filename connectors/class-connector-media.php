<?php
/** MainWP Child Reports media connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_Media.
 * @package WP_MainWP_Stream
 */
class Connector_Media extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'media';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'add_attachment',
		'edit_attachment',
		'delete_attachment',
		'wp_save_image_editor_file',
		'wp_save_image_file',
	);

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html__( 'Media', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'attached'   => esc_html__( 'Attached', 'mainwp-child-reports' ),
			'uploaded'   => esc_html__( 'Uploaded', 'mainwp-child-reports' ),
			'updated'    => esc_html__( 'Updated', 'mainwp-child-reports' ),
			'deleted'    => esc_html__( 'Deleted', 'mainwp-child-reports' ),
			'assigned'   => esc_html__( 'Assigned', 'mainwp-child-reports' ),
			'unassigned' => esc_html__( 'Unassigned', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * Based on extension types used by wp_ext2type() in wp-includes/functions.php.
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'image'       => esc_html__( 'Image', 'mainwp-child-reports' ),
			'audio'       => esc_html__( 'Audio', 'mainwp-child-reports' ),
			'video'       => esc_html__( 'Video', 'mainwp-child-reports' ),
			'document'    => esc_html__( 'Document', 'mainwp-child-reports' ),
			'spreadsheet' => esc_html__( 'Spreadsheet', 'mainwp-child-reports' ),
			'interactive' => esc_html__( 'Interactive', 'mainwp-child-reports' ),
			'text'        => esc_html__( 'Text', 'mainwp-child-reports' ),
			'archive'     => esc_html__( 'Archive', 'mainwp-child-reports' ),
			'code'        => esc_html__( 'Code', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return the file type for an attachment which corresponds with a context label
	 *
	 * @param object $file_uri URI of the attachment
	 *
	 * @return string A file type which corresponds with a context label
	 */
	public function get_attachment_type( $file_uri ) {
		$extension      = pathinfo( $file_uri, PATHINFO_EXTENSION );
		$extension_type = wp_ext2type( $extension );

		if ( empty( $extension_type ) ) {
			$extension_type = 'document';
		}

		$context_labels = $this->get_context_labels();

		if ( ! isset( $context_labels[ $extension_type ] ) ) {
			$extension_type = 'document';
		}

		return $extension_type;
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}
	 *
	 * @param array  $links  Previous links registered
	 * @param object $record Stream record
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		if ( $record->object_id ) {
			$edit_post_link = get_edit_post_link( $record->object_id );
			if ( $edit_post_link ) {
				$links[ esc_html__( 'Edit Media', 'mainwp-child-reports' ) ] = $edit_post_link;
			}
			$permalink = get_permalink( $record->object_id );
			if ( $permalink ) {
				$links[ esc_html__( 'View', 'mainwp-child-reports' ) ] = $permalink;
			}
		}

		return $links;
	}

	/**
	 * Tracks creation of attachments
	 *
	 * @action add_attachment
	 *
	 * @param int $post_id
	 */
	public function callback_add_attachment( $post_id ) {
		$post = get_post( $post_id );
		if ( $post->post_parent ) {
			// translators: Placeholders refer to an attachment title, and a post title (e.g. "PIC001", "Hello World")
			$message = _x(
				'Attached "%1$s" to "%2$s"',
				'1: Attachment title, 2: Parent post title',
				'mainwp-child-reports'
			);
		} else {
			// translators: Placeholder refers to an attachment title (e.g. "PIC001")
			$message = esc_html__( 'Added "%s" to Media library', 'mainwp-child-reports' );
		}

		$name            = $post->post_title;
		$url             = $post->guid;
		$parent_id       = $post->post_parent;
		$parent          = get_post( $parent_id );
		$parent_title    = $parent_id ? $parent->post_title : null;
		$attachment_type = $this->get_attachment_type( $post->guid );

		$this->log(
			$message,
			compact( 'name', 'parent_title', 'parent_id', 'url' ),
			$post_id,
			$attachment_type,
			$post->post_parent ? 'attached' : 'uploaded'
		);
	}

	/**
	 * Tracks editing attachments
	 *
	 * @action edit_attachment
	 *
	 * @param int $post_id
	 */
	public function callback_edit_attachment( $post_id ) {
		$post            = get_post( $post_id );
		$name            = $post->post_title;
		$attachment_type = $this->get_attachment_type( $post->guid );

		// translators: Placeholder refers to an attachment title (e.g. "PIC001")
		$message = esc_html__( 'Updated "%s"', 'mainwp-child-reports' );

		$this->log(
			$message,
			compact( 'name' ),
			$post_id,
			$attachment_type,
			'updated'
		);
	}

	/**
	 * Tracks deletion of attachments
	 *
	 * @action delete_attachment
	 *
	 * @param int $post_id
	 */
	public function callback_delete_attachment( $post_id ) {
		$post            = get_post( $post_id );
		$parent          = $post->post_parent ? get_post( $post->post_parent ) : null;
		$parent_id       = $parent ? $parent->ID : null;
		$name            = $post->post_title;
		$url             = $post->guid;
		$attachment_type = $this->get_attachment_type( $post->guid );

		// translators: Placeholder refers to an attachment title (e.g. "PIC001")
		$message = esc_html__( 'Deleted "%s"', 'mainwp-child-reports' );

		$this->log(
			$message,
			compact( 'name', 'parent_id', 'url' ),
			$post_id,
			$attachment_type,
			'deleted'
		);
	}

	/**
	 * Tracks changes made in the image editor
	 *
	 * @action delete_attachment
	 *
	 * @param string $dummy
	 * @param string $filename
	 * @param string $image
	 * @param string $mime_type
	 * @param int $post_id
	 */
	public function callback_wp_save_image_editor_file( $dummy, $filename, $image, $mime_type, $post_id ) {
		unset( $dummy );
		unset( $image );
		unset( $mime_type );

		$name = basename( $filename );
		$post = get_post( $post_id );

		$attachment_type = $this->get_attachment_type( $post->guid );

		$this->log(
			// translators: Placeholder refers to an attachment title (e.g. "PIC001")
			__( 'Edited image "%s"', 'mainwp-child-reports' ),
			compact( 'name', 'filename', 'post_id' ),
			$post_id,
			$attachment_type,
			'edited'
		);
	}

    /**
     * WP save image file callback.
     *
     * @param $dummy
     * @param $filename
     * @param $image
     * @param $mime_type
     * @param $post_id
     */
	public function callback_wp_save_image_file( $dummy, $filename, $image, $mime_type, $post_id ) {
		return $this->callback_wp_save_image_editor_file( $dummy, $filename, $image, $mime_type, $post_id );
	}
}
