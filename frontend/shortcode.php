<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MW_Shortcode {

    public static function init() {
        add_shortcode( 'musikwuensche', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        add_action( 'wp_ajax_mw_search',         array( __CLASS__, 'ajax_search' ) );
        add_action( 'wp_ajax_nopriv_mw_search',  array( __CLASS__, 'ajax_search' ) );
        add_action( 'wp_ajax_mw_submit',         array( __CLASS__, 'ajax_submit' ) );
        add_action( 'wp_ajax_nopriv_mw_submit',  array( __CLASS__, 'ajax_submit' ) );

        add_filter( 'fusion_builder_shortcode_output', 'do_shortcode', 11 );
        add_filter( 'the_content',                     'do_shortcode', 11 );
        add_filter( 'widget_text',                     'do_shortcode', 11 );
    }

    public static function enqueue_assets() {
        wp_enqueue_style( 'mw-frontend', MW_PLUGIN_URL . 'assets/css/frontend.css', array(), MW_VERSION );
        wp_enqueue_script( 'mw-frontend', MW_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), MW_VERSION, true );
        wp_localize_script( 'mw-frontend', 'MW', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mw_frontend_nonce' ),
        ) );
    }

    public static function render( $atts ) {
        $s = MW_Settings::get();
        ob_start();
        echo MW_Settings::inline_css();
        ?>
        <div class="mw-form-wrap">
            <div class="mw-form-inner">
                <h2 class="mw-form-title"><?php echo esc_html( $s['form_title'] ); ?></h2>
                <p class="mw-form-intro"><?php echo esc_html( $s['form_intro'] ); ?></p>

                <form id="mw-form" novalidate>
                    <div class="mw-field">
                        <label><?php echo esc_html( $s['label_name'] ); ?></label>
                        <input type="text" id="mw-name" name="name"
                            placeholder="<?php echo esc_attr( $s['placeholder_name'] ); ?>" required>
                    </div>

                    <div class="mw-field mw-search-field">
                        <label><?php echo esc_html( $s['label_search'] ); ?></label>
                        <input type="text" id="mw-search" name="search"
                            placeholder="<?php echo esc_attr( $s['placeholder_search'] ); ?>"
                            autocomplete="off">
                        <div id="mw-search-results"></div>
                    </div>

                    <div class="mw-divider">— oder manuell eingeben —</div>

                    <div class="mw-field-row">
                        <div class="mw-field">
                            <label><?php echo esc_html( $s['label_titel'] ); ?></label>
                            <input type="text" id="mw-titel" name="titel">
                        </div>
                        <div class="mw-field">
                            <label><?php echo esc_html( $s['label_interpret'] ); ?></label>
                            <input type="text" id="mw-interpret" name="interpret">
                        </div>
                    </div>

                    <div class="mw-field">
                        <label><?php echo esc_html( $s['label_link'] ); ?></label>
                        <input type="url" id="mw-link" name="link"
                            placeholder="<?php echo esc_attr( $s['placeholder_link'] ); ?>">
                    </div>

                    <input type="hidden" id="mw-spotify-id" name="spotify_id">
                    <input type="hidden" id="mw-spotify-url" name="spotify_url">
                    <input type="hidden" id="mw-apple-id" name="apple_id">
                    <input type="hidden" id="mw-apple-url" name="apple_url">

                    <div class="mw-notice mw-notice--error" id="mw-error" style="display:none"></div>

                    <button type="submit" class="mw-submit" id="mw-submit">
                        <span class="mw-submit-text"><?php echo esc_html( $s['btn_submit'] ); ?></span>
                        <span class="mw-submit-loading" style="display:none"><?php echo esc_html( $s['btn_loading'] ); ?></span>
                    </button>
                </form>

                <div class="mw-success" id="mw-success" style="display:none">
                    <div class="mw-success-icon">🎵</div>
                    <h3><?php echo esc_html( $s['success_title'] ); ?></h3>
                    <p id="mw-success-msg"><?php echo esc_html( $s['success_msg'] ); ?></p>
                    <button type="button" id="mw-add-another" class="mw-submit">Weiteren Wunsch hinzufügen</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** AJAX: Spotify search autocomplete */
    public static function ajax_search() {
        check_ajax_referer( 'mw_frontend_nonce', 'nonce' );
        $query = sanitize_text_field( $_POST['query'] ?? '' );
        if ( strlen( $query ) < 2 ) wp_send_json_success( array() );

        $results = MW_Spotify::search( $query, 6 );
        wp_send_json_success( $results );
    }

    /** AJAX: submit a wish */
    public static function ajax_submit() {
        check_ajax_referer( 'mw_frontend_nonce', 'nonce' );

        $name      = sanitize_text_field( $_POST['name'] ?? '' );
        $titel     = sanitize_text_field( $_POST['titel'] ?? '' );
        $interpret = sanitize_text_field( $_POST['interpret'] ?? '' );
        $link      = esc_url_raw( $_POST['link'] ?? '' );
        $spotify_id  = sanitize_text_field( $_POST['spotify_id']  ?? '' );
        $spotify_url = esc_url_raw( $_POST['spotify_url'] ?? '' );
        $apple_id    = sanitize_text_field( $_POST['apple_id']  ?? '' );
        $apple_url   = esc_url_raw( $_POST['apple_url'] ?? '' );

        if ( ! $name ) wp_send_json_error( array( 'message' => 'Bitte gib deinen Namen ein.' ) );

        // If a link was provided, try to extract IDs and fetch metadata
        if ( $link ) {
            if ( strpos( $link, 'spotify' ) !== false ) {
                $tid = MW_Spotify::extract_track_id( $link );
                if ( $tid ) {
                    $track = MW_Spotify::get_track( $tid );
                    if ( $track ) {
                        $titel       = $track['titel'];
                        $interpret   = $track['interpret'];
                        $spotify_id  = $track['id'];
                        $spotify_url = $track['url'];
                    }
                }
            } elseif ( strpos( $link, 'apple.com' ) !== false ) {
                $tid = MW_Apple_Music::extract_track_id( $link );
                if ( $tid ) {
                    $track = MW_Apple_Music::get_track( $tid );
                    if ( $track ) {
                        $titel     = $track['titel'];
                        $interpret = $track['interpret'];
                        $apple_id  = $track['id'];
                        $apple_url = $track['url'];
                    }
                }
            }
        }

        if ( ! $titel || ! $interpret ) {
            wp_send_json_error( array( 'message' => 'Bitte Titel und Interpret angeben oder einen gültigen Link einfügen.' ) );
        }

        $result = MW_Wunsch::add_or_increment( array(
            'titel'       => $titel,
            'interpret'   => $interpret,
            'wunsch_name' => $name,
            'spotify_id'  => $spotify_id ?: null,
            'spotify_url' => $spotify_url ?: null,
            'apple_id'    => $apple_id ?: null,
            'apple_url'   => $apple_url ?: null,
        ) );

        // Auto-add to playlists in background
        $wunsch_id = $result['id'];

        // Spotify
        $w = $result['wunsch'];
        if ( $w->spotify_id && ! $w->spotify_added ) {
            $r = MW_Spotify::add_to_playlist( $w->spotify_id );
            if ( $r['success'] ) MW_Wunsch::mark_spotify_added( $wunsch_id );
        } elseif ( ! $w->spotify_id && ! $w->spotify_added ) {
            $found = MW_Spotify::search( $titel . ' ' . $interpret, 1 );
            if ( $found ) {
                global $wpdb;
                $wpdb->update( $wpdb->prefix . MW_TABLE, array(
                    'spotify_id' => $found[0]['id'], 'spotify_url' => $found[0]['url'],
                ), array( 'id' => $wunsch_id ), array( '%s','%s' ), array( '%d' ) );
                $r = MW_Spotify::add_to_playlist( $found[0]['id'] );
                if ( $r['success'] ) MW_Wunsch::mark_spotify_added( $wunsch_id );
            }
        }

        // Apple
        if ( $w->apple_id && ! $w->apple_added ) {
            $r = MW_Apple_Music::add_to_playlist( $w->apple_id );
            if ( $r['success'] ) MW_Wunsch::mark_apple_added( $wunsch_id );
        } elseif ( ! $w->apple_id && ! $w->apple_added ) {
            $found = MW_Apple_Music::search( $titel . ' ' . $interpret, 1 );
            if ( $found ) {
                global $wpdb;
                $wpdb->update( $wpdb->prefix . MW_TABLE, array(
                    'apple_id' => $found[0]['id'], 'apple_url' => $found[0]['url'],
                ), array( 'id' => $wunsch_id ), array( '%s','%s' ), array( '%d' ) );
                $r = MW_Apple_Music::add_to_playlist( $found[0]['id'] );
                if ( $r['success'] ) MW_Wunsch::mark_apple_added( $wunsch_id );
            }
        }

        wp_send_json_success( array(
            'duplicate' => $result['duplicate'],
            'count'     => $result['wunsch']->anzahl_wuensche,
        ) );
    }
}

MW_Shortcode::init();
