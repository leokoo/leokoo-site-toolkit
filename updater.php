<?php
/**
 * GitHub self-update wiring — EXCLUDED from the wordpress.org package.
 *
 * wordpress.org forbids a plugin that updates itself from outside the directory, and Plugin
 * Check enforces it STATICALLY (`plugin_updater_detected`): a `file_exists( vendor/… )` guard
 * around the call is not enough, because the scanner reads the source it is given and flags the
 * reference whether or not it can execute. So the wiring lives in its own file, `.distignore`
 * keeps that file out of the built ZIP, and the wordpress.org build contains no updater code to
 * find. GitHub and self-hosted installs still auto-update, because this file is present there.
 *
 * @package Zehoro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Self-hosted installs have vendor/; a source checkout without `composer install` does not.
// Bail quietly rather than fatal — an absent updater is a missing convenience, not an error.
if ( ! file_exists( __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php' ) ) {
    return;
}

require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

// The MAIN plugin file, passed in by the caller — NOT __FILE__, which now resolves to this
// file since the wiring moved out of zehoro-toolkit.php. PUC keys the update off the plugin's
// basename, so getting this wrong silently breaks every self-hosted install's updates.
$zehoro_main_file = $zehoro_updater_plugin_file ?? dirname( __FILE__ ) . '/zehoro-toolkit.php';

$lkst_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/leokoo/zehoro-toolkit/',
    $zehoro_main_file,
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
