<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Archiviazione soci decaduti (mancato rinnovo) con possibilità di riabilitarli
 * o eliminarli completamente (GDPR).
 *
 *   - Decaduto: ultima quota pagata anno L e oggi è oltre il 31 marzo di L+1
 *     senza rinnovo (art. 9 Statuto: perdita qualità di socio per morosità).
 *   - Archivia: salva i ruoli correnti e assegna il ruolo 'gfoss_archiviato'
 *     (esce dal registro soci attivo, ma resta recuperabile).
 *   - Riabilita: ripristina i ruoli precedenti.
 *   - Elimina: rimuove utente + meta + quote + candidature (dati personali).
 */
class Archivio {

    public const ROLE       = 'gfoss_archiviato';
    public const META_FLAG  = 'gf_archiviato';
    public const META_DATE  = 'gf_archiviato_data';
    public const META_ROLES = 'gf_ruoli_precedenti';

    public static function is_archived( int $uid ): bool {
        return get_user_meta( $uid, self::META_FLAG, true ) === '1';
    }

    /** Ultimo anno con quota pagata (0 se nessuno). */
    public static function last_paid_year( int $uid ): int {
        $max = 0;
        foreach ( Quote::for_user( $uid ) as $q ) {
            if ( $q['stato'] === 'paid' ) { $max = max( $max, (int) $q['anno'] ); }
        }
        return $max;
    }

    /** Decaduto: non in regola e oltre il 31 marzo dell'anno dopo l'ultima quota. */
    public static function is_lapsed( int $uid ): bool {
        if ( self::is_archived( $uid ) ) { return false; }
        $cy = (int) gmdate( 'Y' );
        if ( in_array( Quote::status_for( $uid, $cy ), [ 'paid', 'expiring' ], true ) ) { return false; }

        $base = self::last_paid_year( $uid );
        if ( $base <= 0 ) {
            // Mai pagato: usa l'anno di ammissione come riferimento.
            $base = (int) substr( (string) get_user_meta( $uid, 'gf_data_ammissione', true ), 0, 4 ) - 1;
            if ( $base <= 0 ) { return false; }
        }
        $grace_end = mktime( 23, 59, 59, 3, 31, $base + 1 ); // 31 marzo dell'anno successivo
        return time() > $grace_end;
    }

    /** Soci attivi che risultano decaduti (candidati all'archiviazione). */
    public static function lapsed_members(): array {
        $users = get_users( [ 'role__in' => self::active_roles(), 'orderby' => 'display_name' ] );
        return array_values( array_filter( $users, static fn( $u ) => self::is_lapsed( (int) $u->ID ) ) );
    }

    public static function archived_members(): array {
        return get_users( [ 'role' => self::ROLE, 'orderby' => 'display_name' ] );
    }

    public static function archive( int $uid ): void {
        $u = get_userdata( $uid );
        if ( ! $u || self::is_archived( $uid ) ) { return; }
        update_user_meta( $uid, self::META_ROLES, wp_json_encode( array_values( (array) $u->roles ) ) );
        update_user_meta( $uid, self::META_FLAG, '1' );
        update_user_meta( $uid, self::META_DATE, gmdate( 'Y-m-d' ) );
        $u->set_role( self::ROLE );
    }

    public static function reactivate( int $uid ): void {
        $u = get_userdata( $uid );
        if ( ! $u ) { return; }
        $prev = json_decode( (string) get_user_meta( $uid, self::META_ROLES, true ), true );
        if ( ! is_array( $prev ) || ! $prev ) { $prev = [ 'gfoss_socio' ]; }
        $u->set_role( (string) array_shift( $prev ) );
        foreach ( $prev as $r ) { $u->add_role( (string) $r ); }
        delete_user_meta( $uid, self::META_FLAG );
        delete_user_meta( $uid, self::META_DATE );
        delete_user_meta( $uid, self::META_ROLES );
    }

    /** Eliminazione completa: dati personali, quote, candidature, account. */
    public static function delete_with_data( int $uid ): bool {
        global $wpdb;
        $u = get_userdata( $uid );
        if ( ! $u ) { return false; }

        $wpdb->delete( Schema::table_quote(), [ 'user_id' => $uid ] );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table_candidatura() . ' WHERE user_id = %d', $uid ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table_candidatura() . ' WHERE email = %s', $u->user_email ) );

        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        // I contenuti pubblici (news) eventualmente scritti vengono riassegnati a un
        // amministratore per non perderli; i dati personali del socio sono rimossi.
        $reassign = self::fallback_admin( $uid );
        return (bool) wp_delete_user( $uid, $reassign ?: null );
    }

    private static function fallback_admin( int $exclude ): int {
        $a = get_users( [ 'role' => 'administrator', 'exclude' => [ $exclude ], 'number' => 1, 'fields' => 'ID' ] );
        return $a ? (int) $a[0] : 0;
    }

    private static function active_roles(): array {
        return [ 'gfoss_socio', 'gfoss_consigliere', 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore', 'gfoss_comunicazione', 'gfoss_segreteria' ];
    }
}
