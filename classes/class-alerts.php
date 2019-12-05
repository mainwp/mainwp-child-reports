<?php
/**
 * Alerts feature class.
 *
 * @package WP_MainWP_Stream
 */

namespace WP_MainWP_Stream;

/**
 * Class Alerts
 *
 * @package WP_MainWP_Stream
 */
class Alerts {

	/**
	 * Alerts post type slug
	 */
	const POST_TYPE = 'wp_mainwp_alerts';

	/**
	 * Triggered Alerts meta key for Records
	 */
	const ALERTS_TRIGGERED_META_KEY = 'wp_mainwp_alerts_triggered';

	/**
	 * Hold Plugin class
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Post meta prefix
	 *
	 * @var string
	 */
	public $meta_prefix = 'wp_mainwp_stream';

	/**
	 * Alert Types
	 *
	 * @var array
	 */
	public $alert_types = array();

	/**
	 * Alert Triggers
	 *
	 * @var array
	 */
	public $alert_triggers = array();

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Register custom post type.
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Add custom post type to menu.
		//add_action( 'wp_mainwp_stream_admin_menu', array( $this, 'register_menu' ) );

		// Add scripts to post screens.
		add_action( 'admin_enqueue_scripts', array(
			$this,
			'register_scripts',
		) );

		add_action( 'network_admin_menu', array(
			$this,
			'change_menu_link_url',
		), 99 );

		add_filter( 'wp_mainwp_stream_record_inserted', array(
			$this,
			'check_records',
		), 10, 2 );

		add_action( 'wp_ajax_load_alerts_settings', array(
			$this,
			'load_alerts_settings',
		) );
		add_action( 'wp_ajax_get_actions', array( $this, 'get_actions' ) );
		add_action( 'wp_ajax_save_new_alert', array(
			$this,
			'save_new_alert',
		) );
		add_action(
			'wp_ajax_get_new_alert_triggers_notifications', array(
				$this,
				'get_new_alert_triggers_notifications',
			)
		);

		$this->load_alert_types();
		$this->load_alert_triggers();

		add_filter( 'wp_mainwp_stream_action_links_posts', array(
			$this,
			'change_alert_action_links',
		), 11, 2 );

	}

	/**
	 * Load alert_type classes
	 *
	 * @return void
	 */
	public function load_alert_types() {
		$alert_types = array(
			'none',
			'highlight',
			'email',
			'ifttt',
			'slack',
		);

		$classes = array();
		foreach ( $alert_types as $alert_type ) {
			$file_location = $this->plugin->locations['dir'] . '/alerts/class-alert-type-' . $alert_type . '.php';
			if ( file_exists( $file_location ) ) {
				include_once $file_location;
				$class_name = sprintf( '\WP_MainWP_Stream\Alert_Type_%s', str_replace( '-', '_', $alert_type ) );
				if ( ! class_exists( $class_name ) ) {
					continue;
				}
				$class = new $class_name( $this->plugin );
				if ( ! property_exists( $class, 'slug' ) ) {
					continue;
				}
				$classes[ $class->slug ] = $class;
			}
		}

		/**
		 * Allows for adding additional alert_types via classes that extend Notifier.
		 *
		 * @param array $classes An array of Notifier objects. In the format alert_type_slug => Notifier_Class()
		 */
		$this->alert_types = apply_filters( 'wp_mainwp_stream_alert_types', $classes );

		// Ensure that all alert_types extend Notifier.
		foreach ( $this->alert_types as $key => $alert_type ) {
			if ( ! $this->is_valid_alert_type( $alert_type ) ) {
				unset( $this->alert_types[ $key ] );
			}
		}
	}

	/**
	 * Load alert_type classes
	 *
	 * @return void
	 */
	public function load_alert_triggers() {
		$alert_triggers = array(
			'author',
			'context',
			'action',
		);

		$classes = array();
		foreach ( $alert_triggers as $alert_trigger ) {
			$file_location = $this->plugin->locations['dir'] . '/alerts/class-alert-trigger-' . $alert_trigger . '.php';
			if ( file_exists( $file_location ) ) {
				include_once $file_location;
				$class_name = sprintf( '\WP_MainWP_Stream\Alert_Trigger_%s', str_replace( '-', '_', $alert_trigger ) );
				if ( ! class_exists( $class_name ) ) {
					continue;
				}
				$class = new $class_name( $this->plugin );
				if ( ! property_exists( $class, 'slug' ) ) {
					continue;
				}
				$classes[ $class->slug ] = $class;
			}
		}

		/**
		 * Allows for adding additional alert_triggers via classes that extend Notifier.
		 *
		 * @param array $classes An array of Notifier objects. In the format alert_trigger_slug => Notifier_Class()
		 */
		$this->alert_triggers = apply_filters( 'wp_mainwp_stream_alert_triggers', $classes );

		// Ensure that all alert_triggers extend Notifier.
		foreach ( $this->alert_triggers as $key => $alert_trigger ) {
			if ( ! $this->is_valid_alert_trigger( $alert_trigger ) ) {
				unset( $this->alert_triggers[ $key ] );
			}
		}
	}

	/**
	 * Checks whether a Alert Type class is valid
	 *
	 * @param Alert_Type $alert_type The class to check.
	 *
	 * @return bool
	 */
	public function is_valid_alert_type( $alert_type ) {
		if ( ! is_a( $alert_type, 'WP_MainWP_Stream\Alert_Type' ) ) {
			return false;
		}

		if ( ! method_exists( $alert_type, 'is_dependency_satisfied' ) || ! $alert_type->is_dependency_satisfied() ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether a Alert Trigger class is valid
	 *
	 * @param Alert_Trigger $alert_trigger The class to check.
	 *
	 * @return bool
	 */
	public function is_valid_alert_trigger( $alert_trigger ) {
		if ( ! is_a( $alert_trigger, 'WP_MainWP_Stream\Alert_Trigger' ) ) {
			return false;
		}

		if ( ! method_exists( $alert_trigger, 'is_dependency_satisfied' ) || ! $alert_trigger->is_dependency_satisfied() ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks record being processed against active alerts.
	 *
	 * @filter wp_mainwp_stream_record_inserted
	 *
	 * @param int   $record_id The record being processed.
	 * @param array $recordarr Record data.
	 *
	 * @return array
	 */
	public function check_records( $record_id, $recordarr ) {
		$args = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'wp_mainwp_stream_enabled',
		);

		$alerts = new \WP_Query( $args );
		foreach ( $alerts->posts as $alert ) {
			$alert = $this->get_alert( $alert->ID );

			$status = $alert->check_record( $record_id, $recordarr );
			if ( $status ) {
				$alert->send_alert( $record_id, $recordarr ); // @todo send_alert expects int, not array.
			}
		}

		return $recordarr;

	}

	/**
	 * Register scripts for page load
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @return void
	 */
	public function register_scripts() {
		$screen = get_current_screen();
		if ( 'edit-wp_mainwp_alerts' === $screen->id ) {

			$min = wp_mainwp_stream_min_suffix();
			wp_register_script( 'wp-mainwp-stream-alerts', $this->plugin->locations['url'] . 'ui/js/alerts.' . $min . 'js', array(
				'wp-mainwp-stream-select2',
				'jquery',
				'inline-edit-post',
			) );

			wp_localize_script(
				'wp-mainwp-stream-alerts', 'streamAlerts',
				array(
					'any'        => __( 'Any', 'mainwp-child-reports' ),
					'anyContext' => __( 'Any Context', 'mainwp-child-reports' ),
				)
			);
			wp_enqueue_script( 'wp-mainwp-stream-alerts' );
			wp_enqueue_style( 'wp-mainwp-stream-select2' );
		}
	}

	/**
	 * Register custom post type
	 *
	 * @action init
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Alerts', 'post type general name', 'mainwp-child-reports' ),
			'singular_name'      => _x( 'Alert', 'post type singular name', 'mainwp-child-reports' ),
			'menu_name'          => _x( 'Alerts', 'admin menu', 'mainwp-child-reports' ),
			'name_admin_bar'     => _x( 'Alert', 'add new on admin bar', 'mainwp-child-reports' ),
			'add_new'            => _x( 'Add New', 'book', 'mainwp-child-reports' ),
			'add_new_item'       => __( 'Add New Alert', 'mainwp-child-reports' ),
			'new_item'           => __( 'New Alert', 'mainwp-child-reports' ),
			'edit_item'          => __( 'Edit Alert', 'mainwp-child-reports' ),
			'view_item'          => __( 'View Alert', 'mainwp-child-reports' ),
			'all_items'          => __( 'Alerts', 'mainwp-child-reports' ),
			'search_items'       => __( 'Search Alerts', 'mainwp-child-reports' ),
			'parent_item_colon'  => __( 'Parent Alerts:', 'mainwp-child-reports' ),
			'not_found'          => __( 'No alerts found.', 'mainwp-child-reports' ),
			'not_found_in_trash' => __( 'No alerts found in Trash.', 'mainwp-child-reports' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Alerts for Stream.', 'mainwp-child-reports' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // @see modify_admin_menu
			'supports'            => false,
			'capabilities'        => array(
				'publish_posts'       => 'manage_options',
				'edit_others_posts'   => 'manage_options',
				'delete_posts'        => 'manage_options',
				'delete_others_posts' => 'manage_options',
				'read_private_posts'  => 'manage_options',
				'edit_post'           => 'manage_options',
				'delete_post'         => 'manage_options',
				'read_post'           => 'manage_options',
			),
		);

		register_post_type( self::POST_TYPE, $args );

		$args = array(
			'label'                     => _x( 'Enabled', 'alert', 'mainwp-child-reports' ),
			'public'                    => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			// translators: Placeholder refers to a number of items (e.g. "42")
			'label_count'               => _n_noop( 'Enabled <span class="count">(%s)</span>', 'Enabled <span class="count">(%s)</span>', 'mainwp-child-reports' ),
		);

		register_post_status( 'wp_mainwp_stream_enabled', $args );

		$args = array(
			'label'                     => _x( 'Disabled', 'alert', 'mainwp-child-reports' ),
			'public'                    => false,
			'internal'                  => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			// translators: Placeholder refers to a number of items (e.g. "42")
			'label_count'               => _n_noop( 'Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', 'mainwp-child-reports' ),
		);

		register_post_status( 'wp_mainwp_stream_disabled', $args );
	}

	/**
	 * Return alert object of the given ID
	 *
	 * @param string $post_id Post ID for the alert.
	 *
	 * @return Alert
	 */
	public function get_alert( $post_id = '' ) {
		if ( ! $post_id ) {
			$obj = new Alert( null, $this->plugin );

			return $obj;
		}

		$post = get_post( $post_id );

		$alert_type = get_post_meta( $post_id, 'alert_type', true );
		$alert_meta = get_post_meta( $post_id, 'alert_meta', true );

		$obj = (object) array(
			'ID'         => $post->ID,
			'status'     => $post->post_status,
			'date'       => $post->post_date,
			'author'     => $post->post_author,
			'alert_type' => $alert_type,
			'alert_meta' => $alert_meta,
		);

		return new Alert( $obj, $this->plugin );

	}

	/**
	 * Add custom post type to menu
	 *
	 * @action admin_menu
	 *
	 * @return void
	 */
	// DISABLED
	public function register_menu() {
		add_submenu_page(
			$this->plugin->admin->records_page_slug,
			__( 'Alerts', 'mainwp-child-reports' ),
			__( 'Alerts', 'mainwp-child-reports' ),
			'manage_options',
			'edit.php?post_type=wp_mainwp_alerts'
		);
	}

	/**
	 * Modify the Stream > Alerts Network Admin Menu link.
	 *
	 * In self::register_menu(), the Alerts submenu item
	 * is essentially set to go to the Site's admin area.
	 *
	 * However, on the Network admin, we need to redirect
	 * it to the first site in the network, as this is
	 * where the true Network Alerts settings page is located.
	 *
	 * @action network_admin_menu
	 * @return bool
	 */
	public function change_menu_link_url() {
		global $submenu;

		$parent = 'wp_mainwp_stream';
		$page   = 'edit.php?post_type=wp_mainwp_alerts';

		// If we're not on the Stream menu item, return.
		if ( ! isset( $submenu[ $parent ] ) ) {
			return false;
		}

		// Get the first existing Site in the Network.
		$sites = wp_mainwp_stream_get_sites(
			array(
				'limit' => 5, // Limit the size of the query.
			)
		);

		$site_id = '1';

		// Function wp_get_sites() can return an empty array if the network is too large.
		if ( ! empty( $sites ) && ! empty( $sites[0]->blog_id ) ) {
			$site_id = $sites[0]->blog_id;
		}

		$new_url = get_admin_url( $site_id, $page );

		foreach ( $submenu[ $parent ] as $key => $value ) {
			// Set correct URL for the menu item.
			if ( $page === $value[2] ) {
				// This hack is not kosher, see the docblock for an explanation.
				$submenu[ $parent ][ $key ][2] = $new_url; // override okay
				break;
			}
		}

		return true;
	}

	/**
	 * Display Alert Type Meta Box
	 *
	 * @param \WP_Post|array $post Post object for current alert.
	 *
	 * @return void
	 */
	public function display_notification_box( $post = array() ) {
		$alert_type = 'none';
		if ( is_object( $post ) ) {
			$alert      = $this->get_alert( $post->ID );
			$alert_type = $alert->alert_type;
		}
		$form = new Form_Generator();

		$field_html = $form->render_field(
			'select', array(
				'id'          => 'wp_mainwp_stream_alert_type',
				'name'        => 'wp_mainwp_stream_alert_type',
				'value'       => $alert_type,
				'options'     => $this->get_notification_values(),
				'placeholder' => __( 'No Alert', 'mainwp-child-reports' ),
				'title'       => 'Alert Type:',
			)
		);

		echo '<label>' . esc_html__( 'Alert me by', 'mainwp-child-reports' ) . '</label>';
		echo $field_html; // Xss ok.

		echo '<div id="wp_mainwp_stream_alert_type_form">';
		if ( is_object( $alert ) ) {
			$alert->get_alert_type_obj()->display_fields( $alert );
		} else {
			$this->plugin->alerts->alert_types['none']->display_fields( array() );
		}

		echo '</div>';
	}

	/**
	 * Returns settings form HTML for AJAX use
	 *
	 * @action wp_ajax_load_alerts_settings
	 *
	 * @return void
	 */
	public function load_alerts_settings() {
		$alert   = array();
		$post_id = wp_mainwp_stream_filter_input( INPUT_POST, 'post_id' );
		if ( ! empty( $post_id ) ) {
			$alert = $this->get_alert( $post_id );
			if ( ! $alert ) {
				wp_send_json_error(
					array(
						'message' => 'Could not find alert.',
					)
				);
			}
		}

		$alert_type = wp_mainwp_stream_filter_input( INPUT_POST, 'alert_type' );
		if ( empty( $alert_type ) ) {
			$alert_type = 'none';
		}
		if ( ! array_key_exists( $alert_type, $this->alert_types ) ) {
			wp_send_json_error(
				array(
					'message' => 'Could not find alert type.',
				)
			);
		}

		ob_start();
		$this->alert_types[ $alert_type ]->display_fields( $alert );
		$output = ob_get_contents();
		ob_end_clean();

		$data = array(
			'html' => $output,
		);
		wp_send_json_success( $data );
	}

	/**
	 * Display Trigger Meta Box
	 *
	 * @param \WP_Post|array $post Post object for current alert.
	 *
	 * @return void
	 */
	public function display_triggers_box( $post = array() ) {
		if ( is_object( $post ) ) {
			$alert = $this->get_alert( $post->ID );
		} else {
			$alert = array();
		}
		$form = new Form_Generator();
		do_action( 'wp_mainwp_stream_alert_trigger_form_display', $form, $alert );
		// @TODO use human readable text.
		echo '<label>' . esc_html__( 'Alert me when', 'mainwp-child-reports' ) . '</label>';
		echo $form->render_fields(); // Xss ok.
		wp_nonce_field( 'save_alert', 'wp_mainwp_alerts_nonce' );
	}

	/**
	 * Display Submit Box
	 *
	 * @param \WP_Post $post Post object for current alert.
	 *
	 * @return void
	 */
	public function display_submit_box( $post ) {
		if ( empty( $post ) ) {
			return;
		}

		$post_status = $post->post_status;
		if ( 'auto-draft' === $post_status ) {
			$post_status = 'wp_mainwp_stream_enabled';
		}
		?>
		<div class="submitbox" id="submitpost">
			<div id="minor-publishing">
				<div id="misc-publishing-actions">
					<div class="misc-pub-section misc-pub-post-status">
						<label for="wp_mainwp_stream_alert_status"><?php esc_html_e( 'Status', 'mainwp-child-reports' ); ?></label>
						<select name='wp_mainwp_stream_alert_status' id='wp_mainwp_stream_alert_status'>
							<option<?php selected( $post_status, 'wp_mainwp_stream_enabled' ); ?>
									value='wp_mainwp_stream_enabled'><?php esc_html_e( 'Enabled', 'mainwp-child-reports' ); ?></option>
							<option<?php selected( $post_status, 'wp_mainwp_stream_disabled' ); ?>
									value='wp_mainwp_stream_disabled'><?php esc_html_e( 'Disabled', 'mainwp-child-reports' ); ?></option>
						</select>
					</div>
				</div>
				<div class="clear"></div>
			</div>

			<div id="major-publishing-actions">
				<div id="delete-action">
					<?php
					if ( current_user_can( 'delete_post', $post->ID ) ) {
						if ( ! EMPTY_TRASH_DAYS ) {
							$delete_text = __( 'Delete Permanently', 'mainwp-child-reports' );
						} else {
							$delete_text = __( 'Move to Trash', 'mainwp-child-reports' );
						}
						?>
						<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>">
							<?php esc_html( $delete_text ); ?>
						</a>
						<?php
					}
					?>
				</div>
				<div id="publishing-action">
					<span class="spinner"></span>
					<?php submit_button( __( 'Save' ), 'primary button-large', 'publish', false ); ?>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display Status Box
	 *
	 * @return void
	 */
	public function display_status_box() {
		?>
		<div id="minor-publishing">
			<div id="misc-publishing-actions">
				<div class="misc-pub-section misc-pub-post-status">
					<label for="wp_mainwp_stream_alert_status">
						<span class="title"><?php esc_html_e( 'Status:', 'mainwp-child-reports' ); ?></span>
						<span class="input-text-wrap">
							<select name='wp_mainwp_stream_alert_status' id='wp_mainwp_stream_alert_status'>
								<option selected value='wp_mainwp_stream_enabled'><?php esc_html_e( 'Enabled', 'mainwp-child-reports' ); ?></option>
								<option value='wp_mainwp_stream_disabled'><?php esc_html_e( 'Disabled', 'mainwp-child-reports' ); ?></option>
							</select>
						</span>
					</label>
				</div>
			</div>
			<div class="clear"></div>
		</div>
		<?php
	}

	/**
	 * Return all notification values
	 *
	 * @return array
	 */
	public function get_notification_values() {
		$result = array();
		$names  = wp_list_pluck( $this->alert_types, 'name', 'slug' );
		foreach ( $names as $slug => $name ) {
			$result[ $slug ] = $name;
		}

		return $result;
	}

	/**
	 * Update actions dropdown options based on the connector selected.
	 */
	public function get_actions() {
		$connector_name    = wp_mainwp_stream_filter_input( INPUT_POST, 'connector' );
		$stream_connectors = wp_mainwp_stream_get_instance()->connectors;
		if ( ! empty( $connector_name ) ) {
			if ( isset( $stream_connectors->connectors[ $connector_name ] ) ) {
				$connector = $stream_connectors->connectors[ $connector_name ];
				if ( method_exists( $connector, 'get_action_labels' ) ) {
					$actions = $connector->get_action_labels();
				}
			}
		} else {
			$actions = $stream_connectors->term_labels['stream_action'];
		}
		ksort( $actions );
		wp_send_json_success( $actions );
	}

	/**
	 * Save a new alert
	 */
	public function save_new_alert() {
		check_ajax_referer( 'save_alert', 'wp_mainwp_alerts_nonce' );
		$trigger_author                = wp_mainwp_stream_filter_input( INPUT_POST, 'wp_mainwp_stream_trigger_author' );
		$trigger_connector_and_context = wp_mainwp_stream_filter_input( INPUT_POST, 'wp_mainwp_stream_trigger_context' );
		if ( false !== strpos( $trigger_connector_and_context, '-' ) ) {
			// This is a connector with a context such as posts-post.
			$trigger_connector_and_context_split = explode( '-', $trigger_connector_and_context );
			$trigger_connector                   = $trigger_connector_and_context_split[0];
			$trigger_context                     = $trigger_connector_and_context_split[1];
		} else {
			if ( ! empty( $trigger_connector_and_context ) ) {
				// This is a parent connector with no dash such as posts.
				$trigger_connector = $trigger_connector_and_context;
				$trigger_context   = '';
			} else {
				// There is no connector or context.
				$trigger_connector = '';
				$trigger_context   = '';
			}
		}

		$trigger_action = wp_mainwp_stream_filter_input( INPUT_POST, 'wp_mainwp_stream_trigger_action' );
		$alert_type     = wp_mainwp_stream_filter_input( INPUT_POST, 'wp_mainwp_stream_alert_type' );
		$alert_status   = wp_mainwp_stream_filter_input( INPUT_POST, 'wp_mainwp_stream_alert_status' );

		// Insert the post into the database
		$item    = (object) array(
			'alert_type'   => $alert_type,
			'alert_meta'   => array(
				'trigger_author'    => $trigger_author,
				'trigger_connector' => $trigger_connector,
				'trigger_action'    => $trigger_action,
				'trigger_context'   => $trigger_context,
			),
			'alert_status' => $alert_status,
		);
		$alert   = new Alert( $item, $this->plugin );
		$title   = $alert->get_title();
		$post_id = wp_insert_post(
			array(
				'post_status' => $alert_status,
				'post_type'   => 'wp_mainwp_alerts',
				'post_title'  => $title,
			)
		);
		if ( empty( $post_id ) ) {
			wp_send_json_error();
		}
		add_post_meta( $post_id, 'alert_type', $alert_type );

		$alert_meta = array(
			'trigger_author'    => $trigger_author,
			'trigger_connector' => $trigger_connector,
			'trigger_action'    => $trigger_action,
			'trigger_context'   => $trigger_context,
		);
		$alert_meta = apply_filters( 'wp_mainwp_alerts_save_meta', $alert_meta, $alert_type );
		add_post_meta( $post_id, 'alert_meta', $alert_meta );
		wp_send_json_success(
			array(
				'success' => true,
			)
		);
	}

	/**
	 *
	 */
	public function get_new_alert_triggers_notifications() {
		ob_start();
		?>
		<fieldset class="inline-edit-col inline-edit-wp_mainwp_alerts inline-edit-add-new-triggers">
			<legend class="inline-edit-legend">Add New</legend>
			<?php $GLOBALS['wp_mainwp_stream']->alerts->display_triggers_box(); ?>
		</fieldset>
		<fieldset class="inline-edit-col inline-edit-wp_mainwp_alerts inline-edit-add-new-notifications">
			<?php $GLOBALS['wp_mainwp_stream']->alerts->display_notification_box(); ?>
		</fieldset>
		<fieldset class="inline-edit-col inline-edit-wp_mainwp_alerts inline-edit-add-new-status">
			<?php $GLOBALS['wp_mainwp_stream']->alerts->display_status_box(); ?>
		</fieldset>
		<?php
		$html = ob_get_clean();
		wp_send_json_success(
			array(
				'success' => true,
				'html'    => $html,
			)
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}
	 *
	 * @param array  $links Previous links registered
	 * @param Record $record Stream record
	 *
	 * @return array Action links
	 */
	public function change_alert_action_links( $links, $record ) {
		$post = get_post( $record->object_id );

		if ( $post && self::POST_TYPE === $post->post_type && $post->post_status === $record->get_meta( 'new_status', true ) ) {
			if ( 'trash' !== $post->post_status ) {
				$connector_posts = new \WP_MainWP_Stream\Connector_Posts();
				$post_type_name  = $connector_posts->get_post_type_name( get_post_type( $post->ID ) );

				// translators: Placeholder refers to the post type singular name (e.g. "Post")
				$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'mainwp-child-reports' ), $post_type_name ) ] = admin_url( 'edit.php?post_type=wp_mainwp_alerts#post-' . $post->ID );
				unset( $links[ esc_html__( 'View', 'mainwp-child-reports' ) ] );
			}
		}

		return $links;
	}
}
