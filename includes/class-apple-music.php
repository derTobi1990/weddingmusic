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
     * Remove a track from a library playlist.
     * Apple's API requires the library-playlist-track-id, not the catalog ID.
     * We fetch the playlist's tracks first to find the matching one.
     */
    public static function remove_from_playlist( $catalog_track_id ) {
        $dev_token   = self::get_developer_token();
        $user_token  = MW_Settings::get( 'apple_user_token' );
        $playlist_id = MW_Settings::get( 'apple_playlist_id' );

        if ( ! $dev_token || ! $user_token || ! $playlist_id ) {
            return array( 'success' => false, 'error' => 'Apple Music nicht konfiguriert.' );
        }

        // Fetch playlist tracks to find the library-track-id matching this catalog track
        $tracks_url = "https://api.music.apple.com/v1/me/library/playlists/{$playlist_id}/tracks?limit=100&include=catalog";
        $response = wp_remote_get( $tracks_url, array(
            'headers' => array(
                'Authorization'    => 'Bearer ' . $dev_token,
                'Music-User-Token' => $user_token,
                'Origin'           => 'https://music.apple.com',
            ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['data'] ) ) {
            return array( 'success' => false, 'error' => 'Konnte Playlist-Tracks nicht laden.' );
        }

        // Find library-track that maps to our catalog track
        $library_track_id = null;
        foreach ( $body['data'] as $track ) {
            // Match via catalog ID in playParams or in relationships.catalog
            $play_params = $track['attributes']['playParams'] ?? array();
            if ( ! empty( $play_params['catalogId'] ) && $play_params['catalogId'] === $catalog_track_id ) {
                $library_track_id = $track['id'];
                break;
            }
            // Alternative: relationships.catalog.data[0].id
            $catalog = $track['relationships']['catalog']['data'][0]['id'] ?? null;
            if ( $catalog === $catalog_track_id ) {
                $library_track_id = $track['id'];
                break;
            }
        }

        if ( ! $library_track_id ) {
            // Track not in playlist – treat as success (already removed)
            return array( 'success' => true, 'message' => 'Song war nicht in der Playlist.' );
        }

        // Delete via library-tracks endpoint
        $del_url = "https://api.music.apple.com/v1/me/library/playlists/{$playlist_id}/tracks/{$library_track_id}";
        $del = wp_remote_request( $del_url, array(
            'method'  => 'DELETE',
            'headers' => array(
                'Authorization'    => 'Bearer ' . $dev_token,
                'Music-User-Token' => $user_token,
                'Origin'           => 'https://music.apple.com',
            ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $del ) ) {
            return array( 'success' => false, 'error' => $del->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $del );
        if ( $code === 204 || $code === 200 ) return array( 'success' => true );
        return array( 'success' => false, 'error' => "HTTP {$code}: " . substr( wp_remote_retrieve_body( $del ), 0, 200 ) );
    }

    private static function format_duration( $ms ) {
        $sec = (int) floor( $ms / 1000 );
        return sprintf( '%d:%02d', floor( $sec / 60 ), $sec % 60 );
    }
}
