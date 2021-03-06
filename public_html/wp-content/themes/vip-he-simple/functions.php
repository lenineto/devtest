<?php

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
require_once( WP_CONTENT_DIR . '/themes/vip-he-simple/plugins/vip-helper.php' );

function mvp_setup() {
	/*
	 * Let WordPress manage the document title.
	 * By adding theme support, we declare that this theme does not use a
	 * hard-coded <title> tag in the document head, and expect WordPress to
	 * provide it for us.
	 */
	add_theme_support( 'title-tag' );
}
add_action( 'after_setup_theme', 'mvp_setup' );

/**
 * Enqueue scripts and styles.
 */
function mvp_scripts() {
	wp_enqueue_style( 'mvp-style', get_stylesheet_uri() );
}
add_action( 'wp_enqueue_scripts', 'mvp_scripts' );

//add_filter('show_admin_bar', '__return_false');

/* Flush rewrite rules for custom post types. */
add_action( 'after_switch_theme', 'flush_rewrite_rules' );
