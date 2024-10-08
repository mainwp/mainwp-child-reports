<?php
/** MainWp WooCommerce Connector. */
namespace WP_MainWP_Stream;

/**
 * Class Connector_Woocommerce
 *
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_Woocommerce extends Connector {

	/** @var string Connector Slug. */
	public $name = 'woocommerce';

	/** @const string Holds WooCommerce Plugin minimum required version. */
	const PLUGIN_MIN_VERSION = '2.1.10';

	/** @var array Actions registered for this context. */
	public $actions = array(
		'wp_mainwp_stream_record_array',
		// 'updated_option',
		'transition_post_status',
		'deleted_post',
		'woocommerce_order_status_changed',
		'woocommerce_attribute_added',
		'woocommerce_attribute_updated',
		'woocommerce_attribute_deleted',
		'woocommerce_tax_rate_added',
		'woocommerce_tax_rate_updated',
		'woocommerce_tax_rate_deleted',
	);

	/** @var string[] Array of taxonomies. */
	public $taxonomies = array(
		'product_type',
		'product_cat',
		'product_tag',
		'product_shipping_class',
		'shop_order_status',
	);

	/** @var string[] Array of post types. */
	public $post_types = array(
		'product',
		'product_variation',
		'shop_order',
		'shop_coupon',
	);

	/** @var bool Return TRUE if order update has logged, or FALSE on failure. */
	private $order_update_logged = false;

	/** @var array Holds settings pages. */
	private $settings_pages = array();

	/** @var array Settings array. */
	private $settings = array();

	/** @var string Current WooCommerce plugin version. */
	private $plugin_version = null;

	/**
	 * Register WooCommerce Connector with parent class.
	 *
	 * @uses \WP_MainWP_Stream\Connector::register()
	 */
	public function register() {
		parent::register();

		add_filter( 'wp_mainwp_stream_posts_exclude_post_types', array( $this, 'exclude_order_post_types' ) );
		add_action( 'wp_mainwp_stream_comments_exclude_comment_types', array( $this, 'exclude_order_comment_types' ) );

		$this->get_woocommerce_settings_fields();
	}

	/**
	 * Check if plugin dependencies are satisfied.
	 *
	 * @return bool Return TRUE if dependencies were satisfied, FALSE if not.
	 */
	public function is_dependency_satisfied() {

		/** @global object $woocommerce WooCommerce class instance. */
		global $woocommerce;

		if ( class_exists( 'WooCommerce' ) && version_compare( $woocommerce->version, self::PLUGIN_MIN_VERSION, '>=' ) ) {
			$this->plugin_version = $woocommerce->version;
			return true;
		}

		return false;
	}

	/**
	 * Return translated context .
	 *
	 * @return string Translated context label.
	 */
	public function get_label() {
		return esc_html_x( 'WooCommerce', 'woocommerce', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array Action label translations.
	 */
	public function get_action_labels() {
		return array(
			'updated' => esc_html_x( 'Updated', 'woocommerce', 'mainwp-child-reports' ),
			'created' => esc_html_x( 'Created', 'woocommerce', 'mainwp-child-reports' ),
			'trashed' => esc_html_x( 'Trashed', 'woocommerce', 'mainwp-child-reports' ),
			'deleted' => esc_html_x( 'Deleted', 'woocommerce', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array Context label translations.
	 *
	 * @uses \WP_MainWP_Stream\Connector_Posts
	 */
	public function get_context_labels() {
		$context_labels = array();

		if ( class_exists( 'Connector_Posts' ) ) {
			$posts_connector = new Connector_Posts();
			$context_labels  = array_merge(
				$context_labels,
				$posts_connector->get_context_labels()
			);
		}

		$custom_context_labels = array(
			'attributes' => esc_html_x( 'Attributes', 'woocommerce', 'mainwp-child-reports' ),
		);

		$context_labels = array_merge(
			$context_labels,
			$custom_context_labels,
			$this->settings_pages
		);

		return apply_filters( 'wp_mainwp_stream_woocommerce_contexts', $context_labels );
	}

	/**
	 * Return settings used by WooCommerce that aren't registered.
	 *
	 * @return array Custom settings with translated title and page.
	 */
	public function get_custom_settings() {
		$custom_settings = array(
			'woocommerce_frontend_css_colors'     => array(
				'title'   => esc_html__( 'Frontend Styles', 'mainwp-child-reports' ),
				'page'    => 'wc-settings',
				'tab'     => 'general',
				'section' => '',
				'type'    => esc_html__( 'setting', 'mainwp-child-reports' ),
			),
			'woocommerce_default_gateway'         => array(
				'title'   => esc_html__( 'Gateway Display Default', 'mainwp-child-reports' ),
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => '',
				'type'    => esc_html__( 'setting', 'mainwp-child-reports' ),
			),
			'woocommerce_gateway_order'           => array(
				'title'   => esc_html__( 'Gateway Display Order', 'mainwp-child-reports' ),
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => '',
				'type'    => esc_html__( 'setting', 'mainwp-child-reports' ),
			),
			'woocommerce_default_shipping_method' => array(
				'title'   => esc_html__( 'Shipping Methods Default', 'mainwp-child-reports' ),
				'page'    => 'wc-settings',
				'tab'     => 'shipping',
				'section' => '',
				'type'    => esc_html__( 'setting', 'mainwp-child-reports' ),
			),
			'woocommerce_shipping_method_order'   => array(
				'title'   => esc_html__( 'Shipping Methods Order', 'mainwp-child-reports' ),
				'page'    => 'wc-settings',
				'tab'     => 'shipping',
				'section' => '',
				'type'    => esc_html__( 'setting', 'mainwp-child-reports' ),
			),
			'shipping_debug_mode'                 => array(
				'title'   => esc_html__( 'Shipping Debug Mode', 'mainwp-child-reports' ),
				'page'    => 'wc-status',
				'tab'     => 'tools',
				'section' => '',
				'type'    => esc_html__( 'tool', 'mainwp-child-reports' ),
			),
			'template_debug_mode'                 => array(
				'title'   => esc_html__( 'Template Debug Mode', 'mainwp-child-reports' ),
				'page'    => 'wc-status',
				'tab'     => 'tools',
				'section' => '',
				'type'    => esc_html__( 'tool', 'mainwp-child-reports' ),
			),
			'uninstall_data'                      => array(
				'title'   => esc_html__( 'Remove post types on uninstall', 'mainwp-child-reports' ),
				'page'    => 'wc-status',
				'tab'     => 'tools',
				'section' => '',
				'type'    => esc_html__( 'tool', 'mainwp-child-reports' ),
			),
		);

		return apply_filters( 'wp_mainwp_stream_woocommerce_custom_settings', $custom_settings );
	}

	/**
	 * Add action links to Stream drop row in admin list screen.
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}.
	 *
	 * @param array  $links   Previous links registered.
	 * @param Record $record Stream record.
	 *
	 * @return array Action links.
	 *
	 * @uses \WP_MainWP_Stream\Connector_Posts
	 */
	public function action_links( $links, $record ) {
		if ( in_array( $record->context, $this->post_types, true ) && get_post( $record->object_id ) ) {
			$edit_post_link = get_edit_post_link( $record->object_id );
			if ( $edit_post_link ) {
				$posts_connector = new Connector_Posts();
				$post_type_name  = $posts_connector->get_post_type_name( get_post_type( $record->object_id ) );
				// translators: Placeholder refers to a post type singular name (e.g. "Post")
				$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'mainwp-child-reports' ), $post_type_name ) ] = $edit_post_link;
			}

			$permalink = get_permalink( $record->object_id );
			if ( post_type_exists( get_post_type( $record->object_id ) ) && $permalink ) {
				$links[ esc_html__( 'View', 'mainwp-child-reports' ) ] = $permalink;
			}
		}

		$context_labels = $this->get_context_labels();
		$option_key     = $record->get_meta( 'option', true );
		$option_page    = $record->get_meta( 'page', true );
		$option_tab     = $record->get_meta( 'tab', true );
		$option_section = $record->get_meta( 'section', true );

		if ( $option_key && $option_tab ) {
			// translators: Placeholder refers to a context (e.g. "Attribute")
			$text = sprintf( esc_html__( 'Edit WooCommerce %s', 'mainwp-child-reports' ), $context_labels[ $record->context ] );
			$url  = add_query_arg(
				array(
					'page'    => $option_page,
					'tab'     => $option_tab,
					'section' => $option_section,
				),
				admin_url( 'admin.php' ) // Not self_admin_url here, as WooCommerce doesn't exist in Network Admin
			);

			$links[ $text ] = $url . '#wp-mainwp-stream-highlight:' . $option_key;
		}

		return $links;
	}

	/**
	 * Prevent the Stream Posts connector from logging orders
	 * so that we can handle them differently here.
	 *
	 * @filter wp_mainwp_stream_posts_exclude_post_types.
	 *
	 * @param array $post_types Ignored post types.
	 *
	 * @return array Filtered post types.
	 */
	public function exclude_order_post_types( $post_types ) {
		$post_types[] = 'shop_order';

		return $post_types;
	}

	/**
	 * Prevent the Stream Comments connector from logging status
	 * change comments on orders.
	 *
	 * @filter wp_mainwp_stream_commnent_exclude_comment_types.
	 *
	 * @param array $comment_types Ignored post types.
	 *
	 * @return array Filtered post types.
	 */
	public function exclude_order_comment_types( $comment_types ) {
		$comment_types[] = 'order_note';

		return $comment_types;
	}

	/**
	 * Log Order major status changes ( creating / updating / trashing ).
	 *
	 * @action transition_post_status.
	 *
	 * @param string   $new Post status.
	 * @param string   $old Post status.
	 * @param \WP_Post $post WP_Posts object.
	 */
	public function callback_transition_post_status( $new, $old, $post ) {

		// Only track orders.
		if ( 'shop_order' !== $post->post_type ) {
			return;
		}

		// Don't track customer actions.
		if ( ! is_admin() ) {
			return;
		}

		// Don't track minor status change actions.
		if ( in_array( wp_mainwp_stream_filter_input( INPUT_GET, 'action' ), array( 'mark_processing', 'mark_on-hold', 'mark_completed' ), true ) || defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Don't log updates when more than one happens at the same time.
		if ( $post->ID === $this->order_update_logged ) {
			return;
		}

		if ( in_array( $new, array( 'auto-draft', 'draft', 'inherit' ), true ) ) {
			return;
		} elseif ( 'auto-draft' === $old && 'publish' === $new ) {
			// translators: Placeholder refers to an order title (e.g. "Order #42").
			$message = esc_html_x(
				'%s created',
				'Order title',
				'mainwp-child-reports'
			);
			$action  = 'created';
		} elseif ( 'trash' === $new ) {
			// translators: Placeholder refers to an order title (e.g. "Order #42").
			$message = esc_html_x(
				'%s trashed',
				'Order title',
				'mainwp-child-reports'
			);
			$action  = 'trashed';
		} elseif ( 'trash' === $old && 'publish' === $new ) {
			// translators: Placeholder refers to an order title (e.g. "Order #42").
			$message = esc_html_x(
				'%s restored from the trash',
				'Order title',
				'mainwp-child-reports'
			);
			$action  = 'untrashed';
		} else {
			// translators: Placeholder refers to an order title (e.g. "Order #42").
			$message = esc_html_x(
				'%s updated',
				'Order title',
				'mainwp-child-reports'
			);
		}

		if ( empty( $action ) ) {
			$action = 'updated';
		}

		$order           = new \WC_Order( $post->ID );
		$order_title     = esc_html__( 'Order number', 'mainwp-child-reports' ) . ' ' . esc_html( $order->get_order_number() );
		$order_type_name = esc_html__( 'order', 'mainwp-child-reports' );

		$this->log(
			$message,
			array(
				'post_title'    => $order_title,
				'singular_name' => $order_type_name,
				'new_status'    => $new,
				'old_status'    => $old,
				'revision_id'   => null,
			),
			$post->ID,
			$post->post_type,
			$action
		);

		$this->order_update_logged = $post->ID;
	}

	/**
	 * Log order deletion.
	 *
	 * @action deleted_post
	 *
	 * @param int $post_id Post ID.
	 */
	public function callback_deleted_post( $post_id ) {

		$post = get_post( $post_id );

		// We check if post is an instance of WP_Post as it doesn't always resolve in unit testing.
		if ( ! ( $post instanceof \WP_Post ) || 'shop_order' !== $post->post_type ) {
			return;
		}

		// Ignore auto-drafts that are deleted by the system, see issue-293.
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$order           = new \WC_Order( $post->ID );
		$order_title     = esc_html__( 'Order number', 'mainwp-child-reports' ) . ' ' . esc_html( $order->get_order_number() );
		$order_type_name = esc_html__( 'order', 'mainwp-child-reports' );

		$this->log(
			// translators: Placeholder refers to an order title (e.g. "Order #42").
			_x(
				'"%s" deleted from trash',
				'Order title',
				'mainwp-child-reports'
			),
			array(
				'post_title'    => $order_title,
				'singular_name' => $order_type_name,
			),
			$post->ID,
			$post->post_type,
			'deleted'
		);
	}

	/**
	 * Log Order minor status changes ( pending / on-hold / failed / processing / completed / refunded / cancelled ).
	 *
	 * @action woocommerce_order_status_changed.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $old Post status.
	 * @param string $new Post status.
	 */
	public function callback_woocommerce_order_status_changed( $order_id, $old, $new ) {

		// Don't track customer actions.
		if ( ! is_admin() ) {
			return;
		}

		// Don't track new statuses.
		if ( empty( $old ) ) {
			return;
		}

		if ( version_compare( $this->plugin_version, '2.2', '>=' ) ) {
			$old_status_name = wc_get_order_status_name( $old );
			$new_status_name = wc_get_order_status_name( $new );
		} else {
			$old_status      = wp_mainwp_stream_is_vip() ? wpcom_vip_get_term_by( 'slug', $old, 'shop_order_status' ) : get_term_by( 'slug', $old, 'shop_order_status' );
			$new_status      = wp_mainwp_stream_is_vip() ? wpcom_vip_get_term_by( 'slug', $new, 'shop_order_status' ) : get_term_by( 'slug', $new, 'shop_order_status' );
			$new_status_name = $new_status->name;
			$old_status_name = $old_status->name;
		}

		/**
		 * translators: Placeholders refer to an order title, and order status,
		 * and another order status (e.g. "Order #42", "processing", "complete").
		 */
		$message = esc_html_x(
			'%1$s status changed from %2$s to %3$s',
			'1. Order title, 2. Old status, 3. New status',
			'mainwp-child-reports'
		);

		$order           = new \WC_Order( $order_id );
		$order_title     = esc_html__( 'Order number', 'mainwp-child-reports' ) . ' ' . esc_html( $order->get_order_number() );
		$order_type_name = esc_html__( 'order', 'mainwp-child-reports' );

		$this->log(
			$message,
			array(
				'post_title'      => $order_title,
				'old_status_name' => $old_status_name,
				'new_status_name' => $new_status_name,
				'singular_name'   => $order_type_name,
				'new_status'      => $new,
				'old_status'      => $old,
				'revision_id'     => null,
			),
			$order_id,
			'shop_order',
			'updated'
		);
	}

	/**
	 * Log adding a product attribute.
	 *
	 * @action woocommerce_attribute_added
	 *
	 * @param int   $attribute_id Attribute ID.
	 * @param array $attribute Attribute array.
	 */
	public function callback_woocommerce_attribute_added( $attribute_id, $attribute ) {
		$this->log(
			// translators: Placeholder refers to a term name (e.g. "color").
			_x(
				'"%s" product attribute created',
				'Term name',
				'mainwp-child-reports'
			),
			$attribute,
			$attribute_id,
			'attributes',
			'created'
		);
	}

	/**
	 * Log updating a product.
	 *
	 * @action woocommerce_attribute_updated
	 *
	 * @param int   $attribute_id Attribute ID.
	 * @param array $attribute Attribute array.
	 */
	public function callback_woocommerce_attribute_updated( $attribute_id, $attribute ) {
		$this->log(
			// translators: Placeholder refers to a term name (e.g. "color")
			_x(
				'"%s" product attribute updated',
				'Term name',
				'mainwp-child-reports'
			),
			$attribute,
			$attribute_id,
			'attributes',
			'updated'
		);
	}

	/**
	 * Log deleting a product attribute.
	 *
	 * @action woocommerce_attribute_updated
	 *
	 * @param int    $attribute_id Attribute ID.
	 * @param string $attribute_name Attribute name.
	 */
	public function callback_woocommerce_attribute_deleted( $attribute_id, $attribute_name ) {
		$this->log(
			// translators: Placeholder refers to a term name (e.g. "color")
			_x(
				'"%s" product attribute deleted',
				'Term name',
				'mainwp-child-reports'
			),
			array(
				'attribute_name' => $attribute_name,
			),
			$attribute_id,
			'attributes',
			'deleted'
		);
	}

	/**
	 * Log adding a tax rate.
	 *
	 * @action woocommerce_tax_rate_added
	 *
	 * @param int   $tax_rate_id Tax rate ID.
	 * @param array $tax_rate Tax rate array.
	 */
	public function callback_woocommerce_tax_rate_added( $tax_rate_id, $tax_rate ) {
		$this->log(
			// translators: Placeholder refers to a tax rate name (e.g. "GST").
			_x(
				'"%4$s" tax rate created',
				'Tax rate name',
				'mainwp-child-reports'
			),
			$tax_rate,
			$tax_rate_id,
			'tax',
			'created'
		);
	}

	/**
	 * Log updating a tax rate.
	 *
	 * @action woocommerce_tax_rate_updated
	 *
	 * @param int   $tax_rate_id Tax rate ID.
	 * @param array $tax_rate Tax rate array.
	 */
	public function callback_woocommerce_tax_rate_updated( $tax_rate_id, $tax_rate ) {

		$this->log(
			// translators: Placeholder refers to a tax rate name (e.g. "GST").
			_x(
				'"%1$s" tax rate updated',
				'Tax rate name',
				'mainwp-child-reports'
			),
			$tax_rate,
			$tax_rate_id,
			'tax',
			'updated'
		);
	}

	/**
	 * Log deleting a tax rate.
	 *
	 * @action woocommerce_tax_rate_updated
	 *
	 * @param int $tax_rate_id Tax rate ID.
	 */
	public function callback_woocommerce_tax_rate_deleted( $tax_rate_id ) {

		/** @global object $wpdb WordPress DB object. */
		global $wpdb;

		$tax_rate_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates
				WHERE tax_rate_id = %s
				",
				$tax_rate_id
			)
		);

		$this->log(
			// translators: Placeholder refers to a tax rate name (e.g. "GST").
			_x(
				'"%s" tax rate deleted',
				'Tax rate name',
				'mainwp-child-reports'
			),
			array(
				'tax_rate_name' => $tax_rate_name,
			),
			$tax_rate_id,
			'tax',
			'deleted'
		);
	}

	/**
	 * Filter records and take-over our precious data.
	 *
	 * @filter wp_mainwp_stream_record_array
	 *
	 * @param array $recordarr Record data to be inserted.
	 *
	 * @return array Filtered record data.
	 */
	public function callback_wp_mainwp_stream_record_array( $recordarr ) {
		foreach ( $recordarr as $key => $record ) {
			if ( ! isset( $record['connector'] ) || ! isset( $record['context'] ) ) {
				continue;
			}

			// Change connector::posts records.
			if ( 'posts' === $record['connector'] && in_array( $record['context'], $this->post_types, true ) ) {
				$recordarr[ $key ]['connector'] = $this->name;
			} elseif ( 'taxonomies' === $record['connector'] && in_array( $record['context'], $this->taxonomies, true ) ) {
				$recordarr[ $key ]['connector'] = $this->name;
			} elseif ( 'settings' === $record['connector'] ) {
				$option = isset( $record['meta']['option_key'] ) ? $record['meta']['option_key'] : false;

				if ( $option && isset( $this->settings[ $option ] ) ) {
					return false;
				}
			}
		}

		return $recordarr;
	}

	/**
	 * Updated option callback.
	 *
	 * @param string $option_key Option Key.
	 * @param string $old_value Old options value.
	 * @param string $value New options value.
	 */
	public function callback_updated_option( $option_key, $old_value, $value ) {
		$options = array( $option_key );

		if ( is_array( $old_value ) || is_array( $value ) ) {
			foreach ( $this->get_changed_keys( $old_value, $value ) as $field_key ) {
				$options[] = $field_key;
			}
		}

		foreach ( $options as $option ) {
			if ( ! array_key_exists( $option, $this->settings ) ) {
				continue;
			}

			$this->log(
				// translators: Placeholders refer to a setting name and a setting type (e.g. "Direct Deposit", "Payment Method")
				__( '"%1$s" %2$s updated', 'mainwp-child-reports' ),
				array(
					'label'     => $this->settings[ $option ]['title'],
					'type'      => $this->settings[ $option ]['type'],
					'page'      => $this->settings[ $option ]['page'],
					'tab'       => $this->settings[ $option ]['tab'],
					'section'   => $this->settings[ $option ]['section'],
					'option'    => $option,
					'old_value' => $old_value,
					'value'     => $value,
				),
				null,
				$this->settings[ $option ]['tab'],
				'updated'
			);
		}
	}

	/**
	 * Get WooCommerce settings fileds.
	 *
	 * @return array|bool Return WooCommerce settings array, or FALSE on failure.
	 */
	public function get_woocommerce_settings_fields() {
		if ( ! defined( 'WC_VERSION' ) || ! class_exists( 'WC_Admin_Settings' ) ) {
			return false;
		}

		if ( ! empty( $this->settings ) ) {
			return $this->settings;
		}

		$settings_cache_key = 'stream_connector_woocommerce_settings_' . sanitize_key( WC_VERSION );
		$settings_transient = get_transient( $settings_cache_key );

		if ( $settings_transient ) {
			$settings       = $settings_transient['settings'];
			$settings_pages = $settings_transient['settings_pages'];
		} else {

			/** @global object $woocommerce WooCommerce class instance. */
			global $woocommerce;

			$settings       = array();
			$settings_pages = array();

			foreach ( \WC_Admin_Settings::get_settings_pages() as $page ) {

				if ( ! is_object( $page ) || ! property_exists( $page, 'add_settings_page' )) {
					continue;
				}

				/**
				 * Get ID / Label of the page, since they're protected, by hacking into
				 * the callback filter for 'woocommerce_settings_tabs_array'.
				 */
				$info       = $page->add_settings_page( array() );
				$page_id    = key( $info );
				$page_label = current( $info );
				$sections   = $page->get_sections();

				if ( empty( $sections ) ) {
					$sections[''] = $page_label;
				}

				$settings_pages[ $page_id ] = $page_label;

				// Remove non-fields .
				$fields = array();

				foreach ( $sections as $section_key => $section_label ) {
					$section_settings = $page->get_settings( $section_key );
					if ( is_array( $section_settings ) ) {
						$_fields = array_filter(
							$page->get_settings( $section_key ),
							function( $item ) {
								return isset( $item['id'] ) && ( ! in_array( $item['type'], array( 'title', 'sectionend' ), true ) );
							}
						);

						if ( ! empty( $_fields ) ) {
							foreach ( $_fields as $field ) {
								$title                  = isset( $field['title'] ) ? $field['title'] : ( isset( $field['desc'] ) ? $field['desc'] : 'N/A' );
								$fields[ $field['id'] ] = array(
									'title'   => $title,
									'page'    => 'wc-settings',
									'tab'     => $page_id,
									'section' => $section_key,
									'type'    => esc_html__( 'setting', 'mainwp-child-reports' ),
								);
							}
						}
					}
				}

				// Store fields in the global array to be searched later.
				$settings = array_merge( $settings, $fields );
			}

			// Provide additional context for each of the settings pages.
			array_walk(
				$settings_pages,
				function( &$value ) {
					$value .= ' ' . esc_html__( 'Settings', 'mainwp-child-reports' );
				}
			);

			// Load Payment Gateway Settings.
			$payment_gateway_settings = array();
			$payment_gateways         = $woocommerce->payment_gateways();

			foreach ( $payment_gateways->payment_gateways as $section_key => $payment_gateway ) {
				$title = $payment_gateway->title;
				$key   = $payment_gateway->plugin_id . $payment_gateway->id . '_settings';

				$payment_gateway_settings[ $key ] = array(
					'title'   => $title,
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => strtolower( $section_key ),
					'type'    => esc_html__( 'payment gateway', 'mainwp-child-reports' ),
				);
			}

			$settings = array_merge( $settings, $payment_gateway_settings );

			// Load Shipping Method Settings.
			$shipping_method_settings = array();
			$shipping_methods         = $woocommerce->shipping();

			foreach ( (array) $shipping_methods->shipping_methods as $section_key => $shipping_method ) {
				$title = $shipping_method->title;
				$key   = $shipping_method->plugin_id . $shipping_method->id . '_settings';

				$shipping_method_settings[ $key ] = array(
					'title'   => $title,
					'page'    => 'wc-settings',
					'tab'     => 'shipping',
					'section' => strtolower( $section_key ),
					'type'    => esc_html__( 'shipping method', 'mainwp-child-reports' ),
				);
			}

			$settings = array_merge( $settings, $shipping_method_settings );

			// Load Email Settings.
			$email_settings = array();
			$emails         = $woocommerce->mailer();

			foreach ( $emails->emails as $section_key => $email ) {
				$title = $email->title;
				$key   = $email->plugin_id . $email->id . '_settings';

				$email_settings[ $key ] = array(
					'title'   => $title,
					'page'    => 'wc-settings',
					'tab'     => 'email',
					'section' => strtolower( $section_key ),
					'type'    => esc_html__( 'email', 'mainwp-child-reports' ),
				);
			}

			$settings = array_merge( $settings, $email_settings );

			// Tools page.
			$tools_page = array(
				'tools' => esc_html__( 'Tools', 'mainwp-child-reports' ),
			);

			$settings_pages = array_merge( $settings_pages, $tools_page );

			// Cache the results
			$settings_cache = array(
				'settings'       => $settings,
				'settings_pages' => $settings_pages,
			);

			set_transient( $settings_cache_key, $settings_cache, MINUTE_IN_SECONDS * 60 * 6 );
		}

		$custom_settings      = $this->get_custom_settings();
		$this->settings       = array_merge( $settings, $custom_settings );
		$this->settings_pages = $settings_pages;

		return $this->settings;
	}
}
