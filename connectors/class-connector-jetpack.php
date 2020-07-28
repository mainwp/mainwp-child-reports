<?php
/** Jetpack Connector. */
namespace WP_MainWP_Stream;

/**
 * Class Connector_Jetpack
 * @package WP_MainWP_Stream
 */
class Connector_Jetpack extends Connector {

	/** @var string Connector slug. */
	public $name = 'jetpack';

	/** @const string Holds tracked plugin minimum version required. */
	const PLUGIN_MIN_VERSION = '3.0.2';

	/** @var array Actions registered for this connector. */
	public $actions = array(
		'jetpack_log_entry',
		'sharing_get_services_state',
		'update_option',
		'add_option',
		'delete_option',
		'jetpack_module_configuration_load_monitor',
		'wp_ajax_jetpack_post_by_email_enable', // @todo These three actions do not verify whether the action has been done or if an error has been raised
		'wp_ajax_jetpack_post_by_email_regenerate',
		'wp_ajax_jetpack_post_by_email_disable',
	);

	/** @var bool Register connector in the WP Frontend. */
	public $register_frontend = false;

	/** @var array Tracked option keys. */
	public $options = array();

	/** @var array Tracking registered Settings, with overridden data */
	public $options_override = array();

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not.
	 *
	 * @return bool Return TRUE|FALSE.
	 */
	public function is_dependency_satisfied() {
		if ( class_exists( 'Jetpack' ) && defined( 'JETPACK__VERSION' ) && version_compare( JETPACK__VERSION, self::PLUGIN_MIN_VERSION, '>=' ) ) {
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
		return esc_html_x( 'Jetpack', 'jetpack', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels.
	 *.
	 * @return array Action label translations.
	 */
	public function get_action_labels() {
		return array(
			'activated'   => esc_html_x( 'Activated', 'jetpack', 'mainwp-child-reports' ),
			'deactivated' => esc_html_x( 'Deactivated', 'jetpack', 'mainwp-child-reports' ),
			'register'    => esc_html_x( 'Connected', 'jetpack', 'mainwp-child-reports' ),
			'disconnect'  => esc_html_x( 'Disconnected', 'jetpack', 'mainwp-child-reports' ),
			'authorize'   => esc_html_x( 'Link', 'jetpack', 'mainwp-child-reports' ),
			'unlink'      => esc_html_x( 'Unlink', 'jetpack', 'mainwp-child-reports' ),
			'updated'     => esc_html_x( 'Updated', 'jetpack', 'mainwp-child-reports' ),
			'added'       => esc_html_x( 'Added', 'jetpack', 'mainwp-child-reports' ),
			'removed'     => esc_html_x( 'Removed', 'jetpack', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array Context label translations.
	 */
	public function get_context_labels() {
		return array(
			'blogs'              => esc_html_x( 'Blogs', 'jetpack', 'mainwp-child-reports' ),
			'carousel'           => esc_html_x( 'Carousel', 'jetpack', 'mainwp-child-reports' ),
			'custom-css'         => esc_html_x( 'Custom CSS', 'jetpack', 'mainwp-child-reports' ),
			'gplus-authorship'   => esc_html_x( 'Google+ Profile', 'jetpack', 'mainwp-child-reports' ),
			'infinite-scroll'    => esc_html_x( 'Infinite Scroll', 'jetpack', 'mainwp-child-reports' ),
			'jetpack-comments'   => esc_html_x( 'Comments', 'jetpack', 'mainwp-child-reports' ),
			'likes'              => esc_html_x( 'Likes', 'jetpack', 'mainwp-child-reports' ),
			'minileven'          => esc_html_x( 'Mobile', 'jetpack', 'mainwp-child-reports' ),
			'modules'            => esc_html_x( 'Modules', 'jetpack', 'mainwp-child-reports' ),
			'monitor'            => esc_html_x( 'Monitor', 'jetpack', 'mainwp-child-reports' ),
			'options'            => esc_html_x( 'Options', 'jetpack', 'mainwp-child-reports' ),
			'post-by-email'      => esc_html_x( 'Post by Email', 'jetpack', 'mainwp-child-reports' ),
			'protect'            => esc_html_x( 'Protect', 'jetpack', 'mainwp-child-reports' ),
			'publicize'          => esc_html_x( 'Publicize', 'jetpack', 'mainwp-child-reports' ),
			'related-posts'      => esc_html_x( 'Related Posts', 'jetpack', 'mainwp-child-reports' ),
			'sharedaddy'         => esc_html_x( 'Sharing', 'jetpack', 'mainwp-child-reports' ),
			'subscriptions'      => esc_html_x( 'Subscriptions', 'jetpack', 'mainwp-child-reports' ),
			'sso'                => esc_html_x( 'SSO', 'jetpack', 'mainwp-child-reports' ),
			'stats'              => esc_html_x( 'WordPress.com Stats', 'jetpack', 'mainwp-child-reports' ),
			'tiled-gallery'      => esc_html_x( 'Tiled Galleries', 'jetpack', 'mainwp-child-reports' ),
			'users'              => esc_html_x( 'Users', 'jetpack', 'mainwp-child-reports' ),
			'verification-tools' => esc_html_x( 'Site Verification', 'jetpack', 'mainwp-child-reports' ),
			'videopress'         => esc_html_x( 'VideoPress', 'jetpack', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen.
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}.
	 *
	 * @param array $links   Previous links registered.
	 * @param object $record Stream record.
	 *
	 * @return array Action links.
	 */
	public function action_links( $links, $record ) {
		// @todo provide proper action links
		if ( 'jetpack' === $record->connector ) {
			if ( 'modules' === $record->context ) {
				$slug = $record->get_meta( 'module_slug', true );

				if ( is_array( $slug ) ) {
					$slug = current( $slug );
				}

				if ( \Jetpack::is_module_active( $slug ) ) {
					if ( apply_filters( 'jetpack_module_configurable_' . $slug, false ) ) {
						$links[ esc_html__( 'Configure', 'mainwp-child-reports' ) ] = \Jetpack::module_configuration_url( $slug );
					}

					$links[ esc_html__( 'Deactivate', 'mainwp-child-reports' ) ] = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'deactivate',
								'module' => $slug,
							),
							\Jetpack::admin_url()
						),
						'jetpack_deactivate-' . sanitize_title( $slug )
					);
				} else {
					$links[ esc_html__( 'Activate', 'mainwp-child-reports' ) ] = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'activate',
								'module' => $slug,
							),
							\Jetpack::admin_url()
						),
						'jetpack_activate-' . sanitize_title( $slug )
					);
				}
			} elseif ( \Jetpack::is_module_active( str_replace( 'jetpack-', '', $record->context ) ) ) {
				$slug = str_replace( 'jetpack-', '', $record->context ); // handling jetpack-comment anomaly

				if ( apply_filters( 'jetpack_module_configurable_' . $slug, false ) ) {
					$links[ esc_html__( 'Configure module', 'mainwp-child-reports' ) ] = \Jetpack::module_configuration_url( $slug );
				}
			}
		}

		return $links;
	}

    /**
     * Register with parent class.
     */
    public function register() {
		parent::register();

		add_filter( 'wp_mainwp_stream_log_data', array( $this, 'log_override' ) );

		$this->options = array(
			'jetpack_options'                   => null,
			// Sharing module
			'hide_gplus'                        => null,
			'gplus_authors'                     => null,
			'sharing-options'                   => array(
				'label'   => esc_html__( 'Sharing options', 'mainwp-child-reports' ),
				'context' => 'sharedaddy',
			),
			'sharedaddy_disable_resources'      => null,
			'jetpack-twitter-cards-site-tag'    => array(
				'label'   => esc_html__( 'Twitter site tag', 'mainwp-child-reports' ),
				'context' => 'sharedaddy',
			),
			// Stats module
			'stats_options'                     => array(
				'label'   => esc_html__( 'WordPress.com Stats', 'mainwp-child-reports' ),
				'context' => 'stats',
			),
			// Comments
			'jetpack_comment_form_color_scheme' => array(
				'label'   => esc_html__( 'Color Scheme', 'mainwp-child-reports' ),
				'context' => 'jetpack-comments',
			),
			// Likes
			'disabled_likes'                    => array(
				'label'   => esc_html__( 'WP.com Site-wide Likes', 'mainwp-child-reports' ),
				'context' => 'likes',
			),
			// Mobile
			'wp_mobile_excerpt'                 => array(
				'label'   => esc_html__( 'Excerpts appearance', 'mainwp-child-reports' ),
				'context' => 'minileven',
			),
			'wp_mobile_app_promos'              => array(
				'label'   => esc_html__( 'App promos', 'mainwp-child-reports' ),
				'context' => 'minileven',
			),
		);

		$this->options_override = array(
			// Carousel Module
			'carousel_background_color'        => array(
				'label'   => esc_html__( 'Background color', 'mainwp-child-reports' ),
				'context' => 'carousel',
			),
			'carousel_display_exif'            => array(
				'label'   => esc_html__( 'Metadata', 'mainwp-child-reports' ),
				'context' => 'carousel',
			),
			// Subscriptions
			'stb_enabled'                      => array(
				'label'   => esc_html__( 'Follow blog comment form button', 'mainwp-child-reports' ),
				'context' => 'subscriptions',
			),
			'stc_enabled'                      => array(
				'label'   => esc_html__( 'Follow comments form button', 'mainwp-child-reports' ),
				'context' => 'subscriptions',
			),
			// Jetpack comments
			'highlander_comment_form_prompt'   => array(
				'label'   => esc_html__( 'Greeting Text', 'mainwp-child-reports' ),
				'context' => 'jetpack-comments',
			),
			// Infinite Scroll
			'infinite_scroll_google_analytics' => array(
				'label'   => esc_html__( 'Infinite Scroll Google Analytics', 'mainwp-child-reports' ),
				'context' => 'infinite-scroll',
			),
			// Protect
			'jetpack_protect_blocked_attempts' => array(
				'label'   => esc_html__( 'Blocked Attempts', 'mainwp-child-reports' ),
				'context' => 'protect',
			),
			// SSO
			'jetpack_sso_require_two_step'     => array(
				'label'   => esc_html__( 'Require Two-Step Authentication', 'mainwp-child-reports' ),
				'context' => 'sso',
			),
			'jetpack_sso_match_by_email'       => array(
				'label'   => esc_html__( 'Match by Email', 'mainwp-child-reports' ),
				'context' => 'sso',
			),
			// Related posts
			'jetpack_relatedposts'             => array(
				'show_headline'   => array(
					'label'   => esc_html__( 'Show Related Posts Headline', 'mainwp-child-reports' ),
					'context' => 'related-posts',
				),
				'show_thumbnails' => array(
					'label'   => esc_html__( 'Show Related Posts Thumbnails', 'mainwp-child-reports' ),
					'context' => 'related-posts',
				),
			),
			// Site verification
			'verification_services_codes'      => array(
				'google'    => array(
					'label'   => esc_html__( 'Google Webmaster Tools Token', 'mainwp-child-reports' ),
					'context' => 'verification-tools',
				),
				'bing'      => array(
					'label'   => esc_html__( 'Bing Webmaster Center Token', 'mainwp-child-reports' ),
					'context' => 'verification-tools',
				),
				'pinterest' => array(
					'label'   => esc_html__( 'Pinterest Site Verification Token', 'mainwp-child-reports' ),
					'context' => 'verification-tools',
				),
			),
			// Tiled galleries
			'tiled_galleries'                  => array(
				'label'   => esc_html__( 'Tiled Galleries', 'mainwp-child-reports' ),
				'context' => 'tiled-gallery',
			),
		);
	}

	/**
	 * Track Jetpack log entries.
     *
	 * Includes:
	 * - Activation/Deactivation of modules
	 * - Registration/Disconnection of blogs
	 * - Authorization/unlinking of users
	 *
	 * @param array $entry Jetpack log entry.
	 */
	public function callback_jetpack_log_entry( array $entry ) {
		if ( isset( $entry['code'] ) ) {
			$method = $entry['code'];
		} else {
			return;
		}

		if ( isset( $entry['data'] ) ) {
			$data = $entry['data'];
		} else {
			$data = null;
		}

		$context = null;
		$action  = null;
		$meta    = array();

		if ( in_array( $method, array( 'activate', 'deactivate' ), true ) && ! is_null( $data ) ) {
			$module_slug = $data;
			$module      = \Jetpack::get_module( $module_slug );
			$module_name = $module['name'];
			$context     = 'modules';
			$action      = $method . 'd';
			$meta        = compact( 'module_slug' );
			$message     = sprintf(
				// translators: Placeholders refer to a module name, and a status (e.g. "Photon", "activated")
				__( '%1$s module %2$s', 'mainwp-child-reports' ),
				$module_name,
				( 'activated' === $action ) ? esc_html__( 'activated', 'mainwp-child-reports' ) : esc_html__( 'deactivated', 'mainwp-child-reports' )
			);
		} elseif ( in_array( $method, array( 'authorize', 'unlink' ), true ) && ! is_null( $data ) ) {
			$user_id = intval( $data );

			if ( empty( $user_id ) ) {
				$user_id = get_current_user_id();
			}

			$user       = new \WP_User( $user_id );
			$user_email = $user->user_email;
			$user_login = $user->user_login;
			$context    = 'users';
			$action     = $method;
			$meta       = compact( 'user_id', 'user_email', 'user_login' );
			$message    = sprintf(
				// translators: Placeholders refer to a user display name, a status, and the connection either "from" or "to" (e.g. "Jane Doe", "unlinked", "from")
				__( '%1$s\'s account %2$s %3$s Jetpack', 'mainwp-child-reports' ),
				$user->display_name,
				( 'unlink' === $action ) ? esc_html__( 'unlinked', 'mainwp-child-reports' ) : esc_html__( 'linked', 'mainwp-child-reports' ),
				( 'unlink' === $action ) ? esc_html__( 'from', 'mainwp-child-reports' ) : esc_html__( 'to', 'mainwp-child-reports' )
			);
		} elseif ( in_array( $method, array( 'register', 'disconnect', 'subsiteregister', 'subsitedisconnect' ), true ) ) {
			$context      = 'blogs';
			$action       = str_replace( 'subsite', '', $method );
			$is_multisite = ( 0 === strpos( $method, 'subsite' ) );
			$blog_id      = $is_multisite ? ( isset( $_GET['site_id'] ) ? intval( wp_unslash( $_GET['site_id'] ) ) : null ) : get_current_blog_id(); // phpcs: input var okay, CSRF okay

			if ( empty( $blog_id ) ) {
				return;
			}

			if ( ! $is_multisite ) {
				$message = sprintf(
					// translators: Placeholder refers to a connection status. Either "connected to" or "disconnected from".
					__( 'Site %s Jetpack', 'mainwp-child-reports' ),
					( 'register' === $action ) ? esc_html__( 'connected to', 'mainwp-child-reports' ) : esc_html__( 'disconnected from', 'mainwp-child-reports' )
				);
			} else {
				$blog_details = get_blog_details(
					array(
						'blog_id' => $blog_id,
					)
				);
				$blog_name    = $blog_details->blogname;
				$meta        += compact( 'blog_id', 'blog_name' );

				$message = sprintf(
					// translators: Placeholder refers to a connection status. Either "connected to" or "disconnected from".
					__( '"%1$s" blog %2$s Jetpack', 'mainwp-child-reports' ),
					$blog_name,
					( 'register' === $action ) ? esc_html__( 'connected to', 'mainwp-child-reports' ) : esc_html__( 'disconnected from', 'mainwp-child-reports' )
				);
			}
		}

		if ( empty( $message ) ) {
			return;
		}

		$this->log(
			$message,
			$meta,
			null,
			$context,
			$action
		);
	}

	/**
	 * Track visible/enabled sharing services ( buttons ).
	 *
	 * @param string $state Services state.
	 */
	public function callback_sharing_get_services_state( $state ) {
		$this->log(
			__( 'Sharing services updated', 'mainwp-child-reports' ),
			$state,
			null,
			'sharedaddy',
			'updated'
		);
	}

    /**
     * Check update option callback.
     *
     * @param string $option Update option to update.
     * @param string $old Old option value.
     * @param string $new New option value.
     */
    public function callback_update_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

    /**
     * Add option callback.
     *
     * @param string $option Option to add.
     * @param string $val option value.
     */
    public function callback_add_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

    /**
     * Delete option callback.
     *
     * @param string $option Option to delete.
     */
    public function callback_delete_option( $option ) {
		$this->check( $option, null, null );
	}

	/**
	 * Track Monitor module notification status.
	 */
	public function callback_jetpack_module_configuration_load_monitor() {
		$active = wp_mainwp_stream_filter_input( INPUT_POST, 'receive_jetpack_monitor_notification' );

		if ( ! $active ) {
			return;
		}

		$this->log(
			// translators: Placeholder refers to a status (e.g. "activated")
			__( 'Monitor notifications %s', 'mainwp-child-reports' ),
			array(
				'status'    => $active ? esc_html__( 'activated', 'mainwp-child-reports' ) : esc_html__( 'deactivated', 'mainwp-child-reports' ),
				'option'    => 'receive_jetpack_monitor_notification',
				'old_value' => ! $active,
				'value'     => $active,
			),
			null,
			'monitor',
			'updated'
		);
	}

    /**
     * Jetpack post by email enabled calback.
     */
    public function callback_wp_ajax_jetpack_post_by_email_enable() {
		$this->track_post_by_email( true );
	}

    /**
     * Jetpack post by email regeneration callback.
     */
    public function callback_wp_ajax_jetpack_post_by_email_regenerate() {
		$this->track_post_by_email( null );
	}

    /**
     * Jetpack post by email disabled callback.
     */
    public function callback_wp_ajax_jetpack_post_by_email_disable() {
		$this->track_post_by_email( false );
	}

    /**
     * Track post by email status.
     *
     * @param string $status Email status.
     */
    public function track_post_by_email( $status ) {
		if ( true === $status ) {
			$action = esc_html__( 'enabled', 'mainwp-child-reports' );
		} elseif ( false === $status ) {
			$action = esc_html__( 'disabled', 'mainwp-child-reports' );
		} elseif ( null === $status ) {
			$action = esc_html__( 'regenerated', 'mainwp-child-reports' );
		}

		$user = wp_get_current_user();

		$this->log(
			// translators: Placeholders refer to a user display name, and a status (e.g. "Jane Doe", "enabled").
			__( '%1$s %2$s Post by Email', 'mainwp-child-reports' ),
			array(
				'user_displayname' => $user->display_name,
				'action'           => $action,
				'status'           => $status,
			),
			null,
			'post-by-email',
			'updated'
		);
	}

    /**
     * Check if option already exists.
     *
     * @param string $option Option to check.
     * @param string $old_value Old option value.
     * @param string $new_value New option value.
     */
    public function check( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, $this->options ) ) {
			return;
		}

		if ( is_null( $this->options[ $option ] ) ) {
			call_user_func( array( $this, 'check_' . str_replace( '-', '_', $option ) ), $old_value, $new_value );
		} else {
			$data         = $this->options[ $option ];
			$option_title = $data['label'];

			$this->log(
				// translators: Placeholder refers to a setting name (e.g. "Language")
				__( '"%s" setting updated', 'mainwp-child-reports' ),
				compact( 'option_title', 'option', 'old_value', 'new_value' ),
				null,
				$data['context'],
				isset( $data['action'] ) ? $data['action'] : 'updated'
			);
		}
	}

    /**
     * Check Jetpack options.
     *
     * @param string $old_value Old option value.
     * @param string $new_value New option value.
     */
    public function check_jetpack_options($old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( $this->get_changed_keys( $old_value, $new_value, 1 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		foreach ( $options as $option => $option_value ) {
			$settings = $this->get_settings_def( $option, $option_value );

			if ( ! $settings ) {
				continue;
			}

			if ( 0 === $option_value ) { // Skip updated array with updated members, we'll be logging those instead.
				continue;
			}

			$settings['meta'] += array(
				'option'    => $option,
				'old_value' => $old_value,
				'value'     => $new_value,
			);

			$this->log(
				$settings['message'],
				$settings['meta'],
				isset( $settings['object_id'] ) ? $settings['object_id'] : null,
				$settings['context'],
				$settings['action']
			);
		}
	}

    /**
     * Check hide Google Plus profile.
     *
     * @param string $old_value Old option value.
     * @param string $new_value New option value.
     *
     * @return bool Return FALSE on failure.
     */
    public function check_hide_gplus( $old_value, $new_value ) {
		$status = ! is_null( $new_value );

		if ( $status && $old_value ) {
			return false;
		}

		$this->log(
			// translators: Placeholder refers to a status (e.g. "enabled").
			__( 'G+ profile display %s', 'mainwp-child-reports' ),
			array(
				'action' => $status ? esc_html__( 'enabled', 'mainwp-child-reports' ) : esc_html__( 'disabled', 'mainwp-child-reports' ),
			),
			null,
			'gplus-authorship',
			'updated'
		);
	}

    /**
     * Check Google Plus Authors.
     *
     * @param string $old_value Old option value.
     * @param string $new_value New option value.
     */
    public function check_gplus_authors($old_value, $new_value ) {
		unset( $old_value );

		$user      = wp_get_current_user();
		$connected = is_array( $new_value ) && array_key_exists( $user->ID, $new_value );

		$this->log(
			// translators: Placeholders refer to a user display name, and a status (e.g. "Jane Doe", "connected")
			__( '%1$s\'s Google+ account %2$s', 'mainwp-child-reports' ),
			array(
				'display_name' => $user->display_name,
				'action'       => $connected ? esc_html__( 'connected', 'mainwp-child-reports' ) : esc_html__( 'disconnected', 'mainwp-child-reports' ),
				'user_id'      => $user->ID,
			),
			$user->ID,
			'gplus-authorship',
			'updated'
		);
	}

    /**
     * Check ShareDaddy disable resources.
     *
     * @param string $old_value Old option value.
     * @param string $new_value New option value.
     */
    public function check_sharedaddy_disable_resources($old_value, $new_value ) {
		if ( $old_value === $new_value ) {
			return;
		}

		$status = ! $new_value ? 'enabled' : 'disabled'; // disabled = 1

		$this->log(
			// translators: Placeholder refers to a status (e.g. "enabled")
			__( 'Sharing CSS/JS %s', 'mainwp-child-reports' ),
			compact( 'status', 'old_value', 'new_value' ),
			null,
			'sharing',
			'updated'
		);
	}

	/**
	 * Override connector log for our own Settings / Actions.
	 *
	 * @param array $data Log data.
	 *
	 * @return array|bool Updated Log data on success, of FALSE on failure.
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		// Handling our Settings.
		if ( 'settings' === $data['connector'] && isset( $this->options_override[ $data['args']['option'] ] ) ) {
			if ( isset( $data['args']['option_key'] ) ) {
				$overrides = $this->options_override[ $data['args']['option'] ][ $data['args']['option_key'] ];
			} else {
				$overrides = $this->options_override[ $data['args']['option'] ];
			}

			if ( isset( $overrides ) ) {
				$data['args']['label']   = $overrides['label'];
				$data['args']['context'] = $overrides['context'];
				$data['context']         = $overrides['context'];
				$data['connector']       = $this->name;
			}
		} elseif ( 'posts' === $data['connector'] && 'safecss' === $data['context'] ) {
			$data = array_merge(
				$data,
				array(
					'connector' => $this->name,
					'message'   => esc_html__( 'Custom CSS updated', 'mainwp-child-reports' ),
					'args'      => array(),
					'object_id' => null,
					'context'   => 'custom-css',
					'action'    => 'updated',
				)
			);
		}

		return $data;
	}

    /**
     * Get settings deffin
     * @param string $key Options Key.
     * @param string $value Options value.
     *
     * @return array|bool Return success array, or FALSE on failure.
     */
    private function get_settings_def($key, $value = null ) {
		// Sharing.
		if ( 0 === strpos( $key, 'publicize_connections::' ) ) {
			global $publicize_ui;

			$name = str_replace( 'publicize_connections::', '', $key );

			return array(
				// translators: Placeholders refer to a service, and a status (e.g. "Facebook", "added").
				'message' => esc_html__( '%1$s connection %2$s', 'mainwp-child-reports' ),
				'meta'    => array(
					'connection' => $publicize_ui->publicize->get_service_label( $name ),
					'action'     => $value ? esc_html__( 'added', 'mainwp-child-reports' ) : esc_html__( 'removed', 'mainwp-child-reports' ),
					'option'     => 'jetpack_options',
					'option_key' => $key,
				),
				'action'  => $value ? 'added' : 'removed',
				'context' => 'publicize',
			);
		} elseif ( 0 === strpos( $key, 'videopress::' ) ) {
			$name    = str_replace( 'videopress::', '', $key );
			$options = array(
				'access'  => esc_html__( 'Video Library Access', 'mainwp-child-reports' ),
				'upload'  => esc_html__( 'Allow users to upload videos', 'mainwp-child-reports' ),
				'freedom' => esc_html__( 'Free formats', 'mainwp-child-reports' ),
				'hd'      => esc_html__( 'Default quality', 'mainwp-child-reports' ),
			);

			if ( ! isset( $options[ $name ] ) ) {
				return false;
			}

			return array(
				// translators: Placeholder refers to a setting name (e.g. "Language").
				'message' => esc_html__( '"%s" setting updated', 'mainwp-child-reports' ),
				'meta'    => array(
					'option_name' => $options[ $name ],
					'option'      => 'jetpack_options',
					'option_key'  => $key,
				),
				'action'  => 'updated',
				'context' => 'videopress',
			);
		}

		return false;
	}
}
