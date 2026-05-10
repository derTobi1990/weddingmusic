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
                <span class="mw-help" data-tooltip="Diese Methode benötigt KEINEN kostenpflichtigen Developer-Account.&#10;Die Tokens werden direkt aus deinem eingeloggten Apple-Music-Browser ausgelesen.&#10;Die Tokens laufen ca. alle 180 Tage ab und müssen dann erneuert werden.">ℹ</span>
            </h2>
            <p class="description" style="margin-bottom:16px">
                <strong>Anleitung – So holst du dir die Tokens (kein Developer-Account nötig):</strong><br>
                1. In Chrome <a href="https://music.apple.com" target="_blank">music.apple.com</a> öffnen und mit deiner Apple-ID einloggen<br>
                2. Mit <kbd>F12</kbd> die Entwicklertools öffnen → Reiter „<strong>Network</strong>" (Netzwerkanalyse)<br>
                3. Auf der Apple-Music-Seite irgendwas anklicken (z.B. ein Album öffnen) damit Requests entstehen<br>
                4. Im Network-Tab einen Request zu <code>amp-api.music.apple.com</code> anklicken<br>
                5. Im rechten Bereich unter „Request Headers" zwei Werte kopieren:
                <ul style="margin-left:20px;margin-top:6px">
                    <li><code>authorization: Bearer <strong>eyJh...</strong></code> → das ist der <strong>Developer Token</strong> (langer JWT-String)</li>
                    <li><code>media-user-token: <strong>Atxxx...</strong></code> → das ist der <strong>Music User Token</strong></li>
                </ul>
            </p>
            <table class="form-table">
                <tr>
                    <th><label>Developer Token (Bearer)</label></th>
                    <td>
                        <textarea name="apple_dev_token" rows="4" class="large-text" style="font-family:monospace;font-size:11px"
                            placeholder="eyJhbGciOiJFUzI1NiIs..."><?php echo esc_textarea( $s['apple_dev_token'] ); ?></textarea>
                        <p class="description">Beginnt mit <code>eyJ</code>, ist ein langer JWT-String (oft 600+ Zeichen). Ohne „Bearer" davor einfügen!</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Music User Token</label></th>
                    <td>
                        <textarea name="apple_user_token" rows="3" class="large-text" style="font-family:monospace;font-size:11px"
                            placeholder="Atxxx..."><?php echo esc_textarea( $s['apple_user_token'] ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label>Playlist-ID
                        <span class="mw-help" data-tooltip="Eigene Playlist in Apple Music erstellen → Teilen → Link kopieren&#10;Beispiel: music.apple.com/de/playlist/.../pl.u-xxxxxx&#10;ID = pl.u-xxxxxx">ℹ</span>
                    </label></th>
                    <td><input type="text" name="apple_playlist_id" class="regular-text"
                        value="<?php echo esc_attr( $s['apple_playlist_id'] ); ?>"
                        placeholder="pl.u-xxxxxxx"></td>
                </tr>
                <tr>
                    <th><label>Status</label></th>
                    <td>
                        <?php
                        $status = MW_Apple_Health::status();
                        $days   = MW_Apple_Health::days_remaining();
                        switch ( $status ) {
                            case 'ok':
                                echo '<span class="mw-status mw-status--ok">✔ Gültig</span>';
                                if ( $days !== null ) echo ' <span style="color:#666">(läuft in <strong>' . $days . '</strong> Tagen ab)</span>';
                                break;
                            case 'warn':
                                echo '<span class="mw-status mw-status--warn">⚠ Erneuerung empfohlen</span>';
                                echo ' <span style="color:#666">(läuft in <strong>' . $days . '</strong> Tagen ab)</span>';
                                break;
                            case 'urgent':
                                echo '<span class="mw-status mw-status--error">🚨 Bald abgelaufen</span>';
                                echo ' <span style="color:#666">(noch <strong>' . $days . '</strong> Tag' . ( $days === 1 ? '' : 'e' ) . ')</span>';
                                break;
                            case 'expired':
                            case 'failed':
                                echo '<span class="mw-status mw-status--error">✘ Abgelaufen / ungültig</span>';
                                break;
                            default:
                                echo '<span class="mw-status mw-status--error">✘ Nicht konfiguriert</span>';
                        }
                        ?>
                        <button type="button" id="mw-test-apple" class="button" style="margin-left:10px">🔍 Tokens jetzt testen</button>
                        <span id="mw-test-result" style="margin-left:10px"></span>
                    </td>
                </tr>
                <tr>
                    <th><label>Erinnerungs-E-Mail
                        <span class="mw-help" data-tooltip="An diese Adresse wird automatisch eine Erinnerungs-Mail geschickt, sobald die Tokens in 14 Tagen ablaufen, sowie bei Ablauf bzw. Fehlern.">ℹ</span>
                    </label></th>
                    <td><input type="email" name="apple_notify_email" class="regular-text"
                        value="<?php echo esc_attr( $s['apple_notify_email'] ); ?>"></td>
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
