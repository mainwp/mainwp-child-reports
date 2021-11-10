<?php
/**
 * Highlight Alert type.
 *
 * @package WP_MainWP_Stream
 */

namespace WP_MainWP_Stream;

/**
 * Class Alert_Type_Highlight
 *
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Alert_Type
 */
class Alert_Type_Highlight extends Alert_Type {

	/**
	 * Main JS file script handle.
	 */
	const SCRIPT_HANDLE = 'wp-mainwp-stream-alert-highlight-js';

	/**
	 * Remove Highlight Ajax action label.
	 */
	const REMOVE_ACTION = 'stream_remove_highlight';

	/**
	 * Remove Action nonce name.
	 */
	const REMOVE_ACTION_NONCE = 'stream-remove-highlight';

	/**
	 * Alert type name
	 *
	 * @var string
	 */
	public $name = 'Highlight';

	/**
	 * Alert type slug
	 *
	 * @var string
	 */
	public $slug = 'highlight';

	/**
	 * The single Alert ID.
	 *
	 * @var int|string
	 */
	public $single_alert_id;

	/**
	 * The Plugin
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Alert_Type_Highlight constructor.
	 *
	 * Run each time the class is called.
	 *
	 * @param Plugin $plugin Plugin object.
	 * @return void
	 *
	 * @uses \WP_MainWP_Stream\Alert_Type
	 * @uses \WP_MainWP_Stream\Alert_Type_Highlight
	 */
	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		$this->plugin = $plugin;
		if ( ! is_admin() ) {
			return;
		}
		add_filter(
			'wp_mainwp_stream_record_classes', array(
				$this,
				'post_class',
			), 10, 2
		);
		add_action(
			'admin_enqueue_scripts', array(
				$this,
				'enqueue_scripts',
			)
		);
		
		if ( ! empty( $this->plugin->connectors->connectors ) && is_array( $this->plugin->connectors->connectors ) ) {
			foreach ( $this->plugin->connectors->connectors as $connector ) {
				add_filter(
					'wp_mainwp_stream_action_links_' . $connector->name, array(
						$this,
						'action_link_remove_highlight',
					), 10, 2
				);
			}
		}

		add_filter(
			'wp_mainwp_alerts_save_meta', array(
				$this,
				'add_alert_meta',
			), 10, 2
		);
	}

	/**
	 * Record that the Alert was triggered by a Record.
	 *
	 * In self::post_class() this value is checked so we can determine
	 * if the class should be added to the Record's display.
	 *
	 * @param int|string $record_id Record that triggered alert.
	 * @param array      $recordarr Record details.
	 * @param object     $alert Alert options.
	 *
	 * @return void
	 *
	 * @uses \WP_MainWP_Stream\Alert::update_record_triggered_alerts()
	 *
	 */
	public function alert( $record_id, $recordarr, $alert ) {
		$recordarr['ID']       = $record_id;
		$this->single_alert_id = $alert->ID;
		if ( ! empty( $alert->alert_meta['color'] ) ) {
			$alert_meta = array(
				'highlight_color' => $alert->alert_meta['color'],
			);
			Alert::update_record_triggered_alerts( (object) $recordarr, $this->slug, $alert_meta );
		}
	}

	/**
	 * Displays a settings form for the alert type
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 *
	 * @uses \WP_MainWP_Stream\Form_Generator
	 */
	public function display_fields( $alert ) {
		$alert_meta = array();
		if ( is_object( $alert ) ) {
			$alert_meta = $alert->alert_meta;
		}
		$options = wp_parse_args(
			$alert_meta, array(
				'color' => 'yellow',
			)
		);

		$form = new Form_Generator();
		echo '<span class="wp_mainwp_stream_alert_type_description">' . esc_html__( 'Highlight this alert on the Reports records page.', 'mainwp-child-reports' ) . '</span>';
		echo '<label for="wp_mainwp_stream_highlight_color"><span class="title">' . esc_html__( 'Color', 'mainwp-child-reports' ) . '</span>';
		echo '<span class="input-text-wrap">';
		echo $form->render_field(
			'select', array(
				'name'    => 'wp_mainwp_stream_highlight_color',
				'title'   => esc_attr( __( 'Highlight Color', 'mainwp-child-reports' ) ),
				'options' => $this->get_highlight_options(),
				'value'   => $options['color'],
			)
		); // Xss ok.
		echo '</span></label>';
	}

	/**
	 * Lists available color options for alerts.
	 *
	 * @return array List of highlight color options.
	 */
	public function get_highlight_options() {
		return array(
			'yellow' => __( 'Yellow', 'mainwp-child-reports' ),
			'red'    => __( 'Red', 'mainwp-child-reports' ),
			'green'  => __( 'Green', 'mainwp-child-reports' ),
			'blue'   => __( 'Blue', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Validates and saves form settings for later use.
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function save_fields( $alert ) {
		check_admin_referer( 'save_alert', 'wp_mainwp_alerts_nonce' );

		if ( empty( $_POST['wp_mainwp_stream_highlight_color'] ) ) {
			$alert->alert_meta['color'] = 'yellow';
		}
		$input_color = sanitize_text_field( wp_unslash( $_POST['wp_mainwp_stream_highlight_color'] ) );
		if ( ! array_key_exists( $input_color, $this->get_highlight_options() ) ) {
			$alert->alert_meta['color'] = 'yellow';
		} else {
			$alert->alert_meta['color'] = $input_color;
		}

	}

	/**
	 * Apply highlight to records
	 *
	 * @param array  $classes List of classes being applied to the post.
	 * @param object $record Record data.
	 * @return array New list of classes.
	 */
	public function post_class( $classes, $record ) {
		if ( ! empty( $record->meta['wp_mainwp_alerts_triggered']['highlight']['highlight_color'] ) ) {
			$color = $record->meta['wp_mainwp_alerts_triggered']['highlight']['highlight_color'];
		}

		if ( empty( $color ) || ! is_string( $color ) ) {
			return $classes;
		}
		$classes[] = 'alert-highlight highlight-' . esc_attr( $color ) . ' record-id-' . $record->ID;

		return $classes;
	}

	/**
	 * Maybe add the "Remove Highlight" action link.
	 *
	 * This will appear on highlighted items on
	 * the Record List page.
	 *
	 * This is set to run for all Connectors
	 * in self::__construct().
	 *
	 * @filter wp_mainwp_stream_action_links_{ connector }
	 *
	 * @param array  $actions Action links.
	 * @param object $record A record object.
	 *
	 * @return mixed
	 *
	 * @uses \WP_MainWP_Stream\Alerts::ALERTS_TRIGGERED_META_KEY
	 * @uses \WP_MainWP_Stream\Record
	 */
	public function action_link_remove_highlight( $actions, $record ) {
		$record           = new Record( $record );
		$alerts_triggered = $record->get_meta( Alerts::ALERTS_TRIGGERED_META_KEY, true );
		if ( ! empty( $alerts_triggered[ $this->slug ] ) ) {
			$actions[ __( 'Remove Highlight', 'mainwp-child-reports' ) ] = '#';
		}

		return $actions;
	}

	/**
	 * Enqueue Highlight-specific scripts.
	 *
	 * @param string $page WP admin page.
	 */
	public function enqueue_scripts( $page ) {
		if ( 'settings_page_mainwp-reports-page' === $page ) {
			$min = wp_mainwp_stream_min_suffix();
			wp_register_script( self::SCRIPT_HANDLE, $this->plugin->locations['url'] . 'alerts/js/alert-type-highlight.js', array( 'jquery' ) );

			$exports = array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'removeAction' => self::REMOVE_ACTION,
				'security'     => wp_create_nonce( self::REMOVE_ACTION_NONCE ),
			);

			wp_scripts()->add_data(
				self::SCRIPT_HANDLE,
				'data',
				sprintf( 'var _streamAlertTypeHighlightExports = %s;', wp_json_encode( $exports ) )
			);

			wp_add_inline_script( self::SCRIPT_HANDLE, 'streamAlertTypeHighlight.init();', 'after' );
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}
	}

	/**
	 * Add alert meta if this is a highlight alert
	 *
	 * @param array  $alert_meta The metadata to be inserted for this alert.
	 * @param string $alert_type The type of alert being added or updated.
	 *
	 * @return mixed
	 */
	public function add_alert_meta( $alert_meta, $alert_type ) {
		if ( $this->slug === $alert_type ) {
			$color = wp_mainwp_stream_filter_input( INPUT_POST, 'wp_mainwp_stream_highlight_color' );
			if ( empty( $color ) ) {
				$alert_meta['color'] = 'yellow';
			} else {
				$alert_meta['color'] = $color;
			}
		}

		return $alert_meta;
	}
}
