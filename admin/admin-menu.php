<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MW_Admin {

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_init',            array( __CLASS__, 'handle_forms' ) );

        add_action( 'wp_ajax_mw_sync_spotify', array( __CLASS__, 'ajax_sync_spotify' ) );
        add_action( 'wp_ajax_mw_sync_apple',   array( __CLASS__, 'ajax_sync_apple' ) );
        add_action( 'wp_ajax_mw_admin_search', array( __CLASS__, 'ajax_admin_search' ) );
        add_action( 'admin_post_mw_export_xlsx', array( 'MW_Export', 'download_xlsx' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_spotify_callback' ) );
    }

    public static function register_menu() {
        add_menu_page(
            'Musikwünsche', '🎵 Musikwünsche', 'manage_options',
            'musikwuensche', array( __CLASS__, 'page_liste' ),
            'dashicons-format-audio', 31
        );
        add_submenu_page( 'musikwuensche', 'Wunschliste',  'Wunschliste',
            'manage_options', 'musikwuensche', array( __CLASS__, 'page_liste' ) );
        add_submenu_page( 'musikwuensche', 'Neuer Wunsch', '+ Neuer Wunsch',
            'manage_options', 'musikwuensche-neu', array( __CLASS__, 'page_neu' ) );
        add_submenu_page( 'musikwuensche', 'Einstellungen', '⚙ Einstellungen',
            'manage_options', 'musikwuensche-einstellungen', array( __CLASS__, 'page_einstellungen' ) );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'musikwuensche' ) === false ) return;
        wp_enqueue_style( 'mw-admin', MW_PLUGIN_URL . 'assets/css/admin.css', array(), MW_VERSION );
        wp_enqueue_script( 'mw-admin', MW_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), MW_VERSION, true );
        wp_localize_script( 'mw-admin', 'MW_ADMIN', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mw_admin_nonce' ),
        ) );
    }

    public static function handle_forms() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $action = isset( $_POST['mw_action'] ) ? $_POST['mw_action'] : '';
        if ( ! $action ) return;
        if ( ! check_admin_referer( 'mw_nonce', 'mw_nonce_field' ) ) wp_die( 'Sicherheitsfehler' );

        switch ( $action ) {
            case 'add_wunsch':
                $data = array(
                    'titel'         => $_POST['titel'] ?? '',
                    'interpret'     => $_POST['interpret'] ?? '',
                    'wunsch_name'   => 'Brautpaar',
                    'ist_brautpaar' => 1,
                );
                if ( ! empty( $_POST['spotify_url'] ) ) {
                    $data['spotify_url'] = $_POST['spotify_url'];
                    $data['spotify_id']  = MW_Spotify::extract_track_id( $_POST['spotify_url'] );
                }
                if ( ! empty( $_POST['apple_url'] ) ) {
                    $data['apple_url'] = $_POST['apple_url'];
                    $data['apple_id']  = MW_Apple_Music::extract_track_id( $_POST['apple_url'] );
                }
                MW_Wunsch::add_or_increment( $data );
                wp_redirect( admin_url( 'admin.php?page=musikwuensche&msg=added' ) );
                exit;

            case 'edit_wunsch':
                MW_Wunsch::update( absint( $_POST['id'] ), $_POST );
                wp_redirect( admin_url( 'admin.php?page=musikwuensche&msg=updated' ) );
                exit;

            case 'delete_wunsch':
                MW_Wunsch::delete( absint( $_POST['id'] ) );
                wp_redirect( admin_url( 'admin.php?page=musikwuensche&msg=deleted' ) );
                exit;

            case 'save_settings':
                MW_Settings::save( $_POST );
                wp_redirect( admin_url( 'admin.php?page=musikwuensche-einstellungen&msg=saved' ) );
                exit;

            case 'repair_db':
                MW_Database::install();
                wp_redirect( admin_url( 'admin.php?page=musikwuensche&msg=repaired' ) );
                exit;
        }
    }

    public static function handle_spotify_callback() {
        if ( ! isset( $_GET['mw_spotify_callback'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( ! empty( $_GET['code'] ) ) {
            $redirect_uri = self::spotify_redirect_uri();
            $ok = MW_Spotify::exchange_code( sanitize_text_field( $_GET['code'] ), $redirect_uri );
            wp_redirect( admin_url( 'admin.php?page=musikwuensche-einstellungen&msg=' . ( $ok ? 'spotify_ok' : 'spotify_fail' ) ) );
            exit;
        }
    }

    public static function spotify_redirect_uri() {
        return add_query_arg( array( 'mw_spotify_callback' => 1 ), admin_url() );
    }

    public static function ajax_sync_spotify() {
        check_ajax_referer( 'mw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Forbidden' ) );

        $id = absint( $_POST['id'] ?? 0 );
        $w  = MW_Wunsch::get( $id );
        if ( ! $w ) wp_send_json_error( array( 'message' => 'Wunsch nicht gefunden.' ) );

        $track_id = $w->spotify_id;
        if ( ! $track_id ) {
            $results = MW_Spotify::search( $w->titel . ' ' . $w->interpret, 1 );
            if ( ! $results ) wp_send_json_error( array( 'message' => 'Song bei Spotify nicht gefunden.' ) );
            $track_id = $results[0]['id'];
            global $wpdb;
            $wpdb->update( $wpdb->prefix . MW_TABLE, array(
                'spotify_id'  => $track_id,
                'spotify_url' => $results[0]['url'],
            ), array( 'id' => $id ), array( '%s','%s' ), array( '%d' ) );
        }

        $result = MW_Spotify::add_to_playlist( $track_id );
        if ( $result['success'] ) {
            MW_Wunsch::mark_spotify_added( $id );
            wp_send_json_success( array( 'message' => 'Zur Spotify-Playlist hinzugefügt.' ) );
        }
        wp_send_json_error( array( 'message' => $result['error'] ) );
    }

    public static function ajax_sync_apple() {
        check_ajax_referer( 'mw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Forbidden' ) );

        $id = absint( $_POST['id'] ?? 0 );
        $w  = MW_Wunsch::get( $id );
        if ( ! $w ) wp_send_json_error( array( 'message' => 'Wunsch nicht gefunden.' ) );

        $track_id = $w->apple_id;
        if ( ! $track_id ) {
            $results = MW_Apple_Music::search( $w->titel . ' ' . $w->interpret, 1 );
            if ( ! $results ) wp_send_json_error( array( 'message' => 'Song bei Apple Music nicht gefunden.' ) );
            $track_id = $results[0]['id'];
            global $wpdb;
            $wpdb->update( $wpdb->prefix . MW_TABLE, array(
                'apple_id'  => $track_id,
                'apple_url' => $results[0]['url'],
            ), array( 'id' => $id ), array( '%s','%s' ), array( '%d' ) );
        }

        $result = MW_Apple_Music::add_to_playlist( $track_id );
        if ( $result['success'] ) {
            MW_Wunsch::mark_apple_added( $id );
            wp_send_json_success( array( 'message' => 'Zur Apple-Music-Playlist hinzugefügt.' ) );
        }
        wp_send_json_error( array( 'message' => $result['error'] ) );
    }

    public static function ajax_admin_search() {
        check_ajax_referer( 'mw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Forbidden' ) );

        $query = sanitize_text_field( $_POST['query'] ?? '' );
        if ( strlen( $query ) < 2 ) wp_send_json_success( array() );

        $spotify_results = MW_Spotify::search( $query, 8 );
        $apple_results   = MW_Apple_Music::search( $query, 8 );

        $merged = array();
        foreach ( $spotify_results as $r ) {
            $key = strtolower( trim( $r['titel'] ) . '|' . trim( $r['interpret'] ) );
            $merged[ $key ] = array(
                'titel'       => $r['titel'],
                'interpret'   => $r['interpret'],
                'cover'       => $r['cover'],
                'duration'    => $r['duration'],
                'spotify_url' => $r['url'],
                'apple_url'   => '',
                'sources'     => array( 'spotify' ),
            );
        }
        foreach ( $apple_results as $r ) {
            $key = strtolower( trim( $r['titel'] ) . '|' . trim( $r['interpret'] ) );
            if ( isset( $merged[ $key ] ) ) {
                $merged[ $key ]['apple_url'] = $r['url'];
                $merged[ $key ]['sources'][] = 'apple';
                if ( ! $merged[ $key ]['cover'] ) $merged[ $key ]['cover'] = $r['cover'];
            } else {
                $merged[ $key ] = array(
                    'titel'       => $r['titel'],
                    'interpret'   => $r['interpret'],
                    'cover'       => $r['cover'],
                    'duration'    => $r['duration'],
                    'spotify_url' => '',
                    'apple_url'   => $r['url'],
                    'sources'     => array( 'apple' ),
                );
            }
        }
        usort( $merged, function ( $a, $b ) {
            return count( $b['sources'] ) - count( $a['sources'] );
        } );

        wp_send_json_success( array_slice( array_values( $merged ), 0, 10 ) );
    }

    public static function page_liste()         { include MW_PLUGIN_DIR . 'admin/views/liste.php'; }
    public static function page_neu()            { include MW_PLUGIN_DIR . 'admin/views/neu.php'; }
    public static function page_einstellungen()  { include MW_PLUGIN_DIR . 'admin/views/einstellungen.php'; }
}

MW_Admin::init();
