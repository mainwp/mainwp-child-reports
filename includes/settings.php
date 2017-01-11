<?php
/**
 * Settings class
 *
 * @author X-Team <x-team.com>
 * @author Shady Sharaf <shady@x-team.com>
 */
class MainWP_WP_Stream_Settings {

	const KEY = 'mainwp_wp_stream';
	const NETWORK_KEY = 'mainwp_wp_stream_network';
	const DEFAULTS_KEY = 'mainwp_wp_stream_defaults';
	public static $options = array();
	public static $option_key = '';
	public static $fields = array();
	public static function load() {
		self::$option_key = self::get_option_key();
		self::$options    = self::get_options();

		// Register settings, and fields
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Check if we need to flush rewrites rules
		add_action( 'update_option_' . self::KEY, array( __CLASS__, 'updated_option_trigger_flush_rules' ), 10, 2 );

		// Remove records when records TTL is shortened
		add_action( 'update_option_' . self::KEY, array( __CLASS__, 'updated_option_ttl_remove_records' ), 10, 2 );

		add_filter( 'mainwp_wp_stream_serialized_labels', array( __CLASS__, 'get_settings_translations' ) );

		// Ajax callback function to search users
		add_action( 'wp_ajax_mainwp_stream_get_users', array( __CLASS__, 'get_users' ) );

		// Ajax callback function to search IPs
		add_action( 'wp_ajax_mainwp_stream_get_ips', array( __CLASS__, 'get_ips' ) );
	}

	public static function get_users(){
		if ( ! defined( 'DOING_AJAX' ) || ! current_user_can( MainWP_WP_Stream_Admin::SETTINGS_CAP ) ) {
			return;
		}

		check_ajax_referer( 'stream_get_users', 'nonce' );

		$response = (object) array(
			'status'  => false,
			'message' => esc_html__( 'There was an error in the request', 'mainwp-child-reports' ),
		);

		$search  = ( isset( $_POST['find'] )? wp_unslash( trim( $_POST['find'] ) ) : '' );
		$request = (object) array(
			'find' => $search,
		);

		add_filter( 'user_search_columns', array( __CLASS__, 'add_display_name_search_columns' ), 10, 3 );

		$users = new WP_User_Query(
			array(
				'search' => "*{$request->find}*",
				'search_columns' => array(
					'user_login',
					'user_nicename',
					'user_email',
					'user_url',
				),
				'orderby' => 'display_name',
				'number'  => MainWP_WP_Stream_Admin::PRELOAD_AUTHORS_MAX,
			)
		);

		remove_filter( 'user_search_columns', array( __CLASS__, 'add_display_name_search_columns' ), 10 );

		if ( 0 === $users->get_total() ) {
			wp_send_json_error( $response );
		}

		$response->status  = true;
		$response->message = '';
		$response->users   = array();

		require_once MAINWP_WP_STREAM_INC_DIR . 'class-wp-stream-author.php';

		foreach ( $users->results as $key => $user ) {
			$author = new MainWP_WP_Stream_Author( $user->ID );

			$args = array(
				'id'   => $author->ID,
				'text' => $author->display_name,
			);

			$args['tooltip'] = esc_attr(
				sprintf(
					__( "ID: %d\nUser: %s\nEmail: %s\nRole: %s", 'mainwp-child-reports' ),
					$author->id,
					$author->user_login,
					$author->user_email,
					ucwords( $author->get_role() )
				)
			);

			$args['icon'] = $author->get_avatar_src( 32 );

			$response->users[] = $args;
		}

		if ( empty( $search ) || preg_match( '/wp|cli|system|unknown/i', $search ) ) {
			$author = new MainWP_WP_Stream_Author( 0 );
			$response->users[] = array(
				'id'      => $author->id,
				'text'    => $author->get_display_name(),
				'icon'    => $author->get_avatar_src( 32 ),
				'tooltip' => esc_html__( 'Actions performed by the system when a user is not logged in (e.g. auto site upgrader, or invoking WP-CLI without --user)', 'mainwp-child-reports' ),
			);
		}

		wp_send_json_success( $response );
	}

	public static function get_ips(){
		if ( ! defined( 'DOING_AJAX' ) || ! current_user_can( MainWP_WP_Stream_Admin::SETTINGS_CAP ) ) {
			return;
		}

		check_ajax_referer( 'stream_get_ips', 'nonce' );

		global $wpdb;

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"
					SELECT distinct(`ip`)
					FROM `{$wpdb->mainwp_reports}`
					WHERE `ip` LIKE %s
					ORDER BY inet_aton(`ip`) ASC
					LIMIT %d;
				",
				like_escape( $_POST['find'] ) . '%',
				$_POST['limit']
			)
		);

		wp_send_json_success( $results );
	}

	public static function add_display_name_search_columns( $search_columns, $search, $query ){
		$search_columns[] = 'display_name';

		return $search_columns;
	}

	public static function get_option_key() {
		$option_key = self::KEY;

		$current_page = mainwp_wp_stream_filter_input( INPUT_GET, 'page' );

		if ( ! $current_page ) {
			$current_page = mainwp_wp_stream_filter_input( INPUT_GET, 'action' );
		}

		if ( 'mainwp_wp_stream_default_settings' === $current_page ) {
			$option_key = self::DEFAULTS_KEY;
		}

		if ( 'mainwp_wp_stream_network_settings' === $current_page ) {
			$option_key = self::NETWORK_KEY;
		}

		return apply_filters( 'mainwp_wp_stream_settings_option_key', $option_key );
	}

	public static function get_fields() {                
		if ( empty( self::$fields ) ) {			 
                        if (!class_exists('MainWP_WP_Stream_Admin'))
				require_once MAINWP_WP_STREAM_INC_DIR . 'admin.php';                        
			$branding_text = MainWP_WP_Stream_Admin::get_branding_title();
			$branding_text = !empty($branding_text) ? 'Reset ' . $branding_text . ' Reports Database' : esc_html__( 'Reset MainWP Child Reports Database', 'mainwp-child-reports' );                        
                        $branding_name = !empty($branding_text) ? $branding_text : 'MainWP Child';
                        $chk_label = 'Hide ' . $branding_name . ' and ' . $branding_name . ' Reports from reports';
			$chk_desc = 'If selected, the ' . $branding_name . ' plugin and the ' . $branding_name . ' Reports plugin will be left out from reports for this site.';
                        $hide_child_plugins = get_option('mainwp_creport_hide_child_plugins', 'yes');
                        // to fix can not set default checked checkbox
                        $checkbox_hide_childs = '<tr><th scope="row"><label for="mainwp_creport_hide_child_plugins">' . $chk_label;
                        $checkbox_hide_childs .= '</label></th><td><label><input name="mainwp_creport_hide_child_plugins" id="mainwp_creport_hide_child_plugins" value="1" type="checkbox" ' . ($hide_child_plugins == 'yes' ? 'checked' : '') . '> '; 
                        $checkbox_hide_childs .= '</label><p class="description">' . $chk_desc . '.</p></td></tr>';
                        
			self::$fields = array(
				'general' => array(
					'title'  => esc_html__( 'General', 'default' ),
					'fields' => array(						
						array(
							'name'        => 'records_ttl',
							'title'       => esc_html__( 'Keep Records for', 'mainwp-child-reports' ),
							'type'        => 'number',
							'class'       => 'small-text',
							'desc'        => esc_html__( 'Maximum number of days to keep activity records. Leave blank to keep records forever.', 'mainwp-child-reports' ),
							'default'     => 180,
							'after_field' => esc_html__( 'days', 'mainwp-child-reports' ),
						),
                                                array(
							'name'        => 'period_of_time',
							'title'       => esc_html__( 'Minimum time between posts/pages update reports', 'mainwp-child-reports' ),
							'type'        => 'select',
							'choices'       => array( '0' => '0', '30' => '30', '60' => '60', '90' => '90', '120' => '120'),
							'desc'        => '',
                                                        'default'     => 30, 
							'current_value'     => array( '30' ),
							'after_field' => esc_html__( 'minutes', 'mainwp-child-reports' ) . $checkbox_hide_childs, // to add checkbox
						),                                                
						array(
							'name'        => 'delete_all_records',
							'title'       => 'Reset ' . $branding_name . ' Reports Database',
							'type'        => 'link',
							'href'        => add_query_arg(
								array(
									'action'          => 'mainwp_wp_stream_reset',
									'mainwp_wp_stream_nonce' => wp_create_nonce( 'stream_nonce' ),
								),
								admin_url( 'admin-ajax.php' )
							),
							'desc'        => esc_html__( 'Warning: Clicking this will delete all activity records from the database.', 'mainwp-child-reports' ),
							'default'     => 0,
						),
					),
				),
			);			
		}

		return self::$fields;
	}

	public static function get_options() {
		$option_key = self::$option_key;

		$defaults = self::get_defaults( $option_key );

		if ( self::DEFAULTS_KEY === $option_key ) {
			return $defaults;
		}

		return apply_filters(
			'mainwp_wp_stream_options',
			wp_parse_args(
				(array) get_option( $option_key, array() ),
				$defaults
			),
			$option_key
		);
	}

	public static function get_defaults() {
		$fields   = self::get_fields();
		$defaults = array();

		foreach ( $fields as $section_name => $section ) {
			foreach ( $section['fields'] as $field ) {
				$defaults[ $section_name.'_'.$field['name'] ] = isset( $field['default'] )
					? $field['default']
					: null;
			}
		}

		return apply_filters(
			'mainwp_wp_stream_option_defaults',
			wp_parse_args(
				(array) get_site_option( self::DEFAULTS_KEY, array() ),
				$defaults
			)
		);
	}

	public static function register_settings() {
		$sections = self::get_fields();

		register_setting( self::$option_key, self::$option_key, array( 'MainWP_WP_Stream_Settings', 'sanitize_settings' ) );                

		foreach ( $sections as $section_name => $section ) {
			add_settings_section(
				$section_name,
				null,
				'__return_false',
				self::$option_key
			);

			foreach ( $section['fields'] as $field_idx => $field ) {
				if ( ! isset( $field['type'] ) ) { // No field type associated, skip, no GUI
					continue;
				}
				add_settings_field(
					$field['name'],
					$field['title'],
					( isset( $field['callback'] ) ? $field['callback'] : array( __CLASS__, 'output_field' ) ),
					self::$option_key,
					$section_name,
					$field + array(
						'section'   => $section_name,
						'label_for' => sprintf( '%s_%s_%s', self::$option_key, $section_name, $field['name'] ), // xss ok
					)
				);
			}
		}
	}
        
        public static function sanitize_settings( $input ) {
            if (isset($_POST['mainwp_creport_hide_child_plugins'])) {
                update_option('mainwp_creport_hide_child_plugins', 'yes');
            } else {
                update_option('mainwp_creport_hide_child_plugins', 'no');
            }		
            return $input;
	}
  
	public static function updated_option_trigger_flush_rules( $old_value, $new_value ) {
		if ( is_array( $new_value ) && is_array( $old_value ) ) {
			$new_value = ( array_key_exists( 'general_private_feeds', $new_value ) ) ? $new_value['general_private_feeds'] : 0;
			$old_value = ( array_key_exists( 'general_private_feeds', $old_value ) ) ? $old_value['general_private_feeds'] : 0;

			if ( $new_value !== $old_value ) {
				delete_option( 'rewrite_rules' );
			}
		}
	}

	public static function render_field( $field ) {
		$output        = null;
		$type          = isset( $field['type'] ) ? $field['type'] : null;
		$section       = isset( $field['section'] ) ? $field['section'] : null;
		$name          = isset( $field['name'] ) ? $field['name'] : null;
		$class         = isset( $field['class'] ) ? $field['class'] : null;
		$placeholder   = isset( $field['placeholder'] ) ? $field['placeholder'] : null;
		$description   = isset( $field['desc'] ) ? $field['desc'] : null;
		$href          = isset( $field['href'] ) ? $field['href'] : null;
		$after_field   = isset( $field['after_field'] ) ? $field['after_field'] : null;
		$default       = isset( $field['default'] ) ? $field['default'] : null;
		$title         = isset( $field['title'] ) ? $field['title'] : null;
		$nonce         = isset( $field['nonce'] ) ? $field['nonce'] : null;
		$current_value = self::$options[ $section . '_' . $name ];
		$option_key    = self::$option_key;
                
                    
		if ( is_callable( $current_value ) ) {
			$current_value = call_user_func( $current_value );
		}

		if ( ! $type || ! $section || ! $name ) {
			return;
		}

		if ( 'multi_checkbox' === $type
			&& ( empty( $field['choices'] ) || ! is_array( $field['choices'] ) )
		) {
			return;
		}

		switch ( $type ) {
			case 'text':
			case 'number':
				$output = sprintf(
					'<input type="%1$s" name="%2$s[%3$s_%4$s]" id="%2$s_%3$s_%4$s" class="%5$s" placeholder="%6$s" value="%7$s" /> %8$s',
					esc_attr( $type ),
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class ),
					esc_attr( $placeholder ),
					esc_attr( $current_value ),
					$after_field // xss ok
				);
				break;
			case 'checkbox':
				$output = sprintf(
					'<label><input type="checkbox" name="%1$s[%2$s_%3$s]" id="%1$s[%2$s_%3$s]" value="1" %4$s /> %5$s</label>',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					checked( $current_value, 1, false ),
					$after_field // xss ok
				);
				break;
			case 'multi_checkbox':
				$output = sprintf(
					'<div id="%1$s[%2$s_%3$s]"><fieldset>',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				// Fallback if nothing is selected
				$output .= sprintf(
					'<input type="hidden" name="%1$s[%2$s_%3$s][]" value="__placeholder__" />',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				$current_value = (array) $current_value;
				$choices = $field['choices'];
				if ( is_callable( $choices ) ) {
					$choices = call_user_func( $choices );
				}
				foreach ( $choices as $value => $label ) {
					$output .= sprintf(
						'<label>%1$s <span>%2$s</span></label><br />',
						sprintf(
							'<input type="checkbox" name="%1$s[%2$s_%3$s][]" value="%4$s" %5$s />',
							esc_attr( $option_key ),
							esc_attr( $section ),
							esc_attr( $name ),
							esc_attr( $value ),
							checked( in_array( $value, $current_value ), true, false )
						),
						esc_html( $label )
					);
				}
				$output .= '</fieldset></div>';
				break;
			case 'select':                                
                                $current_value = (array) self::$options[ $section . '_' . $name ];                                       
				$default_value = isset( $default['value'] ) ? $default['value'] : '-1';
				$default_name  = isset( $default['name'] ) ? $default['name'] : 'Choose Setting';
                                                                
				$output  = sprintf(
					'<select name="%1$s[%2$s_%3$s]" id="%1$s_%2$s_%3$s">',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				$output .= sprintf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $default_value ),
					selected( in_array( $default_value, $current_value ), true, false ),
					esc_html( $default_name )
				);
				foreach ( $field['choices'] as $value => $label ) {
					$output .= sprintf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $value ),
						selected( in_array( $value, $current_value ), true, false ),
						esc_html( $label )
					);
				}
				$output .= '</select>';
                                $output .= $after_field;
				break;
			case 'file':
				$output = sprintf(
					'<input type="file" name="%1$s[%2$s_%3$s]" id="%1$s_%2$s_%3$s" class="%4$s">',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class )
				);
				break;
			case 'link':
				$output = sprintf(
					'<a id="%1$s_%2$s_%3$s" class="%4$s" href="%5$s">%6$s</a>',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class ),
					esc_attr( $href ),
					esc_attr( $title )
				);
				break;
			case 'select2' :
				if ( ! isset ( $current_value ) ) {
					$current_value = array();
				}

				if ( false !== ( $key = array_search( '__placeholder__', $current_value ) ) ) {
					unset( $current_value[ $key ] );
				}

				$data_values     = array();
				$selected_values = array();
				if ( isset( $field['choices'] ) ) {
					$choices = $field['choices'];
					if ( is_callable( $choices ) ) {
						$param   = ( isset( $field['param'] ) ) ? $field['param'] : null;
						$choices = call_user_func( $choices, $param );
					}
					foreach ( $choices as $key => $value ) {
						$data_values[] = array( 'id' => $key, 'text' => $value );
						if ( in_array( $key, $current_value ) ) {
							$selected_values[] = array( 'id' => $key, 'text' => $value );
						}
					}
					$class .= ' with-source';
				} else {
					foreach ( $current_value as $value ) {
						if ( '__placeholder__' === $value || '' === $value ) {
							continue;
						}
						$selected_values[] = array( 'id' => $value, 'text' => $value );
					}
				}

				$output  = sprintf(
					'<div id="%1$s[%2$s_%3$s]">',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				$output .= sprintf(
					'<input type="hidden" data-values=\'%1$s\' data-selected=\'%2$s\' value="%3$s" class="select2-select %4$s" data-select-placeholder="%5$s-%6$s-select-placeholder" %7$s />',
					esc_attr( json_encode( $data_values ) ),
					esc_attr( json_encode( $selected_values ) ),
					esc_attr( implode( ',', $current_value ) ),
					$class,
					esc_attr( $section ),
					esc_attr( $name ),
					isset( $nonce ) ? sprintf( ' data-nonce="%s"', esc_attr( wp_create_nonce( $nonce ) ) ) : ''
				);
				// to store data with default value if nothing is selected
				$output .= sprintf(
					'<input type="hidden" name="%1$s[%2$s_%3$s][]" class="%2$s-%3$s-select-placeholder" value="__placeholder__" />',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				$output .= '</div>';
				break;
			case 'select2_user_role':
				$current_value = (array)$current_value;
				$data_values   = array();

				if ( isset( $field['choices'] ) ) {
					$choices = $field['choices'];
					if ( is_callable( $choices ) ) {
						$param   = ( isset( $field['param'] ) ) ? $field['param'] : null;
						$choices = call_user_func( $choices, $param );
					}
				} else {
					$choices = array();
				}

				foreach ( $choices as $key => $role ) {
					$args  = array( 'id' => $key, 'text' => $role );
					$users = get_users( array( 'role' => $key ) );
					if ( count( $users ) ) {
						$args['user_count'] = sprintf( _n( '1 user', '%s users', count( $users ), 'mainwp-child-reports' ), count( $users ) );
					}
					$data_values[] = $args;
				}

				$selected_values = array();
				foreach ( $current_value as $value ) {
					if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
						continue;
					}

					if ( '__placeholder__' === $value || '' === $value ) {
						continue;
					}

					if ( is_numeric( $value ) ) {
						$user              = new WP_User( $value );
						$selected_values[] = array( 'id' => $user->ID, 'text' => $user->display_name );
					} else {
						foreach ( $data_values as $role ) {
							if ( $role['id'] !== $value ) {
								continue;
							}
							$selected_values[] = $role;
						}
					}
				}

				$output  = sprintf(
					'<div id="%1$s[%2$s_%3$s]">',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				$output .= sprintf(
					'<input type="hidden" data-values=\'%1$s\' data-selected=\'%2$s\' value="%3$s" class="select2-select %5$s" data-select-placeholder="%4$s-%5$s-select-placeholder" data-nonce="%6$s" />',
					json_encode( $data_values ),
					json_encode( $selected_values ),
					esc_attr( implode( ',', $current_value ) ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( wp_create_nonce( 'stream_get_users' ) )
				);
				// to store data with default value if nothing is selected
				$output .= sprintf(
					'<input type="hidden" name="%1$s[%2$s_%3$s][]" class="%2$s-%3$s-select-placeholder" value="__placeholder__" />',
					esc_attr( $option_key ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				$output .= '</div>';
				break;
		}
		$output .= ! empty( $description ) ? sprintf( '<p class="description">%s</p>', $description /* xss ok */ ) : null;

		return $output;
	}

	public static function output_field( $field ) {
		$method = 'output_' . $field['name'];

		if ( method_exists( __CLASS__, $method ) ) {
			return call_user_func( array( __CLASS__, $method ), $field );
		}

		$output = self::render_field( $field );

		echo $output; // xss okay
	}

	public static function get_roles() {
		$wp_roles = new WP_Roles();
		$roles    = array();

		foreach ( $wp_roles->get_names() as $role => $label ) {
			$roles[ $role ] = translate_user_role( $label );
		}

		return $roles;
	}

	public static function get_connectors() {
		return MainWP_WP_Stream_Connectors::$term_labels['stream_connector'];
	}

	public static function get_default_connectors() {
		return array_keys( MainWP_WP_Stream_Connectors::$term_labels['stream_connector'] );
	}

	public static function get_terms_labels( $column ) {
		$return_labels = array();

		if ( isset ( MainWP_WP_Stream_Connectors::$term_labels[ 'stream_' . $column ] ) ) {
			$return_labels = MainWP_WP_Stream_Connectors::$term_labels[ 'stream_' . $column ];
			ksort( $return_labels );
		}

		return $return_labels;
	}
	
	public static function get_active_connectors() {
		$excluded_connectors = self::get_excluded_by_key( 'connectors' );
		$active_connectors   = array_diff( array_keys( self::get_terms_labels( 'connector' ) ), $excluded_connectors );
		$active_connectors   = wp_list_filter( $active_connectors, array( '__placeholder__' ), 'NOT' );

		return $active_connectors;
	}

	public static function get_excluded_by_key( $column ) {
		$option_name = ( 'authors' === $column || 'roles' === $column ) ? 'exclude_authors_and_roles' : 'exclude_' . $column;

		$excluded_values = ( isset( self::$options[ $option_name ] ) ) ? self::$options[ $option_name ] : array();

		if ( is_callable( $excluded_values ) ) {
			$excluded_values = call_user_func( $excluded_values );
		}

		$excluded_values = wp_list_filter( $excluded_values, array( '__placeholder__' ), 'NOT' );

		if ( 'exclude_authors_and_roles' === $option_name ) {
			// Convert numeric strings to integers
			array_walk( $excluded_values,
				function ( &$value ) {
					if ( is_numeric( $value ) ) {
						$value = absint( $value );
					}
				}
			);

			$filter = ( 'roles' === $column ) ? 'is_string' : 'is_int'; // Author roles are always strings and author ID's are always integers

			$excluded_values = array_values( array_filter( $excluded_values, $filter ) ); // Reset the array keys
		}

		return $excluded_values;
	}

	public static function get_settings_translations( $labels ) {
		if ( ! isset( $labels[ self::KEY ] ) ) {
			$labels[ self::KEY ] = array();
		}

		foreach ( self::get_fields() as $section_slug => $section ) {
			foreach ( $section['fields'] as $field ) {
				$labels[ self::KEY ][ sprintf( '%s_%s', $section_slug, $field['name'] ) ] = $field['title'];
			}
		}

		return $labels;
	}

	public static function updated_option_ttl_remove_records( $old_value, $new_value ) {
		$ttl_before = isset( $old_value['general_records_ttl'] ) ? (int) $old_value['general_records_ttl'] : -1;
		$ttl_after  = isset( $new_value['general_records_ttl'] ) ? (int) $new_value['general_records_ttl'] : -1;

		if ( $ttl_after < $ttl_before ) {
			/**
			 * Action assists in purging when TTL is shortened
			 */
			do_action( 'mainwp_wp_stream_auto_purge' );
		}
	}
}
