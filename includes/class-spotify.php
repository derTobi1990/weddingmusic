<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Spotify Web API Wrapper
 *
 * Two flows are used:
 *  1. Client Credentials (search) – server-side only, no user context.
 *  2. Authorization Code (playlist add) – requires admin to authenticate once,
 *     then uses refresh_token saved in settings to keep access alive.
 */
class MW_Spotify {

    const SEARCH_URL  = 'https://api.spotify.com/v1/search';
    const TOKEN_URL   = 'https://accounts.spotify.com/api/token';
    const AUTHORIZE_URL = 'https://accounts.spotify.com/authorize';
    const PLAYLIST_URL = 'https://api.spotify.com/v1/playlists/%s/tracks';

    /** App-only token for /search (Client Credentials) – cached 50 min */
    public static function get_app_token() {
        $cached = get_transient( 'mw_spotify_app_token' );
        if ( $cached ) return $cached;

        $client_id     = MW_Settings::get( 'spotify_client_id' );
        $client_secret = MW_Settings::get( 'spotify_client_secret' );
        if ( ! $client_id || ! $client_secret ) return null;

        $response = wp_remote_post( self::TOKEN_URL, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array( 'grant_type' => 'client_credentials' ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $response ) ) return null;
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) return null;

        set_transient( 'mw_spotify_app_token', $body['access_token'], 50 * MINUTE_IN_SECONDS );
        return $body['access_token'];
    }

    /** User-context access token via stored refresh token – cached 50 min */
    public static function get_user_token() {
        $cached = get_transient( 'mw_spotify_user_token' );
        if ( $cached ) return $cached;

        $client_id     = MW_Settings::get( 'spotify_client_id' );
        $client_secret = MW_Settings::get( 'spotify_client_secret' );
        $refresh       = MW_Settings::get( 'spotify_refresh_token' );
        if ( ! $client_id || ! $client_secret || ! $refresh ) return null;

        $response = wp_remote_post( self::TOKEN_URL, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh,
            ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $response ) ) return null;
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) return null;

        if ( ! empty( $body['refresh_token'] ) ) {
            $settings = MW_Settings::get();
            $settings['spotify_refresh_token'] = $body['refresh_token'];
            update_option( MW_Settings::OPTION, $settings );
        }

        set_transient( 'mw_spotify_user_token', $body['access_token'], 50 * MINUTE_IN_SECONDS );
        return $body['access_token'];
    }

    /** Search tracks – returns simplified array */
    public static function search( $query, $limit = 6 ) {
        $token = self::get_app_token();
        if ( ! $token ) return array();

        $url = self::SEARCH_URL . '?' . http_build_query( array(
            'q'     => $query,
            'type'  => 'track',
            'limit' => $limit,
            'market' => 'DE',
        ) );

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
            'timeout' => 10,
        ) );
        if ( is_wp_error( $response ) ) return array();
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['tracks']['items'] ) ) return array();

        $results = array();
        foreach ( $body['tracks']['items'] as $track ) {
            $results[] = array(
                'id'        => $track['id'],
                'titel'     => $track['name'],
                'interpret' => implode( ', ', wp_list_pluck( $track['artists'], 'name' ) ),
                'album'     => $track['album']['name'] ?? '',
                'cover'     => $track['album']['images'][2]['url'] ?? ( $track['album']['images'][0]['url'] ?? '' ),
                'url'       => $track['external_urls']['spotify'] ?? '',
                'preview'   => $track['preview_url'] ?? '',
                'duration'  => self::format_duration( $track['duration_ms'] ?? 0 ),
            );
        }
        return $results;
    }

    /** Extract Spotify track ID from URL */
    public static function extract_track_id( $url ) {
        if ( preg_match( '#spotify\.com/track/([A-Za-z0-9]+)#', $url, $m ) ) return $m[1];
        if ( preg_match( '#^spotify:track:([A-Za-z0-9]+)$#', $url, $m ) ) return $m[1];
        return null;
    }

    /** Fetch single track info by ID */
    public static function get_track( $track_id ) {
        $token = self::get_app_token();
        if ( ! $token ) return null;

        $response = wp_remote_get( "https://api.spotify.com/v1/tracks/{$track_id}?market=DE", array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
            'timeout' => 10,
        ) );
        if ( is_wp_error( $response ) ) return null;
        $track = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $track['id'] ) ) return null;

        return array(
            'id'        => $track['id'],
            'titel'     => $track['name'],
            'interpret' => implode( ', ', wp_list_pluck( $track['artists'], 'name' ) ),
            'url'       => $track['external_urls']['spotify'] ?? '',
        );
    }

    /** Add track to configured playlist */
    public static function add_to_playlist( $track_id ) {
        $token       = self::get_user_token();
        $playlist_id = MW_Settings::get( 'spotify_playlist_id' );
        if ( ! $token || ! $playlist_id ) {
            return array( 'success' => false, 'error' => 'Spotify nicht vollständig konfiguriert.' );
        }

        $url = sprintf( self::PLAYLIST_URL, $playlist_id );
        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode( array( 'uris' => array( "spotify:track:{$track_id}" ) ) ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 201 || $code === 200 ) {
            return array( 'success' => true );
        }
        $body = wp_remote_retrieve_body( $response );
        return array( 'success' => false, 'error' => "HTTP {$code}: " . substr( $body, 0, 200 ) );
    }

    /** Build OAuth authorization URL for the admin to authorize the playlist */
    public static function build_auth_url( $redirect_uri ) {
        $client_id = MW_Settings::get( 'spotify_client_id' );
        if ( ! $client_id ) return null;
        return self::AUTHORIZE_URL . '?' . http_build_query( array(
            'client_id'     => $client_id,
            'response_type' => 'code',
            'redirect_uri'  => $redirect_uri,
            'scope'         => 'playlist-modify-public playlist-modify-private',
            'state'         => wp_create_nonce( 'mw_spotify_oauth' ),
        ) );
    }

    /** Exchange code for refresh token after admin OAuth callback */
    public static function exchange_code( $code, $redirect_uri ) {
        $client_id     = MW_Settings::get( 'spotify_client_id' );
        $client_secret = MW_Settings::get( 'spotify_client_secret' );

        $response = wp_remote_post( self::TOKEN_URL, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $redirect_uri,
            ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $response ) ) return false;
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['refresh_token'] ) ) return false;

        $settings = MW_Settings::get();
        $settings['spotify_refresh_token'] = $body['refresh_token'];
        update_option( MW_Settings::OPTION, $settings );

        return true;
    }

    private static function format_duration( $ms ) {
        $sec = (int) floor( $ms / 1000 );
        return sprintf( '%d:%02d', floor( $sec / 60 ), $sec % 60 );
    }
}
