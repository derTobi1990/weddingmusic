<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap mw-wrap">
    <h1>+ Neuer Musikwunsch</h1>
    <p>Im Backend angelegte Wünsche werden automatisch mit ⭐ als „vom Brautpaar" markiert.</p>

    <div class="mw-card">
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
