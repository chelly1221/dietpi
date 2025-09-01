<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */
define('FORCE_SSL_ADMIN', false);
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'wordpress' );

/** Database password */
define( 'DB_PASSWORD', 'radar2960!' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

// 내부망 전용 모드 활성화
define('THREECHAN_API_INTERNAL_ONLY', true);

// 내부 API 서버 주소 (실제 내부망 IP로 변경하세요)
define('THREECHAN_API_INTERNAL_URL', 'http://192.168.10.101:8001');

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'A@ P2( ,[|:4?w+1NH/$Ie-dcA;%N%M;f7}@R`L0Ok+oF<lI&)ts{K@ufAy/)7@m' );
define( 'SECURE_AUTH_KEY',  'dAm,,7q@B_}W$p6SNLMw/HmVZM:jP$_<bzVHpB@%S4=~6A%nRNC0)I-hqRn)f>,[' );
define( 'LOGGED_IN_KEY',    'a+{fl$xOEVLIsfv#<R5:73Ls.oG]*e_1T`]3~QJip 3Egn-G}6sb[x[~c}Jg+(I`' );
define( 'NONCE_KEY',        ']7mr/a-M]Y]A}Byk.FDXlA@|>F=U]N8>W3roR`qvp=PqE>A^SnA0nsvsNYU;?3)i' );
define( 'AUTH_SALT',        '81%x*D?5}}waEmCax>>O#XC|}yS6nrHA/ (@U7 b=s.SnTn{?al+wk{?+F[N@d|C' );
define( 'SECURE_AUTH_SALT', 'zc h91Zik= )s@ =/[m?YBN+2yXbKh1)%!S7+SW?hjZ4fd.}W#2u)s6fJkcT6~_V' );
define( 'LOGGED_IN_SALT',   'ZGKGr$Xb?I%Y2|9x]Ub*]tIBP)!}v_s{}x$PIN(?T1.t&9-Z#2sv]FKPq8v</fUb' );
define( 'NONCE_SALT',       '`=#Z,9St}Ky}W6.8*`xL8 ${`2,SmuX)3w*M**Ciu$Glk$7 >)}S)C8(_/^nUk*b' );

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . './' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

@ini_set('memory_limit', '10024M');
@ini_set('max_execution_time', '6000');

