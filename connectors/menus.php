<?php

class MainWP_WP_Stream_Connector_Menus extends MainWP_WP_Stream_Connector {

	public static $name = 'menus';

	public static $actions = array(
		'wp_create_nav_menu',
		'wp_update_nav_menu',
		'delete_nav_menu',
	);

	public static function get_label() {
		return __( 'Menus', 'default' );
	}

	public static function get_action_labels() {
		return array(
			'created'    => __( 'Created', 'mainwp-child-reports' ),
			'updated'    => __( 'Updated', 'mainwp-child-reports' ),
			'deleted'    => __( 'Deleted', 'mainwp-child-reports' ),			
		);
	}

	public static function get_context_labels() {
		$labels = array();
		$menus  = get_terms( 'nav_menu', array( 'hide_empty' => false ) );

		foreach ( $menus as $menu ) {
			$slug            = sanitize_title( $menu->name );
			$labels[ $slug ] = $menu->name;
		}

		return $labels;
	}

	public static function register() {
		parent::register();

		//add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), array( __CLASS__, 'callback_update_option_theme_mods' ), 10, 2 );
	}

	public static function action_links( $links, $record ) {
		if ( $record->object_id ) {
			$menus    = wp_get_nav_menus();
			$menu_ids = wp_list_pluck( $menus, 'term_id' );

			if ( in_array( $record->object_id, $menu_ids ) ) {
				$links[ __( 'Edit Menu', 'mainwp-child-reports' ) ] = admin_url( 'nav-menus.php?action=edit&menu=' . $record->object_id ); // xss ok (@todo fix WPCS rule)
			}
		}

		return $links;
	}

	public static function callback_wp_create_nav_menu( $menu_id, $menu_data ) {
		$name = $menu_data['menu-name'];

		self::log(
			__( 'Created new menu "%s"', 'mainwp-child-reports' ),
			compact( 'name', 'menu_id' ),
			$menu_id,
			array( sanitize_title( $name ) => 'created' )
		);
	}

	public static function callback_wp_update_nav_menu( $menu_id, $menu_data = array() ) {
		if ( empty( $menu_data ) ) {
			return;
		}

		$name = $menu_data['menu-name'];

		self::log(
			_x( 'Updated menu "%s"', 'Menu name', 'mainwp-child-reports' ),
			compact( 'name', 'menu_id', 'menu_data' ),
			$menu_id,
			array( sanitize_title( $name ) => 'updated' )
		);
	}

	public static function callback_delete_nav_menu( $term, $tt_id, $deleted_term ) {
		$name    = $deleted_term->name;
		$menu_id = $term;

		self::log(
			_x( 'Deleted "%s"', 'Menu name', 'mainwp-child-reports' ),
			compact( 'name', 'menu_id' ),
			$menu_id,
			array( sanitize_title( $name ) => 'deleted' )
		);
	}

	

}
