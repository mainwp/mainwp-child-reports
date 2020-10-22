<?php
/** GravityForms Connector. */

namespace WP_MainWP_Stream;

/**
 * Class Connector_GravityForms
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_GravityForms extends Connector {

	/** @var string Connector slug. */
	public $name = 'gravityforms';

	/** @const string Holds tracked plugin minimum version required. */
	const PLUGIN_MIN_VERSION = '1.9.14';

	/** @var array Actions registered for this connector. */
	public $actions = array(
		'gform_after_save_form',
		'gform_pre_confirmation_save',
		'gform_pre_notification_save',
		'gform_pre_notification_deleted',
		'gform_pre_confirmation_deleted',
		'gform_before_delete_form',
		'gform_post_form_trashed',
		'gform_post_form_restored',
		'gform_post_form_activated',
		'gform_post_form_deactivated',
		'gform_post_form_duplicated',
		'gform_post_form_views_deleted',
		'gform_post_export_entries',
		'gform_forms_post_import',
		'gform_delete_lead',
		'gform_post_note_added',
		'gform_pre_note_deleted',
		'gform_update_status',
		'gform_update_is_read',
		'gform_update_is_starred',
		'update_option',
		'add_option',
		'delete_option',
		'update_site_option',
		'add_site_option',
		'delete_site_option',
	);

	/** @var array Tracked option keys. */
	public $options = array();

	/** @var array Tracking registered Settings, with overridden data. */
	public $options_override = array();

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool Return TRUE|FALSE.
	 */
	public function is_dependency_satisfied() {
		if ( class_exists( 'GFForms' ) && version_compare( \GFCommon::$version, self::PLUGIN_MIN_VERSION, '>=' ) ) {
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
		return esc_html_x( 'Gravity Forms', 'gravityforms', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array Action label translations.
	 */
	public function get_action_labels() {
		return array(
			'created'       => esc_html_x( 'Created', 'gravityforms', 'mainwp-child-reports' ),
			'updated'       => esc_html_x( 'Updated', 'gravityforms', 'mainwp-child-reports' ),
			'exported'      => esc_html_x( 'Exported', 'gravityforms', 'mainwp-child-reports' ),
			'imported'      => esc_html_x( 'Imported', 'gravityforms', 'mainwp-child-reports' ),
			'added'         => esc_html_x( 'Added', 'gravityforms', 'mainwp-child-reports' ),
			'deleted'       => esc_html_x( 'Deleted', 'gravityforms', 'mainwp-child-reports' ),
			'trashed'       => esc_html_x( 'Trashed', 'gravityforms', 'mainwp-child-reports' ),
			'untrashed'     => esc_html_x( 'Restored', 'gravityforms', 'mainwp-child-reports' ),
			'duplicated'    => esc_html_x( 'Duplicated', 'gravityforms', 'mainwp-child-reports' ),
			'activated'     => esc_html_x( 'Activated', 'gravityforms', 'mainwp-child-reports' ),
			'deactivated'   => esc_html_x( 'Deactivated', 'gravityforms', 'mainwp-child-reports' ),
			'views_deleted' => esc_html_x( 'Views Reset', 'gravityforms', 'mainwp-child-reports' ),
			'starred'       => esc_html_x( 'Starred', 'gravityforms', 'mainwp-child-reports' ),
			'unstarred'     => esc_html_x( 'Unstarred', 'gravityforms', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array Context label translations.
	 */
	public function get_context_labels() {
		return array(
			'forms'    => esc_html_x( 'Forms', 'gravityforms', 'mainwp-child-reports' ),
			'settings' => esc_html_x( 'Settings', 'gravityforms', 'mainwp-child-reports' ),
			'export'   => esc_html_x( 'Import/Export', 'gravityforms', 'mainwp-child-reports' ),
			'entries'  => esc_html_x( 'Entries', 'gravityforms', 'mainwp-child-reports' ),
			'notes'    => esc_html_x( 'Notes', 'gravityforms', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen.
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}.
	 *
	 * @param  array  $links     Previous links registered.
	 * @param  object $record    Stream record.
	 *
	 * @return array             Action links.
	 */
	public function action_links( $links, $record ) {
		if ( 'forms' === $record->context ) {
			$links[ esc_html__( 'Edit', 'mainwp-child-reports' ) ] = add_query_arg(
				array(
					'page' => 'gf_edit_forms',
					'id'   => $record->object_id,
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'entries' === $record->context ) {
			$links[ esc_html__( 'View', 'mainwp-child-reports' ) ] = add_query_arg(
				array(
					'page' => 'gf_entries',
					'view' => 'entry',
					'lid'  => $record->object_id,
					'id'   => $record->get_meta( 'form_id', true ),
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'notes' === $record->context ) {
			$links[ esc_html__( 'View', 'mainwp-child-reports' ) ] = add_query_arg(
				array(
					'page' => 'gf_entries',
					'view' => 'entry',
					'lid'  => $record->get_meta( 'lead_id', true ),
					'id'   => $record->get_meta( 'form_id', true ),
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'settings' === $record->context ) {
			$links[ esc_html__( 'Edit Settings', 'mainwp-child-reports' ) ] = add_query_arg(
				array(
					'page' => 'gf_settings',
				),
				admin_url( 'admin.php' )
			);
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

		$this->options = array(
			'rg_gforms_disable_css'         => array(
				'label' => esc_html_x( 'Output CSS', 'gravityforms', 'mainwp-child-reports' ),
			),
			'rg_gforms_enable_html5'        => array(
				'label' => esc_html_x( 'Output HTML5', 'gravityforms', 'mainwp-child-reports' ),
			),
			'gform_enable_noconflict'       => array(
				'label' => esc_html_x( 'No-Conflict Mode', 'gravityforms', 'mainwp-child-reports' ),
			),
			'rg_gforms_currency'            => array(
				'label' => esc_html_x( 'Currency', 'gravityforms', 'mainwp-child-reports' ),
			),
			'rg_gforms_captcha_public_key'  => array(
				'label' => esc_html_x( 'reCAPTCHA Public Key', 'gravityforms', 'mainwp-child-reports' ),
			),
			'rg_gforms_captcha_private_key' => array(
				'label' => esc_html_x( 'reCAPTCHA Private Key', 'gravityforms', 'mainwp-child-reports' ),
			),
			'rg_gforms_key'                 => null,
		);
	}

	/**
	 * Track Create/Update actions on Forms.
	 *
	 * @param array $form Forms array.
	 * @param bool $is_new Check if is new action.
	 * @return void
	 */
	public function callback_gform_after_save_form( $form, $is_new ) {
		$title = $form['title'];
		$id    = $form['id'];

		$this->log(
			sprintf(
				// translators: Placeholders refer to a form title, and a status (e.g. "Contact Form", "created").
				__( '"%1$s" form %2$s', 'mainwp-child-reports' ),
				$title,
				$is_new ? esc_html__( 'created', 'mainwp-child-reports' ) : esc_html__( 'updated', 'mainwp-child-reports' )
			),
			array(
				'action' => $is_new,
				'id'     => $id,
				'title'  => $title,
			),
			$id,
			'forms',
			$is_new ? 'created' : 'updated'
		);
	}

	/**
	 * Track saving form confirmations.
	 *
	 * @param array $confirmation Confirmations array.
	 * @param array $form Forms array.
	 * @param bool $is_new Check if new submission.
	 * @return array Return response array.
	 */
	public function callback_gform_pre_confirmation_save( $confirmation, $form, $is_new = true ) {
		if ( ! isset( $is_new ) ) {
			$is_new = false;
		}

		$this->log(
			sprintf(
				// translators: Placeholders refer to a confirmation name, a status, and a form title (e.g. "Email", "created", "Contact Form")
				__( '"%1$s" confirmation %2$s for "%3$s"', 'mainwp-child-reports' ),
				$confirmation['name'],
				$is_new ? esc_html__( 'created', 'mainwp-child-reports' ) : esc_html__( 'updated', 'mainwp-child-reports' ),
				$form['title']
			),
			array(
				'is_new'  => $is_new,
				'form_id' => $form['id'],
			),
			$form['id'],
			'forms',
			'updated'
		);

		return $confirmation;
	}

	/**
	 * Track saving form notifications.
	 *
	 * @param array $notification Notifications array.
	 * @param array $form Form array.
	 * @param bool $is_new Check if new form post.
	 * @return array Return response array.
	 */
	public function callback_gform_pre_notification_save( $notification, $form, $is_new = true ) {
		if ( ! isset( $is_new ) ) {
			$is_new = false;
		}

		$this->log(
			sprintf(
				// translators: Placeholders refer to a notification name, a status, and a form title (e.g. "Email", "created", "Contact Form")
				__( '"%1$s" notification %2$s for "%3$s"', 'mainwp-child-reports' ),
				$notification['name'],
				$is_new ? esc_html__( 'created', 'mainwp-child-reports' ) : esc_html__( 'updated', 'mainwp-child-reports' ),
				$form['title']
			),
			array(
				'is_update' => $is_new,
				'form_id'   => $form['id'],
			),
			$form['id'],
			'forms',
			'updated'
		);

		return $notification;
	}

	/**
	 * Track deletion of notifications.
	 *
	 * @param array $notification Notifications array.
	 * @param array $form Forms array.
	 * @return void
	 */
	public function callback_gform_pre_notification_deleted( $notification, $form ) {
		$this->log(
			sprintf(
				// translators: Placeholders refer to a notification name, and a form title (e.g. "Email", "Contact Form").
				__( '"%1$s" notification deleted from "%2$s"', 'mainwp-child-reports' ),
				$notification['name'],
				$form['title']
			),
			array(
				'form_id'      => $form['id'],
				'notification' => $notification,
			),
			$form['id'],
			'forms',
			'updated'
		);
	}

	/**
	 * Track deletion of confirmations.
	 *
	 * @param array $confirmation Confirmation array.
	 * @param array $form Form array.
	 * @return void
	 */
	public function callback_gform_pre_confirmation_deleted( $confirmation, $form ) {
		$this->log(
			sprintf(
				// translators: Placeholders refer to a confirmation name, and a form title (e.g. "Email", "Contact Form")
				__( '"%1$s" confirmation deleted from "%2$s"', 'mainwp-child-reports' ),
				$confirmation['name'],
				$form['title']
			),
			array(
				'form_id'      => $form['id'],
				'confirmation' => $confirmation,
			),
			$form['id'],
			'forms',
			'updated'
		);
	}

	/**
	 * Track status change of confirmations.
	 *
	 * @param array $confirmation Confirmation array.
	 * @param array $form Forms array.
	 * @param bool $is_active Check if is active.
	 * @return void
	 */
	public function callback_gform_confirmation_status( $confirmation, $form, $is_active ) {
		$this->log(
			sprintf(
				// translators: Placeholders refer to a confirmation name, a status, and a form title (e.g. "Email", "activated", "Contact Form").
				__( '"%1$s" confirmation %2$s from "%3$s"', 'mainwp-child-reports' ),
				$confirmation['name'],
				$is_active ? esc_html__( 'activated', 'mainwp-child-reports' ) : esc_html__( 'deactivated', 'mainwp-child-reports' ),
				$form['title']
			),
			array(
				'form_id'      => $form['id'],
				'confirmation' => $confirmation,
				'is_active'    => $is_active,
			),
			null,
			'forms',
			'updated'
		);
	}

	/**
	 * Track status change of notifications.
	 *
	 * @param array $notification Notifications array.
	 * @param array $form Forms array.
	 * @param bool $is_active Check if active.
	 * @return void
	 */
	public function callback_gform_notification_status( $notification, $form, $is_active ) {
		$this->log(
			sprintf(
				// translators: Placeholders refer to a notification name, a status, and a form title (e.g. "Email", "activated", "Contact Form").
				__( '"%1$s" notification %2$s from "%3$s"', 'mainwp-child-reports' ),
				$notification['name'],
				$is_active ? esc_html__( 'activated', 'mainwp-child-reports' ) : esc_html__( 'deactivated', 'mainwp-child-reports' ),
				$form['title']
			),
			array(
				'form_id'      => $form['id'],
				'notification' => $notification,
				'is_active'    => $is_active,
			),
			$form['id'],
			'forms',
			'updated'
		);
	}

    /**
     * Update option contact callback.
     *
     * @param string $option Option to update.
     * @param string $old Old value.
     * @param string $new New value.
     */
    public function callback_update_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

    /**
     * Add option callback.
     *
     * @param string $option Option to update.
     * @param string $val Option value.
     */
    public function callback_add_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

    /**
     * Delete option callback.
     *
     * @param $option Option to delete.
     */
    public function callback_delete_option( $option ) {
		$this->check( $option, null, null );
	}

    /**
     * Update site option callback.
     *
     * @param string $option Option to update.
     * @param string $old Old value.
     * @param string $new New value.
     */
    public function callback_update_site_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

    /**
     * Add site option callback.
     *
     * @param string $option Option to update.
     * @param string $val Option value.
     */
    public function callback_add_site_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

    /**
     * Delete site option callback.
     *
     * @param string $option Option to update.
     */
    public function callback_delete_site_option( $option ) {
		$this->check( $option, null, null );
	}

    /**
     *  Check if option exists.
     *
     * @param string $option Option to update.
     * @param string $old_value Old value.
     * @param string $new_value New value.
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
     * Check Register GravityForms key.
     *
     * @param string $old_value Old value.
     * @param string $new_value New value.
     */
    public function check_rg_gforms_key($old_value, $new_value ) {
		$is_update = ( $new_value && strlen( $new_value ) );
		$option    = 'rg_gforms_key';

		$this->log(
			sprintf(
				// translators: Placeholder refers to a status (e.g. "updated").
				__( 'Gravity Forms license key %s', 'mainwp-child-reports' ),
				$is_update ? esc_html__( 'updated', 'mainwp-child-reports' ) : esc_html__( 'deleted', 'mainwp-child-reports' )
			),
			compact( 'option', 'old_value', 'new_value' ),
			null,
			'settings',
			$is_update ? 'updated' : 'deleted'
		);
	}

    /**
     * GravityForms post export entries callback.
     *
     * @param array $form Form data array.
     * @param int $start_date Start date.
     * @param int $end_date End date.
     * @param array $fields Form fields array.
     */
    public function callback_gform_post_export_entries( $form, $start_date, $end_date, $fields ) {
		unset( $fields );
		$this->log(
			// translators: Placeholder refers to a form title (e.g. "Contact Form").
			__( '"%s" form entries exported', 'mainwp-child-reports' ),
			array(
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
				'start_date' => empty( $start_date ) ? null : $start_date,
				'end_date'   => empty( $end_date ) ? null : $end_date,
			),
			$form['id'],
			'export',
			'exported'
		);
	}

    /**
     * GravityForms post import callback.
     *
     * @param $forms Forms array.
     */
    public function callback_gform_forms_post_import( $forms ) {
		$forms_total  = count( $forms );
		$forms_ids    = wp_list_pluck( $forms, 'id' );
		$forms_titles = wp_list_pluck( $forms, 'title' );

		$this->log(
			// translators: Placeholder refers to a number of forms (e.g. "42").
			_n( '%d form imported', '%d forms imported', $forms_total, 'mainwp-child-reports' ),
			array(
				'count'  => $forms_total,
				'ids'    => $forms_ids,
				'titles' => $forms_titles,
			),
			null,
			'export',
			'imported'
		);
	}

    /**
     * GravityForms export separator callback.
     *
     * @param $dummy
     * @param $form_id Form ID.
     *
     * @return mixed
     */
    public function callback_gform_export_separator( $dummy, $form_id ) {
		$form = $this->get_form( $form_id );

		$this->log(
			// translators: Placeholder refers to a form title (e.g. "Contact Form").
			__( '"%s" form exported', 'mainwp-child-reports' ),
			array(
				'form_title' => $form['title'],
				'form_id'    => $form_id,
			),
			$form_id,
			'export',
			'exported'
		);

		return $dummy;
	}

    /**
     * GravityForms export options callback.
     *
     * @param $dummy
     * @param $forms Forms array.
     * @return mixed
     */
    public function callback_gform_export_options( $dummy, $forms ) {
		$ids    = wp_list_pluck( $forms, 'id' );
		$titles = wp_list_pluck( $forms, 'title' );

		$this->log(
			// translators: Placeholder refers to a number of forms (e.g. "42").
			_n( 'Export process started for %d form', 'Export process started for %d forms', count( $forms ), 'mainwp-child-reports' ),
			array(
				'count'  => count( $forms ),
				'ids'    => $ids,
				'titles' => $titles,
			),
			null,
			'export',
			'imported'
		);

		return $dummy;
	}

    /**
     * GravityForms delete leads callback.
     *
     * @param $lead_id Lead ID.
     */
    public function callback_gform_delete_lead( $lead_id ) {
		$lead = $this->get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
			// translators: Placeholders refer to an ID, and a form title (e.g. "42", "Contact Form").
			__( 'Lead #%1$d from "%2$s" deleted', 'mainwp-child-reports' ),
			array(
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
			),
			$lead_id,
			'entries',
			'deleted'
		);
	}

    /**
     * GravityForms post note added callback.
     *
     * @param string $note_id Note ID.
     * @param string $lead_id Lead ID.
     * @param string $user_id User ID.
     * @param string $user_name User Name.
     * @param string $note Note.
     * @param string $note_type Note type.
     */
    public function callback_gform_post_note_added( $note_id, $lead_id, $user_id, $user_name, $note, $note_type ) {
		unset( $user_id );
		unset( $user_name );
		unset( $note );
		unset( $note_type );

		$lead = \GFFormsModel::get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
			// translators: Placeholders refer to an ID, another ID, and a form title (e.g. "42", "7", "Contact Form").
			__( 'Note #%1$d added to lead #%2$d on "%3$s" form', 'mainwp-child-reports' ),
			array(
				'note_id'    => $note_id,
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
			),
			$note_id,
			'notes',
			'added'
		);
	}

    /**
     * GravityForms pre note deleted callback.
     *
     * @param string $note_id Note ID.
     * @param string $lead_id Lead ID.
     */
    public function callback_gform_pre_note_deleted($note_id, $lead_id ) {
		$lead = $this->get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
			// translators: Placeholders refer to an ID, another ID, and a form title (e.g. "42", "7", "Contact Form")
			__( 'Note #%1$d deleted from lead #%2$d on "%3$s" form', 'mainwp-child-reports' ),
			array(
				'note_id'    => $note_id,
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
			),
			$note_id,
			'notes',
			'deleted'
		);
	}

    /**
     * GravityForm update status callback.
     *
     * @param string $lead_id Lead ID.
     * @param string $status Update status.
     * @param string $prev Trashed status.
     */
    public function callback_gform_update_status( $lead_id, $status, $prev = '' ) {
		$lead = $this->get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		if ( 'active' === $status && 'trash' === $prev ) {
			$status = 'restore';
		}

		$actions = array(
			'active'  => esc_html__( 'activated', 'mainwp-child-reports' ),
			'spam'    => esc_html__( 'marked as spam', 'mainwp-child-reports' ),
			'trash'   => esc_html__( 'trashed', 'mainwp-child-reports' ),
			'restore' => esc_html__( 'restored', 'mainwp-child-reports' ),
		);

		if ( ! isset( $actions[ $status ] ) ) {
			return;
		}

		$this->log(
			sprintf(
				// translators: Placeholders refer to an ID, a status, and a form title (e.g. "42", "activated", "Contact Form").
				__( 'Lead #%1$d %2$s on "%3$s" form', 'mainwp-child-reports' ),
				$lead_id,
				$actions[ $status ],
				$form['title']
			),
			array(
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
				'status'     => $status,
				'prev'       => $prev,
			),
			$lead_id,
			'entries',
			$status
		);
	}

	/**
	 * Callback fired when an entry is read/unread.
	 *
	 * @param  int $lead_id Lead ID.
	 * @param  int $status Read / unread status.
	 * @return void
	 */
	public function callback_gform_update_is_read( $lead_id, $status ) {
		$lead   = $this->get_lead( $lead_id );
		$form   = $this->get_form( $lead['form_id'] );
		$status = ( ! empty( $status ) ) ? esc_html__( 'read', 'mainwp-child-reports' ) : esc_html__( 'unread', 'mainwp-child-reports' );

		$this->log(
			sprintf(
				// translators: Placeholders refer to an ID, a status, and a form title (e.g. "42", "unread", "Contact Form").
				__( 'Entry #%1$d marked as %2$s on form #%3$d ("%4$s")', 'mainwp-child-reports' ),
				$lead_id,
				$status,
				$form['id'],
				$form['title']
			),
			array(
				'lead_id'     => $lead_id,
				'lead_status' => $status,
				'form_id'     => $form['id'],
				'form_title'  => $form['title'],
			),
			$lead_id,
			'entries',
			'updated'
		);
	}

	/**
	 * Callback fired when an entry is starred/unstarred
	 *
	 * @param  int $lead_id Leads ID.
	 * @param  int $status Stared / unstared status.
	 * @return void
	 */
	public function callback_gform_update_is_starred( $lead_id, $status ) {
		$lead   = $this->get_lead( $lead_id );
		$form   = $this->get_form( $lead['form_id'] );
		$status = ( ! empty( $status ) ) ? esc_html__( 'starred', 'mainwp-child-reports' ) : esc_html__( 'unstarred', 'mainwp-child-reports' );
		$action = $status;

		$this->log(
			sprintf(
				// translators: Placeholders refer to an ID, a status, and a form title (e.g. "42", "starred", "Contact Form").
				__( 'Entry #%1$d %2$s on form #%3$d ("%4$s")', 'mainwp-child-reports' ),
				$lead_id,
				$status,
				$form['id'],
				$form['title']
			),
			array(
				'lead_id'     => $lead_id,
				'lead_status' => $status,
				'form_id'     => $form['id'],
				'form_title'  => $form['title'],
			),
			$lead_id,
			'entries',
			$action
		);
	}

	/**
	 * Callback fired when a form is deleted.
	 *
	 * @param  int $form_id Form ID.
	 * @return void
	 */
	public function callback_gform_before_delete_form( $form_id ) {
		$this->log_form_action( $form_id, 'deleted' );
	}

	/**
	 * Callback fired when a form is trashed.
	 *
	 * @param  int $form_id Form ID.
	 * @return void
	 */
	public function callback_gform_post_form_trashed( $form_id ) {
		$this->log_form_action( $form_id, 'trashed' );
	}

	/**
	 * Callback fired when a form is restored.
	 *
	 * @param  int $form_id Form ID.
	 * @return void
	 */
	public function callback_gform_post_form_restored( $form_id ) {
		$this->log_form_action( $form_id, 'untrashed' );
	}

	/**
	 * Callback fired when a form is activated.
	 *
	 * @param  int $form_id Form ID.
	 * @return void
	 */
	public function callback_gform_post_form_activated( $form_id ) {
		$this->log_form_action( $form_id, 'activated' );
	}

	/**
	 * Callback fired when a form is deactivated.
	 *
	 * @param  int $form_id Form ID.
	 * @return void
	 */
	public function callback_gform_post_form_deactivated( $form_id ) {
		$this->log_form_action( $form_id, 'deactivated' );
	}

	/**
	 * Callback fired when a form is duplicated.
	 *
	 * @param  int $form_id Form ID.
	 * @return void
	 */
	public function callback_gform_post_form_duplicated( $form_id ) {
		$this->log_form_action( $form_id, 'duplicated' );
	}

	/**
	 * Callback fired when a form's views are reset.
	 *
	 * @param  int $form_id Form ID.
	 * @return void
	 */
	public function callback_gform_post_form_views_deleted( $form_id ) {
		$this->log_form_action( $form_id, 'views_deleted' );
	}

	/**
	 * Track status change of forms.
	 *
	 * @param int $form_id Form ID.
	 * @param string $action Form action.
	 * @return void
	 */
	public function log_form_action( $form_id, $action ) {
		$form = $this->get_form( $form_id );

		if ( empty( $form ) ) {
			return;
		}

		$actions = array(
			'activated'     => esc_html__( 'Activated', 'mainwp-child-reports' ),
			'deactivated'   => esc_html__( 'Deactivated', 'mainwp-child-reports' ),
			'trashed'       => esc_html__( 'Trashed', 'mainwp-child-reports' ),
			'untrashed'     => esc_html__( 'Restored', 'mainwp-child-reports' ),
			'duplicated'    => esc_html__( 'Duplicated', 'mainwp-child-reports' ),
			'deleted'       => esc_html__( 'Deleted', 'mainwp-child-reports' ),
			'views_deleted' => esc_html__( 'Views Reset', 'mainwp-child-reports' ),
		);

		$this->log(
			sprintf(
				// translators: Placeholders refer to an ID, a form title, and a status (e.g. "42", "Contact Form", "Activated")
				__( 'Form #%1$d ("%2$s") %3$s', 'mainwp-child-reports' ),
				$form_id,
				$form['title'],
				strtolower( $actions[ $action ] )
			),
			array(
				'form_id'     => $form_id,
				'form_title'  => $form['title'],
				'form_status' => strtolower( $action ),
			),
			$form['id'],
			'forms',
			$action
		);
	}

	/**
	 * Helper function to get a single entry.
	 *
	 * @param  int $lead_id Lead ID.
	 * @return array Return Lead array.
	 */
	private function get_lead( $lead_id ) {
		return \GFFormsModel::get_lead( $lead_id );
	}

	/**
	 * Helper function to get a single form.
	 *
	 * @param  int $form_id Form ID.
	 * @return array Return form meta array.
	 */
	private function get_form( $form_id ) {
		return \GFFormsModel::get_form_meta( $form_id );
	}
}
