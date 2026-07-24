<?php
/**
 * Plugin Name: Two Factor Guard
 * Description: Add TOTP-based two-factor authentication to WordPress with QR setup, backup codes and role-based enforcement.
 * Version: 1.0.0
 * Author: mrshahbazdev
 * Author URI: https://github.com/mrshahbazdev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: two-factor-guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TFG_VERSION', '1.0.0' );
define( 'TFG_FILE', __FILE__ );
define( 'TFG_DIR', plugin_dir_path( __FILE__ ) );
define( 'TFG_URL', plugin_dir_url( __FILE__ ) );

require_once TFG_DIR . 'includes/class-two-factor-guard.php';

add_action( 'plugins_loaded', array( 'Two_Factor_Guard', 'init' ) );
