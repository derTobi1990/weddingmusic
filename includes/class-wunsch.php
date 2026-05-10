<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MW_Wunsch {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . MW_TABLE;
    }

    public static function get_all() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . self::table() . " ORDER BY ist_brautpaar DESC, anzahl_wuensche DESC, erstellt_am DESC"
        );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id
        ) );
    }

    /** Find duplicate by titel + interpret (case-insensitive, whitespace-trimmed) */
    public static function find_duplicate( $titel, $interpret ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . "
             WHERE LOWER(TRIM(titel)) = LOWER(TRIM(%s))
             AND   LOWER(TRIM(interpret)) = LOWER(TRIM(%s))
             LIMIT 1",
            $titel, $interpret
        ) );
    }

    /**
     * Add a wish or increment count if duplicate.
     * Returns: array( 'id' => int, 'duplicate' => bool, 'wunsch' => object )
     */
    public static function add_or_increment( $data ) {
        global $wpdb;

        $titel     = sanitize_text_field( $data['titel'] );
        $interpret = sanitize_text_field( $data['interpret'] );
        $wuenscher = isset( $data['wunsch_name'] ) ? sanitize_text_field( $data['wunsch_name'] ) : '';
        $is_couple = ! empty( $data['ist_brautpaar'] );

        $duplicate = self::find_duplicate( $titel, $interpret );

        if ( $duplicate ) {
            // Append name to wunsch_namen list
            $namen = $duplicate->wunsch_namen ? explode( '|', $duplicate->wunsch_namen ) : array();
            if ( $wuenscher && ! in_array( $wuenscher, $namen ) ) {
                $namen[] = $wuenscher;
            }
            $wpdb->update( self::table(), array(
                'anzahl_wuensche' => $duplicate->anzahl_wuensche + 1,
                'wunsch_namen'    => implode( '|', array_unique( $namen ) ),
            ), array( 'id' => $duplicate->id ), array( '%d', '%s' ), array( '%d' ) );

            return array(
                'id'        => $duplicate->id,
                'duplicate' => true,
                'wunsch'    => self::get( $duplicate->id ),
            );
        }

        $wpdb->insert( self::table(), array(
            'titel'           => $titel,
            'interpret'       => $interpret,
            'spotify_id'      => isset( $data['spotify_id'] )  ? sanitize_text_field( $data['spotify_id'] )  : null,
            'spotify_url'     => isset( $data['spotify_url'] ) ? esc_url_raw( $data['spotify_url'] )         : null,
            'apple_id'        => isset( $data['apple_id'] )    ? sanitize_text_field( $data['apple_id'] )    : null,
            'apple_url'       => isset( $data['apple_url'] )   ? esc_url_raw( $data['apple_url'] )           : null,
            'anzahl_wuensche' => 1,
            'wunsch_namen'    => $wuenscher,
            'ist_brautpaar'   => $is_couple ? 1 : 0,
        ), array( '%s','%s','%s','%s','%s','%s','%d','%s','%d' ) );

        $id = $wpdb->insert_id;
        return array(
            'id'        => $id,
            'duplicate' => false,
            'wunsch'    => self::get( $id ),
        );
    }

    public static function update( $id, $data ) {
        global $wpdb;
        return $wpdb->update( self::table(), array(
            'titel'         => sanitize_text_field( $data['titel'] ),
            'interpret'     => sanitize_text_field( $data['interpret'] ),
            'spotify_url'   => isset( $data['spotify_url'] ) ? esc_url_raw( $data['spotify_url'] ) : null,
            'apple_url'     => isset( $data['apple_url'] )   ? esc_url_raw( $data['apple_url'] )   : null,
            'ist_brautpaar' => ! empty( $data['ist_brautpaar'] ) ? 1 : 0,
        ), array( 'id' => $id ), array( '%s','%s','%s','%s','%d' ), array( '%d' ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    public static function mark_spotify_added( $id ) {
        global $wpdb;
        $wpdb->update( self::table(), array( 'spotify_added' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
    }

    public static function mark_apple_added( $id ) {
        global $wpdb;
        $wpdb->update( self::table(), array( 'apple_added' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
    }

    public static function get_stats() {
        global $wpdb;
        $t = self::table();
        return array(
            'total'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ),
            'total_wuensche'=> (int) $wpdb->get_var( "SELECT SUM(anzahl_wuensche) FROM {$t}" ),
            'brautpaar'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE ist_brautpaar = 1" ),
            'spotify_synced' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE spotify_added = 1" ),
            'apple_synced'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE apple_added = 1" ),
        );
    }
}
