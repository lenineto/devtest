<?php

/*
Plugin Name: VIP Local Development Helper
Description: Helps you test your <a href="http://vip.wordpress.com/hosting/">WordPress.com VIP</a> theme in your local development environment by defining some functions that are always loaded on WordPress.com
Plugin URI:  http://lobby.vip.wordpress.com/getting-started/development-environment/
Author:      Automattic
Author URI:  http://vip.wordpress.com/

For help with this plugin, please see http://wp.me/PPtWC-2T or contact VIP support at vip-support@wordpress.com

This plugin is enabled automatically on WordPress.com for VIPs.
*/


/**
 * Loads a plugin out of our shared plugins directory.
 *
 * @link http://lobby.vip.wordpress.com/plugins/ VIP Shared Plugins
 * @param string $plugin Optional. Plugin folder name (and filename) of the plugin
 * @param string $folder Optional. Folder to include from; defaults to "plugins". Useful for when you have multiple themes and your own shared plugins folder.
 * @param string|bool $version Optional. Specify which version of the plugin to load. Version should be in the format 1.0.0. Passing true triggers legacy release candidate support.
 *
 * @return bool True if the include was successful
 */
function wpcom_vip_load_plugin( $plugin = false, $folder = 'plugins', $version = false ) {
	static $loaded_plugin_slugs = array();

	// Make sure there's a plugin to load
	if ( empty( $plugin ) ) {
		// On WordPress.com, use an internal function to message VIP about a bad call to this function
		if ( function_exists( 'wpcom_is_vip' ) ) {
			if ( function_exists( 'send_vip_team_debug_message' ) ) {
				// Use an expiring cache value to avoid spamming messages
				if ( ! wp_cache_get( 'noplugin', 'wpcom_vip_load_plugin' ) ) {
					send_vip_team_debug_message( 'WARNING: wpcom_vip_load_plugin() is being called without a $plugin parameter', 1 );
					wp_cache_set( 'noplugin', 1, 'wpcom_vip_load_plugin', 3600 );
				}
			}
			return false;
		}
		else {
			die( 'wpcom_vip_load_plugin() was called without a first parameter!' );
		}
	}

	$plugin_slug = $plugin; // Unversioned plugin name

	// Get the version number, if we have one
	if ( is_string( $version ) && false !== $plugin ) {
		$plugin = $plugin . '-' . $version; // Versioned plugin name
	}

    // Liveblog is a special flower. We need to check this theme/site can use it
    // Skip if we're loading 1.3, as that's loaded by vip-friends.php
    // $plugin will include a version number by this point if it's above 1.3
    if ( 'liveblog' == $plugin_slug && 'liveblog' != $plugin ) {

        if ( function_exists( 'wpcom_vip_is_liveblog_enabled' ) && ! wpcom_vip_is_liveblog_enabled() ) {
            // For now, we'll just bail.
            // @todo Log to IRC
            return false;
        }
    }

	// Prevent double-loading of different versions of the same plugin.
	$local_plugin_key = sprintf( '%s__%s', $folder, $plugin_slug );
	if ( isset( $loaded_plugin_slugs[ $local_plugin_key ] ) ) {
		// TODO: send alert when `$loaded_plugin_slugs[ $local_plugin_key ] !== $version`

		return false;
	}
	$loaded_plugin_slugs[ $local_plugin_key ] = $version;

    // Find the plugin
	$plugin_locations = _wpcom_vip_load_plugin_get_locations( $folder, $version );
	$include_path = _wpcom_vip_load_plugin_get_include_path( $plugin_locations, $plugin, $plugin_slug );

	// Reset the folder based on where the plugin actually lives, and get the full path for inclusion
	if ( is_array( $include_path ) ) {
		$folder = $include_path['folder'];
		$include_path = $include_path['full_path'];
	}

	// Now check we have an include path and include the plugin
	if ( false !== $include_path ) {

		wpcom_vip_add_loaded_plugin( "$folder/$plugin" );

		// Since we're going to be include()'ing inside of a function,
		// we need to do some hackery to get the variable scope we want.
		// See http://www.php.net/manual/en/language.variables.scope.php#91982

		// Start by marking down the currently defined variables (so we can exclude them later)
		$pre_include_variables = get_defined_vars();

		// Now include
		include_once( $include_path );

		// If there's a wpcom-helper file for the plugin, load that too
		$helper_path = WP_CONTENT_DIR . "/themes/vip/$folder/$plugin/wpcom-helper.php";

		if ( file_exists( $helper_path ) ) {
			require_once( $helper_path );
		}

		// Blacklist out some variables
		$blacklist = array( 'blacklist' => 0, 'pre_include_variables' => 0, 'new_variables' => 0 );

		// Let's find out what's new by comparing the current variables to the previous ones
		$new_variables = array_diff_key( get_defined_vars(), $GLOBALS, $blacklist, $pre_include_variables );

		// global each new variable
		foreach ( $new_variables as $new_variable => $devnull )
			global ${$new_variable};

		// Set the values again on those new globals
		extract( $new_variables );

		return true;
	} else {
		// On WordPress.com, use an internal function to message VIP about the bad call to this function
		if ( function_exists( 'wpcom_is_vip' ) ) {
			if ( function_exists( 'send_vip_team_debug_message' ) ) {
				// Use an expiring cache value to avoid spamming messages
				$cachekey = md5( $folder . '|' . $plugin );
				if ( ! wp_cache_get( "notfound_$cachekey", 'wpcom_vip_load_plugin' ) ) {
					send_vip_team_debug_message( "WARNING: wpcom_vip_load_plugin() is trying to load a non-existent file ( /$folder/$plugin/$plugin_slug.php )", 1 );
					wp_cache_set( "notfound_$cachekey", 1, 'wpcom_vip_load_plugin', 3600 );
				}
			}
			return false;

		// die() in non-WordPress.com environments so you know you made a mistake
		} else {
			die( "Unable to load $plugin ({$folder}) using wpcom_vip_load_plugin()!" );
		}
	}
}

/**
 * Get a list of possible plugin locations.
 *
 * Given the details passed to wpcom_vip_load_plugin(), figure out where the plugin could reside and pass that back.
 *
 * @param string $folder The folder we should be looking for
 * @param int $version A version number
 *
 * @return array Returns an array of possible plugin locations
 */
function _wpcom_vip_load_plugin_get_locations( $folder, $version ) {

	// Make a list of possible plugin locations
	$plugin_locations = [];

	// Allow VIPs to load plugins bundled in their theme
	if ( 'theme' === $folder ) {
		$theme = wp_get_theme();

		// Add the child theme to paths array, if applicable
		if ( $theme->get_stylesheet() !== $theme->get_template() ) {
			// Convert "vip/[theme-name]" to "[theme-name]/plugins"
			$plugin_locations[] = str_replace( 'vip/', '', $theme->get_stylesheet() ) . '/plugins';
		}

		// Always check the "parent" (which may just be the active theme)
		// and convert "vip/[theme-name]" to "[theme-name]/plugins"
		$plugin_locations[] = str_replace( 'vip/', '', $theme->get_template() ) . '/plugins';

	}

	// Provide backwards-compatibility for release candidates
	if ( true === $version ) {
		$plugin_locations[] = $folder . '/release-candidates';
	}

	// Always look for plugins in the standard plugins dir/shared plugins repos
	$plugin_locations[] = $folder;

	return $plugin_locations;

}

/**
 * Determine the full include path to the plugin.
 *
 * Gathers all the various bits, puts them together and checks that the plugin path is valid.
 *
 * @param array $plugin_locations A list of possible locations from _wpcom_vip_load_plugin_get_locations()
 * @param string $plugin The versioned plugin name
 * @param string $plugin_slug The unversioned plugin slug
 *
 * @return array|bool An array with the full path, and folder part or false if no valid path was found
 */
function _wpcom_vip_load_plugin_get_include_path( $plugin_locations = [], $plugin, $plugin_slug ) {

	// Check each possible location, using the first gives a usable plugin path
	foreach ( $plugin_locations as $plugin_location ) {
		$path_to_check = sprintf(
			'%s/themes/vip/%s/%s/%s.php',
			WP_CONTENT_DIR,
			$plugin_location,
			_wpcom_vip_load_plugin_sanitizer( $plugin ),
			_wpcom_vip_load_plugin_sanitizer( $plugin_slug )
		);

		if ( file_exists( $path_to_check ) ) { // We've found a valid plugin path
			// We need to return the full path for the include, but also the location which is used
			// elsewhere to check what plugins are active on a site.
			return [
				'full_path' => $path_to_check,
				'folder' => $plugin_location,
			];
		}
	}

	// If we don't find the plugin anywhere, return false
	return false;
}

/**
 * Helper function for wpcom_vip_load_plugin(); sanitizes plugin folder name.
 *
 * You shouldn't use this function.
 *
 * @param string $folder Folder name
 * @return string Sanitized folder name
 */
function _wpcom_vip_load_plugin_sanitizer( $folder ) {
	$folder = preg_replace( '#([^a-zA-Z0-9-_.]+)#', '', $folder );
	$folder = str_replace( '..', '', $folder ); // To prevent going up directories

	return $folder;
}

/**
 * Require a library in the VIP shared code library.
 *
 * @param string $slug
 */
function wpcom_vip_require_lib( $slug ) {
	if ( !preg_match( '|^[a-z0-9/_.-]+$|i', $slug ) ) {
		trigger_error( "Cannot load a library with invalid slug $slug.", E_USER_ERROR );
		return;
	}
	$basename = basename( $slug );
	$lib_dir = WP_CONTENT_DIR . '/themes/vip/plugins/lib';
	$choices = array(
		"$lib_dir/$slug.php",
		"$lib_dir/$slug/0-load.php",
		"$lib_dir/$slug/$basename.php",
	);
	foreach( $choices as $file_name ) {
		if ( is_readable( $file_name ) ) {
			require_once $file_name;
			return;
		}
	}
	trigger_error( "Cannot find a library with slug $slug.", E_USER_ERROR );
}

/**
 * Loads the shared VIP helper file which defines some helpful functions.
 *
 * @link http://vip.wordpress.com/documentation/development-environment/ Setting up your Development Environment
 */
function wpcom_vip_load_helper() {
	$includepath = WP_CONTENT_DIR . '/themes/vip/plugins/vip-helper.php';

	if ( file_exists( $includepath ) ) {
		require_once( $includepath );
	} else {
		die( "Unable to load vip-helper.php using wpcom_vip_load_helper(). The file doesn't exist!" );
	}
}


/**
 * Loads the WordPress.com-only VIP helper file which defines some helpful functions.
 *
 * @link http://vip.wordpress.com/documentation/development-environment/ Setting up your Development Environment
 */
function wpcom_vip_load_helper_wpcom() {
	$includepath = WP_CONTENT_DIR . '/themes/vip/plugins/vip-helper-wpcom.php';
	require_once( $includepath );
}

/**
 * Loads the WordPress.com-only VIP helper file for stats which defines some helpful stats-related functions.
 */
function wpcom_vip_load_helper_stats() {
	$includepath = WP_CONTENT_DIR . '/themes/vip/plugins/vip-helper-stats-wpcom.php';
	require_once( $includepath );
}

/**
 * Store the name of a VIP plugin that will be loaded
 *
 * @param string $plugin Plugin name and folder
 * @see wpcom_vip_load_plugin()
 */
function wpcom_vip_add_loaded_plugin( $plugin ) {
	global $vip_loaded_plugins;

	if ( ! isset( $vip_loaded_plugins ) )
		$vip_loaded_plugins = array();

	array_push( $vip_loaded_plugins, $plugin );
}

/**
 * Get the names of VIP plugins that have been loaded
 *
 * @return array
 */
function wpcom_vip_get_loaded_plugins() {
	global $vip_loaded_plugins;

	if ( ! isset( $vip_loaded_plugins ) )
		$vip_loaded_plugins = array();

	return array_unique( $vip_loaded_plugins );
}

/**
 * Returns the raw path to the VIP themes dir.
 *
 * @return string
 */
function wpcom_vip_themes_root() {
	return WP_CONTENT_DIR . '/themes/vip';
}

/**
 * Returns the non-CDN URI to the VIP themes dir.
 *
 * Sometimes enqueuing/inserting resources can trigger cross-domain errors when
 * using the CDN, so this function allows bypassing the CDN to eradicate those
 * unwanted errors.
 *
 * @return string The URI
 */
function wpcom_vip_themes_root_uri() {
	if ( ! is_admin() ) {
		return home_url( '/wp-content/themes/vip' );
	} else {
		return content_url( '/themes/vip' );
	}
}

/**
 * Returns the non-CDN'd URI to the specified path.
 *
 * @param string $path Must be a full path, e.g. dirname( __FILE__ )
 * @return string
 */
function wpcom_vip_noncdn_uri( $path ) {
	// Be gentle on Windows, borrowed from core, see plugin_basename
	$path = str_replace( '\\','/', $path ); // sanitize for Win32 installs
	$path = preg_replace( '|/+|','/', $path ); // remove any duplicate slash

	return sprintf( '%s%s', wpcom_vip_themes_root_uri(), str_replace( wpcom_vip_themes_root(), '', $path ) );
}

/**
 * Filter plugins_url() so that it works for plugins inside the shared VIP plugins directory or a theme directory.
 *
 * Props to the GigaOm dev team for coming up with this method.
 *
 * @param string $url Optional. Absolute URL to the plugins directory.
 * @param string $path Optional. Path relative to the plugins URL.
 * @param string $plugin Optional. The plugin file that you want the URL to be relative to.
 * @return string
 */
function wpcom_vip_plugins_url( $url = '', $path = '', $plugin = '' ) {
	static $content_dir, $vip_dir, $vip_url;

	if ( ! isset( $content_dir ) ) {
		// Be gentle on Windows, borrowed from core, see plugin_basename
		$content_dir = str_replace( '\\','/', WP_CONTENT_DIR ); // sanitize for Win32 installs
		$content_dir = preg_replace( '|/+|','/', $content_dir ); // remove any duplicate slash
	}

	if ( ! isset( $vip_dir ) ) {
		$vip_dir = $content_dir . '/themes/vip';
	}

	if ( ! isset( $vip_url ) ) {
		$vip_url = content_url( '/themes/vip' );
	}

	// Don't bother with non-VIP or non-path URLs
	if ( ! $plugin || 0 !== strpos( $plugin, $vip_dir ) ) {
		return $url;
	}

	if( 0 === strpos( $plugin, $vip_dir ) )
		$url_override = str_replace( $vip_dir, $vip_url, dirname( $plugin ) );
	elseif  ( 0 === strpos( $plugin, get_stylesheet_directory() ) )
		$url_override = str_replace(get_stylesheet_directory(), get_stylesheet_directory_uri(), dirname( $plugin ) );

	if ( isset( $url_override ) )
		$url = trailingslashit( $url_override ) . $path;

	return $url;
}
add_filter( 'plugins_url', 'wpcom_vip_plugins_url', 10, 3 );

/**
 * Return a URL for given VIP theme and path. Does not work with VIP shared plugins.
 *
 * @param string $path Optional. Path to suffix to the theme URL.
 * @param string $theme Optional. Name of the theme folder.
 * @return string|bool URL for the specified theme and path. If path doesn't exist, returns false.
 */
function wpcom_vip_theme_url( $path = '', $theme = '' ) {
	if ( empty( $theme ) )
		$theme = str_replace( 'vip/', '', get_stylesheet() );

	// We need to reference a file in the specified theme; style.css will almost always be there.
	$theme_folder = sprintf( '%s/themes/vip/%s', WP_CONTENT_DIR, $theme );
	$theme_file = $theme_folder . '/style.css';

	// For local environments where the theme isn't under /themes/vip/themename/
	$theme_folder_alt = sprintf( '%s/themes/%s', WP_CONTENT_DIR, $theme );
	$theme_file_alt = $theme_folder_alt . '/style.css';

	$path = ltrim( $path, '/' );

	// We pass in a dummy file to plugins_url even if it doesn't exist, otherwise we get a URL relative to the parent of the theme folder (i.e. /themes/vip/)
	if ( is_dir( $theme_folder ) ) {
		return plugins_url( $path, $theme_file );
	} elseif ( is_dir( $theme_folder_alt ) ) {
		return plugins_url( $path, $theme_file_alt );
	}

	return false;
}

/**
 * Return the directory path for a given VIP theme
 *
 * @link http://vip.wordpress.com/documentation/mobile-theme/ Developing for Mobile Phones and Tablets
 * @param string $theme Optional. Name of the theme folder
 * @return string Path for the specified theme
 */
function wpcom_vip_theme_dir( $theme = '' ) {
	if ( empty( $theme ) )
		$theme = get_stylesheet();

	// Simple sanity check, in case we get passed a lame path
	$theme = ltrim( $theme, '/' );
	$theme = str_replace( 'vip/', '', $theme );

	return sprintf( '%s/themes/vip/%s', WP_CONTENT_DIR, $theme );
}


/**
 * VIPs and other themes can declare the permastruct, tag and category bases in their themes.
 * This is done by filtering the option.
 *
 * To ensure we're using the freshest values, and that the option value is available earlier
 * than when the theme is loaded, we need to get each option, save it again, and then
 * reinitialize wp_rewrite.
 *
 * On WordPress.com this happens auto-magically when theme updates are deployed
 */
function wpcom_vip_local_development_refresh_wp_rewrite() {
	// No-op on WordPress.com
	if ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV )
		return;

	global $wp_rewrite;

	// Permastructs available in the options table and their core defaults
	$permastructs = array(
			'permalink_structure',
			'category_base',
			'tag_base',
		);

	$needs_flushing = false;

	foreach( $permastructs as $option_key ) {
		$filter = 'pre_option_' . $option_key;
		$callback = '_wpcom_vip_filter_' . $option_key;

		$option_value = get_option( $option_key );
		$filtered = has_filter( $filter, $callback );
		if ( $filtered ) {
			remove_filter( $filter, $callback, 99 );
			$raw_option_value = get_option( $option_key );
			add_filter( $filter, $callback, 99 );

			// Are we overriding this value in the theme?
			if ( $option_value != $raw_option_value ) {
				$needs_flushing = true;
				update_option( $option_key, $option_value );
			}
		}

	}

	// If the options are different from the theme let's fix it.
	if ( $needs_flushing ) {
		// Reconstruct WP_Rewrite and make sure we persist any custom endpoints, etc.
		$old_values = array();
		$custom_rules = array(
				'extra_rules',
				'non_wp_rules',
				'endpoints',
			);
		foreach( $custom_rules as $key ) {
			$old_values[$key] = $wp_rewrite->$key;
		}
		$wp_rewrite->init();
		foreach( $custom_rules as $key ) {
			$wp_rewrite->$key = array_merge( $old_values[$key], $wp_rewrite->$key );
		}

		flush_rewrite_rules( false );
	}
}
if ( defined( 'WPCOM_IS_VIP_ENV' ) && ! WPCOM_IS_VIP_ENV ) {
	add_action( 'init', 'wpcom_vip_local_development_refresh_wp_rewrite', 9999 );
}


/**
 * If you don't want people (de)activating plugins via this UI
 * and only want to enable plugins via wpcom_vip_load_plugin()
 * calls in your theme's functions.php file, then call this
 * function to disable this plugin's (de)activation links.
 */
function wpcom_vip_plugins_ui_disable_activation() {
	//The Class is not loaded on local environments
	if ( class_exists( "WPcom_VIP_Plugins_UI" )){
		WPcom_VIP_Plugins_UI()->activation_disabled = true;
	}
}

/**
 * Check if a plugin is versioned or not.
 *
 * @param string $plugin_slug A plugin slug. Can also be a file path.
 *
 * @return bool True if there is more than one version, false otherwise.
 */
function wpcom_vip_is_plugin_versioned( $plugin_slug ) {

	// Strip everything but the unversioned plugin slug.
	$plugin_slug = str_replace( '.php', '', end( explode( '/', $plugin_slug ) ) );

	// Get plugin versions.
	$plugins = wpcom_vip_get_plugin_versions();

	return ( 1 < $plugins[ $plugin_slug ] ) ? true : false;

}

/**
 * Get the latest version of a plugin.
 *
 * @param string $plugin_slug A plugin slug. Can also be a file path.
 *
 * @return bool|string Latest available version number, or false if it's not versioned.
 */
function wpcom_vip_get_plugin_latest_version( $plugin_slug ) {

	// Strip everything but the unversioned plugin slug.
	$plugin_slug = explode( '/', $plugin_slug );
	if ( is_array( $plugin_slug ) ) {
		$plugin_slug = end( $plugin_slug );
	}
	$plugin_slug = str_replace( '.php', '', $plugin_slug );

	// Get plugin version numbers.
	$plugin_versions = wpcom_vip_get_plugin_version_numbers();

	// Is this plugin versioned?
	if ( ! array_key_exists( $plugin_slug, $plugin_versions ) ) {
		return false;
	}

	// Sort the array, and return the last element, which will be the latest version.
	asort( $plugin_versions[ $plugin_slug ] );
	return end( $plugin_versions[ $plugin_slug ] );

}

/**
 * Get an array of plugins with multiple available versions, along with the
 * number of versions available.
 *
 * @return array An array of plugin slugs and number of versions. E.g.;
 *                  array(
 *                  	'facebook-instant-articles'=> 6,
 *                  	'brightcove-video-connect' => 5,
 *                  )
 */
function wpcom_vip_get_plugin_versions() {

	// for non-admin page use
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	// Get a simple array of available plugins.
	$plugins = array_keys( get_plugins( '/../themes/vip/plugins' ) );

	// Strip down to plugin slug.
	$plugins = array_map( function ( $plugin ) {
		return str_replace( '.php', '', end( explode( '/', $plugin ) ) );
	}, $plugins );

	// Count the number of versions we have for each plugin.
	$plugins = array_count_values( $plugins );

	return $plugins;

}

/**
 * Get a list of versioned plugins and the available versions.
 *
 * @return array List of plugins, with a list of versions for each. E.g.;
 *                    array(
 *                    	'facebook-instant-articles' => array(
 *                    		'2.11', '3.0', '3.1', '3.2', '3.3',
 *                    	),
 *                    	'apple-news' => array(
 *                    		'1.2', '1.2.6',
 *                    	),
 *                    )
 */
function wpcom_vip_get_plugin_version_numbers() {

	// for non-admin page use
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	// Get a simple array of available plugins.
	$plugins = array_keys( get_plugins( '/../themes/vip/plugins' ) );

	// Strip down to plugin slug, with version.
	$plugins = array_map( function ( $plugin ) {
		// Strip to the plugin folder, which includes the version number.
		return explode( '/', $plugin )[0];
	}, $plugins );

	// sort plugins
	sort( $plugins );

	// Now create a list of plugins and the versions we have.
	$plugin_versions = [];
	foreach ( $plugins as $plugin ) {
		$version = end( explode( '-', $plugin ) );

		// get rid of . for digit check
		$digits = str_replace( '.', '', $version );

		// check to see if it is a valid version
		$is_version = is_numeric( $digits );
		if ( ! $is_version ) {
			continue; // Not a versioned plugin.
		}

		// Remove the version to get the plugin slug.
		$plugin_slug = str_replace( '-' . $version, '', $plugin );

		// Add the version to the array.
		if ( ! isset( $plugin_versions[ $plugin_slug ] ) ) {
			$plugin_versions[ $plugin_slug ] = [ $version ];
		} else {
			$plugin_versions[ $plugin_slug ][] = $version;
		}

	}

	return $plugin_versions;

}

/**
 * Return the language code.
 *
 * Internal wpcom function that's used by the wpcom-sitemap plugin
 *
 * Note: Not overrideable in production - this function exists solely for dev environment
 * compatibility. To set blog language, use the Dashboard UI.
 *
 * @return string
 */
if ( ! function_exists( 'get_blog_lang_code' ) ) {
	function get_blog_lang_code() {
		return 'en';
	}
}

/**
 * Loads the built-in WP REST API endpoints in WordPress.com VIP context.
 */
function wpcom_vip_load_wp_rest_api() {
	if( defined( "WPCOM_VIP_WP_REST_API_LOADED" ) && true === WPCOM_VIP_WP_REST_API_LOADED ) {
		return;
	}

	add_action( 'rest_api_init', 'register_initial_settings',  10 );
	add_action( 'rest_api_init', 'create_initial_rest_routes', 99 );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-posts-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-attachments-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-post-types-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-post-statuses-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-revisions-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-taxonomies-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-terms-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-users-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-comments-controller.php' );
	require_once( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-settings-controller.php' );

	global $wpcom_json_api_production_versions;
	if ( isset( $wpcom_json_api_production_versions ) || defined( 'WPCOM_OEMBED_CACHE_GROUP' ) || defined( 'WPCOM_JOBS' ) ) {
		rest_get_server();
	}

	// Tell the rest of the WPCOM code base that we are loading the
	// WP REST API on the domain of a VIP client at /wp-json
	define( 'WPCOM_VIP_WP_REST_API_LOADED', true );

	if ( did_action( 'init' ) ) {
		// TODO: this probably shouldn't happen.
		do_action( 'rest_api_init' );
	}
}
