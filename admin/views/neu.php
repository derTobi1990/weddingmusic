<?php if ( ! defined( 'ABSPATH' ) ) exit;
$s = MW_Settings::get();
$spotify_ready = $s['spotify_client_id'] && $s['spotify_client_secret'];
?>
<div class="wrap mw-wrap">
    <h1>+ Neuer Musikwunsch</h1>
    <p>Im Backend angelegte Wünsche werden automatisch mit ⭐ als „vom Brautpaar" markiert.</p>

    <div class="mw-card">
        <?php if ( $spotify_ready ) : ?>
            <h2>🔍 Songsuche</h2>
            <p>Tippe Titel oder Interpret ein und wähle einen Song aus den Vorschlägen – Felder werden automatisch befüllt.</p>
            <div class="mw-search-field" style="margin-bottom:20px">
                <input type="text" id="mw-admin-search" class="regular-text"
                    placeholder="Song bei Spotify suchen…" autocomplete="off"
                    style="width:100%;max-width:500px">
                <div id="mw-admin-search-results"></div>
            </div>
            <hr>
            <h3 style="margin-top:20px">Manuelle Eingabe</h3>
        <?php else : ?>
            <div class="notice notice-info inline">
                <p>💡 Tipp: Wenn du Spotify in den <a href="<?php echo admin_url( 'admin.php?page=musikwuensche-einstellungen' ); ?>">Einstellungen</a> einrichtest,
                kannst du hier direkt nach Songs suchen.</p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'mw_nonce', 'mw_nonce_field' ); ?>
            <input type="hidden" name="mw_action" value="add_wunsch">
            <table class="form-table">
                <tr>
                    <th><label for="titel">Titel *</label></th>
                    <td><input type="text" id="titel" name="titel" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="interpret">Interpret *</label></th>
                    <td><input type="text" id="interpret" name="interpret" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="spotify_url">Spotify-Link</label></th>
                    <td><input type="url" id="spotify_url" name="spotify_url" class="large-text"
                        placeholder="https://open.spotify.com/track/..."></td>
                </tr>
                <tr>
                    <th><label for="apple_url">Apple-Music-Link</label></th>
                    <td><input type="url" id="apple_url" name="apple_url" class="large-text"
                        placeholder="https://music.apple.com/de/album/.../i=..."></td>
                </tr>
            </table>
            <p class="submit">
                <button class="button button-primary">★ Hinzufügen</button>
                <a href="<?php echo admin_url( 'admin.php?page=musikwuensche' ); ?>" class="button">Abbrechen</a>
            </p>
        </form>
    </div>
</div>

<style>
#mw-admin-search-results {
    margin-top: 8px;
    max-width: 500px;
}
.mw-admin-result {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background: #f6f7f7;
    border: 1px solid transparent;
    border-radius: 4px;
    margin-bottom: 4px;
    cursor: pointer;
    transition: background .15s, border-color .15s;
}
.mw-admin-result:hover { background: #e7eef0; }
.mw-admin-result.is-selected { border-color: #2271b1; background: #e7eef0; }
.mw-admin-result img { width: 40px; height: 40px; border-radius: 3px; object-fit: cover; flex-shrink: 0; }
.mw-admin-result-meta { flex: 1; min-width: 0; }
.mw-admin-result-meta strong { display: block; font-size: 13px; }
.mw-admin-result-meta span { display: block; color: #666; font-size: 12px; }
.mw-admin-result-duration { color: #888; font-size: 12px; }
</style>
