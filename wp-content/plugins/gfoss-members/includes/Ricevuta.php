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
        // Il nonce wp_rest autentica la richiesta via cookie (senza, la REST è anonima → 401).
        return add_query_arg(
            [ 'user' => $user_id, 'anno' => $anno, '_wpnonce' => wp_create_nonce( 'wp_rest' ) ],
            rest_url( 'gfoss/v1/ricevuta' )
        );
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
        if ( ! Quote::has_ricevuta( $row ) ) {
            return new \WP_REST_Response( 'Ricevuta non ancora emessa: mancano il numero progressivo e/o la data di pagamento.', 404 );
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
        $importo  = number_format( (float) $row['importo'], 2, ',', '.' );
        $data_pag = ! empty( $row['data_pagamento'] ) ? date_i18n( 'd.m.Y', strtotime( $row['data_pagamento'] ) ) : '';
        $num_ric  = sprintf( '%d/%d', (int) $row['ricevuta_numero'], $anno );

        $cf_assoc = defined( 'GFOSS_ASSOC_CF' )   ? GFOSS_ASSOC_CF   : '95090860131';
        $piva     = defined( 'GFOSS_ASSOC_PIVA' ) ? GFOSS_ASSOC_PIVA : 'IT03158540132';
        $cap      = defined( 'GFOSS_ASSOC_CAP' )  ? GFOSS_ASSOC_CAP  : '35127';

        $e = static fn( $v ) => esc_html( (string) $v );

        $metodo_map = [ 'bonifico' => 'un bonifico', 'paypal' => 'un pagamento tramite PayPal', 'contanti' => 'un versamento in contanti', 'carta' => 'un pagamento con carta' ];
        $metodo_txt = $metodo_map[ (string) $row['metodo'] ] ?? 'un versamento';

        $nome_socio = $u ? $u->display_name : '';

        // Pagatore: terzo (se impostato) oppure il socio stesso.
        if ( ! empty( $row['pagatore_nome'] ) ) {
            $pag = '<strong>' . $e( $row['pagatore_nome'] ) . '</strong>';
            if ( ! empty( $row['pagatore_sede'] ) ) { $pag .= ' con sede in ' . $e( $row['pagatore_sede'] ); }
            $ids = [];
            if ( ! empty( $row['pagatore_cf'] ) )   { $ids[] = 'Codice Fiscale ' . $e( $row['pagatore_cf'] ); }
            if ( ! empty( $row['pagatore_piva'] ) ) { $ids[] = 'Partita IVA ' . $e( $row['pagatore_piva'] ); }
            if ( $ids ) { $pag .= ', ' . implode( ' e ', $ids ); }
        } else {
            $cf_socio = (string) get_user_meta( $user_id, 'gf_codice_fiscale', true );
            $prov = (string) get_user_meta( $user_id, 'gf_provincia', true );
            $ind  = trim( get_user_meta( $user_id, 'gf_indirizzo', true ) . ', ' . get_user_meta( $user_id, 'gf_cap', true ) . ' ' . get_user_meta( $user_id, 'gf_citta', true ) . ( $prov ? ' (' . $prov . ')' : '' ) );
            $ind  = trim( $ind, ', ' );
            $pag  = '<strong>' . $e( $nome_socio ) . '</strong>';
            if ( $ind !== '' )  { $pag .= ' residente in ' . $e( $ind ); }
            if ( $cf_socio )    { $pag .= ', Codice Fiscale ' . $e( $cf_socio ); }
        }

        $verbale = '';
        if ( ! empty( $row['verbale_data'] ) ) {
            $verbale = ' L\'iscrizione è stata approvata dal Consiglio Direttivo con verbale del ' . $e( date_i18n( 'd.m.Y', strtotime( $row['verbale_data'] ) ) ) . '.';
        }

        $num_e  = $e( $num_ric );
        $data_e = $e( $data_pag );
        $imp_e  = $e( $importo );
        $anno_e = (int) $anno;
        $cap_e  = $e( $cap );
        $piva_e = $e( $piva );
        $cf_e   = $e( $cf_assoc );
        $nome_e = $e( $nome_socio );

        return <<<HTML
<style>
  body { font-family: sans-serif; color:#10242f; font-size:10.5pt; line-height:1.5; }
  .head { border-bottom:2px solid #1A6FA0; padding-bottom:8px; margin-bottom:16px; }
  .org { font-size:13pt; font-weight:bold; color:#1A6FA0; }
  .org small { display:block; font-weight:normal; color:#4A5C6A; font-size:8pt; margin-top:2px; }
  h1 { font-size:13pt; margin:14px 0 12px; }
  .dichiara { text-align:center; font-weight:bold; margin:6px 0; }
  ul { margin:6px 0 6px 0; padding-left:16px; }
  li { margin-bottom:6px; text-align:justify; }
  p { text-align:justify; }
  .sign { margin-top:34px; text-align:right; }
  .bollo { font-style:italic; color:#4A5C6A; font-size:8.5pt; margin-top:18px; }
  .foot { border-top:1px solid #e0e0e0; margin-top:20px; padding-top:8px; text-align:center; color:#888; font-size:8pt; font-style:italic; }
</style>
<div class="head"><div class="org">GFOSS.it APS
  <small>Lungargine Gerolamo Rovetta, 28 - {$cap_e} Padova (PD)<br>
  Partita IVA {$piva_e} - Codice Fiscale {$cf_e}<br>www.gfoss.it – info@gfoss.it</small>
</div></div>

<h1>Ricevuta nr. {$num_e} del {$data_e}</h1>

<p>L'Associazione di Promozione Sociale denominata <strong>GFOSS.it APS</strong> con sede in Lungargine Gerolamo Rovetta, 28 - {$cap_e} Padova (PD)</p>
<p class="dichiara">dichiara</p>
<p>di aver ricevuto {$metodo_txt} di <strong>{$imp_e} EUR</strong> in data {$data_e} tramite versamento sul proprio conto corrente dal {$pag}. La somma ricevuta copre la quota associativa a GFOSS.it APS per il {$anno_e} di <strong>{$nome_e}</strong>.{$verbale}</p>

<p>L'ENTE dichiara inoltre la propria natura non commerciale ai sensi dell'art. 79, comma 5 del D.Lgs n.117 del 03/07/2017 e che pertanto, relativamente alle liberalità erogate, i donatori possono optare:</p>
<ul>
  <li><strong>Se persone fisiche:</strong> per la detrazione fiscale al 30% fino ad un massimo di € 30.000 (o al 35% fino ad un massimo di € 30.000 se ODV) ovvero dedurre la donazione dal proprio reddito complessivo dichiarato per un importo non superiore al 10% del reddito; se la deduzione risultasse maggiore, l'eccedenza può essere computata in aumento dell'importo deducibile dal reddito complessivo dei periodi di imposta successivi, ma non oltre il 4°, fino a concorrenza del suo ammontare (ai sensi dell'art. 83, del D.Lgs n.117 del 03/07/2017).</li>
  <li><strong>Se imprese:</strong> dedurre la donazione dal proprio reddito complessivo dichiarato per un importo non superiore al 10% del reddito; se la deduzione risultasse maggiore, l'eccedenza può essere computata in aumento dell'importo deducibile dal reddito complessivo dei periodi di imposta successivi, ma non oltre il 4°, fino a concorrenza del suo ammontare (ai sensi dell'art. 83, del D.Lgs n.117 del 03/07/2017).</li>
</ul>

<p>Ai sensi e per gli effetti del DLgs n. 193 del 2003, La informiamo che il trattamento dei Suoi dati viene effettuato per l'esclusivo perseguimento delle finalità statutarie dell'Ente.</p>

<div class="sign">Firma del Tesoriere dell'Associazione</div>
<p class="bollo">Esente da bollo ai sensi dell'Art. 82 c. 5 D.lgs. 3 luglio 2017 n. 117</p>
<p class="foot">GFOSS.it è un'associazione non-profit nazionale per la promozione sociale del software geografico libero.<br>Per informazioni sullo statuto, le finalità e le modalità di iscrizione, si consulti il sito Internet www.gfoss.it.</p>
HTML;
    }
}
