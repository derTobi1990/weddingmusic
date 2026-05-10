<?php if ( ! defined( 'ABSPATH' ) ) exit;
$rows  = MW_Wunsch::get_all();
$stats = MW_Wunsch::get_stats();
$msg   = isset( $_GET['msg'] ) ? $_GET['msg'] : '';
$msgs  = array(
    'added'                       => 'Wunsch hinzugefügt.',
    'updated'                     => 'Wunsch aktualisiert.',
    'deleted'                     => 'Wunsch gelöscht und aus Spotify-Playlist entfernt.',
    'spotify_fail_deleted'        => 'Wunsch gelöscht, konnte aber nicht aus Spotify-Playlist entfernt werden.',
    'apple_fail_deleted'          => '⚠ Wunsch gelöscht. Bitte den Song manuell aus deiner Apple-Music-Playlist entfernen (Apple erlaubt kein automatisches Entfernen).',
    'spotify_fail_apple_fail_deleted' => 'Wunsch gelöscht. Spotify-Sync fehlgeschlagen, Apple-Music-Song muss manuell entfernt werden.',
    'repaired'                    => 'Datenbank repariert.',
);
$tables_ok = MW_Database::tables_exist();
$s = MW_Settings::get();
$spotify_ready = $s['spotify_client_id'] && $s['spotify_refresh_token'] && $s['spotify_playlist_id'];
$apple_ready   = $s['apple_dev_token'] && $s['apple_user_token'] && $s['apple_playlist_id'];
?>
<div class="wrap mw-wrap">
    <h1>🎵 Musikwünsche
        <a href="<?php echo admin_url( 'admin.php?page=musikwuensche-neu' ); ?>" class="page-title-action">+ Neu</a>
        <a href="<?php echo admin_url( 'admin-post.php?action=mw_export_xlsx' ); ?>" class="page-title-action">📥 Excel-Export</a>
    </h1>

    <?php if ( $msg && isset( $msgs[ $msg ] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msgs[ $msg ] ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! $tables_ok ) : ?>
        <div class="notice notice-error">
            <p><strong>⚠ Datenbank-Tabelle fehlt.</strong></p>
            <form method="post">
                <?php wp_nonce_field( 'mw_nonce', 'mw_nonce_field' ); ?>
                <input type="hidden" name="mw_action" value="repair_db">
                <button class="button button-primary">🔧 Datenbank reparieren</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="mw-stats-grid">
        <div class="mw-stat"><span class="mw-stat-num"><?php echo $stats['total']; ?></span><span>Songs</span></div>
        <div class="mw-stat"><span class="mw-stat-num"><?php echo $stats['total_wuensche']; ?></span><span>Wünsche gesamt</span></div>
        <div class="mw-stat"><span class="mw-stat-num"><?php echo $stats['brautpaar']; ?></span><span>vom Brautpaar ★</span></div>
        <div class="mw-stat"><span class="mw-stat-num"><?php echo $stats['spotify_synced']; ?> / <?php echo $stats['total']; ?></span><span>auf Spotify</span></div>
        <div class="mw-stat"><span class="mw-stat-num"><?php echo $stats['apple_synced']; ?> / <?php echo $stats['total']; ?></span><span>auf Apple Music</span></div>
    </div>

    <?php if ( ! $spotify_ready || ! $apple_ready ) : ?>
        <div class="notice notice-info inline" style="margin:12px 0">
            <p>
                <?php if ( ! $spotify_ready ) echo '⚠ <strong>Spotify</strong> ist noch nicht konfiguriert. '; ?>
                <?php if ( ! $apple_ready )   echo '⚠ <strong>Apple Music</strong> ist noch nicht konfiguriert. '; ?>
                <a href="<?php echo admin_url( 'admin.php?page=musikwuensche-einstellungen' ); ?>">Jetzt einrichten →</a>
            </p>
        </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped mw-table">
        <thead>
            <tr>
                <th style="width:40px">★</th>
                <th>Titel</th>
                <th>Interpret</th>
                <th style="width:140px">Wünsche</th>
                <th style="width:120px">Spotify</th>
                <th style="width:120px">Apple Music</th>
                <th style="width:140px">Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $rows ) ) : ?>
            <tr><td colspan="7">Noch keine Musikwünsche vorhanden.</td></tr>
        <?php else : foreach ( $rows as $w ) : ?>
            <tr class="<?php echo $w->ist_brautpaar ? 'mw-row--couple' : ''; ?>">
                <td><?php echo $w->ist_brautpaar ? '<span title="vom Brautpaar" style="color:#c9a94d;font-size:18px">★</span>' : ''; ?></td>
                <td><strong><?php echo esc_html( $w->titel ); ?></strong></td>
                <td><?php echo esc_html( $w->interpret ); ?></td>
                <td>
                    <?php if ( $w->anzahl_wuensche > 1 ) : ?>
                        <span class="mw-badge mw-badge--multi"><?php echo intval( $w->anzahl_wuensche ); ?>× gewünscht</span>
                        <?php if ( $w->wunsch_namen ) : ?>
                            <br><small style="color:#888"><?php echo esc_html( str_replace( '|', ', ', $w->wunsch_namen ) ); ?></small>
                        <?php endif; ?>
                    <?php else : ?>
                        <small><?php echo esc_html( $w->wunsch_namen ); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $w->spotify_url ) : ?>
                        <a href="<?php echo esc_url( $w->spotify_url ); ?>" target="_blank" class="button button-small mw-btn-spotify">▶ Öffnen</a>
                    <?php endif; ?>
                    <button class="button button-small mw-sync-btn"
                            data-id="<?php echo $w->id; ?>"
                            data-service="spotify"
                            <?php disabled( $w->spotify_added, 1 ); ?>>
                        <?php echo $w->spotify_added ? '✓ in Playlist' : '+ zur Playlist'; ?>
                    </button>
                </td>
                <td>
                    <?php if ( $w->apple_url ) : ?>
                        <a href="<?php echo esc_url( $w->apple_url ); ?>" target="_blank" class="button button-small mw-btn-apple"></a>
                    <?php endif; ?>
                    <button class="button button-small mw-sync-btn"
                            data-id="<?php echo $w->id; ?>"
                            data-service="apple"
                            <?php disabled( $w->apple_added, 1 ); ?>>
                        <?php echo $w->apple_added ? '✓ in Playlist' : '+ zur Playlist'; ?>
                    </button>
                </td>
                <td>
                    <form method="post" style="display:inline" onsubmit="return confirm('Wunsch löschen?\n\nSpotify-Playlist wird automatisch aktualisiert.\nApple-Music: Song muss manuell aus der Playlist entfernt werden (API-Einschränkung von Apple).')">
                        <?php wp_nonce_field( 'mw_nonce', 'mw_nonce_field' ); ?>
                        <input type="hidden" name="mw_action" value="delete_wunsch">
                        <input type="hidden" name="id" value="<?php echo $w->id; ?>">
                        <button class="button button-small mw-btn-danger">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
