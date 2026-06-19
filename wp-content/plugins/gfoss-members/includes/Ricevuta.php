<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Ricevuta PDF del versamento della quota associativa.
 *
 *   Endpoint: GET /wp-json/gfoss/v1/ricevuta?anno=YYYY
 *   - il socio può scaricare solo le PROPRIE ricevute (salvo manage_soci)
 *   - solo per quote in stato "paid"
 *   Generazione via mPDF (già usato per la tessera).
 */
class Ricevuta {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'gfoss/v1', '/ricevuta', [
            'methods'             => 'GET',
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can( Roles::CAP_VIEW_OWN_QUOTA ),
            'callback'            => [ __CLASS__, 'rest_download' ],
            'args'                => [
                'user' => [ 'sanitize_callback' => 'absint', 'default' => 0 ],
                'anno' => [ 'sanitize_callback' => 'absint', 'default' => 0 ],
            ],
        ] );
    }

    public static function download_url( int $user_id, int $anno ): string {
        return rest_url( 'gfoss/v1/ricevuta?user=' . $user_id . '&anno=' . $anno );
    }

    public static function rest_download( \WP_REST_Request $req ) {
        $current = get_current_user_id();
        $target  = (int) ( $req['user'] ?: $current );
        $anno    = (int) ( $req['anno'] ?: gmdate( 'Y' ) );

        if ( $target !== $current && ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) {
            return new \WP_REST_Response( 'forbidden', 403 );
        }

        $row = self::quota_row( $target, $anno );
        if ( ! $row || $row['stato'] !== 'paid' ) {
            return new \WP_REST_Response( 'Nessuna quota pagata per l\'anno richiesto.', 404 );
        }

        $pdf = self::generate_pdf( $target, $row );
        if ( $pdf instanceof \WP_Error ) {
            return new \WP_REST_Response( $pdf->get_error_message(), 500 );
        }

        $filename = sprintf( 'ricevuta-quota-gfoss-%d-%d.pdf', $anno, $target );
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        header( 'Cache-Control: private, max-age=0, must-revalidate' );
        echo $pdf;
        exit;
    }

    /** Riga quota per (utente, anno) o null. */
    private static function quota_row( int $user_id, int $anno ): ?array {
        foreach ( Quote::for_user( $user_id ) as $r ) {
            if ( (int) $r['anno'] === $anno ) { return $r; }
        }
        return null;
    }

    /** @return string PDF binario o WP_Error. */
    public static function generate_pdf( int $user_id, array $row ): string|\WP_Error {
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            $autoload = GFOSS_MEMBERS_DIR . 'vendor/autoload.php';
            if ( is_file( $autoload ) ) { require_once $autoload; }
        }
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            return new \WP_Error( 'no_mpdf', 'mPDF non installato. Esegui composer install nel plugin gfoss-members.' );
        }

        $html = self::render_html( $user_id, $row );

        try {
            $tmp = WP_CONTENT_DIR . '/uploads/gfoss-tmp';
            if ( ! is_dir( $tmp ) ) { wp_mkdir_p( $tmp ); }
            $mpdf = new \Mpdf\Mpdf( [
                'mode'          => 'utf-8',
                'format'        => 'A5',
                'orientation'   => 'P',
                'margin_left'   => 14,
                'margin_right'  => 14,
                'margin_top'    => 16,
                'margin_bottom' => 14,
                'tempDir'       => $tmp,
            ] );
            $mpdf->SetTitle( 'Ricevuta quota GFOSS.it APS' );
            $mpdf->SetAuthor( 'GFOSS.it APS' );
            $mpdf->WriteHTML( $html );
            return $mpdf->Output( '', 'S' );
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'mpdf_fail', 'Errore generazione PDF: ' . $e->getMessage() );
        }
    }

    private static function render_html( int $user_id, array $row ): string {
        $u        = get_userdata( $user_id );
        $anno     = (int) $row['anno'];
        $numero   = (string) get_user_meta( $user_id, 'gf_numero_socio', true );
        $cf_socio = (string) get_user_meta( $user_id, 'gf_codice_fiscale', true );
        $importo  = number_format_i18n( (float) $row['importo'], 2 );
        $metodo   = ucfirst( (string) $row['metodo'] );
        $data_pag = ! empty( $row['data_pagamento'] ) ? date_i18n( 'd/m/Y', strtotime( $row['data_pagamento'] ) ) : '—';
        $num_ric  = sprintf( '%d/%d', (int) $row['id'], $anno );
        $emessa   = date_i18n( 'd/m/Y' );

        $cf_assoc = defined( 'GFOSS_ASSOC_CF' ) ? GFOSS_ASSOC_CF : '95090860131';
        $iban     = defined( 'GFOSS_ASSOC_IBAN' ) ? GFOSS_ASSOC_IBAN : '';

        $e = static fn( $v ) => esc_html( (string) $v );

        return '
<style>
  body { font-family: sans-serif; color: #0F2330; font-size: 11pt; }
  .head { border-bottom: 2px solid #1A6FA0; padding-bottom: 8px; margin-bottom: 18px; }
  .org { font-size: 14pt; font-weight: bold; color: #1A6FA0; }
  .org small { display:block; font-weight: normal; color:#4A5C6A; font-size: 8.5pt; margin-top:2px; }
  h1 { font-size: 13pt; margin: 18px 0 4px; }
  .meta { color:#4A5C6A; font-size: 9.5pt; margin-bottom: 16px; }
  table.kv { width:100%; border-collapse: collapse; margin: 8px 0 16px; }
  table.kv td { padding: 6px 4px; border-bottom: 1px solid #E2E8EC; vertical-align: top; }
  table.kv td.k { color:#4A5C6A; width: 42%; }
  .amount { font-size: 15pt; font-weight: bold; color:#1A6FA0; }
  .note { font-size: 8.5pt; color:#4A5C6A; margin-top: 18px; line-height: 1.45; }
  .sign { margin-top: 28px; text-align: right; font-size: 9.5pt; color:#4A5C6A; }
</style>
<div class="head">
  <div class="org">GFOSS.it APS
    <small>Associazione Italiana per l\'Informazione Geografica Libera — Ente del Terzo Settore (RUNTS)<br>
    Lungargine Gerolamo Rovetta 28, 35131 Padova — C.F. ' . $e( $cf_assoc ) . '</small>
  </div>
</div>

<h1>Ricevuta di versamento quota associativa</h1>
<div class="meta">Ricevuta n. <strong>' . $e( $num_ric ) . '</strong> &middot; emessa il ' . $e( $emessa ) . '</div>

<table class="kv">
  <tr><td class="k">Ricevuto da</td><td><strong>' . $e( $u ? $u->display_name : '' ) . '</strong></td></tr>
  <tr><td class="k">Codice fiscale</td><td>' . ( $cf_socio ? $e( $cf_socio ) : '—' ) . '</td></tr>
  <tr><td class="k">Socio n.</td><td>' . ( $numero ? $e( $numero ) : '—' ) . '</td></tr>
  <tr><td class="k">Causale</td><td>Quota associativa anno ' . $e( $anno ) . '</td></tr>
  <tr><td class="k">Modalità di pagamento</td><td>' . $e( $metodo ) . '</td></tr>
  <tr><td class="k">Data del pagamento</td><td>' . $e( $data_pag ) . '</td></tr>
  <tr><td class="k">Importo</td><td class="amount">&euro; ' . $e( $importo ) . '</td></tr>
</table>

<div class="note">
  Quota associativa non soggetta a IVA. Le quote e i contributi associativi non costituiscono corrispettivo
  e sono esclusi dal campo di applicazione dell\'imposta. Il presente documento è una ricevuta di versamento
  e non costituisce fattura. Conservare ai fini delle agevolazioni fiscali previste per gli enti del Terzo Settore.
  ' . ( $iban ? '<br>IBAN associazione: ' . $e( $iban ) . '.' : '' ) . '
</div>

<div class="sign">GFOSS.it APS — Il Tesoriere</div>
';
    }
}
