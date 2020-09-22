<?php

/**
 * Plugin Name: WordPress.com VIP Plugins
 * Plugin URI:  http://vip.wordpress.com/
 * Description: Provides an interface to manage the activation and deactivation of the pre-approved WordPress.com VIP plugins.
 * Author:      Alex Mills (Automattic)
 * Author URI:  http://automattic.com/
 *
 * This class structure was stolen from bbPress, so credit to JJJ & co.
 */

if ( ! function_exists( 'wpcom_vip_load_plugin' ) )
	require_once( WP_CONTENT_DIR . '/themes/vip/plugins/vip-init.php' );

/**
 * Sets up and creates the VIP Plugins admin screens
 */
class WPcom_VIP_Plugins_UI {

	/**
	 * @var string Option name containing the list of active plugins.
	 */
	const OPTION_ACTIVE_PLUGINS = 'wpcom_vip_active_plugins';

	/**
	 * @var string This plugin's menu slug.
	 */
	const MENU_SLUG = 'wpcom-vip-plugins';

	/**
	 * @var string Action: Plugin activation.
	 */
	const ACTION_PLUGIN_ACTIVATE = 'wpcom-vip-plugins_activate';

	/**
	 * @var string Action: Plugin deactivation.
	 */
	const ACTION_PLUGIN_DEACTIVATE = 'wpcom-vip-plugins_deactivate';

	/**
	 * @var string Whether or not to disable the plugin activation links.
	 */
	public $activation_disabled = false;

	/**
	 * @var string Path to the extra plugins folder.
	 */
	public $plugin_folder;

	/**
	 * @var string Required capability to access this plugin's features. Use the "wpcom_vip_plugins_ui_capability" filter to change this.
	 */
	public $capability = 'manage_options';

	/**
	 * @var string Parent menu's slug. Use the "wpcom_vip_plugins_ui_parent_menu_slug" filter to change this.
	 */
	public $parent_menu_slug = 'vip-dashboard';

	/**
	 * @var string The $hook_suffix value for the menu page.
	 */
	public $hook_suffix;

	/**
	 * @var array List of plugins that should be hidden.
	 */
	public $hidden_plugins = array();

	/**
	 * @var array List of Featured Partner Program plugins.
	 */
	public $fpp_plugins = array();

	/** Singleton *************************************************************/

	/**
	 * @var WPcom_VIP_Plugins_UI Stores the instance of this class.
	 */
	private static $instance;

	/**
	 * Main WPcom_VIP_Plugins_UI Instance
	 *
	 * Insures that only one instance of WPcom_VIP_Plugins_UI exists in memory at any one time.
	 * Also prevents needing to define globals all over the place.
	 *
	 * @staticvar array $instance
	 * @uses WPcom_VIP_Plugins_UI::setup_globals() Setup the globals needed
	 * @uses WPcom_VIP_Plugins_UI::setup_actions() Setup the hooks and actions
	 * @see WPcom_VIP_Plugins_UI()
	 * @return WPcom_VIP_Plugins_UI The one true WPcom_VIP_Plugins_UI
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WPcom_VIP_Plugins_UI;
			self::$instance->setup_globals();
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	/** Magic Methods *********************************************************/

	/**
	 * A dummy constructor to prevent WPcom_VIP_Plugins_UI from being loaded more than once.
	 *
	 * @see WPcom_VIP_Plugins_UI::instance()
	 * @see WPcom_VIP_Plugins_UI();
	 */
	private function __construct() { /* Do nothing here */ }

	/**
	 * A dummy magic method to prevent WPcom_VIP_Plugins_UI from being cloned
	 */
	public function __clone() { wp_die( __( 'Cheatin’ uh?' ) ); }

	/**
	 * A dummy magic method to prevent WPcom_VIP_Plugins_UI from being unserialized
	 */
	public function __wakeup() { wp_die( __( 'Cheatin’ uh?' ) ); }

	/** Private Methods *******************************************************/

	/**
	 * Set up the class variables.
	 *
	 * @access private
	 */
	private function setup_globals() {
		$this->plugin_folder = WP_CONTENT_DIR . '/themes/vip/plugins';

		// Allow people to change the positioning of the menu
		// Default for localhost should be under plugins.php
		if ( ! $this->is_wpcom_vip() )
			$this->parent_menu_slug = 'plugins.php';

		$this->hidden_plugins = array(
			'vip-do-not-include-on-wpcom', // Local dev helper
			'internacional', // Not ready yet (ever?)
			'wpcom-profiler', // Used internally to debug sites
			'wpcom-legacy-redirector', // requires code-level changes
			'maintenance-mode', // Doesn't work via UI - https://keepingtheirblogsgoing.wordpress.com/2016/03/15/maintenance-mode-should-be-removed-from-vip-dashboard/

			// Premium
			'new-device-notification',

			// Commercial non-FPP plugins. Available but not promoted.
			'disqus',
			'kapost-byline',
			'inform',
			'share-this-classic-wpcom',
			'share-this-wpcom',
			'five-min-video-suggest',
			'stipple',
			'brightcove',
			'lift-search',
			'msm-sitemap',
			'zemanta',
			'pushup',
			'livepress',
			'wp-discourse',
			'simplechart',
			'facebook-simple-translation',

			// deprecated
			'breadcrumb-navxt', // use the newer version instead
			'daylife', // API doesn't work #36756-z
			'feedwordpress', // breaks all the time
			'findthebest', // replaced by Graphiq Search due to comapny rebranding
			'google-calendar-events', // https://viprequests.wordpress.com/2015/01/06/update-google-calendar-events-shared-plugin/
			'ice', // Crazy out-of-date, doesn't work with MCE 4+, still in use by a handful for some reason
			'livefyre', // use livefyre3 instead
			'search-excerpt', // out-of-date and not widely used
			'the-attached-image', // Badness - was missing ton of escaping, not using the settings api
			'watermark-image-uploads', // broken, can't save options, breaks transparent png uploads.
			'wordtwit-1.3-mod', // use publicize
			'wpcom-related-posts', // Now part of JP / WP.com
			'livefyre-apps', 'livefyre3', // http://wp.me/poqVs-eiD

			// The great deprecation of 2016 https://keepingtheirblogsgoing.wordpress.com/2016/04/29/whats-the-status-of-roost/#comment-79726
			'postrelease-vip',
			'breadcrumb-navxt-39',
			'wp-frontend-uploader',
			'pushup',
			'options-importer',
			'wp-seo',
			'nbcs-advanced-blacklist',
			'advanced-excerpt',
			'ajax-comment-loading',
			'ajax-comment-preview',
			'angellist',
			'blimply',
			'category-posts-widget',
			'column-shortcodes',
			'comment-probation',
			'disable-comments-query',
			'dynamic-content-gallery',
			'easy-custom-fields',
			'ecwid',
			'expiring-posts',
			'external-links-new-window',
			'external-permalinks-redux',
			'flag-comments',
			'formategory',
			'sem-frame-buster',
			'gallery-style-cleanup',
			'get-the-image',
			'gumroad',
			'history-bar',
			'image-metadata-cruncher',
			'json-feed',
			'lightbox-plus',
			'localtime',
			'mce-table-buttons',
			'most-commented',
			'nbcs-moderation-queue-alerts',
			'objects-to-objects',
			'objects-to-objects-1.4.5',
			'optimizely',
			'post-forking',
			'post-revision-workflow',
			'tw-print',
			'publishing-checklist',
			'seo-auto-linker',
			'seo-friendly-images-mod',
			'shopify-store',
			'shortcode-ui',
			'simple-page-ordering',
			'simply-show-ids',
			'speed-bumps',
			'sticky-custom-post-types',
			'subheading',
			'table-of-contents',
			'taxonomy-images',
			'term-management-tools',
			'tidal',
			'view-all-posts-pages',
			'editorial-calendar',
			'wp-pagenavi',
			'wp-paginate',
			'wp-google-analytics',
			'wp-help',
			'wp-page-numbers',
		);

		$this->fpp_plugins = array(
			'amp' => array(
				'name'          => 'AMP',
				'description'   => 'An open-source initiative aiming to make the web better for all',
			),
			'apester-interactive-content' => array(
				'name'			=> 'Apester Interactive Content',
				'description'	=> 'Apester allows you to easily create, embed and share interactive content (polls, trivia, etc.) into your posts and articles.',
			),
			'brightcove-video-connect' => array(
				'name'			=> 'Brightcove Video Connect',
				'description'	=> 'Discover How Video Can Move Your Business.',
			),
			'facebook-instant-articles' => array(
				'name'			=> 'Facebook Instant Articles',
				'description'	=> 'Add support for Instant Articles for Facebook to your WordPress site.',
			),
			'getty-images' => array(
				'name'			=> 'Getty Images',
				'description'	=> 'Search and use Getty Images photos in your posts without ever leaving WordPress.com.',
			),
			'inform-video-match' => array(
				'name'			=> 'Inform Video Match',
				'description'	=> 'Better than free.',
			),
			'jwplayer' => array(
				'name'			=> 'JW Player',
				'description'	=> 'The World’s Most Popular Video Player.',
			),
			'laterpay' => array(
                                'name'                  => 'LaterPay',
                                'description'   => 'Convert casual users into paying customers.',
                        ),
			'ooyala' => array(
				'name'			=> 'Ooyala',
				'description'	=> 'Upload, Search and Publish High Quality Video Across All Screens powered by Ooyala.',
			),
			'piano' => array(
				'name'          => 'Piano',
				'description'   => 'Piano is the best way to charge for access to content on your site.',
			),
			'sailthru' => array(
				'name'			=> 'Sailthru for WordPress',
				'description'	=> 'Sailthru is the leading provider of personalized marketing communications.',
			),
			'skyword' => array(
				'name'			=> 'Skyword',
				'description'	=> 'Moving Stories. Forward.',
			),
			'webdam-asset-chooser' => array(
				'name'			=> 'WebDAM Asset Chooser',
				'description'	=> 'Import WebDAM assets into WordPress.',
			),
		);
	}

	/**
	 * Set up early action hooks for this plugin
	 *
	 * @access private
	 * @uses add_option() To register an option
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {

		// Loaded at priority 5 because all plugins are typically loaded before 'plugins_loaded'
		add_action( 'plugins_loaded', array( $this, 'include_active_plugins' ), 5 );

		add_action( 'init', array( $this, 'action_init' ) );
	}

	/** Public Hook Callback Methods ******************************************/

	/**
	 * Now that we've given the theme time to register its own filters,
	 * set up the rest of the plugin's hooks and run some filters.
	 *
	 * @uses add_action() To add various actions
	 */
	public function action_init() {
		// Allow people to customize what capability is required in order to view this menu
		$this->capability       = apply_filters( 'wpcom_vip_plugins_ui_capability',       $this->capability );

		// Controls where this menu is added
		$this->parent_menu_slug = apply_filters( 'wpcom_vip_plugins_ui_parent_menu_slug', $this->parent_menu_slug );

		// Allows hiding of certain plugins from the UI
		$this->hidden_plugins   = apply_filters( 'wpcom_vip_plugins_ui_hidden_plugins',   $this->hidden_plugins );


		add_action( 'admin_menu', array( $this, 'action_admin_menu_add_menu_item' ) );

		add_action( 'wpcom_vip_plugins_ui_menu_page', array( $this, 'cleanup_active_plugins_option' ) );

		add_action( 'admin_post_' . self::ACTION_PLUGIN_ACTIVATE, array( $this, 'action_admin_post_plugin_activate' ) );
		add_action( 'admin_post_' . self::ACTION_PLUGIN_DEACTIVATE, array( $this, 'action_admin_post_plugin_deactivate' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'action_enqueue_scripts' ) );
	}

	/**
	 * Includes any active plugin files that are enabled via the UI/option.
	 */
	public function include_active_plugins() {
		foreach ( $this->get_active_plugins_option() as $plugin ) {
			if ( has_blog_sticker( 'vip-plugins-ui-rc-plugins' ) ) {
				wpcom_vip_load_plugin( $plugin, 'plugins', true );
			} else {
				wpcom_vip_load_plugin( $plugin );
			}
		}
	}

	/**
	 * Adds the new menu item and registers a few more hook callbacks relating to the menu page.
	 */
	public function action_admin_menu_add_menu_item() {

		if ( $this->parent_menu_slug == 'plugins.php' ) {
			$page_title = __( 'WordPress.com VIP Plugins', 'wpcom-vip-plugins-ui' );
			$menu_label = __( 'WP.com VIP Plugins', 'wpcom-vip-plugins-ui' );
		} else {
			$page_title = __( 'WordPress.com VIP Plugins & Services', 'wpcom-vip-plugins-ui' );
			$menu_label = __( 'Plugins & Services', 'wpcom-vip-plugins-ui' );
		}
		$this->hook_suffix = add_submenu_page( $this->parent_menu_slug, $page_title, $menu_label, $this->capability, self::MENU_SLUG, array( $this, 'display_menu_page' ) );

		// This is required because WPcom_VIP_Plugins_UI_List_Table() is defined inside of a function
		add_filter( 'manage_' . $this->hook_suffix . '_columns', array( 'WPcom_VIP_Plugins_UI', 'community_plugins_menu_columns' ) );
	}

	/**
	 * Load the assets for this plugin on the correct screen only
	 *
	 * @param  string $hook
	 * @return void
	 */
	public function action_enqueue_scripts( $hook ) {

		wp_enqueue_style( 'wpcom-vip-plugins-ui', plugin_dir_url( __FILE__ ) . 'css/wpcom-vip-plugins-ui.css' );
		wp_enqueue_script( 'wpcom-vip-plugins-ui', plugin_dir_url( __FILE__ ) . 'js/wpcom-vip-plugins-ui.js' );
	}

	/**
	 * Handles the plugin activation links and activates the requested plugin.
	 */
	public function action_admin_post_plugin_activate() {
		if ( $this->activation_disabled )
			wp_die( __( 'Plugin activation via this UI has been disabled from within your theme.', 'wpcom-vip-plugins-ui' ) );

		if ( empty( $_GET['plugin'] ) )
			wp_die( sprintf( __( 'Missing %s parameter', 'wpcom-vip-plugins-ui' ), '<code>plugin</code>' ) );

		if ( ! current_user_can( $this->capability ) )
			wp_die( __( 'You do not have sufficient permissions to activate plugins for this site.' ) );

		check_admin_referer( 'activate-' . $_GET['plugin'] );

		if ( ! $this->activate_plugin( $_GET['plugin'] ) )
			wp_die( __( "Failed to activate plugin. Maybe it's already activated?", 'wpcom-vip-plugins-ui' ) );

		wp_safe_redirect( $this->get_menu_url( array( 'activated' => '1' ) ) );
		exit();
	}

	/**
	 * Handles the plugin deactivation links and deactivates the requested plugin.
	 */
	public function action_admin_post_plugin_deactivate() {
		if ( empty( $_GET['plugin'] ) )
			wp_die( sprintf( __( 'Missing %s parameter', 'wpcom-vip-plugins-ui' ), '<code>plugin</code>' ) );

		if ( ! current_user_can( $this->capability ) )
			wp_die( __( 'You do not have sufficient permissions to deactivate plugins for this site.' ) );

		check_admin_referer( 'deactivate-' . $_GET['plugin'] );

		if ( ! $this->deactivate_plugin( $_GET['plugin'] ) )
			wp_die( __( "Failed to deactivate plugin. Maybe it was already deactivated?", 'wpcom-vip-plugins-ui' ) );

		wp_safe_redirect( $this->get_menu_url( array( 'deactivated' => '1' ) ) );
		exit();
	}

	/**
	 * Outputs the contents of the menu page.
	 */
	public function display_menu_page() {
		require_once( dirname( __FILE__ ) . '/class-wpcom-vip-plugins-list-tables.php' );

		do_action( 'wpcom_vip_plugins_ui_menu_page' );

		$fpp_table = new WPCOM_VIP_Featured_Plugins_List_Table();
		$fpp_table->prepare_items();

		$wp_list_table = new WPcom_VIP_Plugins_UI_List_Table();
		$wp_list_table->prepare_items();

		if ( ! empty( $_GET['activated'] ) )
			add_settings_error( 'wpcom-vip-plugins-ui', 'wpcom-vip-plugins-activated', __( 'Plugin activated.', 'wpcom-vip-plugins-ui' ), 'updated' );
		elseif( ! empty( $_GET['deactivated'] ) )
			add_settings_error( 'wpcom-vip-plugins-ui', 'wpcom-vip-plugins-activated', __( 'Plugin deactivated.', 'wpcom-vip-plugins-ui' ), 'updated' );

?>
<div class="wrap">
	<?php screen_icon( 'plugins' ); ?>
	<h2><?php esc_html_e( 'WordPress.com VIP Plugins & Services', 'wpcom-vip-plugins-ui' ); ?></h2>

	<?php settings_errors( 'wpcom-vip-plugins-ui' ); ?>

	<main id="plugins" role="main">

		<?php $fpp_table->display(); ?>

		<?php $wp_list_table->display(); ?>

	</main>

</div>
<?php
	}

	/**
	 * Filters the columns of the Community Plugins table.
	 *
	 * @param array $columns An array of existing columns.
	 * @return array Modified list of columns.
	 */
	public static function community_plugins_menu_columns( $columns ) {
		$columns['name']        = 'Community Plugins';
		$columns['description'] = '';

		return $columns;
	}

	/** Helper Functions ******************************************************/

	/**
	 * Are we on WordPress.com VIP or somewhere else?
	 *
	 * Not everyone is using the new loader yet (vip-init.php) so this checks
	 * both the new method (constant) and the legacy method (function).
	 *
	 * @return bool True if on WP.com VIP, false if not.
	 */
	public function is_wpcom_vip() {
		return ( ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV ) || ( function_exists( 'wpcom_is_vip' ) && wpcom_is_vip() ) );
	}

	/**
	 * Gets the list of VIP plugins that have been activated via the UI.
	 *
	 * @return array List of active VIP plugin slugs.
	 */
	public function get_active_plugins_option() {
		return (array) get_option( self::OPTION_ACTIVE_PLUGINS, array() );
	}

	/**
	 * Removes any invalid plugins from the option, i.e. when they're deleted.
	 */
	public function cleanup_active_plugins_option() {
		$active_plugins = $this->get_active_plugins_option();

		foreach ( $active_plugins as $active_plugin ) {
			if ( ! $this->validate_plugin( $active_plugin ) ) {
				$this->deactivate_plugin( $active_plugin, true );
			}
		}
	}

	/**
	 * Generates the URL to activate a VIP plugin.
	 *
	 * @param string $plugin The slug of the VIP plugin to activate.
	 * @return string Activation URL.
	 */
	public function get_plugin_activation_link( $plugin ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_PLUGIN_ACTIVATE . '&plugin=' . urlencode( $plugin ) ), 'activate-' . $plugin );
	}

	/**
	 * Generates the URL to deactivate a VIP plugin.
	 *
	 * @param string $plugin The slug of the VIP plugin to deactivate.
	 * @return string Deactivation URL.
	 */
	public function get_plugin_deactivation_link( $plugin ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_PLUGIN_DEACTIVATE . '&plugin=' . urlencode( $plugin ) ), 'deactivate-' . $plugin );
	}

	/**
	 * Determines if a given plugin slug is already activated or not.
	 *
	 * @param string $plugin The slug of the VIP plugin to check.
	 * @return string|bool "option" if the plugin was activated via UI, "manual" if activated via code, and false if not activated.
	 */
	public function is_plugin_active( $plugin ) {
		// Do exact matching before messing with versioned plugins.
		if ( in_array( $plugin, $this->get_active_plugins_option() ) )
			return 'option';
		elseif ( in_array( 'plugins/' . $plugin, wpcom_vip_get_loaded_plugins() ) )
			return 'manual';

		/*
		 Dirty check for versioned plugins.  Not all plugins will
		 have a '-' in their slug, but ALL versioned plugins do.
		 This should fix some outliers that are hidden and have
		 multiple plugins that have slugs that start with the same
		 string. ex: brightcove (hidden) and brightcove-video-connect
		 */
		if ( false !== strpos( $plugin, '-' ) ) {
			// Loop through and match versioned plugins.
			foreach ( $this->get_active_plugins_option() as $active_plugin ) {
				if ( 0 === strpos( $active_plugin, $plugin ) ) {
					return 'option';
				}
			}

			foreach ( wpcom_vip_get_loaded_plugins() as $active_plugin ) {
				if ( 0 === strpos( $active_plugin, 'plugins/' . $plugin ) && array_key_exists($plugin, $this->fpp_plugins ) ) {
					return 'manual';
				}
			}
		}

		return false;
	}

	/**
	 * Filters an array of action links to add an activation or deactivation link.
	 *
	 * @param array $actions Existing actions.
	 * @param string $plugin Plugin slug to generate the link for.
	 * @return array List of actions, including the new one.
	 */
	public function add_activate_or_deactive_action_link( $actions, $plugin ) {
		$is_active = WPcom_VIP_Plugins_UI()->is_plugin_active( $plugin );

		if ( $is_active ) {
			if ( 'option' == $is_active ) {
				$actions['deactivate'] = '<a href="' . esc_url( WPcom_VIP_Plugins_UI()->get_plugin_deactivation_link( $plugin ) ) . '" title="' . esc_attr__( 'Deactivate this plugin' ) . '">' . __( 'Deactivate' ) . '</a>';
			} elseif ( 'manual' == $is_active ) {
				$actions['deactivate-manually'] = '<span title="To deactivate this particular plugin, edit your theme\'s functions.php file">' . __( "Enabled via your theme's code" ) . '</span>';
			}
		}

		// Only show activation links if they aren't disabled
		elseif ( ! $this->activation_disabled ) {
			$actions['activate'] = '<a href="' . esc_url( WPcom_VIP_Plugins_UI()->get_plugin_activation_link( $plugin ) ) . '" title="' . esc_attr__( 'Activate this plugin' ) . '" class="edit">' . __( 'Activate' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Validates a plugin slug.
	 *
	 * @param string $plugin The slug of the VIP plugin to validate.
	 * @return bool True if valid, false if not.
	 */
	public function validate_plugin( $plugin ) {
		return ( 0 === validate_file( $plugin ) && ( file_exists( $this->plugin_folder . '/' . $plugin . '/' . $plugin . '.php' ) || file_exists( $this->plugin_folder . '/release-candidates/' . $plugin . '/' . $plugin . '.php' ) ) );
	}

	/**
	 * Activates a plugin.
	 *
	 * @param string $plugin The slug of the VIP plugin to activate.
	 * @return bool True if the plugin was activated, false if an error was encountered.
	 */
	public function activate_plugin( $plugin ) {

		if ( ! $this->validate_plugin( $plugin ) ) {
			return false;
		}

		$plugins = $this->get_active_plugins_option();

		// Don't add it twice
		if ( in_array( $plugin, $plugins ) ) {
			return false;
		}

		$plugins[] = $plugin;

		do_action( 'wpcom_vip_plugins_ui_activate_plugin', $plugin );

		return update_option( self::OPTION_ACTIVE_PLUGINS, $plugins );
	}

	/**
	 * Deactivates a plugin.
	 *
	 * @param string $plugin The slug of the VIP plugin to deactivate.
	 * @param string $force Optional. Whether to bypass the validation check or not. Allows disabling invalid plugins.
	 * @return bool True if the plugin was deactivated, false if an error was encountered.
	 */
	public function deactivate_plugin( $plugin, $force = false ) {

		if ( ! $force && ! $this->validate_plugin( $plugin ) ) {
			return false;
		}

		do_action( 'wpcom_vip_plugins_ui_deactivate_plugin', $plugin );

		$plugins = $this->get_active_plugins_option();

		if ( ! in_array( $plugin, $plugins ) ) {
			return false;
		}

		// Remove from array and re-index (just to stay clean)
		$plugins = array_values( array_diff( $plugins, array( $plugin ) ) );

		return update_option( self::OPTION_ACTIVE_PLUGINS, $plugins );
	}

	/**
	 * Generates a link to the plugin's menu page.
	 *
	 * @param array $extra_query_args Optional. Extra arguments to pass to add_query_arg().
	 * @return string URL to the plugin's menu page.
	 */
	public function get_menu_url( $extra_query_args = array() ) {
		$menu_url = ( 'plugins.php' == $this->parent_menu_slug ) ? 'plugins.php' : 'admin.php';

		$menu_url = add_query_arg(
			array_merge(
				array( 'page' => self::MENU_SLUG ),
				$extra_query_args
			),
			$menu_url
		);

		$menu_url = admin_url( $menu_url );

		return $menu_url;
	}
}

/**
 * The main function responsible for returning the one true WPcom_VIP_Plugins_UI instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing to declare the global.
 * Example: <?php $WPcom_VIP_Plugins_UI = WPcom_VIP_Plugins_UI(); ?>
 *
 * @return WPcom_VIP_Plugins_UI The one true WPcom_VIP_Plugins_UI Instance
 */
function WPcom_VIP_Plugins_UI() {
	return WPcom_VIP_Plugins_UI::instance();
}

// Start up the class immediately
WPcom_VIP_Plugins_UI();



?>
