<?php

class MainWP_WP_Stream_Connector_Widgets extends MainWP_WP_Stream_Connector {

	public static $verbose_widget_created_deleted_actions = false;

	public static $name = 'widgets';

	public static $actions = array(
		'update_option_sidebars_widgets',
		'updated_option',
	);

	protected static $customizer_initial_sidebars_widgets = null;

	public static function get_label() {
		return __( 'Widgets', 'default' );
	}

	public static function get_action_labels() {
		return array(
			'added'       => __( 'Added', 'mainwp-child-reports' ),
			'removed'     => __( 'Removed', 'mainwp-child-reports' ),
			'moved'       => __( 'Moved', 'mainwp-child-reports' ),
			'created'     => __( 'Created', 'mainwp-child-reports' ),
			'deleted'     => __( 'Deleted', 'mainwp-child-reports' ),
			'deactivated' => __( 'Deactivated', 'mainwp-child-reports' ),
			'reactivated' => __( 'Reactivated', 'mainwp-child-reports' ),
			'updated'     => __( 'Updated', 'mainwp-child-reports' ),
			'sorted'      => __( 'Sorted', 'mainwp-child-reports' ),
		);
	}

	public static function get_context_labels() {
		global $wp_registered_sidebars;

		$labels = array();

		foreach ( $wp_registered_sidebars as $sidebar ) {
			$labels[ $sidebar['id'] ] = $sidebar['name'];
		}

		$labels['wp_inactive_widgets'] = __( 'Inactive Widgets', 'default' );
		$labels['orphaned_widgets']    = __( 'Orphaned Widgets', 'mainwp-child-reports' );
		$labels['']                    = __( 'Unknown', 'mainwp-child-reports' );

		return $labels;
	}

	public static function action_links( $links, $record ) {
		if ( $sidebar = mainwp_wp_stream_get_meta( $record->ID, 'sidebar_id', true ) ) {
			global $wp_registered_sidebars;

			if ( array_key_exists( $sidebar, $wp_registered_sidebars ) ) {
				$links[ __( 'Edit Widget Area', 'mainwp-child-reports' ) ] = admin_url( 'widgets.php#' . $sidebar ); // xss ok (@todo fix WPCS rule)
			}
		}

		return $links;
	}

	public static function callback_update_option_sidebars_widgets( $old, $new ) {
		// Disable listener if we're switching themes
		if ( did_action( 'after_switch_theme' ) ) {
			return;
		}

		if ( did_action( 'customize_save' ) ) {
			if ( is_null( self::$customizer_initial_sidebars_widgets ) ) {
				self::$customizer_initial_sidebars_widgets = $old;
				add_action( 'customize_save_after', array( __CLASS__, '_callback_customize_save_after' ) );
			}
		} else {
			self::handle_sidebars_widgets_changes( $old, $new );
		}
	}

	public static function _callback_customize_save_after() {
		$old_sidebars_widgets = self::$customizer_initial_sidebars_widgets;
		$new_sidebars_widgets = get_option( 'sidebars_widgets' );

		self::handle_sidebars_widgets_changes( $old_sidebars_widgets, $new_sidebars_widgets );
	}

	protected static function handle_sidebars_widgets_changes( $old, $new ) {
		unset( $old['array_version'] );
		unset( $new['array_version'] );

		if ( $old === $new ) {
			return;
		}

		self::handle_deactivated_widgets( $old, $new );
		self::handle_reactivated_widgets( $old, $new );
		self::handle_widget_removal( $old, $new );
		self::handle_widget_addition( $old, $new );
		self::handle_widget_reordering( $old, $new );
		self::handle_widget_moved( $old, $new );
	}

	static protected function handle_deactivated_widgets( $old, $new ) {
		$new_deactivated_widget_ids = array_diff( $new['wp_inactive_widgets'], $old['wp_inactive_widgets'] );

		foreach ( $new_deactivated_widget_ids as $widget_id ) {
			$sidebar_id = '';

			foreach ( $old as $old_sidebar_id => $old_widget_ids ) {
				if ( in_array( $widget_id, $old_widget_ids ) ) {
					$sidebar_id = $old_sidebar_id;
					break;
				}
			}

			$action       = 'deactivated';
			$name         = self::get_widget_name( $widget_id );
			$title        = self::get_widget_title( $widget_id );
			$labels       = self::get_context_labels();
			$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

			if ( $name && $title ) {
				$message = _x( '%1$s widget named "%2$s" from "%3$s" deactivated', '1: Name, 2: Title, 3: Sidebar Name', 'mainwp-child-reports' );
			} elseif ( $name ) {
				// Empty title, but we have the name
				$message = _x( '%1$s widget from "%3$s" deactivated', '1: Name, 3: Sidebar Name', 'mainwp-child-reports' );
			} elseif ( $title ) {
				// Likely a single widget since no name is available
				$message = _x( 'Unknown widget type named "%2$s" from "%3$s" deactivated', '2: Title, 3: Sidebar Name', 'mainwp-child-reports' );
			} else {
				// Neither a name nor a title are available, so use the widget ID
				$message = _x( '%4$s widget from "%3$s" deactivated', '4: Widget ID, 3: Sidebar Name', 'mainwp-child-reports' );
			}

			$message = sprintf( $message, $name, $title, $sidebar_name, $widget_id );

			self::log(
				$message,
				compact( 'title', 'name', 'widget_id', 'sidebar_id' ),
				null,
				array( 'wp_inactive_widgets' => $action )
			);
		}
	}

	static protected function handle_reactivated_widgets( $old, $new ) {
		$new_reactivated_widget_ids = array_diff( $old['wp_inactive_widgets'], $new['wp_inactive_widgets'] );

		foreach ( $new_reactivated_widget_ids as $widget_id ) {
			$sidebar_id = '';

			foreach ( $new as $new_sidebar_id => $new_widget_ids ) {
				if ( in_array( $widget_id, $new_widget_ids ) ) {
					$sidebar_id = $new_sidebar_id;
					break;
				}
			}

			$action = 'reactivated';
			$name   = self::get_widget_name( $widget_id );
			$title  = self::get_widget_title( $widget_id );

			if ( $name && $title ) {
				$message = _x( '%1$s widget named "%2$s" reactivated', '1: Name, 2: Title', 'mainwp-child-reports' );
			} elseif ( $name ) {
				// Empty title, but we have the name
				$message = _x( '%1$s widget reactivated', '1: Name', 'mainwp-child-reports' );
			} elseif ( $title ) {
				// Likely a single widget since no name is available
				$message = _x( 'Unknown widget type named "%2$s" reactivated', '2: Title', 'mainwp-child-reports' );
			} else {
				// Neither a name nor a title are available, so use the widget ID
				$message = _x( '%3$s widget reactivated', '3: Widget ID', 'mainwp-child-reports' );
			}

			$message = sprintf( $message, $name, $title, $widget_id );

			self::log(
				$message,
				compact( 'title', 'name', 'widget_id', 'sidebar_id' ),
				null,
				array( $sidebar_id => $action )
			);
		}
	}

	static protected function handle_widget_removal( $old, $new ) {
		$all_old_widget_ids = array_unique( call_user_func_array( 'array_merge', $old ) );
		$all_new_widget_ids = array_unique( call_user_func_array( 'array_merge', $new ) );

		$deleted_widget_ids = array_diff( $all_old_widget_ids, $all_new_widget_ids );

		foreach ( $deleted_widget_ids as $widget_id ) {
			$sidebar_id = '';

			foreach ( $old as $old_sidebar_id => $old_widget_ids ) {
				if ( in_array( $widget_id, $old_widget_ids ) ) {
					$sidebar_id = $old_sidebar_id;
					break;
				}
			}

			$action       = 'removed';
			$name         = self::get_widget_name( $widget_id );
			$title        = self::get_widget_title( $widget_id );
			$labels       = self::get_context_labels();
			$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

			if ( $name && $title ) {
				$message = _x( '%1$s widget named "%2$s" removed from "%3$s"', '1: Name, 2: Title, 3: Sidebar Name', 'mainwp-child-reports' );
			} elseif ( $name ) {
				// Empty title, but we have the name
				$message = _x( '%1$s widget removed from "%3$s"', '1: Name, 3: Sidebar Name', 'mainwp-child-reports' );
			} elseif ( $title ) {
				// Likely a single widget since no name is available
				$message = _x( 'Unknown widget type named "%2$s" removed from "%3$s"', '2: Title, 3: Sidebar Name', 'mainwp-child-reports' );
			} else {
				// Neither a name nor a title are available, so use the widget ID
				$message = _x( '%4$s widget removed from "%3$s"', '4: Widget ID, 3: Sidebar Name', 'mainwp-child-reports' );
			}

			$message = sprintf( $message, $name, $title, $sidebar_name, $widget_id );

			self::log(
				$message,
				compact( 'widget_id', 'sidebar_id' ),
				null,
				array( $sidebar_id => $action )
			);
		}
	}

	static protected function handle_widget_addition( $old, $new ) {
		$all_old_widget_ids = array_unique( call_user_func_array( 'array_merge', $old ) );
		$all_new_widget_ids = array_unique( call_user_func_array( 'array_merge', $new ) );
		$added_widget_ids   = array_diff( $all_new_widget_ids, $all_old_widget_ids );

		foreach ( $added_widget_ids as $widget_id ) {
			$sidebar_id = '';

			foreach ( $new as $new_sidebar_id => $new_widget_ids ) {
				if ( in_array( $widget_id, $new_widget_ids ) ) {
					$sidebar_id = $new_sidebar_id;
					break;
				}
			}

			$action       = 'added';
			$name         = self::get_widget_name( $widget_id );
			$title        = self::get_widget_title( $widget_id );
			$labels       = self::get_context_labels();
			$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

			if ( $name && $title ) {
				$message = _x( '%1$s widget named "%2$s" added to "%3$s"', '1: Name, 2: Title, 3: Sidebar Name', 'mainwp-child-reports' );
			} elseif ( $name ) {
				// Empty title, but we have the name
				$message = _x( '%1$s widget added to "%3$s"', '1: Name, 3: Sidebar Name', 'mainwp-child-reports' );
			} elseif ( $title ) {
				// Likely a single widget since no name is available
				$message = _x( 'Unknown widget type named "%2$s" added to "%3$s"', '2: Title, 3: Sidebar Name', 'mainwp-child-reports' );
			} else {
				// Neither a name nor a title are available, so use the widget ID
				$message = _x( '%4$s widget added to "%3$s"', '4: Widget ID, 3: Sidebar Name', 'mainwp-child-reports' );
			}

			$message = sprintf( $message, $name, $title, $sidebar_name, $widget_id );

			self::log(
				$message,
				compact( 'widget_id', 'sidebar_id' ), // @todo Do we care about sidebar_id in meta if it is already context? But there is no 'context' for what the context signifies
				null,
				array( $sidebar_id => $action )
			);
		}
	}

	static protected function handle_widget_reordering( $old, $new ) {
		$all_sidebar_ids = array_intersect( array_keys( $old ), array_keys( $new ) );

		foreach ( $all_sidebar_ids as $sidebar_id ) {
			if ( $old[ $sidebar_id ] === $new[ $sidebar_id ] ) {
				continue;
			}

			// Use intersect to ignore widget additions and removals
			$all_widget_ids       = array_unique( array_merge( $old[ $sidebar_id ], $new[ $sidebar_id ] ) );
			$common_widget_ids    = array_intersect( $old[ $sidebar_id ], $new[ $sidebar_id ] );
			$uncommon_widget_ids  = array_diff( $all_widget_ids, $common_widget_ids );
			$new_widget_ids       = array_values( array_diff( $new[ $sidebar_id ], $uncommon_widget_ids ) );
			$old_widget_ids       = array_values( array_diff( $old[ $sidebar_id ], $uncommon_widget_ids ) );
			$widget_order_changed = ( $new_widget_ids !== $old_widget_ids );

			if ( $widget_order_changed ) {
				$labels         = self::get_context_labels();
				$sidebar_name   = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;
				$old_widget_ids = $old[ $sidebar_id ];
				$message        = _x( 'Widgets reordered in "%s"', 'Sidebar name', 'mainwp-child-reports' );
				$message        = sprintf( $message, $sidebar_name );

				self::log(
					$message,
					compact( 'sidebar_id', 'old_widget_ids' ),
					null,
					array( $sidebar_id => 'sorted' )
				);
			}
		}

	}

	static protected function handle_widget_moved( $old, $new ) {
		$all_sidebar_ids = array_intersect( array_keys( $old ), array_keys( $new ) );

		foreach ( $all_sidebar_ids as $new_sidebar_id ) {
			if ( $old[ $new_sidebar_id ] === $new[ $new_sidebar_id ] ) {
				continue;
			}

			$new_widget_ids = array_diff( $new[ $new_sidebar_id ], $old[ $new_sidebar_id ] );

			foreach ( $new_widget_ids as $widget_id ) {
				// Now find the sidebar that the widget was originally located in, as long it is not wp_inactive_widgets
				$old_sidebar_id = null;
				foreach ( $old as $sidebar_id => $old_widget_ids ) {
					if ( in_array( $widget_id, $old_widget_ids ) ) {
						$old_sidebar_id = $sidebar_id;
						break;
					}
				}

				if ( ! $old_sidebar_id || 'wp_inactive_widgets' === $old_sidebar_id || 'wp_inactive_widgets' === $new_sidebar_id ) {
					continue;
				}

				assert( $old_sidebar_id !== $new_sidebar_id );

				$name             = self::get_widget_name( $widget_id );
				$title            = self::get_widget_title( $widget_id );
				$labels           = self::get_context_labels();
				$old_sidebar_name = isset( $labels[ $old_sidebar_id ] ) ? $labels[ $old_sidebar_id ] : $old_sidebar_id;
				$new_sidebar_name = isset( $labels[ $new_sidebar_id ] ) ? $labels[ $new_sidebar_id ] : $new_sidebar_id;

				if ( $name && $title ) {
					$message = _x( '%1$s widget named "%2$s" moved from "%4$s" to "%5$s"', '1: Name, 2: Title, 4: Old Sidebar Name, 5: New Sidebar Name', 'mainwp-child-reports' );
				} elseif ( $name ) {
					// Empty title, but we have the name
					$message = _x( '%1$s widget moved from "%4$s" to "%5$s"', '1: Name, 4: Old Sidebar Name, 5: New Sidebar Name', 'mainwp-child-reports' );
				} elseif ( $title ) {
					// Likely a single widget since no name is available
					$message = _x( 'Unknown widget type named "%2$s" moved from "%4$s" to "%5$s"', '2: Title, 4: Old Sidebar Name, 5: New Sidebar Name', 'mainwp-child-reports' );
				} else {
					// Neither a name nor a title are available, so use the widget ID
					$message = _x( '%3$s widget moved from "%4$s" to "%5$s"', '3: Widget ID, 4: Old Sidebar Name, 5: New Sidebar Name', 'mainwp-child-reports' );
				}

				$message    = sprintf( $message, $name, $title, $widget_id, $old_sidebar_name, $new_sidebar_name );
				$sidebar_id = $new_sidebar_id;

				self::log(
					$message,
					compact( 'widget_id', 'sidebar_id', 'old_sidebar_id' ),
					null,
					array( $sidebar_id => 'moved', )
				);
			}
		}

	}

	public static function callback_updated_option( $option_name, $old_value, $new_value ) {
		if ( ! preg_match( '/^widget_(.+)$/', $option_name, $matches ) || ! is_array( $new_value ) ) {
			return;
		}

		$is_multi       = ! empty( $new_value['_multiwidget'] );
		$widget_id_base = $matches[1];

		$creates = array();
		$updates = array();
		$deletes = array();

		if ( $is_multi ) {
			$widget_id_format = "$widget_id_base-%d";

			unset( $new_value['_multiwidget'] );
			unset( $old_value['_multiwidget'] );

			
			$created_widget_numbers = array_diff( array_keys( $new_value ), array_keys( $old_value ) );

			foreach ( $created_widget_numbers as $widget_number ) {
				$instance     = $new_value[ $widget_number ];
				$widget_id    = sprintf( $widget_id_format, $widget_number );
				$name         = self::get_widget_name( $widget_id );
				$title        = ! empty( $instance['title'] ) ? $instance['title'] : null;
				$sidebar_id   = self::get_widget_sidebar_id( $widget_id ); // @todo May not be assigned yet

				$creates[] = compact( 'name', 'title', 'widget_id', 'sidebar_id', 'instance' );
			}

			$updated_widget_numbers = array_intersect( array_keys( $old_value ), array_keys( $new_value ) );

			foreach ( $updated_widget_numbers as $widget_number ) {
				$new_instance = $new_value[ $widget_number ];
				$old_instance = $old_value[ $widget_number ];

				if ( $old_instance !== $new_instance ) {
					$widget_id    = sprintf( $widget_id_format, $widget_number );
					$name         = self::get_widget_name( $widget_id );
					$title        = ! empty( $new_instance['title'] ) ? $new_instance['title'] : null;
					$sidebar_id   = self::get_widget_sidebar_id( $widget_id );
					$labels       = self::get_context_labels();
					$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

					$updates[] = compact( 'name', 'title', 'widget_id', 'sidebar_id', 'old_instance', 'sidebar_name' );
				}
			}

			$deleted_widget_numbers = array_diff( array_keys( $old_value ), array_keys( $new_value ) );

			foreach ( $deleted_widget_numbers as $widget_number ) {
				$instance     = $old_value[ $widget_number ];
				$widget_id    = sprintf( $widget_id_format, $widget_number );
				$name         = self::get_widget_name( $widget_id );
				$title        = ! empty( $instance['title'] ) ? $instance['title'] : null;
				$sidebar_id   = self::get_widget_sidebar_id( $widget_id ); // @todo May not be assigned anymore

				$deletes[] = compact( 'name', 'title', 'widget_id', 'sidebar_id', 'instance' );
			}
		} else {
			$widget_id    = $widget_id_base;
			$name         = $widget_id; // There aren't names available for single widgets
			$title        = ! empty( $new_value['title'] ) ? $new_value['title'] : null;
			$sidebar_id   = self::get_widget_sidebar_id( $widget_id );
			$old_instance = $old_value;
			$labels       = self::get_context_labels();
			$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

			$updates[] = compact( 'widget_id', 'title', 'name', 'sidebar_id', 'old_instance', 'sidebar_name' );
		}

		foreach ( $updates as $update ) {
			if ( $update['name'] && $update['title'] ) {
				$message = _x( '%1$s widget named "%2$s" in "%3$s" updated', '1: Name, 2: Title, 3: Sidebar Name', 'mainwp-child-reports' );
			} elseif ( $update['name'] ) {
				// Empty title, but we have the name
				$message = _x( '%1$s widget in "%3$s" updated', '1: Name, 3: Sidebar Name', 'mainwp-child-reports' );
			} elseif ( $update['title'] ) {
				// Likely a single widget since no name is available
				$message = _x( 'Unknown widget type named "%2$s" in "%3$s" updated', '2: Title, 3: Sidebar Name', 'mainwp-child-reports' );
			} else {
				// Neither a name nor a title are available, so use the widget ID
				$message = _x( '%4$s widget in "%3$s" updated', '4: Widget ID, 3: Sidebar Name', 'mainwp-child-reports' );
			}

			$message  = sprintf( $message, $update['name'], $update['title'], $update['sidebar_name'], $update['widget_id'] );
			$contexts = array( $update['sidebar_id'] => 'updated' );

			unset( $update['title'], $update['name'] );

			self::log( $message, $update, null, $contexts );
		}

		if ( self::$verbose_widget_created_deleted_actions ) {
			foreach ( $creates as $create ) {
				if ( $create['name'] && $create['title'] ) {
					$message = _x( '%1$s widget named "%2$s" created', '1: Name, 2: Title', 'mainwp-child-reports' );
				} elseif ( $create['name'] ) {
					// Empty title, but we have the name
					$message = _x( '%1$s widget created', '1: Name', 'mainwp-child-reports' );
				} elseif ( $create['title'] ) {
					// Likely a single widget since no name is available
					$message = _x( 'Unknown widget type named "%2$s" created', '2: Title', 'mainwp-child-reports' );
				} else {
					// Neither a name nor a title are available, so use the widget ID
					$message = _x( '%3$s widget created', '3: Widget ID', 'mainwp-child-reports' );
				}

				$message  = sprintf( $message, $create['name'], $create['title'], $create['widget_id'] );
				$contexts = array( $create['sidebar_id'] => 'created' );

				unset( $create['title'], $create['name'] );

				self::log( $message, $create, null, $contexts );
			}

			foreach ( $deletes as $delete ) {
				if ( $delete['name'] && $delete['title'] ) {
					$message = _x( '%1$s widget named "%2$s" deleted', '1: Name, 2: Title', 'mainwp-child-reports' );
				} elseif ( $delete['name'] ) {
					// Empty title, but we have the name
					$message = _x( '%1$s widget deleted', '1: Name', 'mainwp-child-reports' );
				} elseif ( $delete['title'] ) {
					// Likely a single widget since no name is available
					$message = _x( 'Unknown widget type named "%2$s" deleted', '2: Title', 'mainwp-child-reports' );
				} else {
					// Neither a name nor a title are available, so use the widget ID
					$message = _x( '%3$s widget deleted', '3: Widget ID', 'mainwp-child-reports' );
				}

				$message  = sprintf( $message, $delete['name'], $delete['title'], $delete['widget_id'] );
				$contexts = array( $delete['sidebar_id'] => 'deleted' );

				unset( $delete['title'], $delete['name'] );

				self::log( $message, $delete, null, $contexts );
			}
		}
	}

	public static function get_widget_title( $widget_id ) {
		$instance = self::get_widget_instance( $widget_id );
		return ! empty( $instance['title'] ) ? $instance['title'] : null;
	}

	public static function get_widget_name( $widget_id ) {
		$widget_obj = self::get_widget_object( $widget_id );
		return $widget_obj ? $widget_obj->name : null;
	}

	public static function parse_widget_id( $widget_id ) {
		if ( preg_match( '/^(.+)-(\d+)$/', $widget_id, $matches ) ) {
			return array(
				'id_base'       => $matches[1],
				'widget_number' => intval( $matches[2] ),
			);
		} else {
			return null;
		}
	}

	public static function get_widget_object( $widget_id ) {
		global $wp_widget_factory;

		$parsed_widget_id = self::parse_widget_id( $widget_id );

		if ( ! $parsed_widget_id ) {
			return null;
		}

		$id_base = $parsed_widget_id['id_base'];

		$id_base_to_widget_class_map = array_combine(
			wp_list_pluck( $wp_widget_factory->widgets, 'id_base' ),
			array_keys( $wp_widget_factory->widgets )
		);

		if ( ! isset( $id_base_to_widget_class_map[ $id_base ] ) ) {
			return null;
		}

		return $wp_widget_factory->widgets[ $id_base_to_widget_class_map[ $id_base ] ];
	}

	public static function get_widget_instance( $widget_id ) {
		$instance         = null;
		$parsed_widget_id = self::parse_widget_id( $widget_id );
		$widget_obj       = self::get_widget_object( $widget_id );

		if ( $widget_obj && $parsed_widget_id ) {
			$settings     = $widget_obj->get_settings();
			$multi_number = $parsed_widget_id['widget_number'];

			if ( isset( $settings[ $multi_number ] ) && ! empty( $settings[ $multi_number ]['title'] ) ) {
				$instance = $settings[ $multi_number ];
			}
		} else {
			// Single widgets, try our best guess at the option used
			$potential_instance = get_option( "widget_{$widget_id}" );

			if ( ! empty( $potential_instance ) && ! empty( $potential_instance['title'] ) ) {
				$instance = $potential_instance;
			}
		}

		return $instance;
	}

	public static function get_sidebars_widgets() {
		return apply_filters( 'sidebars_widgets', get_option( 'sidebars_widgets', array() ) );
	}

	public static function get_widget_sidebar_id( $widget_id ) {
		$sidebars_widgets = self::get_sidebars_widgets();

		unset( $sidebars_widgets['array_version'] );

		foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {
			if ( in_array( $widget_id, $widget_ids ) ) {
				return $sidebar_id;
			}
		}

		return 'orphaned_widgets';
	}

}
