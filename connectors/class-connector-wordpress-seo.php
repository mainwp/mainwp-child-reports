<?php
/** Yoast SEO Connector. */
namespace WP_MainWP_Stream;

/**
 * Class Connector_WordPress_SEO
 *
 * @package WP_MainWP_Stream
 *
 * @uses \WP_MainWP_Stream\Connector
 */
class Connector_WordPress_SEO extends Connector {

	/** @var string Connector slug. */
	public $name = 'wordpressseo';

	/** @const string Holds tracked plugin minimum version required. */
	const PLUGIN_MIN_VERSION = '1.5.3.3';

	/** @var  Actions registered for this connector. */
	public $actions = array(
		'wpseo_handle_import',
		'wpseo_import',
		'seo_page_wpseo_files',
		'added_post_meta',
		'updated_post_meta',
		'deleted_post_meta',
	);

	/** @var array Tracking registered Settings, with overridden data */
	public $option_groups = array();

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not.
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		if ( defined( 'WPSEO_VERSION' ) && version_compare( WPSEO_VERSION, self::PLUGIN_MIN_VERSION, '>=' ) ) {
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
		return esc_html_x( 'WordPress SEO', 'wordpress-seo', 'mainwp-child-reports' );
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array Action label translations.
	 */
	public function get_action_labels() {
		return array(
			'created'  => esc_html_x( 'Created', 'wordpress-seo', 'mainwp-child-reports' ),
			'updated'  => esc_html_x( 'Updated', 'wordpress-seo', 'mainwp-child-reports' ),
			'added'    => esc_html_x( 'Added', 'wordpress-seo', 'mainwp-child-reports' ),
			'deleted'  => esc_html_x( 'Deleted', 'wordpress-seo', 'mainwp-child-reports' ),
			'exported' => esc_html_x( 'Exported', 'wordpress-seo', 'mainwp-child-reports' ),
			'imported' => esc_html_x( 'Imported', 'wordpress-seo', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array Context label translations.
	 */
	public function get_context_labels() {
		return array(
			'wpseo_dashboard'               => esc_html_x( 'Dashboard', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_titles'                  => _x( 'Titles &amp; Metas', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_social'                  => esc_html_x( 'Social', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_xml'                     => esc_html_x( 'XML Sitemaps', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_permalinks'              => esc_html_x( 'Permalinks', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_internal-links'          => esc_html_x( 'Internal Links', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_advanced'                => esc_html_x( 'Advanced', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_rss'                     => esc_html_x( 'RSS', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_import'                  => esc_html_x( 'Import & Export', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_bulk-title-editor'       => esc_html_x( 'Bulk Title Editor', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_bulk-description-editor' => esc_html_x( 'Bulk Description Editor', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_files'                   => esc_html_x( 'Files', 'wordpress-seo', 'mainwp-child-reports' ),
			'wpseo_meta'                    => esc_html_x( 'Content', 'wordpress-seo', 'mainwp-child-reports' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen.
	 *
	 * @filter wp_mainwp_stream_action_links_{connector}.
	 *
	 * @param array  $links  Previous links registered.
	 * @param Record $record Stream record.
	 *
	 * @return array Action links.
	 *
	 * @uses \WP_MainWP_Stream\Connector_Posts
	 */
	public function action_links( $links, $record ) {
		// Options
		$option = $record->get_meta( 'option', true );
		if ( $option ) {
			$key = $record->get_meta( 'option_key', true );

			$links[ esc_html__( 'Edit', 'mainwp-child-reports' ) ] = add_query_arg(
				array(
					'page' => $record->context,
				),
				admin_url( 'admin.php' )
			) . '#stream-highlight-' . esc_attr( $key );
		} elseif ( 'wpseo_files' === $record->context ) {
			$links[ esc_html__( 'Edit', 'mainwp-child-reports' ) ] = add_query_arg(
				array(
					'page' => $record->context,
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'wpseo_meta' === $record->context ) {
			$post = get_post( $record->object_id );

			if ( $post ) {
				$posts_connector = new Connector_Posts();
				$post_type_name  = $posts_connector->get_post_type_name( get_post_type( $post->ID ) );

				if ( 'trash' === $post->post_status ) {
					$untrash = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'untrash',
								'post'   => $post->ID,
							),
							admin_url( 'post.php' )
						),
						sprintf( 'untrash-post_%d', $post->ID )
					);

					$delete = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'delete',
								'post'   => $post->ID,
							),
							admin_url( 'post.php' )
						),
						sprintf( 'delete-post_%d', $post->ID )
					);

					// translators: Placeholder refers to a post type singular name (e.g. "Post")
					$links[ sprintf( esc_html_x( 'Restore %s', 'Post type singular name', 'mainwp-child-reports' ), $post_type_name ) ] = $untrash;
					// translators: Placeholder refers to a post type singular name (e.g. "Post")
					$links[ sprintf( esc_html_x( 'Delete %s Permenantly', 'Post type singular name', 'mainwp-child-reports' ), $post_type_name ) ] = $delete;
				} else {
					// translators: Placeholder refers to a post type singular name (e.g. "Post")
					$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'mainwp-child-reports' ), $post_type_name ) ] = get_edit_post_link( $post->ID );

					$view_link = get_permalink( $post->ID );
					if ( $view_link ) {
						$links[ esc_html__( 'View', 'mainwp-child-reports' ) ] = $view_link;
					}

					$revision_id = $record->get_meta( 'revision_id', true );
					if ( $revision_id ) {
						$links[ esc_html__( 'Revision', 'mainwp-child-reports' ) ] = get_edit_post_link( $revision_id );
					}
				}
			}
		}

		return $links;
	}

	/**
	 * Register with parent class.
	 *
	 * @uses \WP_MainWP_Stream\Connector::register()
	 */
	public function register() {
		if ( is_network_admin() && ! is_plugin_active_for_network( 'wordpress-seo/wordpress-seo-main.php' ) ) {
			return;
		}
		parent::register();

		foreach ( \WPSEO_Options::$options as $class ) {
			/* @var $class WPSEO_Options */
			$this->option_groups[ $class::get_instance()->group_name ] = array(
				'class' => $class,
				'name'  => $class::get_instance()->option_name,
			);
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter( 'wp_mainwp_stream_log_data', array( $this, 'log_override' ) );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Page hook.
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 0 === strpos( $hook, 'seo_page_' ) ) {
			$stream = wp_mainwp_stream_get_instance();
			$src    = $stream->locations['url'] . '/ui/js/wpseo-admin.js';
			wp_enqueue_script( 'stream-connector-wpseo', $src, array( 'jquery' ), $stream->get_version() );
		}
	}

	/**
	 * Track importing settings from other plugins.
	 */
	public function callback_wpseo_handle_import() {
		$imports = array(
			'importheadspace'   => esc_html__( 'HeadSpace2', 'mainwp-child-reports' ), // type = checkbox
			'importaioseo'      => esc_html__( 'All-in-One SEO', 'mainwp-child-reports' ), // type = checkbox
			'importaioseoold'   => esc_html__( 'OLD All-in-One SEO', 'mainwp-child-reports' ), // type = checkbox
			'importwoo'         => esc_html__( 'WooThemes SEO framework', 'mainwp-child-reports' ), // type = checkbox
			'importrobotsmeta'  => esc_html__( 'Robots Meta (by Yoast)', 'mainwp-child-reports' ), // type = checkbox
			'importrssfooter'   => esc_html__( 'RSS Footer (by Yoast)', 'mainwp-child-reports' ), // type = checkbox
			'importbreadcrumbs' => esc_html__( 'Yoast Breadcrumbs', 'mainwp-child-reports' ), // type = checkbox
		);

		$opts = wp_mainwp_stream_filter_input( INPUT_POST, 'wpseo' );

		foreach ( $imports as $key => $name ) {
			if ( isset( $opts[ $key ] ) ) {
				$this->log(
					sprintf(
						// translators: Placeholders refer to an import method, and an extra string (sometimes blank) (e.g. "HeadSpace2", ", and deleted old data")
						__( 'Imported settings from %1$s%2$s', 'mainwp-child-reports' ),
						$name,
						isset( $opts['deleteolddata'] ) ? esc_html__( ', and deleted old data', 'mainwp-child-reports' ) : ''
					),
					array(
						'key'           => $key,
						'deleteolddata' => isset( $opts['deleteolddata'] ),
					),
					null,
					'wpseo_import',
					'imported'
				);
			}
		}
	}

	/**
	 * Yoast SEO import callback.
	 */
	public function callback_wpseo_import() {
		$opts = wp_mainwp_stream_filter_input( INPUT_POST, 'wpseo' );

		if ( wp_mainwp_stream_filter_input( INPUT_POST, 'wpseo_export' ) ) {
			$this->log(
				sprintf(
					// translators: Placeholder refers to an extra string (sometimes blank) (e.g. ", including taxonomy meta")
					__( 'Exported settings%s', 'mainwp-child-reports' ),
					isset( $opts['include_taxonomy_meta'] ) ? esc_html__( ', including taxonomy meta', 'mainwp-child-reports' ) : ''
				),
				array(
					'include_taxonomy_meta' => isset( $opts['include_taxonomy_meta'] ),
				),
				null,
				'wpseo_import',
				'exported'
			);
		} elseif ( isset( $_FILES['settings_import_file']['name'] ) ) { // phpcs: input var okay
			$this->log(
				sprintf(
					// translators: Placeholder refers to a filename (e.g. "test.xml")
					__( 'Tried importing settings from "%s"', 'mainwp-child-reports' ),
					sanitize_text_field( wp_unslash( $_FILES['settings_import_file']['name'] ) ) // phpcs: input var okay
				),
				array(
					'file' => sanitize_text_field( wp_unslash( $_FILES['settings_import_file']['name'] ) ), // phpcs: input var okay
				),
				null,
				'wpseo_import',
				'exported'
			);
		}
	}

	/**
	 * Yoast SEO files page callback.
	 */
	public function callback_seo_page_wpseo_files() {
		if ( wp_mainwp_stream_filter_input( INPUT_POST, 'create_robots' ) ) {
			$message = esc_html__( 'Tried creating robots.txt file', 'mainwp-child-reports' );
		} elseif ( wp_mainwp_stream_filter_input( INPUT_POST, 'submitrobots' ) ) {
			$message = esc_html__( 'Tried updating robots.txt file', 'mainwp-child-reports' );
		} elseif ( wp_mainwp_stream_filter_input( INPUT_POST, 'submithtaccess' ) ) {
			$message = esc_html__( 'Tried updating htaccess file', 'mainwp-child-reports' );
		}

		if ( isset( $message ) ) {
			$this->log(
				$message,
				array(),
				null,
				'wpseo_files',
				'updated'
			);
		}
	}

	/**
	 * Record Yoast SEO added post meta log.
	 *
	 * @param string $meta_id Meta ID.
	 * @param string $object_id Object ID.
	 * @param string $meta_key Meta Key.
	 * @param string $meta_value Meta value.
	 */
	public function callback_added_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		$this->meta( $object_id, $meta_key, $meta_value );
	}

	/**
	 * Record Yoast SEO update post meta log.
	 *
	 * @param string $meta_id Meta ID.
	 * @param string $object_id Object ID.
	 * @param string $meta_key Meta Key.
	 * @param string $meta_value Meta value.
	 */
	public function callback_updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		$this->meta( $object_id, $meta_key, $meta_value );
	}

	/**
	 * Record Yoast SEO delete posts meta log.
	 *
	 * @param string $meta_id Meta ID.
	 * @param string $object_id Object ID.
	 * @param string $meta_key Meta Key.
	 * @param string $meta_value Meta value.
	 */
	public function callback_deleted_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		$this->meta( $object_id, $meta_key, $meta_value );
	}

	/**
	 * Record Yoast SEO meta log.
	 *
	 * @param string $object_id Object ID.
	 * @param string $meta_key Meta Key.
	 * @param string $meta_value Meta value.
	 */
	private function meta( $object_id, $meta_key, $meta_value ) {
		//compatible method.
	}

	/**
	 * Override connector log for our own Settings / Actions.
	 *
	 * @param array $data Log data.
	 *
	 * @return array|bool
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		/** @global object $pagenow Pagenow object. */
		global $pagenow;

		if ( 'options.php' === $pagenow && 'settings' === $data['connector'] && wp_mainwp_stream_filter_input( INPUT_POST, '_wp_http_referer' ) ) {
			if ( ! isset( $data['args']['context'] ) || ! isset( $this->option_groups[ $data['args']['context'] ] ) ) {
				return $data;
			}

			$page   = preg_match( '#page=([^&]*)#', wp_mainwp_stream_filter_input( INPUT_POST, '_wp_http_referer' ), $match ) ? $match[1] : '';
			$labels = $this->get_context_labels();

			if ( ! isset( $labels[ $page ] ) ) {
				return $data;
			}

			$label = $this->settings_labels( $data['args']['option_key'] );
			if ( ! $label ) {
				// translators: Placeholder refers to a context (e.g. "Dashboard")
				$data['message'] = esc_html__( '%s settings updated', 'mainwp-child-reports' );
				$label           = $labels[ $page ];
			}

			$data['args']['label']   = $label;
			$data['args']['context'] = $page;
			$data['context']         = $page;
			$data['connector']       = $this->name;
		}

		return $data;
	}

	/**
	 * Set settings labels.
	 *
	 * @param $option
	 * @return array|bool|mixed Return labels array on success, FALSE on failure, error message on failure.
	 */
	private function settings_labels( $option ) {
		$labels = array(
			// wp-content/plugins/wordpress-seo/admin/pages/dashboard.php:
			'yoast_tracking'                         => esc_html_x( "Allow tracking of this WordPress install's anonymous data.", 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'disableadvanced_meta'                   => esc_html_x( 'Disable the Advanced part of the WordPress SEO meta box', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'alexaverify'                            => esc_html_x( 'Alexa Verification ID', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'msverify'                               => esc_html_x( 'Bing Webmaster Tools', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'googleverify'                           => esc_html_x( 'Google Webmaster Tools', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'pinterestverify'                        => esc_html_x( 'Pinterest', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'yandexverify'                           => esc_html_x( 'Yandex Webmaster Tools', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/advanced.php:
			'breadcrumbs-enable'                     => esc_html_x( 'Enable Breadcrumbs', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'breadcrumbs-sep'                        => esc_html_x( 'Separator between breadcrumbs', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'breadcrumbs-home'                       => esc_html_x( 'Anchor text for the Homepage', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'breadcrumbs-prefix'                     => esc_html_x( 'Prefix for the breadcrumb path', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'breadcrumbs-archiveprefix'              => esc_html_x( 'Prefix for Archive breadcrumbs', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'breadcrumbs-searchprefix'               => esc_html_x( 'Prefix for Search Page breadcrumbs', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'breadcrumbs-404crumb'                   => esc_html_x( 'Breadcrumb for 404 Page', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'breadcrumbs-blog-remove'                => esc_html_x( 'Remove Blog page from Breadcrumbs', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'breadcrumbs-boldlast'                   => esc_html_x( 'Bold the last page in the breadcrumb', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'post_types-post-maintax'                => esc_html_x( 'Taxonomy to show in breadcrumbs for post types', 'wordpress-seo', 'mainwp-child-reports' ), // type = select

			// wp-content/plugins/wordpress-seo/admin/pages/metas.php:
			'forcerewritetitle'                      => esc_html_x( 'Force rewrite titles', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'noindex-subpages-wpseo'                 => esc_html_x( 'Noindex subpages of archives', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'usemetakeywords'                        => _x( 'Use <code>meta</code> keywords tag?', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'noodp'                                  => _x( 'Add <code>noodp</code> meta robots tag sitewide', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'noydir'                                 => _x( 'Add <code>noydir</code> meta robots tag sitewide', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'hide-rsdlink'                           => esc_html_x( 'Hide RSD Links', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'hide-wlwmanifest'                       => esc_html_x( 'Hide WLW Manifest Links', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'hide-shortlink'                         => esc_html_x( 'Hide Shortlink for posts', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'hide-feedlinks'                         => esc_html_x( 'Hide RSS Links', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'disable-author'                         => esc_html_x( 'Disable the author archives', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'disable-date'                           => esc_html_x( 'Disable the date-based archives', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox

			// wp-content/plugins/wordpress-seo/admin/pages/network.php:
			'access'                                 => esc_html_x( 'Who should have access to the WordPress SEO settings', 'wordpress-seo', 'mainwp-child-reports' ), // type = select
			'defaultblog'                            => esc_html_x( 'New blogs get the SEO settings from this blog', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'restoreblog'                            => esc_html_x( 'Blog ID', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/permalinks.php:
			'stripcategorybase'                      => _x( 'Strip the category base (usually <code>/category/</code>) from the category URL.', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'trailingslash'                          => esc_html_x( "Enforce a trailing slash on all category and tag URL's", 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'cleanslugs'                             => esc_html_x( 'Remove stop words from slugs.', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'redirectattachment'                     => esc_html_x( "Redirect attachment URL's to parent post URL.", 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'cleanreplytocom'                        => _x( 'Remove the <code>?replytocom</code> variables.', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'cleanpermalinks'                        => esc_html_x( "Redirect ugly URL's to clean permalinks. (Not recommended in many cases!)", 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'force_transport'                        => esc_html_x( 'Force Transport', 'wordpress-seo', 'mainwp-child-reports' ), // type = select
			'cleanpermalink-googlesitesearch'        => esc_html_x( "Prevent cleaning out Google Site Search URL's.", 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'cleanpermalink-googlecampaign'          => esc_html_x( 'Prevent cleaning out Google Analytics Campaign & Google AdWords Parameters.', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'cleanpermalink-extravars'               => esc_html_x( 'Other variables not to clean', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/social.php:
			'opengraph'                              => esc_html_x( 'Add Open Graph meta data', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'facebook_site'                          => esc_html_x( 'Facebook Page URL', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'instagram_url'                          => esc_html_x( 'Instagram URL', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'linkedin_url'                           => esc_html_x( 'LinkedIn URL', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'myspace_url'                            => esc_html_x( 'MySpace URL', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'pinterest_url'                          => esc_html_x( 'Pinterest URL', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'youtube_url'                            => esc_html_x( 'YouTube URL', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'google_plus_url'                        => esc_html_x( 'Google+ URL', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'og_frontpage_image'                     => esc_html_x( 'Image URL', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'og_frontpage_desc'                      => esc_html_x( 'Description', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'og_frontpage_title'                     => esc_html_x( 'Title', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'og_default_image'                       => esc_html_x( 'Image URL', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'twitter'                                => esc_html_x( 'Add Twitter card meta data', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'twitter_site'                           => esc_html_x( 'Site Twitter Username', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'twitter_card_type'                      => esc_html_x( 'The default card type to use', 'wordpress-seo', 'mainwp-child-reports' ), // type = select
			'googleplus'                             => esc_html_x( 'Add Google+ specific post meta data (excluding author metadata)', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'plus-publisher'                         => esc_html_x( 'Google Publisher Page', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'fbadminapp'                             => esc_html_x( 'Facebook App ID', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/xml-sitemaps.php:
			'enablexmlsitemap'                       => esc_html_x( 'Check this box to enable XML sitemap functionality.', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'disable_author_sitemap'                 => esc_html_x( 'Disable author/user sitemap', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'disable_author_noposts'                 => esc_html_x( 'Users with zero posts', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'user_role-administrator-not_in_sitemap' => esc_html_x( 'Filter specific user roles - Administrator', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'user_role-editor-not_in_sitemap'        => esc_html_x( 'Filter specific user roles - Editor', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'user_role-author-not_in_sitemap'        => esc_html_x( 'Filter specific user roles - Author', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'user_role-contributor-not_in_sitemap'   => esc_html_x( 'Filter specific user roles - Contributor', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'user_role-subscriber-not_in_sitemap'    => esc_html_x( 'Filter specific user roles - Subscriber', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'xml_ping_yahoo'                         => esc_html_x( 'Ping Yahoo!', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'xml_ping_ask'                           => esc_html_x( 'Ping Ask.com', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'entries-per-page'                       => esc_html_x( 'Max entries per sitemap page', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'excluded-posts'                         => esc_html_x( 'Posts to exclude', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'post_types-post-not_in_sitemap'         => _x( 'Post Types Posts (<code>post</code>)', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'post_types-page-not_in_sitemap'         => _x( 'Post Types Pages (<code>page</code>)', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'post_types-attachment-not_in_sitemap'   => _x( 'Post Types Media (<code>attachment</code>)', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'taxonomies-category-not_in_sitemap'     => _x( 'Taxonomies Categories (<code>category</code>)', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'taxonomies-post_tag-not_in_sitemap'     => _x( 'Taxonomies Tags (<code>post_tag</code>)', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox

			// Added manually
			'rssbefore'                              => esc_html_x( 'Content to put before each post in the feed', 'wordpress-seo', 'mainwp-child-reports' ),
			'rssafter'                               => esc_html_x( 'Content to put after each post', 'wordpress-seo', 'mainwp-child-reports' ),
		);

		$ast_labels = array(
			'title-'        => esc_html_x( 'Title template', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'metadesc-'     => esc_html_x( 'Meta description template', 'wordpress-seo', 'mainwp-child-reports' ), // type = textarea
			'metakey-'      => esc_html_x( 'Meta keywords template', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'noindex-'      => esc_html_x( 'Meta Robots', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'noauthorship-' => esc_html_x( 'Authorship', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'showdate-'     => esc_html_x( 'Show date in snippet preview?', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'hideeditbox-'  => esc_html_x( 'WordPress SEO Meta Box', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'bctitle-'      => esc_html_x( 'Breadcrumbs Title', 'wordpress-seo', 'mainwp-child-reports' ), // type = textinput
			'post_types-'   => esc_html_x( 'Post types', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
			'taxonomies-'   => esc_html_x( 'Taxonomies', 'wordpress-seo', 'mainwp-child-reports' ), // type = checkbox
		);

		if ( $option ) {
			if ( isset( $labels[ $option ] ) ) {
				return $labels[ $option ];
			} else {
				foreach ( $ast_labels as $key => $trans ) {
					if ( 0 === strpos( $option, $key ) ) {
						return $trans;
					}
				}

				return false;
			}
		}

		return $labels;
	}
}
