<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Apple Music Integration über Browser-Tokens (kein Developer-Account nötig).
 *
 * Beide Tokens werden manuell aus dem eingeloggten music.apple.com im Browser kopiert:
 *   - Developer Token: Bearer-Token aus dem Authorization-Header der Network-Requests
 *   - Music User Token: Cookie 'media-user-token'
 *
 * Tokens laufen nach ca. 180 Tagen ab und müssen dann manuell erneuert werden.
 */
class MW_Apple_Music {

    const SEARCH_URL   = 'https://api.music.apple.com/v1/catalog/de/search';
    const PLAYLIST_URL = 'https://api.music.apple.com/v1/me/library/playlists/%s/tracks';

    public static function get_developer_token() {
        $token = MW_Settings::get( 'apple_dev_token' );
        return $token ?: null;
    }

    public static function search( $query, $limit = 6 ) {
        $token = self::get_developer_token();
        if ( ! $token ) return array();

        $url = self::SEARCH_URL . '?' . http_build_query( array(
            'term'  => $query,
            'types' => 'songs',
            'limit' => $limit,
        ) );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Origin'        => 'https://music.apple.com',
            ),
            'timeout' => 10,
        ) );
        if ( is_wp_error( $response ) ) return array();
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['results']['songs']['data'] ) ) return array();

        $results = array();
        foreach ( $body['results']['songs']['data'] as $song ) {
            $a = $song['attributes'] ?? array();
            $results[] = array(
                'id'        => $song['id'],
                'titel'     => $a['name'] ?? '',
                'interpret' => $a['artistName'] ?? '',
                'album'     => $a['albumName'] ?? '',
                'cover'     => isset( $a['artwork']['url'] )
                    ? str_replace( array( '{w}', '{h}' ), array( 100, 100 ), $a['artwork']['url'] )
                    : '',
                'url'       => $a['url'] ?? '',
                'duration'  => self::format_duration( $a['durationInMillis'] ?? 0 ),
            );
        }
        return $results;
    }

    public static function extract_track_id( $url ) {
        if ( preg_match( '#[?&]i=(\d+)#', $url, $m ) ) return $m[1];
        if ( preg_match( '#/song/[^/]+/(\d+)#', $url, $m ) ) return $m[1];
        return null;
    }

    public static function get_track( $track_id ) {
        $token = self::get_developer_token();
        if ( ! $token ) return null;

        $response = wp_remote_get( "https://api.music.apple.com/v1/catalog/de/songs/{$track_id}", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Origin'        => 'https://music.apple.com',
            ),
            'timeout' => 10,
        ) );
        if ( is_wp_error( $response ) ) return null;
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['data'][0] ) ) return null;
        $song = $body['data'][0];
        $a    = $song['attributes'] ?? array();
        return array(
            'id'        => $song['id'],
            'titel'     => $a['name'] ?? '',
            'interpret' => $a['artistName'] ?? '',
            'url'       => $a['url'] ?? '',
        );
    }

    /** Fetch user's library playlists (only library, only editable) */
    public static function list_user_playlists() {
        $dev  = self::get_developer_token();
        $user = MW_Settings::get( 'apple_user_token' );
        if ( ! $dev || ! $user ) return array();

        $response = wp_remote_get( 'https://api.music.apple.com/v1/me/library/playlists?limit=100', array(
            'headers' => array(
                'Authorization'    => 'Bearer ' . $dev,
                'Music-User-Token' => $user,
                'Origin'           => 'https://music.apple.com',
            ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $response ) ) return array();
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) return array();

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['data'] ) ) return array();

        $playlists = array();
        foreach ( $body['data'] as $p ) {
            $a = $p['attributes'] ?? array();
            // Only editable playlists (canEdit = true)
            if ( empty( $a['canEdit'] ) ) continue;
            $playlists[] = array(
                'id'   => $p['id'],
                'name' => $a['name'] ?? '(unbenannt)',
            );
        }
        return $playlists;
    }

    public static function add_to_playlist( $track_id ) {
        $dev_token   = self::get_developer_token();
        $user_token  = MW_Settings::get( 'apple_user_token' );
        $playlist_id = MW_Settings::get( 'apple_playlist_id' );

        if ( ! $dev_token || ! $user_token || ! $playlist_id ) {
            return array( 'success' => false, 'error' => 'Apple Music nicht vollständig konfiguriert.' );
        }

        $url = sprintf( self::PLAYLIST_URL, $playlist_id );
        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization'    => 'Bearer ' . $dev_token,
                'Music-User-Token' => $user_token,
                'Origin'           => 'https://music.apple.com',
                'Content-Type'     => 'application/json',
            ),
            'body' => json_encode( array(
                'data' => array(
                    array( 'id' => $track_id, 'type' => 'songs' ),
                ),
            ) ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 201 || $code === 204 ) {
            return array( 'success' => true );
        }
        // Auto-detect expired tokens
        if ( $code === 401 || $code === 403 ) {
            if ( class_exists( 'MW_Apple_Health' ) ) MW_Apple_Health::mark_expired();
            return array( 'success' => false, 'error' => 'Apple-Music-Token abgelaufen (HTTP ' . $code . ')' );
        }
        return array( 'success' => false, 'error' => "HTTP {$code}: " . substr( wp_remote_retrieve_body( $response ), 0, 200 ) );
    }

    /**
     * Apple Music API does NOT support DELETE on library playlists.
     * This is a documented limitation – Apple only allows POST for library mutations.
     * The track must be removed manually in the Apple Music app.
     *
     * @return array always returns success=false with explanation
     */
    public static function remove_from_playlist( $catalog_track_id ) {
        return array(
            'success'  => false,
            'error'    => 'Apple Music API erlaubt kein automatisches Entfernen – bitte manuell in Apple Music löschen.',
            'manual_required' => true,
        );
    }

    private static function format_duration( $ms ) {
        $sec = (int) floor( $ms / 1000 );
        return sprintf( '%d:%02d', floor( $sec / 60 ), $sec % 60 );
    }
}
