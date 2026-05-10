<?php if ( ! defined( 'ABSPATH' ) ) exit;
$s   = MW_Settings::get();
$msg = isset( $_GET['msg'] ) ? $_GET['msg'] : '';
$redirect_uri = MW_Admin::spotify_redirect_uri();
$spotify_auth_url = MW_Spotify::build_auth_url( $redirect_uri );
?>
<div class="wrap mw-wrap">
    <h1>⚙ Einstellungen</h1>

    <?php if ( $msg === 'saved' ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ Einstellungen gespeichert.</p></div>
    <?php elseif ( $msg === 'spotify_ok' ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ Spotify wurde erfolgreich verbunden!</p></div>
    <?php elseif ( $msg === 'spotify_fail' ) : ?>
        <div class="notice notice-error is-dismissible"><p>✘ Spotify-Verbindung fehlgeschlagen.</p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'mw_nonce', 'mw_nonce_field' ); ?>
        <input type="hidden" name="mw_action" value="save_settings">

        <!-- ============== SPOTIFY ============== -->
        <div class="mw-card">
            <h2>🟢 Spotify-Integration
                <span class="mw-help" data-tooltip="So bekommst du die API-Keys:&#10;1. https://developer.spotify.com/dashboard öffnen&#10;2. Mit Spotify-Account einloggen&#10;3. 'Create app' klicken&#10;4. Name beliebig, App-Beschreibung beliebig&#10;5. Redirect URI: '<?php echo esc_attr( $redirect_uri ); ?>' eintragen&#10;6. Nach dem Erstellen findest du Client ID und Client Secret">ℹ</span>
            </h2>
            <table class="form-table">
                <tr>
                    <th><label>Client ID</label></th>
                    <td><input type="text" name="spotify_client_id" class="regular-text"
                        value="<?php echo esc_attr( $s['spotify_client_id'] ); ?>"></td>
                </tr>
                <tr>
                    <th><label>Client Secret</label></th>
                    <td><input type="password" name="spotify_client_secret" class="regular-text"
                        value="<?php echo esc_attr( $s['spotify_client_secret'] ); ?>"></td>
                </tr>
                <tr>
                    <th><label>Redirect URI</label></th>
                    <td>
                        <input type="text" readonly class="large-text" value="<?php echo esc_attr( $redirect_uri ); ?>">
                        <p class="description">⚠ Diese URL <strong>exakt so</strong> in den Spotify-App-Einstellungen unter „Redirect URIs" eintragen!</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Playlist-ID
                        <span class="mw-help" data-tooltip="In Spotify die Playlist öffnen → Drei-Punkt-Menü → Teilen → Link kopieren&#10;Aus: open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M&#10;ID = 37i9dQZF1DXcBWIGoYBM5M">ℹ</span>
                    </label></th>
                    <td><input type="text" name="spotify_playlist_id" class="regular-text"
                        value="<?php echo esc_attr( $s['spotify_playlist_id'] ); ?>"
                        placeholder="z.B. 37i9dQZF1DXcBWIGoYBM5M"></td>
                </tr>
                <tr>
                    <th><label>Verbindungsstatus</label></th>
                    <td>
                        <?php if ( $s['spotify_refresh_token'] ) : ?>
                            <span class="mw-status mw-status--ok">✔ Verbunden</span>
                            <?php if ( $spotify_auth_url ) : ?>
                                – <a href="<?php echo esc_url( $spotify_auth_url ); ?>">erneut autorisieren</a>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="mw-status mw-status--error">✘ Nicht verbunden</span>
                            <?php if ( $s['spotify_client_id'] && $s['spotify_client_secret'] ) : ?>
                                <a href="<?php echo esc_url( $spotify_auth_url ); ?>" class="button button-primary">Mit Spotify verbinden</a>
                            <?php else : ?>
                                <p class="description">Erst Client ID & Secret eintragen und speichern, dann hier verbinden.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ============== APPLE MUSIC ============== -->
        <div class="mw-card">
            <h2>🍎 Apple-Music-Integration
                <span class="mw-help" data-tooltip="Du brauchst eine kostenpflichtige Apple-Developer-Mitgliedschaft (99€/Jahr).&#10;1. https://developer.apple.com/account öffnen&#10;2. Certificates, IDs & Profiles → Keys → +&#10;3. 'MusicKit' aktivieren, Key erstellen → .p8-Datei downloaden&#10;4. Team ID findest du oben rechts in deinem Account&#10;5. Key ID steht im erstellten Key&#10;6. Den Inhalt der .p8-Datei (inkl. -----BEGIN... und -----END...) hier einfügen">ℹ</span>
            </h2>
            <table class="form-table">
                <tr>
                    <th><label>Team ID</label></th>
                    <td><input type="text" name="apple_team_id" class="regular-text"
                        value="<?php echo esc_attr( $s['apple_team_id'] ); ?>"
                        placeholder="z.B. ABCDE12345"></td>
                </tr>
                <tr>
                    <th><label>Key ID</label></th>
                    <td><input type="text" name="apple_key_id" class="regular-text"
                        value="<?php echo esc_attr( $s['apple_key_id'] ); ?>"
                        placeholder="z.B. AB12CD34EF"></td>
                </tr>
                <tr>
                    <th><label>Private Key (.p8 Inhalt)</label></th>
                    <td><textarea name="apple_private_key" rows="8" class="large-text" style="font-family:monospace;font-size:11px"
                        placeholder="-----BEGIN PRIVATE KEY-----&#10;…&#10;-----END PRIVATE KEY-----"><?php echo esc_textarea( $s['apple_private_key'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Music User Token
                        <span class="mw-help" data-tooltip="Apple erlaubt keinen serverseitigen OAuth.&#10;Du musst den Token einmal über MusicKit JS im Browser holen:&#10;1. Auf einer Webseite mit MusicKit JS einloggen&#10;2. music.authorize() aufrufen&#10;3. music.musicUserToken in der Browser-Konsole anzeigen lassen&#10;4. Hier einfügen&#10;Token läuft nach ~6 Monaten ab.">ℹ</span>
                    </label></th>
                    <td><textarea name="apple_user_token" rows="3" class="large-text" style="font-family:monospace;font-size:11px"><?php echo esc_textarea( $s['apple_user_token'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Playlist-ID
                        <span class="mw-help" data-tooltip="Eigene Playlist in Apple Music erstellen → Teilen → Link kopieren&#10;Beispiel: music.apple.com/de/playlist/.../pl.u-xxxxxx&#10;ID = pl.u-xxxxxx">ℹ</span>
                    </label></th>
                    <td><input type="text" name="apple_playlist_id" class="regular-text"
                        value="<?php echo esc_attr( $s['apple_playlist_id'] ); ?>"
                        placeholder="pl.u-xxxxxxx"></td>
                </tr>
            </table>
        </div>

        <!-- ============== TEXTE ============== -->
        <div class="mw-card">
            <h2>📝 Frontend-Texte</h2>
            <table class="form-table">
                <?php
                $text_fields = array(
                    'form_title' => 'Überschrift',
                    'form_intro' => 'Einleitungstext',
                    'label_name' => 'Label: Name',
                    'placeholder_name' => 'Platzhalter: Name',
                    'label_search' => 'Label: Suche',
                    'placeholder_search' => 'Platzhalter: Suche',
                    'label_titel' => 'Label: Titel',
                    'label_interpret' => 'Label: Interpret',
                    'label_link' => 'Label: Link',
                    'placeholder_link' => 'Platzhalter: Link',
                    'btn_submit' => 'Button-Text',
                    'btn_loading' => 'Button-Lade-Text',
                    'success_title' => 'Erfolg: Überschrift',
                    'success_msg' => 'Erfolg: Nachricht',
                );
                foreach ( $text_fields as $key => $label ) : ?>
                    <tr>
                        <th><label><?php echo esc_html( $label ); ?></label></th>
                        <td><input type="text" name="<?php echo $key; ?>" class="regular-text"
                            value="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- ============== FARBEN ============== -->
        <div class="mw-card">
            <h2>🎨 Farben (Frontend)</h2>
            <table class="form-table">
                <?php
                $colors = array(
                    'color_bg' => 'Hintergrund',
                    'color_accent' => 'Akzent',
                    'color_text' => 'Text',
                    'color_muted' => 'Gedämpfter Text',
                    'color_btn_bg' => 'Button-Hintergrund',
                    'color_btn_text' => 'Button-Text',
                );
                foreach ( $colors as $key => $label ) : ?>
                    <tr>
                        <th><label><?php echo esc_html( $label ); ?></label></th>
                        <td>
                            <input type="color" name="<?php echo $key; ?>"
                                value="<?php echo esc_attr( $s[ $key ] ); ?>">
                            <span style="font-family:monospace;margin-left:8px"><?php echo esc_html( $s[ $key ] ); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <p class="submit">
            <button class="button button-primary button-large">💾 Einstellungen speichern</button>
        </p>
    </form>
</div>
