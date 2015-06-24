<?php

class MainWP_WP_Stream_Connector_Editor extends MainWP_WP_Stream_Connector {

	public static $name = 'editor';

	public static $actions = array();

	private static $edited_file = array();

	public static function register() {
		parent::register();
		add_action( 'load-theme-editor.php', array( __CLASS__, 'get_edition_data' ) );
		add_filter( 'wp_redirect', array( __CLASS__, 'log_changes' ) );
	}

	public static function get_label() {
		return __( 'Theme Editor', 'mainwp-child-reports' );
	}

	public static function get_action_labels() {
		return array(
			'updated' => __( 'Updated', 'mainwp-child-reports' ),
		);
	}

	public static function get_context_labels() {
		$themes = wp_get_themes();

		$themes_slugs = array_map(
			function( $theme ) {
				return $theme->get_template();
			},
			$themes
		);

		$themes_names = array_map(
			function( $theme ) {
				return (string) $theme;
			},
			$themes
		);

		return array_combine( $themes_slugs, $themes_names );
	}

	public static function get_message() {
		return __( '"%1$s" in "%2$s" updated', 'mainwp-child-reports' );
	}

	public static function action_links( $links, $record ) {
		if ( current_user_can( 'edit_theme_options' ) ) {
			$file_name  = mainwp_wp_stream_get_meta( $record->ID, 'file', true );
			$theme_slug = mainwp_wp_stream_get_meta( $record->ID, 'theme_slug', true );

			if ( '' !== $file_name && '' !== $theme_slug ) {
				$links[ __( 'Edit File', 'mainwp-child-reports' ) ] = admin_url(
					sprintf(
						'theme-editor.php?theme=%s&file=%s',
						$theme_slug,
						$file_name
					)
				);

				$links[ __( 'Edit Theme', 'mainwp-child-reports' ) ] = admin_url(
					sprintf(
						'themes.php?theme=%s',
						$theme_slug
					)
				);
			}
		}

		return $links;
	}

	public static function get_edition_data() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( 'update' !== mainwp_wp_stream_filter_input( INPUT_POST, 'action' ) ) {
			return;
		}

		$theme_slug = mainwp_wp_stream_filter_input( INPUT_POST, 'theme' ) ? mainwp_wp_stream_filter_input( INPUT_POST, 'theme' ) : get_stylesheet();
		$theme      = wp_get_theme( $theme_slug );

		if ( ! $theme->exists() || ( $theme->errors() && 'theme_no_stylesheet' === $theme->errors()->get_error_code() ) ) {
			return;
		}

		$allowed_files              = $theme->get_files( 'php', 1 );
		$style_files                = $theme->get_files( 'css' );
		$allowed_files['style.css'] = $style_files['style.css'];
		$file                       = mainwp_wp_stream_filter_input( INPUT_POST, 'file' );

		if ( empty( $file ) ) {
			$file_name = 'style.css';
			$file_path = $allowed_files['style.css'];
		} else {
			$file_name = $file;
			$file_path = sprintf( '%s/%s', $theme->get_stylesheet_directory(), $file_name );
		}

		$file_contents_before = file_get_contents( $file_path );

		self::$edited_file = compact(
			'file_name',
			'file_path',
			'file_contents_before',
			'theme'
		);
	}

	public static function log_changes( $location ) {
		if ( ! empty( self::$edited_file ) ) {
			$file_contents_after = file_get_contents( self::$edited_file['file_path'] );

			if ( $file_contents_after !== self::$edited_file['file_contents_before'] ) {
				$theme_slug = self::$edited_file['theme']->get_template();
				$properties = array(
					'file'       => self::$edited_file['file_name'],
					'theme_name' => (string) self::$edited_file['theme'],
					'theme_slug' => $theme_slug,
				);

				self::log(
					self::get_message(),
					$properties,
					null,
					array( $theme_slug => 'updated' )
				);
			}
		}

		return $location;
	}

}
