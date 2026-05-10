<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MW_Database {

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . MW_TABLE;

        dbDelta( "CREATE TABLE {$table} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            titel           VARCHAR(255) NOT NULL,
            interpret       VARCHAR(255) NOT NULL,
            spotify_id      VARCHAR(64)  DEFAULT NULL,
            spotify_url     VARCHAR(500) DEFAULT NULL,
            apple_id        VARCHAR(64)  DEFAULT NULL,
            apple_url       VARCHAR(500) DEFAULT NULL,
            anzahl_wuensche INT UNSIGNED NOT NULL DEFAULT 1,
            wunsch_namen    TEXT,
            ist_brautpaar   TINYINT(1) NOT NULL DEFAULT 0,
            spotify_added   TINYINT(1) NOT NULL DEFAULT 0,
            apple_added     TINYINT(1) NOT NULL DEFAULT 0,
            erstellt_am     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY titel_interpret (titel(100), interpret(100))
        ) {$charset};" );

        update_option( 'mw_db_version', MW_VERSION );
    }

    public static function tables_exist() {
        global $wpdb;
        $table = $wpdb->prefix . MW_TABLE;
        return $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
    }

    public static function uninstall() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . MW_TABLE );
        delete_option( 'mw_db_version' );
        delete_option( 'mw_settings' );
        delete_option( 'mw_spotify_token' );
        delete_option( 'mw_apple_token' );
    }
}
