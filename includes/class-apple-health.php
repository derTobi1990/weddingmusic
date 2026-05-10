<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Apple Token Health Monitoring
 *
 * SAFE: All hooks defer work to admin context only.
 * Daily check happens via WP-Cron (background), never on page load.
 */
class MW_Apple_Health {

    const TOKEN_LIFETIME_DAYS = 180;
    const WARN_DAYS_BEFORE    = 14;
    const URGENT_DAYS_BEFORE  = 3;
    const CRON_HOOK           = 'mw_apple_token_check';

    public static function init() {
        // Cron handler – fires only when scheduled, never on regular requests
        add_action( self::CRON_HOOK, array( __CLASS__, 'daily_check' ) );

        // Admin-only hooks
        if ( is_admin() ) {
            add_action( 'admin_init',     array( __CLASS__, 'maybe_schedule_cron' ) );
            add_action( 'admin_notices',  array( __CLASS__, 'admin_banner' ) );
            add_action( 'wp_ajax_mw_test_apple', array( __CLASS__, 'ajax_test' ) );
        }
    }

    /** Schedule cron only once, only in admin context */
    public static function maybe_schedule_cron() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public static function days_remaining() {
        $saved = (int) MW_Settings::get( 'apple_token_saved_at' );
        if ( ! $saved ) return null;
        $expiry = $saved + ( self::TOKEN_LIFETIME_DAYS * DAY_IN_SECONDS );
        return (int) floor( ( $expiry - time() ) / DAY_IN_SECONDS );
    }

    public static function status() {
        $s = MW_Settings::get();
        if ( ($s['apple_token_status'] ?? '') === 'expired' ) return 'failed';
        if ( empty( $s['apple_dev_token'] ) || empty( $s['apple_user_token'] ) ) return 'unknown';

        $days = self::days_remaining();
        if ( $days === null ) return 'unknown';
        if ( $days < 0 ) return 'expired';
        if ( $days <= self::URGENT_DAYS_BEFORE ) return 'urgent';
        if ( $days <= self::WARN_DAYS_BEFORE )  return 'warn';
        return 'ok';
    }

    public static function mark_expired() {
        $s = get_option( MW_Settings::OPTION, array() );
        $s['apple_token_status'] = 'expired';
        update_option( MW_Settings::OPTION, $s );
    }

    /** Run real API tests – called from AJAX or cron only, never on page load */
    public static function test_tokens() {
        $dev    = MW_Settings::get( 'apple_dev_token' );
        $user   = MW_Settings::get( 'apple_user_token' );

        if ( ! $dev )  return array( 'success' => false, 'error' => 'Developer Token fehlt.' );
        if ( ! $user ) return array( 'success' => false, 'error' => 'Music User Token fehlt.' );

        // Test 1: Search (only needs dev token)
        $search = MW_Apple_Music::search( 'test', 1 );
        if ( empty( $search ) ) {
            self::mark_expired();
            return array( 'success' => false, 'error' => 'Developer Token ungültig oder abgelaufen.' );
        }

        // Test 2: User-context call
        $response = wp_remote_get( 'https://api.music.apple.com/v1/me/storefront', array(
            'headers' => array(
                'Authorization'    => 'Bearer ' . $dev,
                'Music-User-Token' => $user,
                'Origin'           => 'https://music.apple.com',
            ),
            'timeout' => 10,
        ) );
        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 401 || $code === 403 ) {
            self::mark_expired();
            return array( 'success' => false, 'error' => 'Music User Token ungültig (HTTP ' . $code . ').' );
        }
        if ( $code !== 200 ) {
            return array( 'success' => false, 'error' => 'Apple antwortete mit HTTP ' . $code );
        }

        $s = get_option( MW_Settings::OPTION, array() );
        $s['apple_token_status'] = 'ok';
        update_option( MW_Settings::OPTION, $s );

        return array( 'success' => true, 'message' => '✔ Beide Tokens sind gültig und funktionieren!' );
    }

    public static function ajax_test() {
        check_ajax_referer( 'mw_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Forbidden' ) );

        $result = self::test_tokens();
        if ( $result['success'] ) wp_send_json_success( array( 'message' => $result['message'] ) );
        wp_send_json_error( array( 'message' => $result['error'] ) );
    }

    /** Daily cron – only called by WP-Cron, NEVER on regular page load */
    public static function daily_check() {
        $s = MW_Settings::get();
        if ( empty( $s['apple_dev_token'] ) || empty( $s['apple_user_token'] ) ) return;

        $status = self::status();
        if ( in_array( $status, array( 'ok', 'warn', 'urgent' ) ) ) {
            $test = self::test_tokens();
            if ( ! $test['success'] ) $status = 'expired';
        }

        $email         = $s['apple_notify_email'] ?: get_option( 'admin_email' );
        $now           = time();
        $last_notified = (int) ($s['apple_last_notified'] ?? 0);
        $cooldown      = 3 * DAY_IN_SECONDS;
        if ( $last_notified && ( $now - $last_notified ) < $cooldown ) return;

        $days = self::days_remaining();
        $send = false; $subject = ''; $body = '';

        if ( $status === 'urgent' || $status === 'expired' || $status === 'failed' ) {
            $send = true;
            $subject = '🚨 Apple-Music-Token läuft ab – Hochzeit Musikwünsche';
            $body    = self::email_body( 'urgent', $days, $status );
        } elseif ( $status === 'warn' ) {
            $send = true;
            $subject = '⚠ Apple-Music-Token läuft bald ab – Hochzeit Musikwünsche';
            $body    = self::email_body( 'warn', $days, $status );
        }

        if ( $send && $email ) {
            wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
            $s['apple_last_notified'] = $now;
            update_option( MW_Settings::OPTION, $s );
        }
    }

    private static function email_body( $level, $days, $status ) {
        $settings_url = admin_url( 'admin.php?page=musikwuensche-einstellungen' );
        if ( $status === 'expired' || $status === 'failed' ) {
            $intro = "<p><strong>Der Apple-Music-Token ist abgelaufen!</strong> Neue Musikwünsche können momentan nicht automatisch zur Apple-Music-Playlist hinzugefügt werden.</p>";
        } elseif ( $level === 'urgent' ) {
            $intro = "<p><strong>Der Apple-Music-Token läuft in nur noch {$days} Tagen ab.</strong> Bitte erneuere ihn jetzt.</p>";
        } else {
            $intro = "<p>Der Apple-Music-Token läuft in {$days} Tagen ab. Du kannst ihn jederzeit erneuern.</p>";
        }

        return <<<HTML
<html><body style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#333;max-width:600px;margin:0 auto;padding:20px">
    <h2 style="color:#c9a94d">🍎 Apple-Music-Token-Erneuerung erforderlich</h2>
    {$intro}
    <h3>So erneuerst du die Tokens:</h3>
    <ol>
        <li>In Chrome <a href="https://music.apple.com">music.apple.com</a> öffnen und einloggen</li>
        <li>Mit <code>F12</code> die Entwicklertools öffnen → Reiter „Network"</li>
        <li>Auf der Seite einen Klick machen damit Requests entstehen</li>
        <li>Einen Request zu <code>amp-api.music.apple.com</code> anklicken</li>
        <li>Die Werte <code>authorization</code> und <code>media-user-token</code> aus den Request Headers kopieren</li>
        <li>In den <a href="{$settings_url}">Plugin-Einstellungen</a> einfügen und speichern</li>
    </ol>
    <p style="margin-top:30px;color:#888;font-size:12px">
        Diese E-Mail wurde automatisch vom Plugin „Hochzeit Musikwünsche" auf alinaundtobias.de versendet.
    </p>
</body></html>
HTML;
    }

    /** Show banner only on plugin's own admin pages OR on the dashboard */
    public static function admin_banner() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) return;

        // Only show on Dashboard, Plugins page, and our own pages
        $allowed = array( 'dashboard', 'plugins' );
        $is_ours = strpos( $screen->id ?? '', 'musikwuensche' ) !== false;
        if ( ! $is_ours && ! in_array( $screen->id ?? '', $allowed ) ) return;

        $status = self::status();
        if ( in_array( $status, array( 'ok', 'unknown' ) ) ) return;

        $days = self::days_remaining();
        $url  = admin_url( 'admin.php?page=musikwuensche-einstellungen' );

        if ( $status === 'expired' || $status === 'failed' ) {
            $class = 'notice-error';
            $msg   = '<strong>🚨 Apple-Music-Token abgelaufen!</strong> Neue Musikwünsche werden nicht mehr zur Apple-Music-Playlist synchronisiert.';
        } elseif ( $status === 'urgent' ) {
            $class = 'notice-error';
            $msg   = "<strong>🚨 Apple-Music-Token läuft in {$days} Tag" . ( $days === 1 ? '' : 'en' ) . " ab!</strong> Bitte jetzt erneuern.";
        } else {
            $class = 'notice-warning';
            $msg   = "<strong>⚠ Apple-Music-Token läuft in {$days} Tagen ab.</strong> Erneuerung empfohlen.";
        }

        echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible">
            <p>🎵 ' . $msg . ' <a href="' . esc_url( $url ) . '" class="button button-small" style="margin-left:8px">Zur Einstellungsseite</a></p>
        </div>';
    }
}
