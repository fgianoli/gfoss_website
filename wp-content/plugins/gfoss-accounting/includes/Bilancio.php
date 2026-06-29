<?php
namespace GFOSS_Accounting;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rendiconto per cassa (Modello D, D.M. 5 marzo 2020) per ETS sotto soglia.
 * Riclassifica i movimenti nelle sezioni ufficiali e produce il quadro di cassa
 * + PDF firmabile. La disponibilità di cassa iniziale è impostata a mano (opzione
 * per anno); la validazione finale resta al commercialista.
 */
class Bilancio {

    /** Categoria → sezione Modello D. */
    const SECTION_MAP = [
        'quota_associativa'   => 'A',
        'donazione'           => 'A',
        'contributo_pubblico' => 'A',
        'cinque_per_mille'    => 'A',
        'spesa_eventi'        => 'A',
        'rimborso_volontario' => 'A',
        'assicurazione'       => 'A',
        'raccolta_fondi'      => 'C',
        'commissioni_bancarie'=> 'D',
        'hosting_servizi'     => 'E',
        'commercialista'      => 'E',
    ];

    const SECTIONS = [
        'A' => 'A) Attività di interesse generale',
        'B' => 'B) Attività diverse',
        'C' => 'C) Attività di raccolta fondi',
        'D' => 'D) Attività finanziarie e patrimoniali',
        'E' => 'E) Attività di supporto generale',
    ];

    public static function cassa_iniziale( int $year ): float {
        return (float) get_option( 'gfoss_acc_cassa_iniziale_' . $year, 0 );
    }

    public static function set_cassa_iniziale( int $year, float $val ): void {
        update_option( 'gfoss_acc_cassa_iniziale_' . $year, round( $val, 2 ), false );
    }

    private static function section_for( string $slug ): string {
        return self::SECTION_MAP[ $slug ] ?? 'E';
    }

    /** Dati riclassificati per il rendiconto. */
    public static function compute( int $year ): array {
        $t = Movement::totals_year( $year );
        $labels = [];
        foreach ( Movement::categories() as $c ) { $labels[ $c['slug'] ] = $c['label']; }

        $group = static function ( array $byslug ) use ( $labels ): array {
            $out = [];
            foreach ( $byslug as $slug => $imp ) {
                $sec = self::section_for( $slug );
                $out[ $sec ]['lines'][ $labels[ $slug ] ?? $slug ] = (float) $imp;
                $out[ $sec ]['tot'] = ( $out[ $sec ]['tot'] ?? 0 ) + (float) $imp;
            }
            return $out;
        };

        $ci = self::cassa_iniziale( $year );
        return [
            'year'           => $year,
            'entrate'        => $group( $t['entrate'] ),
            'uscite'         => $group( $t['uscite'] ),
            'tot_entrate'    => (float) $t['tot_entrate'],
            'tot_uscite'     => (float) $t['tot_uscite'],
            'cassa_iniziale' => $ci,
            'cassa_finale'   => $ci + (float) $t['tot_entrate'] - (float) $t['tot_uscite'],
        ];
    }

    /** @return string PDF binario o WP_Error. */
    public static function generate_pdf( int $year ): string|\WP_Error {
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) && defined( 'GFOSS_MEMBERS_DIR' ) ) {
            $autoload = GFOSS_MEMBERS_DIR . 'vendor/autoload.php';
            if ( is_file( $autoload ) ) { require_once $autoload; }
        }
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            return new \WP_Error( 'no_mpdf', 'mPDF non disponibile (composer install nel plugin gfoss-members).' );
        }
        $html = self::render_html( self::compute( $year ) );
        try {
            $tmp = WP_CONTENT_DIR . '/uploads/gfoss-tmp';
            if ( ! is_dir( $tmp ) ) { wp_mkdir_p( $tmp ); }
            $mpdf = new \Mpdf\Mpdf( [ 'mode' => 'utf-8', 'format' => 'A4', 'margin_top' => 16, 'margin_bottom' => 16, 'tempDir' => $tmp ] );
            $mpdf->SetTitle( 'Rendiconto per cassa ' . $year . ' — GFOSS.it APS' );
            $mpdf->WriteHTML( $html );
            return $mpdf->Output( '', 'S' );
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'mpdf_fail', 'Errore PDF: ' . $e->getMessage() );
        }
    }

    private static function render_html( array $d ): string {
        $eur = static fn( $n ) => number_format( (float) $n, 2, ',', '.' ) . ' €';
        $cf  = defined( 'GFOSS_ASSOC_CF' ) ? GFOSS_ASSOC_CF : '95090860131';

        $col = static function ( array $sezioni, array $order ) use ( $eur ): string {
            $h = '';
            foreach ( $order as $L ) {
                if ( empty( $sezioni[ $L ] ) ) { continue; }
                $h .= '<tr class="sec"><td colspan="2">' . esc_html( self::SECTIONS[ $L ] ) . '</td></tr>';
                foreach ( $sezioni[ $L ]['lines'] as $label => $imp ) {
                    $h .= '<tr><td>' . esc_html( $label ) . '</td><td class="num">' . esc_html( $eur( $imp ) ) . '</td></tr>';
                }
                $h .= '<tr class="subtot"><td>Totale ' . esc_html( $L ) . '</td><td class="num">' . esc_html( $eur( $sezioni[ $L ]['tot'] ) ) . '</td></tr>';
            }
            return $h ?: '<tr><td colspan="2" style="color:#777">—</td></tr>';
        };

        $uscite  = $col( $d['uscite'],  [ 'A','B','C','D','E' ] );
        $entrate = $col( $d['entrate'], [ 'A','B','C','D','E' ] );

        return '
<style>
  body { font-family: sans-serif; color:#10242f; font-size:10pt; }
  .head { border-bottom:2px solid #1A6FA0; padding-bottom:6px; margin-bottom:12px; }
  .org { font-size:13pt; font-weight:bold; color:#1A6FA0; }
  .org small { display:block; font-weight:normal; color:#4A5C6A; font-size:8pt; }
  h1 { font-size:13pt; margin:10px 0 2px; }
  .meta { color:#4A5C6A; font-size:9pt; margin-bottom:12px; }
  table.rc { width:100%; border-collapse:collapse; }
  table.rc td { padding:3px 6px; border-bottom:1px solid #e6edf1; vertical-align:top; }
  table.rc td.num { text-align:right; white-space:nowrap; }
  tr.sec td { background:#eef4f8; font-weight:bold; font-size:9pt; }
  tr.subtot td { font-weight:bold; border-bottom:1px solid #b9c8d2; }
  tr.tot td { font-weight:bold; font-size:11pt; border-top:2px solid #1A6FA0; }
  .grid { width:100%; }
  .grid td { width:50%; vertical-align:top; padding:0 8px; }
  .cassa { margin-top:16px; border:1px solid #b9c8d2; border-radius:6px; padding:8px 10px; width:60%; }
  .cassa table { width:100%; border-collapse:collapse; }
  .cassa td { padding:3px 4px; }
  .cassa td.num { text-align:right; }
  .sign { margin-top:30px; }
  .sign td { width:50%; padding-top:26px; border-top:1px solid #333; font-size:9pt; color:#4A5C6A; text-align:center; }
  .note { font-size:7.5pt; color:#777; margin-top:14px; }
</style>
<div class="head"><div class="org">GFOSS.it APS
  <small>Associazione Italiana per l\'Informazione Geografica Libera — Ente del Terzo Settore (RUNTS) · C.F. ' . esc_html( $cf ) . '</small>
</div></div>
<h1>Rendiconto per cassa — esercizio ' . (int) $d['year'] . '</h1>
<div class="meta">Modello D (D.M. 5 marzo 2020). Bozza generata dalla contabilità — da validare e approvare in assemblea.</div>

<table class="grid"><tr>
  <td>
    <table class="rc">
      <tr class="sec"><td colspan="2" style="background:#1A6FA0;color:#fff">USCITE / ONERI</td></tr>
      ' . $uscite . '
      <tr class="tot"><td>TOTALE USCITE</td><td class="num">' . esc_html( $eur( $d['tot_uscite'] ) ) . '</td></tr>
    </table>
  </td>
  <td>
    <table class="rc">
      <tr class="sec"><td colspan="2" style="background:#5DA34D;color:#fff">ENTRATE / PROVENTI</td></tr>
      ' . $entrate . '
      <tr class="tot"><td>TOTALE ENTRATE</td><td class="num">' . esc_html( $eur( $d['tot_entrate'] ) ) . '</td></tr>
    </table>
  </td>
</tr></table>

<div class="cassa"><strong>Quadro di cassa</strong>
  <table>
    <tr><td>Cassa e banca al 1°/1/' . (int) $d['year'] . '</td><td class="num">' . esc_html( $eur( $d['cassa_iniziale'] ) ) . '</td></tr>
    <tr><td>+ Totale entrate</td><td class="num">' . esc_html( $eur( $d['tot_entrate'] ) ) . '</td></tr>
    <tr><td>− Totale uscite</td><td class="num">' . esc_html( $eur( $d['tot_uscite'] ) ) . '</td></tr>
    <tr style="font-weight:bold;border-top:1px solid #333"><td>= Cassa e banca al 31/12/' . (int) $d['year'] . '</td><td class="num">' . esc_html( $eur( $d['cassa_finale'] ) ) . '</td></tr>
  </table>
</div>

<table class="sign"><tr><td>Il Tesoriere</td><td>Il Presidente</td></tr></table>
<p class="note">Documento generato automaticamente dai movimenti di cassa registrati. La disponibilità iniziale è impostata manualmente. La quadratura e la classificazione vanno verificate dal professionista incaricato. Approvato dall\'assemblea dei soci del __________.</p>
';
    }
}
