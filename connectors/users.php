<?php

class MainWP_WP_Stream_Connector_Users extends MainWP_WP_Stream_Connector {

	public static $name = 'users';

	protected static $_users_object_pre_deleted = array();

	public static $actions = array(
		'user_register',
		'profile_update',
		'delete_user',
		'deleted_user',
		'set_user_role',
	);

	public static function get_label() {
		return __( 'Users', 'default' );
	}

	public static function get_action_labels() {
		return array(
			'updated'         => __( 'Updated', 'mainwp-child-reports' ),
			'created'         => __( 'Created', 'mainwp-child-reports' ),
			'deleted'         => __( 'Deleted', 'mainwp-child-reports' ),
//			'password-reset'  => __( 'Password Reset', 'default' ),
//			'forgot-password' => __( 'Lost Password', 'default' ),
//			'login'           => __( 'Log In', 'default' ),
//			'logout'          => __( 'Log Out', 'default' ),
		);
	}

	public static function get_context_labels() {
		return array(
			'users'    => __( 'Users', 'default' ),
			'sessions' => __( 'Sessions', 'mainwp-child-reports' ),
			'profiles' => __( 'Profiles', 'mainwp-child-reports' ),
		);
	}

	public static function action_links( $links, $record ) {
		if ( $record->object_id ) {
			if ( $link = get_edit_user_link( $record->object_id ) ) {
				$links [ __( 'Edit User', 'default' ) ] = $link;
			}
		}

		return $links;
	}

	public static function get_role_labels( $user ) {
		if ( is_int( $user ) ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! is_a( $user, 'WP_User' ) ) {
			return array();
		}

		global $wp_roles;

		$roles  = $wp_roles->get_names();
		$labels = array();

		foreach ( $roles as $role => $label ) {
			if ( in_array( $role, (array) $user->roles ) ) {
				$labels[] = translate_user_role( $label );
			}
		}

		return $labels;
	}

	public static function callback_user_register( $user_id ) {
		$current_user    = wp_get_current_user();
		$registered_user = get_user_by( 'id', $user_id );

		if ( ! $current_user->ID ) { // Non logged-in user registered themselves
			$message     = __( 'New user registration', 'mainwp-child-reports' );
			$user_to_log = $registered_user->ID;
		} else { // Current logged-in user created a new user
			$message     = _x(
				'New user account created for %1$s (%2$s)',
				'1: User display name, 2: User role',
				'mainwp_child_reports'
			);
			$user_to_log = $current_user->ID;
		}

		self::log(
			$message,
			array(
				'display_name' => ( $registered_user->display_name ) ? $registered_user->display_name : $registered_user->user_login,
				'roles'        => implode( ', ', self::get_role_labels( $user_id ) ),
			),
			$registered_user->ID,
			array( 'users' => 'created' ),
			$user_to_log
		);
	}

	public static function callback_profile_update( $user_id, $user ) {
		self::log(
			__( '%s\'s profile was updated', 'mainwp-child-reports' ),
			array(
				'display_name' => $user->display_name,
			),
			$user->ID,
			array( 'profiles' => 'updated' )
		);
	}

	public static function callback_set_user_role( $user_id, $new_role, $old_roles ) {
		if ( empty( $old_roles ) ) {
			return;
		}

		global $wp_roles;

		self::log(
			_x(
				'%1$s\'s role was changed from %2$s to %3$s',
				'1: User display name, 2: Old role, 3: New role',
				'mainwp_child_reports'
			),
			array(
				'display_name' => get_user_by( 'id', $user_id )->display_name,
				'old_role'     => translate_user_role( $wp_roles->role_names[ $old_roles[0] ] ),
				'new_role'     => translate_user_role( $wp_roles->role_names[ $new_role ] ),
			),
			$user_id,
			array( 'profiles' => 'updated' )
		);
	}

	public static function callback_delete_user( $user_id ) {
		if ( ! isset( self::$_users_object_pre_deleted[ $user_id ] ) ) {
			self::$_users_object_pre_deleted[ $user_id ] = get_user_by( 'id', $user_id );
		}
	}

	public static function callback_deleted_user( $user_id ) {
		$user = wp_get_current_user();

		if ( isset( self::$_users_object_pre_deleted[ $user_id ] ) ) {
			$message      = _x(
				'%1$s\'s account was deleted (%2$s)',
				'1: User display name, 2: User roles',
				'mainwp_child_reports'
			);
			$display_name = self::$_users_object_pre_deleted[ $user_id ]->display_name;
			$deleted_user = self::$_users_object_pre_deleted[ $user_id ];
			unset( self::$_users_object_pre_deleted[ $user_id ] );
		} else {
			$message      = __( 'User account #%d was deleted', 'mainwp-child-reports' );
			$display_name = $user_id;
			$deleted_user = $user_id;
		}

		self::log(
			$message,
			array(
				'display_name' => $display_name,
				'roles'        => implode( ', ', self::get_role_labels( $deleted_user ) ),
			),
			$user_id,
			array( 'users' => 'deleted' ),
			$user->ID
		);
	}

}
