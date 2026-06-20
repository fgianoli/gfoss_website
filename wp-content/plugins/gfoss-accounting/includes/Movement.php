<?php
namespace GFOSS_Accounting;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CRUD movimenti contabili. Tutto in una tabella custom (Schema::table_movement()).
 */
class Movement {

    public static function categories( ?string $tipo = null ): array {
        global $wpdb;
        $sql = "SELECT * FROM " . Schema::table_category() . " WHERE attivo = 1";
        $args = [];
        if ( $tipo ) { $sql .= " AND tipo = %s"; $args[] = $tipo; }
        $sql .= " ORDER BY tipo, label";
        $prepared = $args ? $wpdb->prepare( $sql, $args ) : $sql;
        return $wpdb->get_results( $prepared, ARRAY_A ) ?: [];
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Schema::table_movement() . " WHERE id = %d", $id
        ), ARRAY_A );
        return $row ?: null;
    }

    public static function create( array $data ): int {
        global $wpdb;
        $row = self::sanitize( $data );
        $row['created_at'] = current_time( 'mysql', true );
        $row['created_by'] = get_current_user_id() ?: null;
        $wpdb->insert( Schema::table_movement(), $row );
        $id = (int) $wpdb->insert_id;
        do_action( 'gfoss_accounting_movement_created', $id, $row );
        return $id;
    }

    public static function update( int $id, array $data ): bool {
        global $wpdb;
        return (bool) $wpdb->update( Schema::table_movement(), self::sanitize( $data ), [ 'id' => $id ] );
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( Schema::table_movement(), [ 'id' => $id ] );
    }

    private static function sanitize( array $d ): array {
        return [
            'data'           => sanitize_text_field( (string) ( $d['data'] ?? gmdate( 'Y-m-d' ) ) ),
            'tipo'           => in_array( $d['tipo'] ?? '', [ 'entrata', 'uscita' ], true ) ? $d['tipo'] : 'entrata',
            'categoria_slug' => sanitize_key( (string) ( $d['categoria_slug'] ?? '' ) ),
            'importo'        => round( (float) ( $d['importo'] ?? 0 ), 2 ),
            'descrizione'    => sanitize_text_field( (string) ( $d['descrizione'] ?? '' ) ),
            'socio_id'       => ! empty( $d['socio_id'] ) ? (int) $d['socio_id'] : null,
            'quota_id'       => ! empty( $d['quota_id'] ) ? (int) $d['quota_id'] : null,
            'documento_url'  => isset( $d['documento_url'] ) ? esc_url_raw( (string) $d['documento_url'] ) : null,
            'metodo'         => isset( $d['metodo'] ) ? sanitize_text_field( (string) $d['metodo'] ) : null,
            'fin_5x1000'     => ! empty( $d['fin_5x1000'] ) ? 1 : 0,
            'note'           => isset( $d['note'] ) ? sanitize_textarea_field( (string) $d['note'] ) : null,
        ];
    }

    /** @return array{rows: array, total_count: int} */
    public static function paginated( array $filters = [], int $page = 1, int $per_page = 50 ): array {
        global $wpdb;
        $where = [ '1=1' ]; $args = [];
        if ( ! empty( $filters['anno'] ) )      { $where[] = 'YEAR(data) = %d';   $args[] = (int) $filters['anno']; }
        if ( ! empty( $filters['tipo'] ) )      { $where[] = 'tipo = %s';         $args[] = sanitize_key( $filters['tipo'] ); }
        if ( ! empty( $filters['categoria'] ) ) { $where[] = 'categoria_slug = %s';$args[] = sanitize_key( $filters['categoria'] ); }
        $w = implode( ' AND ', $where );
        $offset = max( 0, ( $page - 1 ) * $per_page );

        $rows_sql = "SELECT * FROM " . Schema::table_movement() . " WHERE $w ORDER BY data DESC, id DESC LIMIT %d OFFSET %d";
        $cnt_sql  = "SELECT COUNT(*) FROM " . Schema::table_movement() . " WHERE $w";

        $rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A ) ?: [];
        $tot  = (int) $wpdb->get_var( $args ? $wpdb->prepare( $cnt_sql, $args ) : $cnt_sql );

        return [ 'rows' => $rows, 'total_count' => $tot ];
    }

    public static function exists_for_quota( int $quota_id ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . Schema::table_movement() . " WHERE quota_id = %d", $quota_id
        ) );
    }

    /** Spese finanziate dal 5×1000 in un anno (rows). */
    public static function spese_5x1000( int $year ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . Schema::table_movement() . "
             WHERE tipo = 'uscita' AND fin_5x1000 = 1 AND YEAR(data) = %d
             ORDER BY data ASC",
            $year
        ), ARRAY_A ) ?: [];
    }

    public static function totals_year( int $year ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT tipo, categoria_slug, SUM(importo) AS tot
             FROM " . Schema::table_movement() . "
             WHERE YEAR(data) = %d GROUP BY tipo, categoria_slug",
            $year
        ), ARRAY_A ) ?: [];

        $entrate = []; $uscite = []; $tot_e = 0.0; $tot_u = 0.0;
        foreach ( $rows as $r ) {
            $imp = (float) $r['tot'];
            if ( $r['tipo'] === 'entrata' ) { $entrate[ $r['categoria_slug'] ] = $imp; $tot_e += $imp; }
            else                            { $uscite[  $r['categoria_slug'] ] = $imp; $tot_u += $imp; }
        }
        return [
            'entrate' => $entrate, 'tot_entrate' => $tot_e,
            'uscite'  => $uscite,  'tot_uscite'  => $tot_u,
            'saldo'   => $tot_e - $tot_u,
        ];
    }
}
