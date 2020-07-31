<?php
/** EDD Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_EDD
 * @package WP_MainWP_Stream
 */
class Connector_EDD extends Connector {

	/** @var string Connector slug */
	public $name = 'edd';

	/**
	 * Defines plugin minimum version required.
	 *
	 * @const ( string ) Holds tracked plugin minimum version required.
	 */
	const PLUGIN_MIN_VERSION = '1.8.8';

	/** @var array Actions registered for this connector. */
	public $actions = array(
		'update_option',
		'add_option',
		'delete_option',
		'update_site_option',
		'add_site_option',
		'delete_site_option',
		'edd_pre_update_discount_status',
		'edd_generate_pdf',
		'edd_earnings_export',
		'edd_payment_export',
		'edd_email_export',
		'edd_downloads_history_export',
		'edd_import_settings',
		'edd_export_settings',
		'add_user_meta',
		'update_user_meta',
		'delete_user_meta',
	);

	/** @var array Tracked option keys. */
	public $options = array();

	/** @var array Tracking registered Settings, with overridden data. */
	public $options_override = array();

	/** @var array Tracking user meta updates related to this connector. */
	public $user_meta = array(
		'edd_user_public_key',
	);

	/** @var bool Flag status changes to not create duplicate entries. */
	public $is_discount_status_change = false;

	/** @var bool Flag status changes to not create duplicate entries. */
	public $is_payment_status_change = false;

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not.
	 *
	 * @return bool Return TRUE|FALSE.
	 */
	public function is_dependency_satisfied() {
		if ( class_exists( 'Easy_Digital_Downloads' ) && defined( 'EDD_VERSION' ) && version_compare( EDD_VERSION, self::PLUGIN_MIN_VERSION, '>=' ) ) {
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
		return esc_html_x( 'Easy Digital Downloads', 'edd', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array Action label translations.
	 */
	public function get_action_labels() {
		return array(
			'created'   => esc_html_x( 'Created', 'edd', 'mainwp-child-reports' ),
			'updated'   => esc_html_x( 'Updated', 'edd', 'mainwp-child-reports' ),
			'added'     => esc_html_x( 'Added', 'edd', 'mainwp-child-reports' ),
			'deleted'   => esc_html_x( 'Deleted', 'edd', 'mainwp-child-reports' ),
			'trashed'   => esc_html_x( 'Trashed', 'edd', 'mainwp-child-reports' ),
			'untrashed' => esc_html_x( 'Restored', 'edd', 'mainwp-child-reports' ),
			'generated' => esc_html_x( 'Generated', 'edd', 'mainwp-child-reports' ),
			'imported'  => esc_html_x( 'Imported', 'edd', 'mainwp-child-reports' ),
			'exported'  => esc_html_x( 'Exported', 'edd', 'mainwp-child-reports' ),
			'revoked'   => esc_html_x( 'Revoked', 'edd', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array Context label translations.
	 */
	public function get_context_labels() {
		return array(
			'downloads'         => esc_html_x( 'Downloads', 'edd', 'mainwp-child-reports' ),
			'download_category' => esc_html_x( 'Categories', 'edd', 'mainwp-child-reports' ),
			'download_tag'      => esc_html_x( 'Tags', 'edd', 'mainwp-child-reports' ),
			'discounts'         => esc_html_x( 'Discounts', 'edd', 'mainwp-child-reports' ),
			'reports'           => esc_html_x( 'Reports', 'edd', 'mainwp-child-reports' ),
			'api_keys'          => esc_html_x( 'API Keys', 'edd', 'mainwp-child-reports' ),
			//'payments'        => esc_html_x( 'Payments', 'edd', 'mainwp-child-reports' ),
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
		if ( in_array( $record->context, array( 'downloads' ), true ) ) {
			$posts_connector = new Connector_Posts();
			$links           = $posts_connector->action_links( $links, $record );
		} elseif ( in_array( $record->context, array( 'discounts' ), true ) ) {
			$post_type_label = get_post_type_labels( get_post_type_object( 'edd_discount' ) )->singular_name;
			$base            = admin_url( 'edit.php?post_type=download&page=edd-discounts' );

			// translators: Placeholder refers to a post type (e.g. "Post").
			$links[ sprintf( esc_html__( 'Edit %s', 'mainwp-child-reports' ), $post_type_label ) ] = add_query_arg(
				array(
					'edd-action' => 'edit_discount',
					'discount'   => $record->object_id,
				),
				$base
			);

			if ( 'active' === get_post( $record->object_id )->post_status ) {
				// translators: Placeholder refers to a post type (e.g. "Post").
				$links[ sprintf( esc_html__( 'Deactivate %s', 'mainwp-child-reports' ), $post_type_label ) ] = add_query_arg(
					array(
						'edd-action' => 'deactivate_discount',
						'discount'   => $record->object_id,
					),
					$base
				);
			} else {
				// translators: Placeholder refers to a post type (e.g. "Post").
				$links[ sprintf( esc_html__( 'Activate %s', 'mainwp-child-reports' ), $post_type_label ) ] = add_query_arg(
					array(
						'edd-action' => 'activate_discount',
						'discount'   => $record->object_id,
					),
					$base
				);
			}
		} elseif ( in_array( $record->context, array(
			'download_category',
			'download_tag',
		), true ) ) {
			$tax_label = get_taxonomy_labels( get_taxonomy( $record->context ) )->singular_name;
			// translators: Placeholder refers to a taxonomy (e.g. "Category")
			$links[ sprintf( esc_html__( 'Edit %s', 'mainwp-child-reports' ), $tax_label ) ] = get_edit_term_link( $record->object_id, $record->get_meta( 'taxonomy', true ) );
		} elseif ( 'api_keys' === $record->context ) {
			$user = new \WP_User( $record->object_id );

			if ( apply_filters( 'edd_api_log_requests', true ) ) {
				$links[ esc_html__( 'View API Log', 'mainwp-child-reports' ) ] = add_query_arg(
					array(
						'view'      => 'api_requests',
						'post_type' => 'download',
						'page'      => 'edd-reports',
						'tab'       => 'logs',
						's'         => $user->user_email,
					), 'edit.php'
				);
			}

			$links[ esc_html__( 'Revoke', 'mainwp-child-reports' ) ]  = add_query_arg(
				array(
					'post_type'       => 'download',
					'user_id'         => $record->object_id,
					'edd_action'      => 'process_api_key',
					'edd_api_process' => 'revoke',
				), 'edit.php'
			);
			$links[ esc_html__( 'Reissue', 'mainwp-child-reports' ) ] = add_query_arg(
				array(
					'post_type'       => 'download',
					'user_id'         => $record->object_id,
					'edd_action'      => 'process_api_key',
					'edd_api_process' => 'regenerate',
				), 'edit.php'
			);
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
			'edd_settings' => null,
		);
	}

    /**
     * Update options callback.
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
     * Check if option value already exists.
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

			$this->log(
				// translators: Placeholder refers to a setting title (e.g. "Language")
				__( '"%s" setting updated', 'mainwp-child-reports' ),
				compact( 'option_title', 'option', 'old_value', 'new_value' ),
				null,
				$context,
				isset( $data['action'] ) ? $data['action'] : 'updated'
			);
		}
	}

    /**
     * Check EDD settings.
     *
     * @param string $old_value Old option value.
     * @param string $new_value New option value.
     */
    public function check_edd_settings($old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( $this->get_changed_keys( $old_value, $new_value, 0 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		/**  TODO: Check this exists first. */
		$settings = \edd_get_registered_settings();

		foreach ( $options as $option => $option_value ) {
			$field = null;

			if ( 'banned_email' === $option ) {
				$field = array(
					'name' => esc_html_x( 'Banned emails', 'edd', 'mainwp-child-reports' ),
				);
				$tab   = 'general';
			} else {
				foreach ( $settings as $tab => $fields ) {
					if ( isset( $fields[ $option ] ) ) {
						$field = $fields[ $option ];
						break;
					}
				}
			}

			if ( empty( $field ) ) {
				continue;
			}

			$this->log(
				// translators: Placeholder refers to a setting title (e.g. "Language").
				__( '"%s" setting updated', 'mainwp-child-reports' ),
				array(
					'option_title' => $field['name'],
					'option'       => $option,
					'old_value'    => $old_value,
					'value'        => $new_value,
					'tab'          => $tab,
				),
				null,
				'settings',
				'updated'
			);
		}
	}

	/**
	 * Override connector log for our own Settings / Actions.
	 *
	 * @param array $data Post or downloads data.
	 *
	 * @return array|bool Return posts or downloads data, or FALSE on faliure.
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( 'posts' === $data['connector'] && 'download' === $data['context'] ) {
			// Download posts operations
			$data['context']   = 'downloads';
			$data['connector'] = $this->name;
		} elseif ( 'posts' === $data['connector'] && 'edd_discount' === $data['context'] ) {
			// Discount posts operations
			if ( $this->is_discount_status_change ) {
				return false;
			}

			if ( 'deleted' === $data['action'] ) {
				// translators: Placeholder refers to a discount title (e.g. "Mother's Day")
				$data['message'] = esc_html__( '"%1s" discount deleted', 'mainwp-child-reports' );
			}

			$data['context']   = 'discounts';
			$data['connector'] = $this->name;
		} elseif ( 'posts' === $data['connector'] && 'edd_payment' === $data['context'] ) {
			// Payment posts operations.
			return false; // Do not track payments, they're well logged!.
		} elseif ( 'posts' === $data['connector'] && 'edd_log' === $data['context'] ) {
			// Logging operations.
			return false; // Do not track notes, because they're basically logs.
		} elseif ( 'comments' === $data['connector'] && 'edd_payment' === $data['context'] ) {
			// Payment notes ( comments ) operations.
			return false; // Do not track notes, because they're basically logs.
		} elseif ( 'taxonomies' === $data['connector'] && 'download_category' === $data['context'] ) {
			$data['connector'] = $this->name;
		} elseif ( 'taxonomies' === $data['connector'] && 'download_tag' === $data['context'] ) {
			$data['connector'] = $this->name;
		} elseif ( 'taxonomies' === $data['connector'] && 'edd_log_type' === $data['context'] ) {
			return false;
		} elseif ( 'settings' === $data['connector'] && 'edd_settings' === $data['args']['option'] ) {
			return false;
		}

		return $data;
	}

    /**
     * Add pre-update discount status callback.
     *
     * @param string $code_id Code ID.
     * @param string $new_status New status.
     */
    public function callback_edd_pre_update_discount_status($code_id, $new_status ) {
		$this->is_discount_status_change = true;

		$this->log(
			sprintf(
				// translators: Placeholders refer to a discount title, and a status (e.g. "Mother's Day", "activated").
				__( '"%1$s" discount %2$s', 'mainwp-child-reports' ),
				get_post( $code_id )->post_title,
				'active' === $new_status ? esc_html__( 'activated', 'mainwp-child-reports' ) : esc_html__( 'deactivated', 'mainwp-child-reports' )
			),
			array(
				'post_id' => $code_id,
				'status'  => $new_status,
			),
			$code_id,
			'discounts',
			'updated'
		);
	}

    /**
     * EDD generated PDF callback.
     */
    private function callback_edd_generate_pdf() {
		$this->report_generated( 'pdf' );
	}

    /**
     * EDD earnings export callback.
     */
    public function callback_edd_earnings_export() {
		$this->report_generated( 'earnings' );
	}

    /**
     * EDD payments export callback.
     */
    public function callback_edd_payment_export() {
		$this->report_generated( 'payments' );
	}

    /**
     * EDD email export callback.
     */
    public function callback_edd_email_export() {
		$this->report_generated( 'emails' );
	}

    /**
     * EDD downloads history export callback.
     */
    public function callback_edd_downloads_history_export() {
		$this->report_generated( 'download-history' );
	}

    /**
     * Generated report.
     *
     * @param string $type PDF type.
     */
    private function report_generated($type ) {
		$label = '';

		if ( 'pdf' === $type ) {
			$label = esc_html__( 'Sales and Earnings', 'mainwp-child-reports' );
		} elseif ( 'earnings' ) {
			$label = esc_html__( 'Earnings', 'mainwp-child-reports' );
		} elseif ( 'payments' ) {
			$label = esc_html__( 'Payments', 'mainwp-child-reports' );
		} elseif ( 'emails' ) {
			$label = esc_html__( 'Emails', 'mainwp-child-reports' );
		} elseif ( 'download-history' ) {
			$label = esc_html__( 'Download History', 'mainwp-child-reports' );
		}

		$this->log(
			sprintf(
				// translators: Placeholder refers to a report title (e.g. "Sales and Earnings").
				__( 'Generated %s report', 'mainwp-child-reports' ),
				$label
			),
			array(
				'type' => $type,
			),
			null,
			'reports',
			'generated'
		);
	}

    /**
     * EDD export settings callback.
     */
    public function callback_edd_export_settings() {
		$this->log(
			__( 'Exported Settings', 'mainwp-child-reports' ),
			array(),
			null,
			'settings',
			'exported'
		);
	}

    /**
     * EDD import settings callback.
     */
    public function callback_edd_import_settings() {
		$this->log(
			__( 'Imported Settings', 'mainwp-child-reports' ),
			array(),
			null,
			'settings',
			'imported'
		);
	}

    /**
     * Update user meta callback.
     *
     * @param string $meta_id Meta ID.
     * @param string $object_id Object ID.
     * @param string $meta_key Meta Key.
     * @param string $_meta_value Meta value.
     */
    public function callback_update_user_meta($meta_id, $object_id, $meta_key, $_meta_value ) {
		unset( $meta_id );
		$this->meta( $object_id, $meta_key, $_meta_value );
	}

    /**
     * Add user meta callback.
     *
     * @param string $object_id Object ID.
     * @param string $meta_key Meta Key.
     * @param string $_meta_value Meta value.
     */
    public function callback_add_user_meta($object_id, $meta_key, $_meta_value ) {
		$this->meta( $object_id, $meta_key, $_meta_value, true );
	}

    /**
     * Delete user meta callback.
     *
     * @param string $meta_id Meta ID.
     * @param string $object_id Object ID.
     * @param string $meta_key Meta Key.
     * @param string $_meta_value Meta value.
     */
    public function callback_delete_user_meta($meta_id, $object_id, $meta_key, $_meta_value ) {
		$this->meta( $object_id, $meta_key, null );
	}

    /**
     * Check if meta option was added.
     *
     * @param $object_id Object ID.
     * @param $key Object key.
     * @param $value Object value.
     * @param bool $is_add Check if option was added. TRUE|FALSE.
     * @return bool|mixed Return respose array or FALSE on failure.
     */
    public function meta($object_id, $key, $value, $is_add = false ) {
		if ( ! in_array( $key, $this->user_meta, true ) ) {
			return false;
		}

		$key = str_replace( '-', '_', $key );

		if ( ! method_exists( $this, 'meta_' . $key ) ) {
			return false;
		}

		return call_user_func( array(
			$this,
			'meta_' . $key,
		), $object_id, $value, $is_add );
	}

    /**
     * EDD user public key callback.
     *
     * @param string $user_id User ID.
     * @param string $value Public key.
     * @param bool $is_add Whether the key was added or not. TRUE|FASLE.
     */
    private function meta_edd_user_public_key($user_id, $value, $is_add = false ) {
		if ( is_null( $value ) ) {
			$action       = 'revoked';
			$action_title = esc_html__( 'revoked', 'mainwp-child-reports' );
		} elseif ( $is_add ) {
			$action       = 'created';
			$action_title = esc_html__( 'created', 'mainwp-child-reports' );
		} else {
			$action       = 'updated';
			$action_title = esc_html__( 'updated', 'mainwp-child-reports' );
		}

		$this->log(
			sprintf(
				// translators: Placeholder refers to a status (e.g. "revoked").
				__( 'User API Key %s', 'mainwp-child-reports' ),
				$action_title
			),
			array(
				'meta_value' => $value,
			),
			$user_id,
			'api_keys',
			$action
		);
	}
}
