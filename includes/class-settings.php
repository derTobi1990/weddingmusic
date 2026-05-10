<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MW_Settings {

    const OPTION = 'mw_settings';

    public static function defaults() {
        return array(
            // Frontend Texte
            'form_title'       => 'Musikwünsche',
            'form_intro'       => 'Welcher Song darf auf unserer Hochzeit nicht fehlen? Such ihn direkt bei Spotify oder gib Titel und Interpret ein.',
            'label_name'       => 'Dein Name',
            'placeholder_name' => 'z.B. Max Mustermann',
            'label_search'     => 'Song suchen',
            'placeholder_search' => 'Titel oder Interpret eingeben…',
            'label_titel'      => 'Titel',
            'label_interpret'  => 'Interpret',
            'label_link'       => 'Spotify- oder Apple-Music-Link (optional)',
            'placeholder_link' => 'https://open.spotify.com/track/…',
            'btn_submit'       => 'Wunsch absenden',
            'btn_loading'      => 'Wird gesendet…',
            'success_title'    => 'Danke für deinen Musikwunsch!',
            'success_msg'      => 'Wir freuen uns – dein Song wandert direkt in unsere Playlist 🎵',

            // Spotify API
            'spotify_client_id'     => '',
            'spotify_client_secret' => '',
            'spotify_playlist_id'   => '',
            'spotify_refresh_token' => '',

            // Apple Music API (Browser-Tokens)
            'apple_dev_token'   => '',
            'apple_user_token'  => '',
            'apple_playlist_id' => '',
            'apple_token_saved_at' => 0,    // Unix timestamp when tokens were saved
            'apple_token_status'   => '',   // empty | 'ok' | 'expired'
            'apple_notify_email'   => get_option( 'admin_email' ),
            'apple_last_notified'  => 0,    // Unix timestamp of last reminder email

            // Farben
            'color_bg'      => '#1b3a3a',
            'color_accent'  => '#c9a94d',
            'color_text'    => '#f0e8d4',
            'color_muted'   => '#a8c0b0',
            'color_btn_bg'  => '#c9a94d',
            'color_btn_text' => '#1b2e2e',
        );
    }

    public static function get( $key = null ) {
        $saved    = get_option( self::OPTION, array() );
        $settings = wp_parse_args( $saved, self::defaults() );
        if ( $key !== null ) {
            return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
        }
        return $settings;
    }

    public static function save( $data ) {
        $defaults = self::defaults();
        $previous = self::get();
        $clean    = array();
        foreach ( $defaults as $key => $default ) {
            if ( ! isset( $data[ $key ] ) ) {
                $clean[ $key ] = $previous[ $key ] ?? $default;
                continue;
            }
            if ( strpos( $key, 'color_' ) === 0 ) {
                $clean[ $key ] = sanitize_hex_color( $data[ $key ] ) ?: $default;
            } elseif ( in_array( $key, array( 'apple_dev_token', 'apple_user_token' ) ) ) {
                $clean[ $key ] = trim( $data[ $key ] );
            } elseif ( in_array( $key, array( 'form_intro', 'success_msg' ) ) ) {
                $clean[ $key ] = sanitize_textarea_field( $data[ $key ] );
            } elseif ( $key === 'apple_notify_email' ) {
                $clean[ $key ] = sanitize_email( $data[ $key ] );
            } else {
                $clean[ $key ] = sanitize_text_field( $data[ $key ] );
            }
        }

        // If Apple tokens were updated, refresh the saved-at timestamp
        $tokens_changed = ( $clean['apple_dev_token'] !== ($previous['apple_dev_token'] ?? '') )
            || ( $clean['apple_user_token'] !== ($previous['apple_user_token'] ?? '') );
        if ( $tokens_changed && $clean['apple_dev_token'] && $clean['apple_user_token'] ) {
            $clean['apple_token_saved_at'] = time();
            $clean['apple_token_status']   = 'ok';
            $clean['apple_last_notified']  = 0;
        } else {
            // Preserve existing timestamps
            $clean['apple_token_saved_at'] = $previous['apple_token_saved_at'] ?? 0;
            $clean['apple_token_status']   = $previous['apple_token_status'] ?? '';
            $clean['apple_last_notified']  = $previous['apple_last_notified'] ?? 0;
        }

        update_option( self::OPTION, $clean );
    }

    public static function inline_css() {
        $s = self::get();
        return "
        <style>
        .mw-form-inner { background: {$s['color_bg']} !important; }
        .mw-form-title, .mw-field label { color: {$s['color_accent']} !important; }
        .mw-form-intro, .mw-success p { color: {$s['color_muted']} !important; }
        .mw-form-inner, .mw-field input, .mw-field textarea { color: {$s['color_text']} !important; }
        .mw-submit { background: {$s['color_btn_bg']} !important; color: {$s['color_btn_text']} !important; }
        .mw-success h3 { color: {$s['color_accent']} !important; }
        .mw-search-result.is-selected { border-color: {$s['color_accent']} !important; }
        </style>";
    }
}
