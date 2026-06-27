<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Registro dei volontari (art. 17 D.Lgs. 117/2017) a fini assicurativi (art. 18).
 *
 * Contiene SOLO i volontari (soci o occasionali non soci) che operano nelle
 * manifestazioni pubbliche, non tutti i soci. Requisiti:
 *  - dati anagrafici (CF oppure generalità complete: nome, cognome, luogo+data nascita)
 *  - residenza/domicilio
 *  - data inizio e data cessazione dell'attività
 *
 * Inalterabilità: la tabella usa SYSTEM VERSIONING di MariaDB (storico automatico),
 * più un audit log applicativo (chi-cosa-quando). La "cancellazione" è una data di
 * cessazione: la riga non viene mai eliminata fisicamente.
 *
 * Data certa: l'elenco è esportabile in PDF con hash SHA-256 dei dati; il PDF + hash
 * vanno messi a verbale del Direttivo e inviati via PEC (processo esterno).
 */
class Volontari {

    /** Colonne anagrafiche gestite dal form (oltre a date/tipo/note). */
    private const FIELDS = [ 'user_id', 'nome', 'cognome', 'codice_fiscale', 'luogo_nascita',
        'data_nascita', 'indirizzo', 'cap', 'citta', 'provincia', 'tipo', 'data_inizio',
        'data_cessazione', 'note' ];

    public static function init(): void {
        add_action( 'admin_post_gfoss_volontari_pdf', [ __CLASS__, 'handle_pdf' ] );
    }

    public static function can_manage(): bool {
        return current_user_can( Roles::CAP_MANAGE_VOLONTARI );
    }

    // --- Lettura -----------------------------------------------------------

    /** @return array<int,array> tutti i volontari (default: anche i cessati). */
    public static function all( bool $solo_attivi = false ): array {
        global $wpdb;
        $t = Schema::table_volontari();
        $where = $solo_attivi ? 'WHERE data_cessazione IS NULL' : '';
        return (array) $wpdb->get_results( "SELECT * FROM $t $where ORDER BY data_cessazione IS NULL DESC, cognome ASC, nome ASC", ARRAY_A );
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $t = Schema::table_volontari();
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    public static function count_attivi(): int {
        global $wpdb;
        $t = Schema::table_volontari();
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE data_cessazione IS NULL" );
    }

    /** Prossimo numero progressivo di registro (art. 17: tenuta numerata). */
    public static function next_n_registro(): int {
        global $wpdb;
        $t = Schema::table_volontari();
        return 1 + (int) $wpdb->get_var( "SELECT COALESCE(MAX(n_registro),0) FROM $t" );
    }

    // --- Liste per evento --------------------------------------------------

    /** @return array<int,\WP_Post> eventi del sito, più recenti prima. */
    public static function eventi_list(): array {
        return get_posts( [
            'post_type'   => Eventi::CPT,
            'numberposts' => 200,
            'post_status' => [ 'publish', 'future', 'draft' ],
            'meta_key'    => '_gf_ev_data',
            'orderby'     => 'meta_value',
            'order'       => 'DESC',
        ] );
    }

    public static function event_meta( int $evento_id ): array {
        $p = get_post( $evento_id );
        return [
            'titolo' => $p ? $p->post_title : '',
            'inizio' => (string) get_post_meta( $evento_id, '_gf_ev_data', true ),
            'fine'   => (string) get_post_meta( $evento_id, '_gf_ev_data_fine', true ),
            'luogo'  => (string) get_post_meta( $evento_id, '_gf_ev_luogo', true ),
        ];
    }

    /** Periodo leggibile dell'evento, gestendo i multi-giorno. */
    public static function format_periodo( string $inizio, string $fine ): string {
        if ( ! $inizio ) { return ''; }
        $i = strtotime( $inizio );
        $f = $fine ? strtotime( $fine ) : 0;
        if ( $f && date( 'Y-m-d', $f ) !== date( 'Y-m-d', $i ) ) {
            return 'dal ' . date_i18n( 'd/m/Y', $i ) . ' al ' . date_i18n( 'd/m/Y', $f );
        }
        return date_i18n( 'd/m/Y', $i );
    }

    /** @return array<int,array> volontari collegati all'evento. */
    public static function volontari_for_event( int $evento_id ): array {
        global $wpdb;
        $tv = Schema::table_volontari();
        $te = Schema::table_volontari_eventi();
        return (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT v.* FROM $te e JOIN $tv v ON v.id = e.volontario_id WHERE e.evento_id = %d ORDER BY v.cognome ASC, v.nome ASC", $evento_id ), ARRAY_A );
    }

    /** @return int[] id volontario già presenti nell'evento. */
    public static function ids_in_event( int $evento_id ): array {
        global $wpdb;
        $te = Schema::table_volontari_eventi();
        return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT volontario_id FROM $te WHERE evento_id = %d", $evento_id ) ) );
    }

    public static function add_to_event( int $volontario_id, int $evento_id ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            'INSERT IGNORE INTO ' . Schema::table_volontari_eventi() . ' (volontario_id, evento_id, created_by) VALUES (%d, %d, %d)',
            $volontario_id, $evento_id, get_current_user_id() ) );
        self::log_audit( $volontario_id, 'evento_add', [ 'evento_id' => $evento_id ] );
    }

    public static function remove_from_event( int $volontario_id, int $evento_id ): void {
        global $wpdb;
        $wpdb->delete( Schema::table_volontari_eventi(), [ 'volontario_id' => $volontario_id, 'evento_id' => $evento_id ] );
        self::log_audit( $volontario_id, 'evento_remove', [ 'evento_id' => $evento_id ] );
    }

    // --- Scrittura ---------------------------------------------------------

    /** Normalizza i dati dal $_POST. */
    private static function sanitize( array $in ): array {
        $tipo = in_array( $in['tipo'] ?? '', [ 'continuativo', 'occasionale' ], true ) ? $in['tipo'] : 'continuativo';
        return [
            'user_id'         => ! empty( $in['user_id'] ) ? (int) $in['user_id'] : null,
            'nome'            => sanitize_text_field( $in['nome'] ?? '' ),
            'cognome'         => sanitize_text_field( $in['cognome'] ?? '' ),
            'codice_fiscale'  => strtoupper( sanitize_text_field( $in['codice_fiscale'] ?? '' ) ) ?: null,
            'luogo_nascita'   => sanitize_text_field( $in['luogo_nascita'] ?? '' ) ?: null,
            'data_nascita'    => self::clean_date( $in['data_nascita'] ?? '' ),
            'indirizzo'       => sanitize_text_field( $in['indirizzo'] ?? '' ) ?: null,
            'cap'             => sanitize_text_field( $in['cap'] ?? '' ) ?: null,
            'citta'           => sanitize_text_field( $in['citta'] ?? '' ) ?: null,
            'provincia'       => strtoupper( sanitize_text_field( $in['provincia'] ?? '' ) ) ?: null,
            'tipo'            => $tipo,
            'data_inizio'     => self::clean_date( $in['data_inizio'] ?? '' ) ?: current_time( 'Y-m-d' ),
            'data_cessazione' => self::clean_date( $in['data_cessazione'] ?? '' ),
            'note'            => sanitize_text_field( $in['note'] ?? '' ) ?: null,
        ];
    }

    private static function clean_date( string $d ): ?string {
        $d = trim( $d );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : null;
    }

    /**
     * Verifica i requisiti minimi di legge: CF *oppure* generalità complete
     * (nome, cognome, luogo e data di nascita), più la residenza.
     * @return string '' se valido, altrimenti il messaggio d'errore.
     */
    public static function validate( array $d ): string {
        if ( $d['nome'] === '' || $d['cognome'] === '' ) {
            return 'Nome e cognome sono obbligatori.';
        }
        $ha_cf            = ! empty( $d['codice_fiscale'] );
        $ha_generalita    = ! empty( $d['luogo_nascita'] ) && ! empty( $d['data_nascita'] );
        if ( ! $ha_cf && ! $ha_generalita ) {
            return 'Inserire il codice fiscale oppure, in alternativa, luogo e data di nascita.';
        }
        if ( empty( $d['indirizzo'] ) || empty( $d['citta'] ) ) {
            return 'La residenza/domicilio (indirizzo e città) è obbligatoria.';
        }
        return '';
    }

    /** @return int|\WP_Error id del nuovo volontario. */
    public static function insert( array $in ) {
        global $wpdb;
        $d = self::sanitize( $in );
        $err = self::validate( $d );
        if ( $err ) { return new \WP_Error( 'invalid', $err ); }

        $d['created_by'] = get_current_user_id();
        $d['n_registro'] = self::next_n_registro();
        $ok = $wpdb->insert( Schema::table_volontari(), $d );
        if ( ! $ok ) { return new \WP_Error( 'db', 'Inserimento non riuscito.' ); }
        $id = (int) $wpdb->insert_id;
        self::log_audit( $id, 'create', [ 'after' => $d ] );
        return $id;
    }

    /** @return true|\WP_Error */
    public static function update( int $id, array $in ) {
        global $wpdb;
        $before = self::get( $id );
        if ( ! $before ) { return new \WP_Error( 'notfound', 'Volontario non trovato.' ); }
        $d = self::sanitize( $in );
        $err = self::validate( $d );
        if ( $err ) { return new \WP_Error( 'invalid', $err ); }

        $ok = $wpdb->update( Schema::table_volontari(), $d, [ 'id' => $id ] );
        if ( $ok === false ) { return new \WP_Error( 'db', 'Aggiornamento non riuscito.' ); }
        self::log_audit( $id, 'update', [ 'before' => self::diff_subset( $before, self::FIELDS ), 'after' => $d ] );
        return true;
    }

    public static function cessa( int $id, string $data ): bool {
        global $wpdb;
        $data = self::clean_date( $data ) ?: current_time( 'Y-m-d' );
        $ok = $wpdb->update( Schema::table_volontari(), [ 'data_cessazione' => $data ], [ 'id' => $id ] );
        if ( $ok !== false ) { self::log_audit( $id, 'cessazione', [ 'data_cessazione' => $data ] ); }
        return $ok !== false;
    }

    public static function riattiva( int $id ): bool {
        global $wpdb;
        $ok = $wpdb->update( Schema::table_volontari(), [ 'data_cessazione' => null ], [ 'id' => $id ] );
        if ( $ok !== false ) { self::log_audit( $id, 'riattivazione', [] ); }
        return $ok !== false;
    }

    private static function diff_subset( array $row, array $keys ): array {
        $out = [];
        foreach ( $keys as $k ) { $out[ $k ] = $row[ $k ] ?? null; }
        return $out;
    }

    // --- Audit log ---------------------------------------------------------

    public static function log_audit( int $volontario_id, string $azione, array $dettaglio ): void {
        global $wpdb;
        $u = wp_get_current_user();
        $wpdb->insert( Schema::table_volontari_audit(), [
            'volontario_id' => $volontario_id,
            'azione'        => $azione,
            'user_id'       => $u ? (int) $u->ID : null,
            'user_login'    => $u ? $u->user_login : null,
            'dettaglio'     => wp_json_encode( $dettaglio, JSON_UNESCAPED_UNICODE ),
            'ip'            => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null,
        ] );
    }

    /** @return array<int,array> ultime righe di audit (opzionalmente per volontario). */
    public static function audit( int $volontario_id = 0, int $limit = 200 ): array {
        global $wpdb;
        $t = Schema::table_volontari_audit();
        if ( $volontario_id ) {
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE volontario_id = %d ORDER BY id DESC LIMIT %d", $volontario_id, $limit ), ARRAY_A );
        }
        return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
    }

    // --- Esportazione PDF con hash (data certa) ----------------------------

    /** Calcola l'hash SHA-256 canonico di un insieme di righe del registro. */
    public static function hash_rows( array $rows ): string {
        $canon = [];
        foreach ( $rows as $r ) {
            $canon[] = [
                $r['id'] ?? 0, $r['nome'] ?? '', $r['cognome'] ?? '', $r['codice_fiscale'] ?? '',
                $r['luogo_nascita'] ?? '', $r['data_nascita'] ?? '', $r['indirizzo'] ?? '',
                $r['cap'] ?? '', $r['citta'] ?? '', $r['provincia'] ?? '', $r['tipo'] ?? '',
                $r['data_inizio'] ?? '', $r['data_cessazione'] ?? '',
            ];
        }
        return hash( 'sha256', (string) wp_json_encode( $canon, JSON_UNESCAPED_UNICODE ) );
    }

    public static function handle_pdf(): void {
        if ( ! self::can_manage() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_volontari_pdf' );

        $evento_id = (int) ( $_POST['evento_id'] ?? 0 );
        if ( $evento_id ) {
            $rows           = self::volontari_for_event( $evento_id );
            $em             = self::event_meta( $evento_id );
            $manifestazione = $em['titolo'];
            $data_label     = self::format_periodo( $em['inizio'], $em['fine'] );
            $fname_date     = $em['inizio'] ? substr( $em['inizio'], 0, 10 ) : current_time( 'Y-m-d' );
        } else {
            $ids            = array_map( 'absint', (array) ( $_POST['ids'] ?? [] ) );
            $manifestazione = sanitize_text_field( wp_unslash( $_POST['manifestazione'] ?? '' ) );
            $dm             = self::clean_date( (string) ( $_POST['data_manifestazione'] ?? '' ) );
            $data_label     = $dm ? date_i18n( 'd/m/Y', strtotime( $dm ) ) : '';
            $fname_date     = $dm ?: current_time( 'Y-m-d' );
            $rows = [];
            foreach ( $ids as $id ) {
                $r = self::get( $id );
                if ( $r ) { $rows[] = $r; }
            }
        }
        if ( ! $rows ) {
            wp_safe_redirect( add_query_arg( 'msg', 'pdf_empty', wp_get_referer() ) ); exit;
        }

        $pdf = self::generate_pdf( $rows, $manifestazione, $data_label );
        if ( $pdf instanceof \WP_Error ) {
            wp_safe_redirect( add_query_arg( 'msg', 'pdf_err', wp_get_referer() ) ); exit;
        }

        // Audit: registrazione dell'avvenuta generazione dell'elenco (data certa).
        self::log_audit( 0, 'export_pdf', [
            'manifestazione' => $manifestazione,
            'data'           => $data_label,
            'evento_id'      => $evento_id,
            'volontari'      => count( $rows ),
            'hash'           => self::hash_rows( $rows ),
        ] );

        $fname = 'registro-volontari-' . $fname_date . '.pdf';
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $fname . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        header( 'Cache-Control: private, max-age=0, must-revalidate' );
        echo $pdf;
        exit;
    }

    /** @return string PDF binario o WP_Error. */
    public static function generate_pdf( array $rows, string $manifestazione, ?string $data_label ): string|\WP_Error {
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            $autoload = GFOSS_MEMBERS_DIR . 'vendor/autoload.php';
            if ( is_file( $autoload ) ) { require_once $autoload; }
        }
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            return new \WP_Error( 'no_mpdf', 'mPDF non installato.' );
        }

        $hash    = self::hash_rows( $rows );
        $emesso  = date_i18n( 'd/m/Y H:i' );
        $html    = self::render_html( $rows, $manifestazione, $data_label, $hash, $emesso );

        try {
            $tmp = WP_CONTENT_DIR . '/uploads/gfoss-tmp';
            if ( ! is_dir( $tmp ) ) { wp_mkdir_p( $tmp ); }
            $mpdf = new \Mpdf\Mpdf( [
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'orientation'   => 'L',
                'margin_left'   => 12,
                'margin_right'  => 12,
                'margin_top'    => 14,
                'margin_bottom' => 16,
                'tempDir'       => $tmp,
            ] );
            $mpdf->SetTitle( 'Registro volontari GFOSS.it APS' );
            $mpdf->SetAuthor( 'GFOSS.it APS' );
            $mpdf->WriteHTML( $html );
            return $mpdf->Output( '', 'S' );
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'mpdf_fail', 'Errore generazione PDF: ' . $e->getMessage() );
        }
    }

    /** Percorso filesystem del logo per il PDF: custom logo di WP o asset del tema. */
    private static function logo_path(): string {
        $id = (int) get_theme_mod( 'custom_logo' );
        if ( $id ) {
            $p = get_attached_file( $id );
            if ( $p && is_file( $p ) ) { return $p; }
        }
        $theme = get_template_directory() . '/assets/img/logo.png';
        return is_file( $theme ) ? $theme : '';
    }

    private static function render_html( array $rows, string $manifestazione, ?string $data_label, string $hash, string $emesso ): string {
        $e   = static fn( $v ) => esc_html( (string) $v );
        $cf_assoc = defined( 'GFOSS_ASSOC_CF' ) ? GFOSS_ASSOC_CF : '95090860131';
        $logo     = self::logo_path();
        $fmt = static fn( $d ) => $d ? date_i18n( 'd/m/Y', strtotime( (string) $d ) ) : '—';

        $righe = '';
        $n = 0;
        foreach ( $rows as $r ) {
            $n++;
            $nascita = $r['luogo_nascita'] ? $e( $r['luogo_nascita'] ) . ' (' . $fmt( $r['data_nascita'] ) . ')' : $fmt( $r['data_nascita'] );
            $resid   = trim( ( $r['indirizzo'] ? $r['indirizzo'] . ', ' : '' ) . ( $r['cap'] ? $r['cap'] . ' ' : '' ) . ( $r['citta'] ?? '' ) . ( $r['provincia'] ? ' (' . $r['provincia'] . ')' : '' ) );
            $righe .= '<tr>'
                . '<td>' . $n . '</td>'
                . '<td><strong>' . $e( $r['cognome'] ) . ' ' . $e( $r['nome'] ) . '</strong></td>'
                . '<td>' . ( $r['codice_fiscale'] ? $e( $r['codice_fiscale'] ) : '—' ) . '</td>'
                . '<td>' . $nascita . '</td>'
                . '<td>' . $e( $resid ) . '</td>'
                . '<td>' . ( $r['tipo'] === 'occasionale' ? 'Occasionale' : 'Continuativo' ) . '</td>'
                . '<td>' . $fmt( $r['data_inizio'] ) . '</td>'
                . '<td>' . $fmt( $r['data_cessazione'] ) . '</td>'
                . '</tr>';
        }

        $sub = $manifestazione
            ? 'Manifestazione: <strong>' . $e( $manifestazione ) . '</strong>' . ( $data_label ? ' &middot; ' . $e( $data_label ) : '' )
            : 'Elenco generale dei volontari';

        return '
<style>
  body { font-family: sans-serif; color:#0F2330; font-size:9.5pt; }
  .head { border-bottom:2px solid #1A6FA0; padding-bottom:6px; margin-bottom:10px; }
  .head td { vertical-align:middle; border:none; padding:0; }
  .org { font-size:13pt; font-weight:bold; color:#1A6FA0; }
  .org small { display:block; font-weight:normal; color:#4A5C6A; font-size:8pt; margin-top:2px; }
  h1 { font-size:12pt; margin:8px 0 2px; }
  .sub { color:#4A5C6A; font-size:9pt; margin-bottom:8px; }
  table.reg { width:100%; border-collapse:collapse; }
  table.reg th, table.reg td { border:1px solid #C7D3DB; padding:4px 5px; text-align:left; vertical-align:top; }
  table.reg th { background:#EAF2F7; font-size:8.5pt; }
  .legal { font-size:7.8pt; color:#4A5C6A; margin-top:10px; line-height:1.4; }
  .cert { margin-top:10px; font-size:8pt; border:1px solid #C7D3DB; padding:6px 8px; background:#F7FAFB; }
  .cert code { font-family:monospace; word-break:break-all; }
  .sign { margin-top:24px; width:100%; }
  .sign td { width:50%; font-size:9pt; color:#4A5C6A; padding-top:18px; }
</style>
<table class="head"><tr>
  ' . ( $logo ? '<td style="width:54px"><img src="' . $e( $logo ) . '" style="width:46px"></td>' : '' ) . '
  <td><div class="org">GFOSS.it APS
    <small>Associazione Italiana per l\'Informazione Geografica Libera — Ente del Terzo Settore (RUNTS)<br>
    Lungargine Gerolamo Rovetta 28, 35131 Padova — C.F. ' . $e( $cf_assoc ) . '</small>
  </div></td>
</tr></table>

<h1>Registro dei volontari</h1>
<div class="sub">' . $sub . ' &middot; documento emesso il ' . $e( $emesso ) . ' &middot; ' . count( $rows ) . ' nominativi</div>

<table class="reg">
  <thead><tr>
    <th>#</th><th>Cognome e nome</th><th>Codice fiscale</th><th>Luogo e data di nascita</th>
    <th>Residenza / domicilio</th><th>Tipo</th><th>Inizio attività</th><th>Cessazione</th>
  </tr></thead>
  <tbody>' . $righe . '</tbody>
</table>

<div class="legal">
  Registro tenuto ai sensi dell\'art. 17, c. 1 del D.Lgs. 117/2017, a fini della copertura assicurativa
  obbligatoria dei volontari (art. 18). I dati sono conservati con tracciamento delle modifiche (audit log)
  e versioning del database a garanzia di inalterabilità.
</div>

<div class="cert">
  <strong>Impronta di integrità (SHA-256) dei dati di questo elenco:</strong><br>
  <code>' . $e( $hash ) . '</code><br>
  Da riportare nel verbale del Consiglio Direttivo e trasmettere via PEC per l\'attribuzione di data certa.
</div>

<table class="sign">
  <tr>
    <td>Data ____________________</td>
    <td>Il Presidente ____________________________</td>
  </tr>
</table>
';
    }
}
