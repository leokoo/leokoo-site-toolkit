<?php
/**
 * Plugin Name:  Zehoro Toolkit
 * Plugin URI:   https://leokoo.com
 * Description:  Editorial toolkit — E-E-A-T Article schema, Table of Contents, FAQ, author boxes and content blocks. Coexists with your SEO plugin.
 * Version:      1.37.0
 * Author:       Leo Koo
 * Author URI:   https://leokoo.com
 * License:      GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  zehoro-toolkit
 * Domain Path:  /languages
 * Requires PHP: 7.4
 * Requires at least: 6.3
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Prevent the plugin running twice when WordPress loads two copies from
// different folder names (e.g. zehoro-toolkit-main/ alongside
// zehoro-toolkit/). The second copy returns immediately.
if ( defined( 'ZEHORO_VERSION' ) ) return;

define( 'ZEHORO_VERSION', '1.37.0' );
define( 'ZEHORO_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ZEHORO_URL',     plugin_dir_url( __FILE__ ) );

// Autoloader for Zehoro namespace
spl_autoload_register( function( $class ) {
    $prefix = 'Zehoro\\';
    $base_dir = ZEHORO_DIR . 'src/';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) return;
    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    if ( file_exists( $file ) ) require $file;
} );

// --- Auto-updater (GitHub) — NOT shipped to wordpress.org ---
// The wiring lives in updater.php, which `.distignore` excludes from the built package.
// It used to sit inline behind a `file_exists( vendor/… )` guard: functionally correct (the
// wp.org build has no vendor/, so it never ran) but Plugin Check scans STATICALLY and flags
// `plugin_updater_detected` on the reference regardless of the guard. Keeping the code in a
// file the package does not contain is what actually satisfies the rule.
// The condition names ONLY updater.php — the vendor check lives inside it. Naming the
// updater library's path here would leave its string in the distributed main file, which is
// what Plugin Check greps for; the package must mention it nowhere.
if ( file_exists( __DIR__ . '/updater.php' ) ) {
    // PUC keys the update off the MAIN plugin file's basename; inside updater.php `__FILE__`
    // would be updater.php, which silently breaks updates for every self-hosted install.
    $zehoro_updater_plugin_file = __FILE__;
    require_once __DIR__ . '/updater.php';
}

// Rename migrator (lkst_* → zehoro_*) runs idempotently early, so all option
// reads downstream find the canonical key. Re-fires on activation in case a
// site was updated via PUC bypassing activation, then re-activated. See
// specs/db-migration-zehoro-rename.md.
add_action( 'plugins_loaded', [ '\\Zehoro\\Migration\\ZehoroRenameMigrator', 'run' ], 1 );

// Load translations on `init` (WP 6.7+ — translating before `init` triggers a
// "just-in-time" notice; menus/settings render on admin_menu/admin_init, later).
// On wordpress.org this is also handled automatically from translate.wordpress.org.
add_action( 'init', function() {
    load_plugin_textdomain( 'zehoro-toolkit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Initialize the core plugin
add_action( 'plugins_loaded', function() {
    $plugin = new \Zehoro\Core\Plugin();
    $plugin->init();
} );

register_activation_hook( __FILE__, function() {
    \Zehoro\Migration\ZehoroRenameMigrator::run();
    \Zehoro\Core\Plugin::activate();
} );

register_deactivation_hook( __FILE__, function() {
    \Zehoro\Core\Plugin::deactivate();
} );
// Add Settings link on the plugin page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
    $settings_link = '<a href="admin.php?page=zehoro-dashboard">' . __( 'Settings', 'zehoro-toolkit' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );