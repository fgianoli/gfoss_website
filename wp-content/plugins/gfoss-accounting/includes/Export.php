<?php
namespace GFOSS_Accounting;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Export contabilità per il commercialista. CSV UTF-8 con BOM, separatore ;
 */
class Export {
    public static function init(): void {
        add_action( 'admin_post_gfoss_acc_export', [ __CLASS__, 'handle' ] );
    }

    public static function handle(): void {
        if ( ! current_user_can( \GFOSS_Members\Roles::CAP_VIEW_ACCOUNTING ) ) { wp_die( 'forbidden', 403 ); }
        check_admin_referer( 'gfoss_acc_export' );

        $year = (int) ( $_POST['anno'] ?? gmdate( 'Y' ) );
        $rows = Movement::paginated( [ 'anno' => $year ], 1, 10000 )['rows'];

        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="contabilita-' . $year . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, [ 'data', 'tipo', 'categoria', 'importo', 'descrizione', 'metodo', 'socio_id', 'quota_id', 'note' ], ';' );
        foreach ( $rows as $r ) {
            self::put_row( $out, [
                $r['data'], $r['tipo'], $r['categoria_slug'],
                number_format( (float) $r['importo'], 2, ',', '' ),
                $r['descrizione'], $r['metodo'],
                $r['socio_id'], $r['quota_id'], $r['note'],
            ] );
        }
        fclose( $out );
        exit;
    }

    /**
     * Neutralizza la CSV/formula injection: descrizione e note sono testo libero
     * inserito a mano e potrebbero iniziare con = + - @ (eseguiti come formule da
     * Excel/LibreOffice). Prefissiamo un apice.
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
}
