<?php
/** MainWP Child Reports Users Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_Users.
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_Users extends Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'users';

	/**
	 * Stores users object before the user being deleted.
	 */
	protected $_users_object_pre_deleted = array();

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'user_register',
		'profile_update',
		'password_reset',
		'retrieve_password',
//		'set_logged_in_cookie',
//		'clear_auth_cookie',
		'delete_user',
		'deleted_user',
		'set_user_role',
	);

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html__( 'Users', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action term labels
	 *
	 * @return array Action terms label translation
	 */
	public function get_action_labels() {
		return array(
			'updated'         => esc_html__( 'Updated', 'mainwp-child-reports' ),
			'created'         => esc_html__( 'Created', 'mainwp-child-reports' ),
			'deleted'         => esc_html__( 'Deleted', 'mainwp-child-reports' ),
			'password-reset'  => esc_html__( 'Password Reset', 'mainwp-child-reports' ),
			'forgot-password' => esc_html__( 'Lost Password', 'mainwp-child-reports' ),
			'switched-to'     => esc_html__( 'Switched To', 'mainwp-child-reports' ),
			'switched-back'   => esc_html__( 'Switched Back', 'mainwp-child-reports' ),
			'switched-off'    => esc_html__( 'Switched Off', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'users'    => esc_html__( 'Users', 'mainwp-child-reports' ),
			'sessions' => esc_html__( 'Sessions', 'mainwp-child-reports' ),
			'profiles' => esc_html__( 'Profiles', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}
	 *
	 * @param array $links   Previous links registered
	 * @param Record $record Stream record
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		if ( $record->object_id ) {
			$link = get_edit_user_link( $record->object_id );
			if ( $link ) {
				$links [ esc_html__( 'Edit User', 'mainwp-child-reports' ) ] = $link;
			}
		}

		return $links;
	}

	/**
	 * Get an array of role lables assigned to a specific user.
	 *
	 * @param  object|int $user User object or user ID to get roles for
	 *
	 * @return array $labels    An array of role labels
	 */
	public function get_role_labels( $user ) {
		if ( is_int( $user ) ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! is_a( $user, 'WP_User' ) ) {
			return array();
		}

		/** @global object $wp_roles Core class used to implement a user roles API. */
		global $wp_roles;

		$roles  = $wp_roles->get_names();
		$labels = array();

		foreach ( $roles as $role => $label ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				$labels[] = translate_user_role( $label );
			}
		}

		return $labels;
	}

	/**
	 * Log user registrations
	 *
	 * @action user_register
	 *
	 * @param int $user_id Newly registered user ID
	 */
	public function callback_user_register( $user_id ) {
		$current_user    = wp_get_current_user();
		$registered_user = get_user_by( 'id', $user_id );

		if ( ! $current_user->ID ) { // Non logged-in user registered themselves
			$message     = esc_html__( 'New user registration', 'mainwp-child-reports' );
			$user_to_log = $registered_user->ID;
		} else { // Current logged-in user created a new user
			// translators: Placeholders refer to a user display name, and a user role (e.g. "Jane Doe", "subscriber")
			$message     = _x(
				'New user account created for %1$s (%2$s)',
				'1: User display name, 2: User role',
				'mainwp-child-reports'
			);
			$user_to_log = $current_user->ID;
		}

		$this->log(
			$message,
			array(
				'display_name' => ( $registered_user->display_name ) ? $registered_user->display_name : $registered_user->user_login,
				'roles'        => implode( ', ', $this->get_role_labels( $user_id ) ),
			),
			$registered_user->ID,
			'users',
			'created',
			$user_to_log
		);
	}

	/**
	 * Log profile update
	 *
	 * @action profile_update
	 *
	 * @param int $user_id   registered user ID
	 * @param \WP_User $user registered user object
	 */
	public function callback_profile_update( $user_id, $user ) {
		unset( $user_id );

		$this->log(
			// translators: Placeholder refers to a user display name (e.g. "Jane Doe")
			__( '%s\'s profile was updated', 'mainwp-child-reports' ),
			array(
				'display_name' => $user->display_name,
			),
			$user->ID,
			'profiles',
			'updated'
		);
	}

	/**
	 * Log role transition
	 *
	 * @action set_user_role
	 *
	 * @param int $user_id
	 * @param string $new_role
	 * @param array $old_roles
	 */
	public function callback_set_user_role( $user_id, $new_role, $old_roles ) {
		if ( empty( $old_roles ) ) {
			return;
		}

		/** @global object $wp_roles Core class used to implement a user roles API. */
		global $wp_roles;

		$this->log(
			/**
			 * translators: Placeholders refer to a user display name,
			 * a user role, and another user role (e.g. "Jane Doe", "editor", "subscriber").
			 */
			_x(
				'%1$s\'s role was changed from %2$s to %3$s',
				'1: User display name, 2: Old role, 3: New role',
				'mainwp-child-reports'
			),
			array(
				'display_name' => get_user_by( 'id', $user_id )->display_name,
				'old_role'     => translate_user_role( $wp_roles->role_names[ current( $old_roles ) ] ),
				'new_role'     => $new_role ? translate_user_role( $wp_roles->role_names[ $new_role ] ) : __( 'N/A', 'mainwp-child-reports' ),
			),
			$user_id,
			'profiles',
			'updated'
		);
	}

	/**
	 * Log password reset
	 *
	 * @action password_reset
	 *
	 * @param \WP_User $user
	 */
	public function callback_password_reset( $user ) {
		$this->log(
			// translators: Placeholder refers to a user display name (e.g. "Jane Doe")
			__( '%s\'s password was reset', 'mainwp-child-reports' ),
			array(
				'email' => $user->display_name,
			),
			$user->ID,
			'profiles',
			'password-reset',
			$user->ID
		);
	}

	/**
	 * Log user requests to retrieve passwords
	 *
	 * @action retrieve_password
	 *
	 * @param string $user_login
	 */
	public function callback_retrieve_password( $user_login ) {
		if ( wp_mainwp_stream_filter_var( $user_login, FILTER_VALIDATE_EMAIL ) ) {
			$user = get_user_by( 'email', $user_login );
		} else {
			$user = get_user_by( 'login', $user_login );
		}

		$this->log(
			// translators: Placeholder refers to a user display name (e.g. "Jane Doe")
			__( '%s\'s password was requested to be reset', 'mainwp-child-reports' ),
			array(
				'display_name' => $user->display_name,
			),
			$user->ID,
			'sessions',
			'forgot-password',
			$user->ID
		);
	}

	/**
	 * Log user login
	 *
	 * @action set_logged_in_cookie
	 *
	 * @param string $logged_in_cookie
	 * @param int $expire
	 * @param int $expiration
	 * @param int $user_id
     *
     * @derecated DISABLED
	 */
	public function callback_set_logged_in_cookie( $logged_in_cookie, $expire, $expiration, $user_id ) {
		unset( $logged_in_cookie );
		unset( $expire );
		unset( $expiration );
		$user = get_user_by( 'id', $user_id );

		$this->log(
			// translators: Placeholder refers to a user display name (e.g. "Jane Doe")
			__( '%s logged in', 'mainwp-child-reports' ),
			array(
				'display_name' => $user->display_name,
			),
			$user->ID,
			'sessions',
			'login',
			$user->ID
		);
	}

	/**
	 * Log user logout
	 *
	 * @action clear_auth_cookie
     *
     * @deprecated DISABLED
	 */
	public function callback_clear_auth_cookie() {
		$user = wp_get_current_user();

		// For some reason, incognito mode calls clear_auth_cookie on failed login attempts
		if ( empty( $user ) || ! $user->exists() ) {
			return;
		}

		$this->log(
			// translators: Placeholder refers to a user display name (e.g. "Jane Doe")
			__( '%s logged out', 'mainwp-child-reports' ),
			array(
				'display_name' => $user->display_name,
			),
			$user->ID,
			'sessions',
			'logout',
			$user->ID
		);
	}

	/**
	 * There's no logging in this callback's action, the reason
	 * behind this hook is so that we can store user objects before
	 * being deleted. During `deleted_user` hook, our callback
	 * receives $user_id param but it's useless as the user record
	 * was already removed from DB.
	 *
	 * @action delete_user
	 * @param int $user_id User ID that maybe deleted
	 */
	public function callback_delete_user( $user_id ) {
		if ( ! isset( $this->_users_object_pre_deleted[ $user_id ] ) ) {
			$this->_users_object_pre_deleted[ $user_id ] = get_user_by( 'id', $user_id );
		}
	}

	/**
	 * Log deleted user.
	 *
	 * @action deleted_user
	 * @param int $user_id Deleted user ID
	 */
	public function callback_deleted_user( $user_id ) {
		$user = wp_get_current_user();

		if ( isset( $this->_users_object_pre_deleted[ $user_id ] ) ) {
			// translators: Placeholders refer to a user display name, and a user role (e.g. "Jane Doe", "subscriber")
			$message      = _x(
				'%1$s\'s account was deleted (%2$s)',
				'1: User display name, 2: User roles',
				'mainwp-child-reports'
			);
			$display_name = $this->_users_object_pre_deleted[ $user_id ]->display_name;
			$deleted_user = $this->_users_object_pre_deleted[ $user_id ];
			unset( $this->_users_object_pre_deleted[ $user_id ] );
		} else {
			// translators: Placeholders refer to a user display name, and a user role (e.g. "Jane Doe", "subscriber")
			$message      = esc_html__( 'User account #%d was deleted', 'mainwp-child-reports' );
			$display_name = $user_id;
			$deleted_user = $user_id;
		}

		$this->log(
			$message,
			array(
				'display_name' => $display_name,
				'roles'        => implode( ', ', $this->get_role_labels( $deleted_user ) ),
			),
			$user_id,
			'users',
			'deleted',
			$user->ID
		);
	}
}
