<?php
/** MainWP BuddyPress Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_BuddyPress
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_BuddyPress extends Connector {

    /** @var string Connector slug */
	public $name = 'buddypress';

    /**
     * Defines plugin minimum version required.
     *
     * @const ( string ) Holds tracked plugin minimum version required.
     */
	const PLUGIN_MIN_VERSION = '2.0.1';

    /** @var array Actions registered for this connector. */
	public $actions = array(
		'update_option',
		'add_option',
		'delete_option',
		'update_site_option',
		'add_site_option',
		'delete_site_option',

		'bp_before_activity_delete',
		'bp_activity_deleted_activities',

		'bp_activity_mark_as_spam',
		'bp_activity_mark_as_ham',
		'bp_activity_admin_edit_after',

		'groups_create_group',
		'groups_update_group',
		'groups_before_delete_group',
		'groups_details_updated',
		'groups_settings_updated',

		'groups_leave_group',
		'groups_join_group',

		'groups_promote_member',
		'groups_demote_member',
		'groups_ban_member',
		'groups_unban_member',
		'groups_remove_member',

		'xprofile_field_after_save',
		'xprofile_fields_deleted_field',

		'xprofile_group_after_save',
		'xprofile_groups_deleted_group',
	);

    /** @var array Tracked option keys. */
	public $options = array(
		'bp-active-components' => null,
		'bp-pages'             => null,
		'buddypress'           => null,
	);

	/** @var bool Flag to stop logging update logic twice */
	public $is_update = false;

	/** @var bool Whether activity was deleted. */
	public $_deleted_activity = false;

	/** @var array Holds deleted activity arguments. */
	public $_delete_activity_args = array();

	/** @var bool Whenter or not to ignore bulk activity deletion. */
	public $ignore_activity_bulk_deletion = false;

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not.
	 *
	 * @return bool Return TRUE|FASLE.
	 */
	public function is_dependency_satisfied() {
		if ( class_exists( 'BuddyPress' ) && version_compare( \BuddyPress::instance()->version, self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated connector label.
	 *
	 * @return string Translated connector label.
	 */
	public function get_label() {
		return esc_html_x( 'BuddyPress', 'buddypress', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array Action label translations.
	 */
	public function get_action_labels() {
		return array(
			'created'     => esc_html_x( 'Created', 'buddypress', 'mainwp-child-reports' ),
			'updated'     => esc_html_x( 'Updated', 'buddypress', 'mainwp-child-reports' ),
			'activated'   => esc_html_x( 'Activated', 'buddypress', 'mainwp-child-reports' ),
			'deactivated' => esc_html_x( 'Deactivated', 'buddypress', 'mainwp-child-reports' ),
			'deleted'     => esc_html_x( 'Deleted', 'buddypress', 'mainwp-child-reports' ),
			'spammed'     => esc_html_x( 'Marked as spam', 'buddypress', 'mainwp-child-reports' ),
			'unspammed'   => esc_html_x( 'Unmarked as spam', 'buddypress', 'mainwp-child-reports' ),
			'promoted'    => esc_html_x( 'Promoted', 'buddypress', 'mainwp-child-reports' ),
			'demoted'     => esc_html_x( 'Demoted', 'buddypress', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array Context label translations.
	 */
	public function get_context_labels() {
		return array(
			'components'     => esc_html_x( 'Components', 'buddypress', 'mainwp-child-reports' ),
			'groups'         => esc_html_x( 'Groups', 'buddypress', 'mainwp-child-reports' ),
			'activity'       => esc_html_x( 'Activity', 'buddypress', 'mainwp-child-reports' ),
			'profile_fields' => esc_html_x( 'Profile fields', 'buddypress', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen.
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}.
	 *
	 * @param  array  $links Previous links registered.
	 * @param  object $record Stream record.
	 *
	 * @return array Action links.
	 */
	public function action_links( $links, $record ) {
		if ( in_array( $record->context, array( 'components' ), true ) ) {
			$option_key = $record->get_meta( 'option_key', true );

			if ( 'bp-active-components' === $option_key ) {
				$links[ esc_html__( 'Edit', 'mainwp-child-reports' ) ] = add_query_arg(
					array(
						'page' => 'bp-components',
					),
					admin_url( 'admin.php' )
				);
			} elseif ( 'bp-pages' === $option_key ) {
				$page_id = $record->get_meta( 'page_id', true );

				$links[ esc_html__( 'Edit setting', 'mainwp-child-reports' ) ] = add_query_arg(
					array(
						'page' => 'bp-page-settings',
					),
					admin_url( 'admin.php' )
				);

				if ( $page_id ) {
					$links[ esc_html__( 'Edit Page', 'mainwp-child-reports' ) ] = get_edit_post_link( $page_id );
					$links[ esc_html__( 'View', 'mainwp-child-reports' ) ]      = get_permalink( $page_id );
				}
			}
		} elseif ( in_array( $record->context, array( 'settings' ), true ) ) {
			$links[ esc_html__( 'Edit setting', 'mainwp-child-reports' ) ] = add_query_arg(
				array(
					'page' => $record->get_meta( 'page', true ),
				),
				admin_url( 'admin.php' )
			);
		} elseif ( in_array( $record->context, array( 'groups' ), true ) ) {
			$group_id = $record->get_meta( 'id', true );
			$group    = \groups_get_group(
				array(
					'group_id' => $group_id,
				)
			);

			if ( $group ) {
				// Build actions URLs.
				$base_url   = \bp_get_admin_url( 'admin.php?page=bp-groups&amp;gid=' . $group_id );
				$delete_url = wp_nonce_url( $base_url . '&amp;action=delete', 'bp-groups-delete' );
				$edit_url   = $base_url . '&amp;action=edit';
				$visit_url  = \bp_get_group_permalink( $group );

				$links[ esc_html__( 'Edit group', 'mainwp-child-reports' ) ]   = $edit_url;
				$links[ esc_html__( 'View group', 'mainwp-child-reports' ) ]   = $visit_url;
				$links[ esc_html__( 'Delete group', 'mainwp-child-reports' ) ] = $delete_url;
			}
		} elseif ( in_array( $record->context, array( 'activity' ), true ) ) {
			$activity_id = $record->get_meta( 'id', true );
			$activities  = \bp_activity_get(
				array(
					'in'   => $activity_id,
					'spam' => 'all',
				)
			);
			if ( ! empty( $activities['activities'] ) ) {
				$activity = reset( $activities['activities'] );

				$base_url   = \bp_get_admin_url( 'admin.php?page=bp-activity&amp;aid=' . $activity->id );
				$spam_nonce = esc_html( '_wpnonce=' . wp_create_nonce( 'spam-activity_' . $activity->id ) );
				$delete_url = $base_url . "&amp;action=delete&amp;$spam_nonce";
				$edit_url   = $base_url . '&amp;action=edit';
				$ham_url    = $base_url . "&amp;action=ham&amp;$spam_nonce";
				$spam_url   = $base_url . "&amp;action=spam&amp;$spam_nonce";

				if ( $activity->is_spam ) {
					$links[ esc_html__( 'Ham', 'mainwp-child-reports' ) ] = $ham_url;
				} else {
					$links[ esc_html__( 'Edit', 'mainwp-child-reports' ) ] = $edit_url;
					$links[ esc_html__( 'Spam', 'mainwp-child-reports' ) ] = $spam_url;
				}
				$links[ esc_html__( 'Delete', 'mainwp-child-reports' ) ] = $delete_url;
			}
		} elseif ( in_array( $record->context, array( 'profile_fields' ), true ) ) {
			$field_id = $record->get_meta( 'field_id', true );
			$group_id = $record->get_meta( 'group_id', true );

			if ( empty( $field_id ) ) { // is a group action
				$links[ esc_html__( 'Edit', 'mainwp-child-reports' ) ]   = add_query_arg(
					array(
						'page'     => 'bp-profile-setup',
						'mode'     => 'edit_group',
						'group_id' => $group_id,
					),
					admin_url( 'users.php' )
				);
				$links[ esc_html__( 'Delete', 'mainwp-child-reports' ) ] = add_query_arg(
					array(
						'page'     => 'bp-profile-setup',
						'mode'     => 'delete_group',
						'group_id' => $group_id,
					),
					admin_url( 'users.php' )
				);
			} else {
				$field = new \BP_XProfile_Field( $field_id );
				if ( empty( $field->type ) ) {
					return $links;
				}
				$links[ esc_html__( 'Edit', 'mainwp-child-reports' ) ]   = add_query_arg(
					array(
						'page'     => 'bp-profile-setup',
						'mode'     => 'edit_field',
						'group_id' => $group_id,
						'field_id' => $field_id,
					),
					admin_url( 'users.php' )
				);
				$links[ esc_html__( 'Delete', 'mainwp-child-reports' ) ] = add_query_arg(
					array(
						'page'     => 'bp-profile-setup',
						'mode'     => 'delete_field',
						'field_id' => $field_id,
					),
					admin_url( 'users.php' )
				);
			}
		}

		return $links;
	}

    /**
     * Register with parent class.
     *
     * @uses \WP_MainWP_Stream\Connector::register()
     */
    public function register() {
		parent::register();

		$this->options = array_merge(
			$this->options,
			array(
				'hide-loggedout-adminbar'       => array(
					'label' => esc_html_x( 'Toolbar', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
				'_bp_force_buddybar'            => array(
					'label' => esc_html_x( 'Toolbar', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-account-deletion'   => array(
					'label' => esc_html_x( 'Account Deletion', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-profile-sync'       => array(
					'label' => esc_html_x( 'Profile Syncing', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
				'bp_restrict_group_creation'    => array(
					'label' => esc_html_x( 'Group Creation', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
				'bb-config-location'            => array(
					'label' => esc_html_x( 'bbPress Configuration', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-blogforum-comments' => array(
					'label' => _x( 'Blog &amp; Forum Comments', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
				'_bp_enable_heartbeat_refresh'  => array(
					'label' => esc_html_x( 'Activity auto-refresh', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
				'_bp_enable_akismet'            => array(
					'label' => esc_html_x( 'Akismet', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-avatar-uploads'     => array(
					'label' => esc_html_x( 'Avatar Uploads', 'buddypress', 'mainwp-child-reports' ),
					'page'  => 'bp-settings',
				),
			)
		);
	}

    /**
     * Update option callback.
     *
     * @param string $option Option to update.
     * @param string $old Old option value.
     * @param string $new New option value.
     */
    public function callback_update_option($option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

    /**
     * Add option callback.
     *
     * @param string $option Option to update.
     * @param string $val Option value.
     */
    public function callback_add_option($option, $val ) {
		$this->check( $option, null, $val );
	}

    /**
     * Delete option callback.
     *
     * @param string $option Option to update.
     */
    public function callback_delete_option($option ) {
		$this->check( $option, null, null );
	}

    /**
     * Update site option callback.
     *
     * @param string $option Option to update.
     * @param string $old Old option value.
     * @param string $new New option value.
     */
    public function callback_update_site_option($option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

    /**
     * Add site option callback.
     *
     * @param string $option Option to update.
     * @param string $val Option value.
     */
    public function callback_add_site_option($option, $val ) {
		$this->check( $option, null, $val );
	}

    /**
     * Delete site option callback.
     *
     * @param string $option Option to update.
     */
    public function callback_delete_site_option($option ) {
		$this->check( $option, null, null );
	}

    /**
     * Check if option values exist.
     *
     * @param string $option Option to update.
     * @param string $old_value Old option value.
     * @param string $new_value New option value.
     */
    public function check($option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, $this->options ) ) {
			return;
		}

		$replacement = str_replace( '-', '_', $option );

		if ( method_exists( $this, 'check_' . $replacement ) ) {
			call_user_func( array(
				$this,
				'check_' . $replacement,
			), $old_value, $new_value );
		} else {
			$data         = $this->options[ $option ];
			$option_title = $data['label'];
			$context      = isset( $data['context'] ) ? $data['context'] : 'settings';
			$page         = isset( $data['page'] ) ? $data['page'] : null;

			$this->log(
				// translators: Placeholder refers to setting name (e.g. "Group Creation")
				__( '"%s" setting updated', 'mainwp-child-reports' ),
				compact( 'option_title', 'option', 'old_value', 'new_value', 'page' ),
				null,
				$context,
				isset( $data['action'] ) ? $data['action'] : 'updated'
			);
		}
	}

    /**
     * Check active components.
     *
     * @param string $old_value Old option value.
     * @param string $new_value New option value.
     */
    public function check_bp_active_components($old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( $this->get_changed_keys( $old_value, $new_value, 0 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		$components = \bp_core_admin_get_components();

		$actions = array(
			true  => esc_html__( 'activated', 'mainwp-child-reports' ),
			false => esc_html__( 'deactivated', 'mainwp-child-reports' ),
		);

		foreach ( $options as $option => $option_value ) {
			if ( ! isset( $components[ $option ], $actions[ $option_value ] ) ) {
				continue;
			}

			$this->log(
				sprintf(
					// translators: Placeholder refers to component title (e.g. "Members").
					__( '"%1$s" component %2$s', 'mainwp-child-reports' ),
					$components[ $option ]['title'],
					$actions[ $option_value ]
				),
				array(
					'option'     => $option,
					'option_key' => 'bp-active-components',
					'old_value'  => $old_value,
					'value'      => $new_value,
				),
				null,
				'components',
				$option_value ? 'activated' : 'deactivated'
			);
		}
	}

    /**
     * Check buddypress pages.
     *
     * @param string $old_value Old option value.
     * @param string $new_value New option value.
     */
    public function check_bp_pages($old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( $this->get_changed_keys( $old_value, $new_value, 0 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		$pages = array_merge(
			$this->bp_get_directory_pages(),
			array(
				'register' => esc_html_x( 'Register', 'buddypress', 'mainwp-child-reports' ),
				'activate' => esc_html_x( 'Activate', 'buddypress', 'mainwp-child-reports' ),
			)
		);

		foreach ( $options as $option => $option_value ) {
			if ( ! isset( $pages[ $option ] ) ) {
				continue;
			}

			$page = ! empty( $new_value[ $option ] ) ? get_post( $new_value[ $option ] )->post_title : esc_html__( 'No page', 'mainwp-child-reports' );

			$this->log(
				sprintf(
					// translators: Placeholders refer to a directory page, and a page title (e.g. "Register", "Registration" ).
					__( '"%1$s" page set to "%2$s"', 'mainwp-child-reports' ),
					$pages[ $option ],
					$page
				),
				array(
					'option'     => $option,
					'option_key' => 'bp-pages',
					'old_value'  => $old_value,
					'value'      => $new_value,
					'page_id'    => empty( $new_value[ $option ] ) ? 0 : $new_value[ $option ],
				),
				null,
				'components',
				'updated'
			);
		}
	}

    /**
     * Buddypress before delete activity callback.
     *
     * @param array $args Deletion arguments.
     */
    public function callback_bp_before_activity_delete($args ) {
		if ( empty( $args['id'] ) ) { // Bail if we're deleting in bulk
			$this->_delete_activity_args = $args;

			return;
		}

		$activity = new \BP_Activity_Activity( $args['id'] );

		$this->_deleted_activity = $activity;
	}

    /**
     * Buddypress delete activities callback.
     *
     * @param array $activities_ids Activity IDs
     */
    public function callback_bp_activity_deleted_activities($activities_ids ) {
		if ( 1 === count( $activities_ids ) && isset( $this->_deleted_activity ) ) { // Single activity deletion
			$activity = $this->_deleted_activity;
			$this->log(
				sprintf(
					// translators: Placeholder refers to an activity title (e.g. "Update").
					__( '"%s" activity deleted', 'mainwp-child-reports' ),
					strip_tags( $activity->action )
				),
				array(
					'id'      => $activity->id,
					'item_id' => $activity->item_id,
					'type'    => $activity->type,
					'author'  => $activity->user_id,
				),
				$activity->id,
				$activity->component,
				'deleted'
			);
		} else {

            /**
             * Bulk deletion.
             *
             * Sometimes some objects removal are followed by deleting relevant
             * activities, so we probably don't need to track those.
             */
			if ( $this->ignore_activity_bulk_deletion ) {
				$this->ignore_activity_bulk_deletion = false;

				return;
			}
			$this->log(
				sprintf(
					// translators: Placeholder refers to an activity title (e.g. "Update").
					__( '"%s" activities were deleted', 'mainwp-child-reports' ),
					count( $activities_ids )
				),
				array(
					'count' => count( $activities_ids ),
					'args'  => $this->_delete_activity_args,
					'ids'   => $activities_ids,
				),
				null,
				'activity',
				'deleted'
			);
		}
	}

    /**
     * Buddypress mark as spam callback.
     *
     * @param array $activity Activity to mark as spam.
     * @param $by ID, item_id, type, user_id.
     */
    public function callback_bp_activity_mark_as_spam($activity, $by ) {
		unset( $by );

		$this->log(
			sprintf(
				// translators: Placeholder refers to an activity title (e.g. "Update")
				__( 'Marked activity "%s" as spam', 'mainwp-child-reports' ),
				strip_tags( $activity->action )
			),
			array(
				'id'      => $activity->id,
				'item_id' => $activity->item_id,
				'type'    => $activity->type,
				'author'  => $activity->user_id,
			),
			$activity->id,
			$activity->component,
			'spammed'
		);
	}


    /**
     * Buddypress mark as ham callback.
     *
     * @param array $activity Activity to mark as spam.
     * @param $by ID, item_id, type, user_id.
     */
    public function callback_bp_activity_mark_as_ham($activity, $by ) {
		unset( $by );

		$this->log(
			sprintf(
				// translators: Placeholder refers to an activity title (e.g. "Update").
				__( 'Unmarked activity "%s" as spam', 'mainwp-child-reports' ),
				strip_tags( $activity->action )
			),
			array(
				'id'      => $activity->id,
				'item_id' => $activity->item_id,
				'type'    => $activity->type,
				'author'  => $activity->user_id,
			),
			$activity->id,
			$activity->component,
			'unspammed'
		);
	}

    /**
     * Buddypress admin after edit activity callback.
     *
     * @param array $activity Activity to mark as spam.
     * @param $error Error message.
     */
    public function callback_bp_activity_admin_edit_after($activity, $error ) {
		unset( $error );

		$this->log(
			sprintf(
				// translators: Placeholder refers to an activity title (e.g. "Update")
				__( '"%s" activity updated', 'mainwp-child-reports' ),
				strip_tags( $activity->action )
			),
			array(
				'id'      => $activity->id,
				'item_id' => $activity->item_id,
				'type'    => $activity->type,
				'author'  => $activity->user_id,
			),
			$activity->id,
			'activity',
			'updated'
		);
	}

    /**
     * Group action.
     *
     * @param array $group Group array.
     * @param strign $action Action to perform.
     * @param array $meta Meta data.
     * @param string $message Response message.
     */
    public function group_action($group, $action, $meta = array(), $message = null ) {
		if ( is_numeric( $group ) ) {
			$group = \groups_get_group(
				array(
					'group_id' => $group,
				)
			);
		}

		$replacements = array(
			$group->name,
		);

		if ( ! $message ) {
			if ( 'created' === $action ) {
				// translators: Placeholder refers to a group name (e.g. "Favourites")
				$message = esc_html__( '"%s" group created', 'mainwp-child-reports' );
			} elseif ( 'updated' === $action ) {
				// translators: Placeholder refers to a group name (e.g. "Favourites")
				$message = esc_html__( '"%s" group updated', 'mainwp-child-reports' );
			} elseif ( 'deleted' === $action ) {
				// translators: Placeholder refers to a group name (e.g. "Favourites")
				$message = esc_html__( '"%s" group deleted', 'mainwp-child-reports' );
			} elseif ( 'joined' === $action ) {
				// translators: Placeholder refers to a group name (e.g. "Favourites")
				$message = esc_html__( 'Joined group "%s"', 'mainwp-child-reports' );
			} elseif ( 'left' === $action ) {
				// translators: Placeholder refers to a group name (e.g. "Favourites")
				$message = esc_html__( 'Left group "%s"', 'mainwp-child-reports' );
			} elseif ( 'banned' === $action ) {
				// translators: Placeholders refer to a user display name, and a group name (e.g. "Jane Doe", "Favourites")
				$message        = esc_html__( 'Banned "%2$s" from "%1$s"', 'mainwp-child-reports' );
				$replacements[] = get_user_by( 'id', $meta['user_id'] )->display_name;
			} elseif ( 'unbanned' === $action ) {
				// translators: Placeholders refer to a user display name, and a group name (e.g. "Jane Doe", "Favourites")
				$message        = esc_html__( 'Unbanned "%2$s" from "%1$s"', 'mainwp-child-reports' );
				$replacements[] = get_user_by( 'id', $meta['user_id'] )->display_name;
			} elseif ( 'removed' === $action ) {
				// translators: Placeholders refer to a user display name, and a group name (e.g. "Jane Doe", "Favourites")
				$message        = esc_html__( 'Removed "%2$s" from "%1$s"', 'mainwp-child-reports' );
				$replacements[] = get_user_by( 'id', $meta['user_id'] )->display_name;
			} else {
				return;
			}
		}

		$this->log(
			vsprintf(
				$message,
				$replacements
			),
			array_merge(
				array(
					'id'   => $group->id,
					'name' => $group->name,
					'slug' => $group->slug,
				),
				$meta
			),
			$group->id,
			'groups',
			$action
		);
	}

    /**
     * Create groups callback.
     *
     * @param string $group_id Group ID.
     * @param string $member Member data.
     * @param array $group Buddypress group.
     */
    public function callback_groups_create_group($group_id, $member, $group ) {
		unset( $group_id );
		unset( $member );

		$this->group_action( $group, 'created' );
	}

    /**
     * Update group calback.
     *
     * @param string $group_id Group ID.
     * @param array $group Buddypress group.
     */
    public function callback_groups_update_group($group_id, $group ) {
		unset( $group_id );

		$this->group_action( $group, 'updated' );
	}

    /**
     * Before delete groups callback.
     *
     * @param string $group_id Group ID.
     */
    public function callback_groups_before_delete_group($group_id ) {
		$this->ignore_activity_bulk_deletion = true;
		$this->group_action( $group_id, 'deleted' );
	}

    /**
     * Updated group details callback.
     *
     * @param string $group_id Group ID.
     */
    public function callback_groups_details_updated($group_id ) {
		$this->is_update = true;
		$this->group_action( $group_id, 'updated' );
	}

    /**
     * Updated group settings.
     *
     * @param string $group_id Group ID.
     */
    public function callback_groups_settings_updated($group_id ) {
		if ( $this->is_update ) {
			return;
		}
		$this->group_action( $group_id, 'updated' );
	}

    /**
     * Leave groups callback.
     *
     * @param string $group_id Group ID.
     * @param string $user_id User ID.
     */
    public function callback_groups_leave_group($group_id, $user_id ) {
		$this->group_action( $group_id, 'left', compact( 'user_id' ) );
	}

    /**
     * Join group callback.
     *
     * @param string $group_id Group ID.
     * @param string $user_id User ID.
     */
    public function callback_groups_join_group($group_id, $user_id ) {
		$this->group_action( $group_id, 'joined', compact( 'user_id' ) );
	}

    /**
     * Promote member.
     *
     * @param string $group_id Group ID.
     * @param string $user_id User ID.
     * @param string $status Member status.
     */
    public function callback_groups_promote_member($group_id, $user_id, $status ) {
		$group   = \groups_get_group(
			array(
				'group_id' => $group_id,
			)
		);
		$user    = new \WP_User( $user_id );
		$roles   = array(
			'admin' => esc_html_x( 'Administrator', 'buddypress', 'mainwp-child-reports' ),
			'mod'   => esc_html_x( 'Moderator', 'buddypress', 'mainwp-child-reports' ),
		);
		$message = sprintf(
			// translators: Placeholders refer to a user's display name, a user role, and a group name (e.g. "Jane Doe", "subscriber", "Favourites").
			__( 'Promoted "%1$s" to "%2$s" in "%3$s"', 'mainwp-child-reports' ),
			$user->display_name,
			$roles[ $status ],
			$group->name
		);
		$this->group_action( $group_id, 'promoted', compact( 'user_id', 'status' ), $message );
	}

    /**
     * Demote member.
     *
     * @param string $group_id Group ID.
     * @param string $user_id User ID.
     */
    public function callback_groups_demote_member($group_id, $user_id ) {
		$group   = \groups_get_group(
			array(
				'group_id' => $group_id,
			)
		);
		$user    = new \WP_User( $user_id );
		$message = sprintf(
			// translators: Placeholders refer to a user's display name, a user role, and a group name (e.g. "Jane Doe", "Member", "Favourites").
			__( 'Demoted "%1$s" to "%2$s" in "%3$s"', 'mainwp-child-reports' ),
			$user->display_name,
			_x( 'Member', 'buddypress', 'mainwp-child-reports' ),
			$group->name
		);
		$this->group_action( $group_id, 'demoted', compact( 'user_id' ), $message );
	}

    /**
     * Ban member.
     *
     * @param string $group_id Group ID.
     * @param string $user_id User ID.
     */
    public function callback_groups_ban_member($group_id, $user_id ) {
		$this->group_action( $group_id, 'banned', compact( 'user_id' ) );
	}

    /**
     * Unban member.
     *
     * @param string $group_id Group ID.
     * @param string $user_id User ID.
     */
    public function callback_groups_unban_member($group_id, $user_id ) {
		$this->group_action( $group_id, 'unbanned', compact( 'user_id' ) );
	}

    /**
     * Remove member.
     *
     * @param string $group_id Group ID.
     * @param string $user_id User ID.
     */
    public function callback_groups_remove_member($group_id, $user_id ) {
		$this->group_action( $group_id, 'removed', compact( 'user_id' ) );
	}

    /**
     * Field action.
     *
     * @param string $field Form field.
     * @param string $action Action to perform.
     * @param array $meta Meta data.
     * @param null $message Response message.
     */
    public function field_action($field, $action, $meta = array(), $message = null ) {
		$replacements = array(
			$field->name,
		);

		if ( ! $message ) {
			if ( 'created' === $action ) {
				// translators: Placeholder refers to a user profile field (e.g. "Job Title")
				$message = esc_html__( 'Created profile field "%s"', 'mainwp-child-reports' );
			} elseif ( 'updated' === $action ) {
				// translators: Placeholder refers to a user profile field (e.g. "Job Title")
				$message = esc_html__( 'Updated profile field "%s"', 'mainwp-child-reports' );
			} elseif ( 'deleted' === $action ) {
				// translators: Placeholder refers to a user profile field (e.g. "Job Title")
				$message = esc_html__( 'Deleted profile field "%s"', 'mainwp-child-reports' );
			} else {
				return;
			}
		}

		$this->log(
			vsprintf(
				$message,
				$replacements
			),
			array_merge(
				array(
					'field_id'   => $field->id,
					'field_name' => $field->name,
					'group_id'   => $field->group_id,
				),
				$meta
			),
			$field->id,
			'profile_fields',
			$action
		);
	}

    /**
     * XPROFILE save field calback.
     *
     * @param string $field Form field.
     */
    public function callback_xprofile_field_after_save($field ) {
		$action = isset( $field->id ) ? 'updated' : 'created';
		$this->field_action( $field, $action );
	}

    /**
     * XPROFILE delete field callback.
     * @param string $field Form field.
     */
    public function callback_xprofile_fields_deleted_field($field ) {
		$this->field_action( $field, 'deleted' );
	}

    /**
     * Field group action.
     *
     * @param string $group Form field group.
     * @param string $action Action to perform.
     * @param array $meta Meta data.
     * @param null $message Response message.
     */
    public function field_group_action($group, $action, $meta = array(), $message = null ) {
		$replacements = array(
			$group->name,
		);

		if ( ! $message ) {
			if ( 'created' === $action ) {
				// translators: Placeholder refers to a user profile field group (e.g. "Appearance")
				$message = esc_html__( 'Created profile field group "%s"', 'mainwp-child-reports' );
			} elseif ( 'updated' === $action ) {
				// translators: Placeholder refers to a user profile field group (e.g. "Appearance")
				$message = esc_html__( 'Updated profile field group "%s"', 'mainwp-child-reports' );
			} elseif ( 'deleted' === $action ) {
				// translators: Placeholder refers to a user profile field group (e.g. "Appearance")
				$message = esc_html__( 'Deleted profile field group "%s"', 'mainwp-child-reports' );
			} else {
				return;
			}
		}

		$this->log(
			vsprintf(
				$message,
				$replacements
			),
			array_merge(
				array(
					'group_id'   => $group->id,
					'group_name' => $group->name,
				),
				$meta
			),
			$group->id,
			'profile_fields',
			$action
		);
	}

    /**
     * XPROFILE save group calback.
     *
     * @param string $group Form field group.
     */
    public function callback_xprofile_group_after_save($group ) {

	    /** @global object $wpdb WordPress Database instance. */
		global $wpdb;

		// a bit hacky, due to inconsistency with BP action scheme, see callback_xprofile_field_after_save for correct behavior
		$action = ( $group->id === $wpdb->insert_id ) ? 'created' : 'updated';
		$this->field_group_action( $group, $action );
	}

    /**
     * XPROFILE delete groups callback.
     *
     * @param string $group Form field group.
     */
    public function callback_xprofile_groups_deleted_group($group ) {
		$this->field_group_action( $group, 'deleted' );
	}

    /**
     * Buddypress get directory pages.
     *
     * @return array Directory pages array.
     */
    private function bp_get_directory_pages() {
		$bp              = \buddypress();
		$directory_pages = array();

		// Loop through loaded components and collect directories.
		if ( is_array( $bp->loaded_components ) ) {
			foreach ( $bp->loaded_components as $component_slug => $component_id ) {
				// Only components that need directories should be listed here.
				if ( isset( $bp->{$component_id} ) && ! empty( $bp->{$component_id}->has_directory ) ) {
					// component->name was introduced in BP 1.5, so we must provide a fallback.
					$directory_pages[ $component_id ] = ! empty( $bp->{$component_id}->name ) ? $bp->{$component_id}->name : ucwords( $component_id );
				}
			}
		}

		return $directory_pages;
	}
}
