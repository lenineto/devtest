<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'dev-test' );

/** MySQL database username */
define( 'DB_USER', 'wp' );

/** MySQL database password */
define( 'DB_PASSWORD', 'wp' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'y4ncBa.:J1]7[Cm8#tGFU]UkV>,Q4Y1YWL&jk0^{U:$}$3^a2p3_pp,*D21@HcE~' );
define( 'SECURE_AUTH_KEY',   'Jo8SoD&q/vb2,PQnUZYqK$v?{k}#/9nNq++.Fuy1:9%6rh-%zOl67&q[p11e]4O-' );
define( 'LOGGED_IN_KEY',     'e`1&!BHo3g0JDs)~iN`I`~L`=%WFjg07AqK0>Knhdhe(b:_E9-Xw*x6-NfFhWWnV' );
define( 'NONCE_KEY',         'fPN;hg,Wh9vohs, (vp:: uJQbm5w^`%-n{`1gICC3@#XuO*x;2|y=a{s.Q/*rZ]' );
define( 'AUTH_SALT',         'G2l!k^Dat)Oxp}Vl%VjNgg:`ZK/2(s@3{<<lhpr_m1( _wxT&G4Rm?4O1q}|Ac;v' );
define( 'SECURE_AUTH_SALT',  'ca:hN2*e]uGpL*&x(*,5=QTrB4;s7tl)9}BKDCf)IOXPI.Yva=71j]axf0SlIhov' );
define( 'LOGGED_IN_SALT',    'tC;cUeYKw2fMZw}>z||cB8bbc0w{StkD=mSig|SQ#oW |G&Xu`%:/<id6XU_-fae' );
define( 'NONCE_SALT',        'w? CBxT4RRO}~c)7WD%?wvVN<f,.%-+tgS`a-Ch8|A?vT<U+=+nA(.o@TpNHZB2<' );
define( 'WP_CACHE_KEY_SALT', 'u:f3)v]r:)8*TgH%$X/3qo~KW[@iXh4{u&4Ifd5gm0Liox(q~f%hmi#]T&Gcs`)%' );

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


define( 'WP_DEBUG', True );
define( 'SCRIPT_DEBUG', true );


define( 'WP_DEBUG_LOG', True );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', True );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
