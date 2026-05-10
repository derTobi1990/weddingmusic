<?php
/**
 * Plugin Name: Hochzeit Musikwünsche
 * Plugin URI:  https://alinaundtobias.de
 * Description: Sammelt Musikwünsche der Gäste mit Spotify/Apple Music Integration und automatischer Playlist-Synchronisation
 * Version:     1.5.0
 * Author:      Tobias Hirche
 * Text Domain: musikwuensche
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MW_VERSION',     '1.5.0' );
define( 'MW_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MW_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'MW_TABLE',       'musikwuensche' );

// GitHub Updater config (in wp-config.php überschreibbar)
if ( ! defined( 'MW_GITHUB_REPO' ) )  define( 'MW_GITHUB_REPO',  '' );
if ( ! defined( 'MW_GITHUB_TOKEN' ) ) define( 'MW_GITHUB_TOKEN', '' );

require_once MW_PLUGIN_DIR . 'includes/class-database.php';
require_once MW_PLUGIN_DIR . 'includes/class-settings.php';
require_once MW_PLUGIN_DIR . 'includes/class-wunsch.php';
require_once MW_PLUGIN_DIR . 'includes/class-spotify.php';
require_once MW_PLUGIN_DIR . 'includes/class-apple-music.php';
require_once MW_PLUGIN_DIR . 'includes/class-apple-health.php';
require_once MW_PLUGIN_DIR . 'includes/class-export.php';
require_once MW_PLUGIN_DIR . 'includes/class-updater.php';
require_once MW_PLUGIN_DIR . 'admin/admin-menu.php';
require_once MW_PLUGIN_DIR . 'frontend/shortcode.php';

register_activation_hook( __FILE__, array( 'MW_Database', 'install' ) );
register_uninstall_hook( __FILE__, array( 'MW_Database', 'uninstall' ) );
register_deactivation_hook( __FILE__, function () {
    if ( class_exists( 'MW_Apple_Health' ) ) {
        MW_Apple_Health::deactivate();
    }
} );

MW_Apple_Health::init();

if ( MW_GITHUB_REPO && is_admin() ) {
    new MW_Updater( __FILE__, MW_GITHUB_REPO, MW_GITHUB_TOKEN );
}
