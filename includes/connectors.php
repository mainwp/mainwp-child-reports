<?php

class MainWP_WP_Stream_Connectors {

	public static $connectors = array();

	public static $term_labels = array(
		'stream_connector' => array(),
		'stream_context'   => array(),
		'stream_action'    => array(),
	);

	protected static $admin_notices = array();

	public static function load() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		require_once MAINWP_WP_STREAM_INC_DIR . 'connector.php';

		$connectors = array(
			'comments',
			'editor',
			'installer',
			'media',
			'menus',
			'posts',
			'users',
			'widgets',
			'updraftplus',
			'backupwordpress',
			'backwpup',
                        'backupbuddy',
                        'wordfence',
                        'maintenance',
		);
		$classes = array();
		foreach ( $connectors as $connector ) {
			include_once MAINWP_WP_STREAM_DIR . '/connectors/' . $connector .'.php';
			$class     = "MainWP_WP_Stream_Connector_$connector";
			$classes[] = $class;
		}

		$exclude_all_connector = false;

		// Check if logging action is enable for user or provide a hook for plugin to override on specific cases
		if ( ! self::is_logging_enabled_for_user() ) {
			$exclude_all_connector = true;
		}

		// Check if logging action is enable for ip or provide a hook for plugin to override on specific cases
		if ( ! self::is_logging_enabled_for_ip() ) {
			$exclude_all_connector = true;
		}

		self::$connectors = apply_filters( 'mainwp_client_reports_connectors', $classes );

		foreach ( self::$connectors as $connector ) {
			self::$term_labels['stream_connector'][ $connector::$name ] = $connector::get_label();
		}

		// Get excluded connectors
		$excluded_connectors = MainWP_WP_Stream_Settings::get_excluded_by_key( 'connectors' );

		foreach ( self::$connectors as $connector ) {
			// Check if the connectors extends the MainWP_WP_Stream_Connector class, if not skip it
			if ( ! is_subclass_of( $connector, 'MainWP_WP_Stream_Connector' ) ) {
				self::$admin_notices[] = sprintf(
					__( "%s class wasn't loaded because it doesn't extends the %s class.", 'mainwp-child-reports' ),
					$connector,
					'MainWP_WP_Stream_Connector'
				);

				continue;
			}

			// Store connector label
			if ( ! in_array( $connector::$name, self::$term_labels['stream_connector'] ) ) {
				self::$term_labels['stream_connector'][ $connector::$name ] = $connector::get_label();
			}

			$is_excluded_connector = apply_filters( 'mainwp_wp_stream_check_connector_is_excluded', in_array( $connector::$name, $excluded_connectors ), $connector::$name, $excluded_connectors );

			if ( $is_excluded_connector ) {
				continue;
			}

			if ( ! $exclude_all_connector ) {
				$connector::register();
			}

			// Add new terms to our label lookup array
			self::$term_labels['stream_action']  = array_merge(
				self::$term_labels['stream_action'],
				$connector::get_action_labels()
			);
			self::$term_labels['stream_context'] = array_merge(
				self::$term_labels['stream_context'],
				$connector::get_context_labels()
			);
		}

		do_action( 'mainwp_wp_stream_after_connectors_registration', self::$term_labels['stream_connector'] );
	}

	public static function admin_notices() {
		if ( ! empty( self::$admin_notices ) ) :
			?>
			<div class="error">
				<?php foreach ( self::$admin_notices as $message ) : ?>
					<?php echo wpautop( esc_html( $message ) ) // xss ok ?>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	}

	public static function is_logging_enabled_for_user( $user = null ) {
		if ( is_null( $user ) ) {
			$user = wp_get_current_user();
		}

		$bool             = true;
		$user_roles       = array_values( $user->roles );
		$excluded_authors = MainWP_WP_Stream_Settings::get_excluded_by_key( 'authors' );
		$excluded_roles   = MainWP_WP_Stream_Settings::get_excluded_by_key( 'roles' );

		// Don't log excluded users
		if ( in_array( $user->ID, $excluded_authors ) ) {
			$bool = false;
		}

		// Don't log excluded user roles
		if ( 0 !== count( array_intersect( $user_roles, $excluded_roles ) ) ) {
			$bool = false;
		}

		// If the user is not a valid user then we always log the action
		if ( ! ( $user instanceof WP_User ) || 0 === $user->ID ) {
			$bool = true;
		}

		return apply_filters( 'mainwp_wp_stream_record_log', $bool, $user, get_called_class() );
	}

	public static function is_logging_enabled_for_ip( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = mainwp_wp_stream_filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
		} else {
			$ip = mainwp_wp_stream_filter_var( $ip, FILTER_VALIDATE_IP );
		}

		// If ip is not valid the we will log the action
		if ( false === $ip ) {
			$bool = true;
		} else {
			$bool = self::is_logging_enabled( 'ip_addresses', $ip );
		}

		return apply_filters( 'mainwp_wp_stream_ip_record_log', $bool, $ip, get_called_class() );
	}

	public static function is_logging_enabled( $column, $value ) {
		$excluded_values = MainWP_WP_Stream_Settings::get_excluded_by_key( $column );
		$bool            = ( ! in_array( $value, $excluded_values ) );

		return $bool;
	}
}
