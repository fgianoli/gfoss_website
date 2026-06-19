<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Export del libro soci e del registro volontari (art. 18 Statuto).
 *
 *   Formato: CSV UTF-8 con BOM (Excel lo apre senza chiedere encoding).
 *   Endpoint: admin-post action 'gfoss_export'. Capability gfoss_export_registro.
 *
 *   Tipi:
 *     - registro_soci    : tutti i soci attivi (campi anagrafici + stato quota anno selezionato)
 *     - registro_volontari : solo soci con gf_volontario = 1
 *     - quote_anno       : storico quote di un anno (utile per il commercialista)
 */
class Export {

    public static function init(): void {
        add_action( 'admin_post_gfoss_export', [ __CLASS__, 'handle' ] );
    }

    public static function handle(): void {
        if ( ! current_user_can( Roles::CAP_EXPORT_REGISTRO ) ) {
            wp_die( 'forbidden', 403 );
        }
        check_admin_referer( 'gfoss_export' );

        $tipo = sanitize_key( (string) ( $_POST['tipo'] ?? 'registro_soci' ) );
        $year = (int) ( $_POST['anno'] ?? gmdate( 'Y' ) );

        switch ( $tipo ) {
            case 'registro_volontari': self::stream_registro( true,  $year ); break;
            case 'quote_anno':         self::stream_quote_anno( $year ); break;
            case 'registro_soci':
            default:                   self::stream_registro( false, $year );
        }
    }

    private static function stream_registro( bool $only_volontari, int $year ): void {
        $args = [
            'role__in' => [ 'gfoss_socio', 'gfoss_consigliere', 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore' ],
            'orderby'  => 'meta_value_num',
            'meta_key' => 'gf_numero_socio',
            'order'    => 'ASC',
            'number'   => -1,
        ];
        if ( $only_volontari ) {
            $args['meta_query'] = [ [ 'key' => 'gf_volontario', 'value' => '1' ] ];
        }
        $users = get_users( $args );

        $headers = [
            'numero_socio', 'cognome', 'nome', 'codice_fiscale',
            'email', 'telefono', 'indirizzo', 'cap', 'citta', 'provincia',
            'data_nascita', 'comune_nascita', 'professione',
            'data_ammissione', 'volontario',
            'ruolo', "quota_{$year}_stato", "quota_{$year}_data", "quota_{$year}_metodo",
        ];
        $filename = ( $only_volontari ? 'registro-volontari' : 'registro-soci' ) . '-' . $year . '.csv';

        self::send_csv_headers( $filename );
        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM
        fputcsv( $out, $headers, ';' );

        foreach ( $users as $u ) {
            $q_status = Quote::status_for( $u->ID, $year );
            // Find paid record for that year if exists
            $stor = Quote::for_user( $u->ID );
            $row_q = null;
            foreach ( $stor as $r ) { if ( (int) $r['anno'] === $year ) { $row_q = $r; break; } }

            $ruolo = self::ruolo_principale( $u );

            self::put_row( $out, [
                (string) get_user_meta( $u->ID, 'gf_numero_socio', true ),
                $u->last_name,
                $u->first_name,
                (string) get_user_meta( $u->ID, 'gf_codice_fiscale', true ),
                $u->user_email,
                (string) get_user_meta( $u->ID, 'gf_telefono', true ),
                (string) get_user_meta( $u->ID, 'gf_indirizzo', true ),
                (string) get_user_meta( $u->ID, 'gf_cap', true ),
                (string) get_user_meta( $u->ID, 'gf_citta', true ),
                (string) get_user_meta( $u->ID, 'gf_provincia', true ),
                (string) get_user_meta( $u->ID, 'gf_data_nascita', true ),
                (string) get_user_meta( $u->ID, 'gf_comune_nascita', true ),
                (string) get_user_meta( $u->ID, 'gf_professione', true ),
                (string) get_user_meta( $u->ID, 'gf_data_ammissione', true ),
                get_user_meta( $u->ID, 'gf_volontario', true ) === '1' ? 'sì' : 'no',
                $ruolo,
                $q_status,
                $row_q['data_pagamento'] ?? '',
                $row_q['metodo'] ?? '',
            ] );
        }
        fclose( $out );
        exit;
    }

    private static function stream_quote_anno( int $year ): void {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT q.*, u.user_email, u.display_name
             FROM " . Schema::table_quote() . " q
             INNER JOIN {$wpdb->users} u ON u.ID = q.user_id
             WHERE q.anno = %d
             ORDER BY q.data_pagamento DESC",
            $year
        ), ARRAY_A );

        self::send_csv_headers( "quote-{$year}.csv" );
        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, [ 'data_pagamento', 'socio', 'email', 'importo', 'metodo', 'stato', 'transaction_ref', 'note' ], ';' );
        foreach ( $rows as $r ) {
            self::put_row( $out, [
                $r['data_pagamento'], $r['display_name'], $r['user_email'],
                number_format( (float) $r['importo'], 2, ',', '' ),
                $r['metodo'], $r['stato'], $r['transaction_ref'], $r['note'],
            ] );
        }
        fclose( $out );
        exit;
    }

    /**
     * Neutralizza la CSV/formula injection: i campi che iniziano con = + - @ (o TAB/CR)
     * vengono eseguiti come formule da Excel/LibreOffice. Prefissiamo un apice.
     * I dati provengono in parte dal form pubblico di candidatura → non fidati.
     */
    private static function csv_safe( $value ): string {
        $s = (string) $value;
        if ( $s !== '' && in_array( $s[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
            return "'" . $s;
        }
        return $s;
    }

    private static function put_row( $out, array $cells ): void {
        fputcsv( $out, array_map( [ __CLASS__, 'csv_safe' ], $cells ), ';' );
    }

    private static function send_csv_headers( string $filename ): void {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    }

    private static function ruolo_principale( \WP_User $u ): string {
        $priority = [ 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore', 'gfoss_consigliere', 'gfoss_socio' ];
        foreach ( $priority as $r ) {
            if ( in_array( $r, (array) $u->roles, true ) ) {
                return str_replace( 'gfoss_', '', $r );
            }
        }
        return implode( ',', $u->roles );
    }
}
