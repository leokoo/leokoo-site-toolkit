<?php
/**
 * Plugin Name:  Zehoro Toolkit
 * Plugin URI:   https://leokoo.com
 * Description:  Editorial toolkit for WordPress — Article schema (E-E-A-T), Table of Contents, FAQ, author boxes, and content blocks. The free base for Zehoro Toolkit Pro.
 * Version:      1.36.0
 * Author:       Leo Koo
 * Author URI:   https://leokoo.com
 * License:      GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  zehoro-toolkit
 * Domain Path:  /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Prevent the plugin running twice when WordPress loads two copies from
// different folder names (e.g. zehoro-toolkit-main/ alongside
// zehoro-toolkit/). The second copy returns immediately.
if ( defined( 'ZEHORO_VERSION' ) ) return;

define( 'ZEHORO_VERSION', '1.36.0' );
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

// --- Auto-updater (GitHub) ---
// Guarded by file_exists(): the wordpress.org build ships WITHOUT vendor/
// (excluded via .distignore), so this whole block is skipped there and
// wordpress.org serves updates instead. GitHub / self-hosted installs keep
// auto-updates because vendor/ is present. wp.org forbids a self-hosted
// updater in the distributed package — excluding vendor/ satisfies that
// without forking the source.
if ( file_exists( __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php' ) ) {
    require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

    $lkst_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/leokoo/zehoro-toolkit/',
        __FILE__,
        'zehoro-toolkit'
    );
    // Use the shared GitHub token if available (avoids API rate-limit / enables
    // private-repo updates). Read the CANONICAL key first — Pro reads/writes
    // `zehoro_pro_github_token`, so without this fallback a token set the canonical
    // way left Free's updater unauthenticated — then the legacy `lkst_*` key.
    // Authenticate on `plugins_loaded` (mirrors Pro) and NEVER forward an
    // ENCRYPTED token. Pro stores `zehoro_pro_github_token` encrypted at rest
    // (ciphertext prefixed `v1:`/`b64:`); Free has no decrypt path, so handing
    // that ciphertext to GitHub as a bearer token yields a 401 and breaks Free's
    // update check. Skip auth for a ciphertext value → fall back to anonymous
    // (rate-limited) public-repo checks. A hand-set plaintext ZEHORO_GITHUB_TOKEN
    // still authenticates. (See reference_wp_no_crypto_at_plugin_load doctrine.)
    add_action( 'plugins_loaded', function () use ( $lkst_updater ) {
        $gh_token = get_option( 'zehoro_pro_github_token', '' );
        if ( empty( $gh_token ) ) {
            $gh_token = get_option( 'lkst_pro_github_token', '' );
        }
        if ( empty( $gh_token ) && defined( 'ZEHORO_GITHUB_TOKEN' ) ) {
            $gh_token = ZEHORO_GITHUB_TOKEN;
        }
        if ( strncmp( (string) $gh_token, 'v1:', 3 ) === 0 || strncmp( (string) $gh_token, 'b64:', 4 ) === 0 ) {
            $gh_token = ''; // Pro's encrypted token — never send ciphertext as a credential.
        }
        if ( ! empty( $gh_token ) ) {
            $lkst_updater->setAuthentication( $gh_token );
        }
    }, 1 );
    $lkst_updater->setBranch( 'main' );
    $lkst_updater->getVcsApi()->enableReleaseAssets();
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