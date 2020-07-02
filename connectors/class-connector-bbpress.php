<?php
/** MainWp Child Reports BBPress Connector. */
namespace WP_MainWP_Stream;

/**
 * Class Connector_BbPress.
 * @package WP_MainWP_Stream
 */
class Connector_BbPress extends Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'bbpress';

	/**
	 * Holds tracked plugin minimum version required.
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '2.5.4';

	/**
	 * Actions registered for this connector.
	 *
	 * @var array
	 */
	public $actions = array(
		'bbp_toggle_topic_admin',
	);

	/**
	 * Tracked option keys.
	 *
	 * @var array
	 */
	public $options = array(
		'bbpress' => null,
	);

	/**
	 * Flag to stop logging update logic twice.
	 *
	 * @var bool
	 */
	public $is_update = false;

	/**
     * Flag for deleted activity.
     *
	 * @var bool
	 */
	public $_deleted_activity = false;

	/**
     * Delete activity arguments.
     *
	 * @var array
	 */
	public $_delete_activity_args = array();

	/**
     * Ignore bulk deletion activity.
     *
	 * @var bool
	 */
	public $ignore_activity_bulk_deletion = false;

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		if ( class_exists( 'bbPress' ) && function_exists( 'bbp_get_version' ) && version_compare( bbp_get_version(), self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html_x( 'bbPress', 'bbpress', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'created'     => esc_html_x( 'Created', 'bbpress', 'mainwp-child-reports' ),
			'updated'     => esc_html_x( 'Updated', 'bbpress', 'mainwp-child-reports' ),
			'activated'   => esc_html_x( 'Activated', 'bbpress', 'mainwp-child-reports' ),
			'deactivated' => esc_html_x( 'Deactivated', 'bbpress', 'mainwp-child-reports' ),
			'deleted'     => esc_html_x( 'Deleted', 'bbpress', 'mainwp-child-reports' ),
			'trashed'     => esc_html_x( 'Trashed', 'bbpress', 'mainwp-child-reports' ),
			'untrashed'   => esc_html_x( 'Restored', 'bbpress', 'mainwp-child-reports' ),
			'generated'   => esc_html_x( 'Generated', 'bbpress', 'mainwp-child-reports' ),
			'imported'    => esc_html_x( 'Imported', 'bbpress', 'mainwp-child-reports' ),
			'exported'    => esc_html_x( 'Exported', 'bbpress', 'mainwp-child-reports' ),
			'closed'      => esc_html_x( 'Closed', 'bbpress', 'mainwp-child-reports' ),
			'opened'      => esc_html_x( 'Opened', 'bbpress', 'mainwp-child-reports' ),
			'sticked'     => esc_html_x( 'Sticked', 'bbpress', 'mainwp-child-reports' ),
			'unsticked'   => esc_html_x( 'Unsticked', 'bbpress', 'mainwp-child-reports' ),
			'spammed'     => esc_html_x( 'Marked as spam', 'bbpress', 'mainwp-child-reports' ),
			'unspammed'   => esc_html_x( 'Unmarked as spam', 'bbpress', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'settings' => esc_html_x( 'Settings', 'bbpress', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}
	 *
	 * @param  array $links      Previous links registered
	 * @param  object $record    Stream record
	 *
	 * @return array             Action links
	 */
	public function action_links( $links, $record ) {
		if ( 'settings' === $record->context ) {
			$option                                  = $record->get_meta( 'option', true );
			$links[ esc_html__( 'Edit', 'mainwp-child-reports' ) ] = esc_url(
				add_query_arg(
					array(
						'page' => 'bbpress',
					),
					admin_url( 'options-general.php' )
				) . esc_url_raw( '#' . $option )
			);
		}
		return $links;
	}

    /**
     * Register log data.
     */
	public function register() {
		parent::register();

		add_filter( 'wp_mainwp_stream_log_data', array( $this, 'log_override' ) );
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( 'settings' === $data['connector'] && 'bbpress' === $data['args']['context'] ) {
			$settings = \bbp_admin_get_settings_fields();

			/* fix for missing title for this single field */
			$settings['bbp_settings_features']['_bbp_allow_threaded_replies']['title'] = esc_html__( 'Reply Threading', 'mainwp-child-reports' );

			$option = $data['args']['option'];

			foreach ( $settings as $section => $fields ) {
				if ( isset( $fields[ $option ] ) ) {
					$field = $fields[ $option ];
					break;
				}
			}

			if ( ! isset( $field ) ) {
				return $data;
			}

			$data['args']['label'] = $field['title'];
			$data['connector']     = $this->name;
			$data['context']       = 'settings';
			$data['action']        = 'updated';
		} elseif ( 'posts' === $data['connector'] && in_array( $data['context'], array( 'forum', 'topic', 'reply' ), true ) ) {
			if ( 'reply' === $data['context'] ) {
				if ( 'updated' === $data['action'] ) {
					// translators: Placeholder refers to a post title (e.g. "Hello World")
					$data['message']            = esc_html__( 'Replied on "%1$s"', 'mainwp-child-reports' );
					$data['args']['post_title'] = get_post( wp_get_post_parent_id( $data['object_id'] ) )->post_title;
				}
				$data['args']['post_title'] = sprintf(
					// translators: Placeholder refers to a post title (e.g. "Hello World")
					__( 'Reply to: %s', 'mainwp-child-reports' ),
					get_post( wp_get_post_parent_id( $data['object_id'] ) )->post_title
				);
			}

			$data['connector'] = $this->name;
		} elseif ( 'taxonomies' === $data['connector'] && in_array( $data['context'], array( 'topic-tag' ), true ) ) {
			$data['connector'] = $this->name;
		}

		return $data;
	}

	/**
	 * Tracks togging the forum topics
	 *
	 * @param bool $success
	 * @param \WP_Post $post_data
	 * @param string $action
	 * @param string $message
	 *
	 * @return array|bool
	 */
	public function callback_bbp_toggle_topic_admin( $success, $post_data, $action, $message ) {
		unset( $success );
		unset( $post_data );
		unset( $action );

		if ( ! empty( $message['failed'] ) ) {
			return;
		}

		$action  = $message['bbp_topic_toggle_notice'];
		$actions = $this->get_action_labels();

		if ( ! isset( $actions[ $action ] ) ) {
			return;
		}

		$topic = get_post( $message['topic_id'] );

		$this->log(
			// translators: Placeholders refer to an action, and a topic title (e.g. "Created", "Read this first")
			_x( '%1$s "%2$s" topic', '1: Action, 2: Topic title', 'mainwp-child-reports' ),
			array(
				'action_title' => $actions[ $action ],
				'topic_title'  => $topic->post_title,
				'action'       => $action,
			),
			$topic->ID,
			'topic',
			$action
		);
	}
}
