<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Apple Music / MusicKit Integration
 *
 * Apple uses JWT tokens signed with an ES256 private key (.p8 file content).
 * For playlist mutation we additionally need a Music User Token, which the
 * admin must obtain once via MusicKit JS in the browser and paste into
 * settings (Apple does not allow server-side OAuth-style refresh).
 */
class MW_Apple_Music {

    const SEARCH_URL   = 'https://api.music.apple.com/v1/catalog/de/search';
    const PLAYLIST_URL = 'https://api.music.apple.com/v1/me/library/playlists/%s/tracks';

    /** Generate developer JWT (valid 12h, cached) */
    public static function get_developer_token() {
        $cached = get_transient( 'mw_apple_dev_token' );
        if ( $cached ) return $cached;

        $team_id     = MW_Settings::get( 'apple_team_id' );
        $key_id      = MW_Settings::get( 'apple_key_id' );
        $private_key = MW_Settings::get( 'apple_private_key' );

        if ( ! $team_id || ! $key_id || ! $private_key ) return null;
        if ( ! function_exists( 'openssl_sign' ) ) return null;

        $header = array( 'alg' => 'ES256', 'kid' => $key_id );
        $now    = time();
        $payload = array(
            'iss' => $team_id,
            'iat' => $now,
            'exp' => $now + 12 * HOUR_IN_SECONDS,
        );

        $segments = array(
            self::base64url( json_encode( $header ) ),
            self::base64url( json_encode( $payload ) ),
        );
        $signing_input = implode( '.', $segments );

        // ES256 signature
        $key = openssl_pkey_get_private( $private_key );
        if ( ! $key ) return null;

        $signature = '';
        $success = openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );
        if ( ! $success ) return null;

        // Convert DER signature to JOSE-compliant raw R||S (64 bytes)
        $signature = self::der_to_jose( $signature );
        if ( ! $signature ) return null;

        $jwt = $signing_input . '.' . self::base64url( $signature );

        // Cache for slightly less than 12h
        set_transient( 'mw_apple_dev_token', $jwt, 11 * HOUR_IN_SECONDS );
        return $jwt;
    }

    /** Search Apple Music catalog */
    public static function search( $query, $limit = 6 ) {
        $token = self::get_developer_token();
        if ( ! $token ) return array();

        $url = self::SEARCH_URL . '?' . http_build_query( array(
            'term'  => $query,
            'types' => 'songs',
            'limit' => $limit,
        ) );

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
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
        // https://music.apple.com/de/album/.../i=1234567890  OR  /song/.../1234567890
        if ( preg_match( '#[?&]i=(\d+)#', $url, $m ) ) return $m[1];
        if ( preg_match( '#/song/[^/]+/(\d+)#', $url, $m ) ) return $m[1];
        return null;
    }

    public static function get_track( $track_id ) {
        $token = self::get_developer_token();
        if ( ! $token ) return null;

        $response = wp_remote_get( "https://api.music.apple.com/v1/catalog/de/songs/{$track_id}", array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
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

    /** Add track to user playlist (requires user token) */
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
                'Authorization' => 'Bearer ' . $dev_token,
                'Music-User-Token' => $user_token,
                'Content-Type' => 'application/json',
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
        return array( 'success' => false, 'error' => "HTTP {$code}: " . substr( wp_remote_retrieve_body( $response ), 0, 200 ) );
    }

    private static function base64url( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /** Convert OpenSSL DER ECDSA signature to JOSE raw R||S */
    private static function der_to_jose( $der ) {
        if ( strlen( $der ) < 8 || ord( $der[0] ) !== 0x30 ) return false;
        $offset = 2;
        if ( ord( $der[1] ) & 0x80 ) $offset += ord( $der[1] ) & 0x7f;

        if ( ord( $der[ $offset ] ) !== 0x02 ) return false;
        $r_len = ord( $der[ $offset + 1 ] );
        $r = substr( $der, $offset + 2, $r_len );
        $offset += 2 + $r_len;

        if ( ord( $der[ $offset ] ) !== 0x02 ) return false;
        $s_len = ord( $der[ $offset + 1 ] );
        $s = substr( $der, $offset + 2, $s_len );

        // Strip leading zero bytes; pad to 32 bytes
        $r = ltrim( $r, "\x00" );
        $s = ltrim( $s, "\x00" );
        $r = str_pad( $r, 32, "\x00", STR_PAD_LEFT );
        $s = str_pad( $s, 32, "\x00", STR_PAD_LEFT );
        return $r . $s;
    }

    private static function format_duration( $ms ) {
        $sec = (int) floor( $ms / 1000 );
        return sprintf( '%d:%02d', floor( $sec / 60 ), $sec % 60 );
    }
}
