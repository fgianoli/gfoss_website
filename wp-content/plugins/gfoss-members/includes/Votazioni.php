<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Voto online d'assemblea, agganciato alle Convocazioni.
 *
 *  - Quesiti creati dal direttivo (CAP_MANAGE_ASSEMBLEE): PALESE (delibere) o
 *    SEGRETO (elezioni), a scelta singola o multipla (N seggi).
 *  - Vota chi è socio in regola; chi ha delegato non vota (vota il delegato).
 *  - Peso = 1 + deleghe ricevute. All'apertura si CONGELA l'elettorato (snapshot
 *    aventi diritto + pesi), così i cambi di quota successivi non influiscono.
 *  - Ogni voto genera una RICEVUTA (codice) che il votante ritrova nell'elenco
 *    urna (verifica), senza svelare l'identità. Alla chiusura si calcola l'HASH
 *    dell'urna (a prova di manomissione). Voti append-only.
 *  - Scheda bianca esplicita, quorum, apertura/chiusura programmata, verbale PDF.
 *
 * Shortcode [gfoss_votazioni].
 */
class Votazioni {

    const DEFAULT_OPZIONI = [ 'Favorevole', 'Contrario', 'Astenuto' ];
    const ROLES = [ 'gfoss_socio','gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_revisore','gfoss_comunicazione','gfoss_segreteria' ];

    public static function init(): void {
        add_shortcode( 'gfoss_votazioni', [ __CLASS__, 'render' ] );
        add_action( 'admin_post_gfoss_voto_create', [ __CLASS__, 'handle_create' ] );
        add_action( 'admin_post_gfoss_voto_edit',   [ __CLASS__, 'handle_edit' ] );
        add_action( 'admin_post_gfoss_voto_state',  [ __CLASS__, 'handle_state' ] );
        add_action( 'admin_post_gfoss_voto_cast',   [ __CLASS__, 'handle_cast' ] );
        add_action( 'admin_post_gfoss_voto_pdf',    [ __CLASS__, 'handle_pdf' ] );
    }

    private static function can_manage(): bool {
        return current_user_can( Roles::CAP_MANAGE_ASSEMBLEE );
    }

    // --- Lettura -----------------------------------------------------------

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::table_votazioni() . ' WHERE id = %d', $id ), ARRAY_A );
        return $row ?: null;
    }

    public static function all(): array {
        global $wpdb;
        return (array) $wpdb->get_results( 'SELECT * FROM ' . Schema::table_votazioni() . ' ORDER BY id DESC', ARRAY_A );
    }

    public static function options( array $vz ): array {
        $o = json_decode( (string) $vz['opzioni'], true );
        if ( ! is_array( $o ) || ! $o ) { return self::DEFAULT_OPZIONI; }
        return array_values( array_map( 'strval', $o ) );
    }

    // --- Ammissibilità, peso, elettorato -----------------------------------

    private static function in_regola( int $uid ): bool {
        return gfoss_members_is_socio( $uid )
            && in_array( Quote::status_for( $uid, (int) gmdate( 'Y' ) ), [ 'paid', 'expiring' ], true );
    }

    private static function has_delegated( int $conv_id, int $uid ): bool {
        if ( ! $conv_id || ! class_exists( __NAMESPACE__ . '\\Convocazioni' ) ) { return false; }
        return isset( Convocazioni::deleghe( $conv_id )[ $uid ] );
    }

    /** Peso "vivo" = 1 + deleghe ricevute da deleganti in regola. */
    private static function live_weight( int $conv_id, int $uid ): int {
        $w = 1;
        if ( $conv_id && class_exists( __NAMESPACE__ . '\\Convocazioni' ) ) {
            foreach ( Convocazioni::deleghe( $conv_id ) as $delegante => $delegato ) {
                if ( (int) $delegato === $uid && self::in_regola( (int) $delegante ) ) { $w++; }
            }
        }
        return $w;
    }

    /** Snapshot degli aventi diritto al momento dell'apertura: [uid => peso]. */
    private static function snapshot_electorate( int $conv_id ): array {
        $snap = [];
        foreach ( get_users( [ 'role__in' => self::ROLES, 'fields' => [ 'ID' ] ] ) as $u ) {
            $uid = (int) $u->ID;
            if ( ! self::in_regola( $uid ) ) { continue; }
            if ( self::has_delegated( $conv_id, $uid ) ) { continue; } // chi delega non è elettore attivo
            $snap[ $uid ] = self::live_weight( $conv_id, $uid );
        }
        return $snap;
    }

    /** Elettorato congelato (o null se non ancora fissato). @return array<int,int>|null */
    private static function electorate( array $vz ): ?array {
        if ( empty( $vz['elettorato'] ) ) { return null; }
        $d = json_decode( (string) $vz['elettorato'], true );
        return is_array( $d ) ? array_map( 'intval', $d ) : null;
    }

    /** Peso dell'utente per questa votazione (0 = non ha diritto). */
    public static function eligible_weight( array $vz, int $uid ): int {
        $snap = self::electorate( $vz );
        if ( is_array( $snap ) ) { return (int) ( $snap[ $uid ] ?? 0 ); }
        // Fallback (votazioni senza snapshot): calcolo vivo.
        if ( ! self::in_regola( $uid ) || self::has_delegated( (int) $vz['convocazione_id'], $uid ) ) { return 0; }
        return self::live_weight( (int) $vz['convocazione_id'], $uid );
    }

    public static function has_voted( int $vid, int $uid ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . Schema::table_votanti() . ' WHERE votazione_id = %d AND user_id = %d', $vid, $uid ) );
    }

    public static function vote_block_reason( array $vz, int $uid ): string {
        if ( $vz['stato'] !== 'aperta' ) { return 'Votazione non aperta.'; }
        if ( self::eligible_weight( $vz, $uid ) <= 0 ) {
            if ( ! self::in_regola( $uid ) ) { return 'Devi essere in regola con la quota per votare.'; }
            if ( self::has_delegated( (int) $vz['convocazione_id'], $uid ) ) { return 'Hai delegato un altro socio: voterà lui per te.'; }
            return 'Non risulti tra gli aventi diritto per questa votazione.';
        }
        if ( self::has_voted( (int) $vz['id'], $uid ) ) { return 'Hai già votato.'; }
        return '';
    }

    // --- Risultati e integrità --------------------------------------------

    public static function results( int $vid ): array {
        global $wpdb;
        $tv = Schema::table_voti_assemblea();
        $tn = Schema::table_votanti();
        $rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT opzione, SUM(peso) peso FROM $tv WHERE votazione_id = %d AND bianca = 0 GROUP BY opzione", $vid ), ARRAY_A );
        $by = [];
        foreach ( $rows as $r ) { $by[ (int) $r['opzione'] ] = (int) $r['peso']; }
        $bianche  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(peso),0) FROM $tv WHERE votazione_id = %d AND bianca = 1", $vid ) );
        $turnout  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tn WHERE votazione_id = %d", $vid ) );
        $tot_peso = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(peso),0) FROM $tv WHERE votazione_id = %d AND bianca = 0", $vid ) );
        // Peso partecipante (una riga per scheda, via codice).
        $turnout_peso = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(p),0) FROM (SELECT MAX(peso) p FROM $tv WHERE votazione_id = %d GROUP BY codice) t", $vid ) );
        return [ 'by' => $by, 'bianche' => $bianche, 'turnout' => $turnout, 'tot_peso' => $tot_peso, 'turnout_peso' => $turnout_peso ];
    }

    public static function electorate_weight( array $vz ): int {
        $snap = self::electorate( $vz );
        return is_array( $snap ) ? (int) array_sum( $snap ) : 0;
    }

    /** Hash SHA-256 dell'urna (serializzazione canonica di codice|opzione|peso|bianca). */
    public static function compute_hash( int $vid ): string {
        global $wpdb;
        $tv = Schema::table_voti_assemblea();
        $rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT codice, opzione, peso, bianca FROM $tv WHERE votazione_id = %d ORDER BY codice, opzione, id", $vid ), ARRAY_A );
        $canon = '';
        foreach ( $rows as $r ) { $canon .= $r['codice'] . '|' . $r['opzione'] . '|' . $r['peso'] . '|' . $r['bianca'] . "\n"; }
        return hash( 'sha256', $canon );
    }

    /** Elenco urna per la verifica: [codice => [labels]]. */
    private static function urna( array $vz ): array {
        global $wpdb;
        $tv = Schema::table_voti_assemblea();
        $opz = self::options( $vz );
        $rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT codice, opzione, bianca FROM $tv WHERE votazione_id = %d ORDER BY codice, opzione", (int) $vz['id'] ), ARRAY_A );
        $out = [];
        foreach ( $rows as $r ) {
            $code = (string) $r['codice'];
            if ( $code === '' ) { $code = '—'; }
            $out[ $code ][] = $r['bianca'] ? 'Scheda bianca' : ( $opz[ (int) $r['opzione'] ] ?? ('#' . (int) $r['opzione']) );
        }
        ksort( $out );
        return $out;
    }

    // --- Transizioni di stato ---------------------------------------------

    private static function open( int $id ): void {
        global $wpdb;
        $vz = self::get( $id );
        if ( ! $vz ) { return; }
        $snap = self::snapshot_electorate( (int) $vz['convocazione_id'] );
        $wpdb->update( Schema::table_votazioni(), [
            'stato'      => 'aperta',
            'apertura'   => current_time( 'mysql' ),
            'elettorato' => wp_json_encode( $snap ),
        ], [ 'id' => $id ] );
    }

    private static function close( int $id ): void {
        global $wpdb;
        $wpdb->update( Schema::table_votazioni(), [
            'stato'     => 'chiusa',
            'chiusura'  => current_time( 'mysql' ),
            'hash_urna' => self::compute_hash( $id ),
        ], [ 'id' => $id ] );
    }

    /** Apre/chiude automaticamente le votazioni programmate scadute. */
    public static function auto_transition(): void {
        global $wpdb;
        $t = Schema::table_votazioni();
        $now = current_time( 'mysql' );
        foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE stato='bozza' AND apertura_prog IS NOT NULL AND apertura_prog <= %s", $now ) ) as $id ) {
            self::open( (int) $id );
        }
        foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE stato='aperta' AND chiusura_prog IS NOT NULL AND chiusura_prog <= %s", $now ) ) as $id ) {
            self::close( (int) $id );
        }
    }

    // --- Handlers ----------------------------------------------------------

    private static function back( string $msg ): void {
        $url = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'msg', $msg, remove_query_arg( [ 'msg', 'voto_edit' ], $url ) ) );
        exit;
    }

    private static function norm_dt( string $v ): ?string {
        $v = trim( $v );
        if ( $v === '' ) { return null; }
        $v = str_replace( 'T', ' ', $v );
        if ( strlen( $v ) === 16 ) { $v .= ':00'; }
        return $v;
    }

    private static function parse_form(): array {
        $opz = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['opzioni'] ?? '' ) ) ) ) );
        if ( ! $opz ) { $opz = self::DEFAULT_OPZIONI; }
        return [
            'convocazione_id' => (int) ( $_POST['convocazione_id'] ?? 0 ) ?: null,
            'titolo'          => sanitize_text_field( wp_unslash( $_POST['titolo'] ?? '' ) ),
            'descrizione'     => sanitize_textarea_field( wp_unslash( $_POST['descrizione'] ?? '' ) ),
            'tipo'            => in_array( $_POST['tipo'] ?? '', [ 'palese', 'segreto' ], true ) ? $_POST['tipo'] : 'palese',
            'opzioni'         => wp_json_encode( array_map( 'sanitize_text_field', $opz ), JSON_UNESCAPED_UNICODE ),
            'max_scelte'      => min( max( 1, (int) ( $_POST['max_scelte'] ?? 1 ) ), count( $opz ) ),
            'quorum'          => max( 0, min( 100, (int) ( $_POST['quorum'] ?? 0 ) ) ),
            'apertura_prog'   => self::norm_dt( (string) ( $_POST['apertura_prog'] ?? '' ) ),
            'chiusura_prog'   => self::norm_dt( (string) ( $_POST['chiusura_prog'] ?? '' ) ),
        ];
    }

    public static function handle_create(): void {
        if ( ! self::can_manage() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_voto' );
        global $wpdb;
        $d = self::parse_form();
        if ( $d['titolo'] === '' ) { self::back( 'err' ); }
        $d['stato'] = 'bozza';
        $d['created_by'] = get_current_user_id();
        $wpdb->insert( Schema::table_votazioni(), $d );
        self::back( 'created' );
    }

    public static function handle_edit(): void {
        if ( ! self::can_manage() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_voto' );
        global $wpdb;
        $id = (int) ( $_POST['id'] ?? 0 );
        $vz = self::get( $id );
        if ( ! $vz || $vz['stato'] !== 'bozza' ) { self::back( 'edit_no' ); }
        $d = self::parse_form();
        if ( $d['titolo'] === '' ) { self::back( 'err' ); }
        $wpdb->update( Schema::table_votazioni(), $d, [ 'id' => $id ] );
        self::back( 'state' );
    }

    public static function handle_state(): void {
        if ( ! self::can_manage() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_voto' );
        global $wpdb;
        $id = (int) ( $_POST['id'] ?? 0 );
        $op = sanitize_key( (string) ( $_POST['op'] ?? '' ) );
        if ( $op === 'apri' ) {
            self::open( $id );
        } elseif ( $op === 'chiudi' ) {
            self::close( $id );
        } elseif ( $op === 'elimina' ) {
            $wpdb->delete( Schema::table_voti_assemblea(), [ 'votazione_id' => $id ] );
            $wpdb->delete( Schema::table_votanti(), [ 'votazione_id' => $id ] );
            $wpdb->delete( Schema::table_votazioni(), [ 'id' => $id ] );
        }
        self::back( 'state' );
    }

    private static function gen_code(): string {
        $chars = 'ACDEFGHJKLMNPQRSTUVWXYZ2345679';
        $c = '';
        for ( $i = 0; $i < 8; $i++ ) { $c .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ]; }
        return substr( $c, 0, 4 ) . '-' . substr( $c, 4, 4 );
    }

    public static function handle_cast(): void {
        if ( ! is_user_logged_in() ) { wp_die( 'Login richiesto.' ); }
        check_admin_referer( 'gfoss_voto_cast' );
        global $wpdb;

        $uid = get_current_user_id();
        $vid = (int) ( $_POST['votazione_id'] ?? 0 );
        $vz  = self::get( $vid );
        if ( ! $vz ) { self::back( 'err' ); }
        if ( self::vote_block_reason( $vz, $uid ) !== '' ) { self::back( 'voto_no' ); }

        $opzioni = self::options( $vz );
        $max     = max( 1, (int) ( $vz['max_scelte'] ?? 1 ) );
        $bianca  = ! empty( $_POST['bianca'] );

        $scelte = [];
        if ( ! $bianca ) {
            $raw    = $_POST['opzione'] ?? null;
            $scelte = is_array( $raw ) ? array_map( 'intval', $raw ) : ( ( $raw === null || $raw === '' ) ? [] : [ (int) $raw ] );
            $scelte = array_values( array_unique( array_filter( $scelte, static fn( $i ) => $i >= 0 && $i < count( $opzioni ) ) ) );
            if ( ! $scelte || count( $scelte ) > $max ) { self::back( 'voto_no' ); }
        }

        // Turnout prima (UNIQUE contro il doppio voto anche in gara).
        $ins = $wpdb->query( $wpdb->prepare(
            'INSERT IGNORE INTO ' . Schema::table_votanti() . ' (votazione_id, user_id) VALUES (%d, %d)', $vid, $uid ) );
        if ( ! $ins ) { self::back( 'voto_no' ); }

        $peso   = self::eligible_weight( $vz, $uid );
        $codice = self::gen_code();
        $seg    = $vz['tipo'] === 'segreto';
        $tv     = Schema::table_voti_assemblea();

        if ( $bianca ) {
            $wpdb->insert( $tv, [ 'votazione_id' => $vid, 'opzione' => 0, 'peso' => $peso, 'bianca' => 1, 'codice' => $codice, 'user_id' => $seg ? null : $uid ] );
        } else {
            foreach ( $scelte as $opz ) {
                $wpdb->insert( $tv, [ 'votazione_id' => $vid, 'opzione' => (int) $opz, 'peso' => $peso, 'bianca' => 0, 'codice' => $codice, 'user_id' => $seg ? null : $uid ] );
            }
        }
        set_transient( 'gf_voto_ric_' . $uid . '_' . $vid, $codice, 900 );
        do_action( 'gfoss_voto_cast', $vid, $uid, $peso );
        self::back( 'voto_ok' );
    }

    public static function handle_pdf(): void {
        if ( ! is_user_logged_in() || ( ! gfoss_members_is_socio( get_current_user_id() ) && ! self::can_manage() ) ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_voto_pdf' );
        $vz = self::get( (int) ( $_GET['id'] ?? 0 ) );
        if ( ! $vz || $vz['stato'] !== 'chiusa' ) { wp_die( 'Verbale disponibile solo per votazioni chiuse.' ); }
        $pdf = self::generate_pdf( $vz );
        if ( $pdf instanceof \WP_Error ) { wp_die( esc_html( $pdf->get_error_message() ) ); }
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="verbale-votazione-' . (int) $vz['id'] . '.pdf"' );
        echo $pdf;
        exit;
    }

    // --- Render ------------------------------------------------------------

    public static function render(): string {
        self::auto_transition();

        if ( ! is_user_logged_in() || ( ! gfoss_members_is_socio( get_current_user_id() ) && ! self::can_manage() ) ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata ai soci. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Accedi</a>.</div>';
        }
        $uid    = get_current_user_id();
        $action = esc_url( admin_url( 'admin-post.php' ) );
        $msg    = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Assemblea</p><h1 class="gf-area__title">Votazioni</h1><p class="gf-area__sub">Esprimi il tuo voto sulle votazioni aperte. Il peso tiene conto delle deleghe.</p></div></header>';

        // Condivisione pagina via QR.
        $page_url = get_permalink() ?: home_url( '/area-soci/votazioni/' );
        $qr = class_exists( __NAMESPACE__ . '\\Qr' ) ? Qr::data_uri( $page_url, 200 ) : '';
        echo '<details class="gf-card" style="margin-bottom:1rem"><summary style="cursor:pointer;font-weight:600">📱 Condividi la pagina di voto (QR)</summary><div style="text-align:center;margin-top:.8rem">';
        if ( $qr ) { echo '<img src="' . esc_attr( $qr ) . '" alt="QR pagina votazioni" style="width:200px;height:200px">'; }
        echo '<p class="gf-muted" style="word-break:break-all;margin:.4rem 0">' . esc_html( $page_url ) . '</p></div></details>';

        $notes = [ 'voto_ok' => [ 'success', 'Voto registrato. Grazie!' ], 'voto_no' => [ 'warn', 'Non è stato possibile registrare il voto.' ], 'created' => [ 'success', 'Votazione creata (in bozza).' ], 'state' => [ 'success', 'Operazione eseguita.' ], 'err' => [ 'warn', 'Dati non validi.' ], 'edit_no' => [ 'warn', 'Si può modificare solo una votazione in bozza.' ] ];
        if ( isset( $notes[ $msg ] ) ) { echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] ) . '">' . esc_html( $notes[ $msg ][1] ) . '</div>'; }

        echo '<script>function gfVoteLimit(cb){var m=parseInt(cb.getAttribute("data-vmax")||"1",10);var b=cb.form.querySelectorAll(\'input[name="opzione[]"]\');var c=0;b.forEach(function(x){if(x.checked)c++;});if(c>m){cb.checked=false;window.alert("Puoi scegliere al massimo "+m+" candidati.");}}'
            . 'function gfVoteConfirm(f){var bi=f.querySelector(\'input[name="bianca"]\');var sel=[];if(bi&&bi.checked){sel=["Scheda bianca"];}else{f.querySelectorAll(\'input[name="opzione"]:checked, input[name="opzione[]"]:checked\').forEach(function(x){var l=x.closest("label");sel.push((l?l.innerText:x.value).trim());});}if(!sel.length){window.alert("Seleziona almeno un\\u0027opzione oppure la scheda bianca.");return false;}return window.confirm("Stai per votare:\\n- "+sel.join("\\n- ")+"\\n\\nConfermi? Il voto non sarà modificabile.");}'
            . 'function gfBianca(cb){var f=cb.form;f.querySelectorAll(\'input[name="opzione"],input[name="opzione[]"]\').forEach(function(x){x.disabled=cb.checked;if(cb.checked)x.checked=false;});}</script>';

        $all = self::all();

        // Ricevute appena emesse (una per votazione).
        foreach ( $all as $vz ) {
            $code = get_transient( 'gf_voto_ric_' . $uid . '_' . (int) $vz['id'] );
            if ( $code ) {
                delete_transient( 'gf_voto_ric_' . $uid . '_' . (int) $vz['id'] );
                echo '<div class="gf-card gf-card--success">🧾 La tua <strong>ricevuta</strong> per «' . esc_html( $vz['titolo'] ) . '»: <code style="font-size:1.1em">' . esc_html( $code ) . '</code><br><small>Conservala: la ritrovi nell\'elenco urna quando la votazione sarà chiusa, per verificare che il tuo voto sia stato conteggiato.</small></div>';
            }
        }

        // --- Votazioni aperte (cabina) ---
        $aperte = array_filter( $all, static fn( $v ) => $v['stato'] === 'aperta' );
        echo '<section class="gf-card"><h2 style="margin-top:0">Votazioni aperte</h2>';
        if ( ! $aperte ) { echo '<p class="gf-muted">Nessuna votazione aperta in questo momento.</p>'; }
        foreach ( $aperte as $vz ) {
            $opzioni = self::options( $vz );
            $block   = self::vote_block_reason( $vz, $uid );
            $max     = max( 1, (int) ( $vz['max_scelte'] ?? 1 ) );
            $is_seg  = $vz['tipo'] === 'segreto';
            $cword   = ( $is_seg || $max > 1 ) ? 'candidati' : 'opzioni';

            echo '<article class="gf-voto" id="voto-' . (int) $vz['id'] . '">';
            echo '<div class="gf-voto__head"><h3>' . esc_html( $vz['titolo'] ) . '</h3>' . self::badges( $vz, $max ) . '</div>';
            if ( $vz['descrizione'] ) { echo '<p class="gf-muted">' . nl2br( esc_html( $vz['descrizione'] ) ) . '</p>'; }
            if ( (int) $vz['chiusura_prog'] || $vz['chiusura_prog'] ) { echo '<p class="gf-muted" style="font-size:.85em">Chiusura programmata: ' . esc_html( mysql2date( 'd/m/Y H:i', $vz['chiusura_prog'] ) ) . '</p>'; }

            if ( $block ) {
                echo '<p class="gf-voto__block">▸ ' . esc_html( $block ) . '</p>';
            } else {
                $peso = self::eligible_weight( $vz, $uid );
                echo '<p class="gf-voto__instr">' . ( $max > 1
                    ? '👉 Seleziona <strong>fino a ' . (int) $max . '</strong> ' . $cword
                    : '👉 Seleziona <strong>una</strong> tra le ' . $cword ) . ', poi premi <em>Vota</em>.</p>';
                echo '<form method="post" action="' . $action . '" onsubmit="return gfVoteConfirm(this)">' . wp_nonce_field( 'gfoss_voto_cast', '_wpnonce', true, false );
                echo '<input type="hidden" name="action" value="gfoss_voto_cast"><input type="hidden" name="votazione_id" value="' . (int) $vz['id'] . '">';
                echo '<div class="gf-opts">';
                $type = $max > 1 ? 'checkbox' : 'radio';
                $name = $max > 1 ? 'opzione[]' : 'opzione';
                foreach ( $opzioni as $i => $opt ) {
                    $extra = $max > 1 ? ' data-vmax="' . (int) $max . '" onclick="gfVoteLimit(this)"' : '';
                    echo '<label class="gf-opt"><input type="' . $type . '" name="' . $name . '" value="' . (int) $i . '"' . $extra . '><span class="gf-opt__txt">' . esc_html( $opt ) . '</span></label>';
                }
                echo '</div>';
                echo '<label class="gf-opt gf-opt--bianca"><input type="checkbox" name="bianca" value="1" onclick="gfBianca(this)"><span class="gf-opt__txt">Scheda bianca</span></label>';
                echo '<p class="gf-muted gf-voto__peso">Il tuo voto vale <strong>' . (int) $peso . '</strong>' . ( $peso > 1 ? ' (incluse le deleghe ricevute)' : '' ) . '.' . ( $is_seg ? ' Voto segreto: non sarà collegato al tuo nome.' : '' ) . '</p>';
                echo '<p><button class="gf-btn gf-btn--primary">Vota</button></p></form>';
            }
            $vurl = $page_url . '#voto-' . (int) $vz['id'];
            $vqr  = class_exists( __NAMESPACE__ . '\\Qr' ) ? Qr::data_uri( $vurl, 150 ) : '';
            if ( $vqr ) {
                echo '<details class="gf-voto__qr"><summary>📱 Condividi questa votazione (QR)</summary><div style="text-align:center;margin-top:.5rem"><img src="' . esc_attr( $vqr ) . '" alt="QR votazione" style="width:150px;height:150px"><p class="gf-muted" style="font-size:.8em;word-break:break-all">' . esc_html( $vurl ) . '</p></div></details>';
            }
            echo '</article>';
        }
        echo '</section>';

        // --- Risultati (chiuse) ---
        $chiuse = array_filter( $all, static fn( $v ) => $v['stato'] === 'chiusa' );
        if ( $chiuse ) {
            echo '<section class="gf-card"><h2 style="margin-top:0">Risultati</h2>';
            foreach ( $chiuse as $vz ) { echo self::render_results( $vz, $action ); }
            echo '</section>';
        }

        if ( self::can_manage() ) { echo self::render_admin( $all, $action ); }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function badges( array $vz, int $max ): string {
        $is_seg = $vz['tipo'] === 'segreto';
        $mode   = $max > 1 ? 'Elezione · ' . (int) $max . ' seggi' : ( $is_seg ? 'Elezione' : 'Delibera' );
        $tb = $is_seg
            ? '<span class="gf-badge gf-badge--seg">🔒 Voto segreto</span>'
            : '<span class="gf-badge gf-badge--pal">👁 Voto palese</span>';
        return $tb . '<span class="gf-badge gf-badge--mode">' . esc_html( $mode ) . '</span>';
    }

    private static function render_results( array $vz, string $action ): string {
        $opzioni = self::options( $vz );
        $res     = self::results( (int) $vz['id'] );
        $max     = max( 1, (int) ( $vz['max_scelte'] ?? 1 ) );

        $rank = [];
        foreach ( $opzioni as $i => $opt ) { $rank[ $i ] = $res['by'][ $i ] ?? 0; }
        arsort( $rank );
        $ref    = max( 1, ( $rank ? max( $rank ) : 1 ) );
        $eletti = $max > 1 ? array_slice( array_keys( $rank ), 0, $max, true ) : [];

        $h  = '<article class="gf-voto" id="voto-' . (int) $vz['id'] . '"><div class="gf-voto__head"><h3>' . esc_html( $vz['titolo'] ) . '</h3>' . self::badges( $vz, $max ) . '</div>';
        $h .= '<p class="gf-muted">Votanti: ' . (int) $res['turnout'] . ' · preferenze totali (pesate): ' . (int) $res['tot_peso'] . ( $res['bianche'] ? ' · schede bianche: ' . (int) $res['bianche'] : '' ) . '</p>';

        // Quorum
        $elw = self::electorate_weight( $vz );
        if ( (int) $vz['quorum'] > 0 && $elw > 0 ) {
            $req = (int) ceil( (int) $vz['quorum'] / 100 * $elw );
            $ok  = $res['turnout_peso'] >= $req;
            $h .= '<p class="gf-muted">Quorum: richiesto ' . (int) $vz['quorum'] . '% (' . $req . ' su ' . $elw . ' pesato) — partecipazione ' . (int) $res['turnout_peso'] . ' → <strong style="color:' . ( $ok ? '#5DA34D' : '#C0392B' ) . '">' . ( $ok ? 'raggiunto' : 'NON raggiunto' ) . '</strong></p>';
        }

        $pos = 0;
        foreach ( $rank as $i => $peso ) {
            $pos++;
            $pct   = round( $peso / $ref * 100 );
            $badge = ( $max > 1 && in_array( $i, $eletti, true ) && $peso > 0 ) ? ' <strong style="color:#5DA34D">✓ eletto</strong>' : '';
            $h .= '<div class="gf-voto__bar"><span>' . ( $max > 1 ? (int) $pos . '. ' : '' ) . esc_html( $opzioni[ $i ] ) . ' — <strong>' . (int) $peso . '</strong>' . $badge . '</span>'
                . '<div class="gf-voto__track"><div class="gf-voto__fill" style="width:' . (int) $pct . '%"></div></div></div>';
        }
        if ( $max > 1 ) { $h .= '<p class="gf-muted" style="font-size:.85em">In caso di parità sull\'ultimo seggio è necessario un ballottaggio.</p>'; }

        // Integrità: hash urna + verbale PDF
        if ( $vz['hash_urna'] ) {
            $h .= '<p class="gf-muted" style="font-size:.82em">Impronta urna (SHA-256): <code style="word-break:break-all">' . esc_html( $vz['hash_urna'] ) . '</code></p>';
        }
        $pdf_url = add_query_arg( [ 'action' => 'gfoss_voto_pdf', 'id' => (int) $vz['id'], '_wpnonce' => wp_create_nonce( 'gfoss_voto_pdf' ) ], admin_url( 'admin-post.php' ) );
        $h .= '<p><a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( $pdf_url ) . '">⬇ Verbale PDF</a></p>';

        // Elenco urna (verifica ricevute)
        $urna = self::urna( $vz );
        if ( $urna ) {
            $h .= '<details class="gf-voto__qr"><summary>🧾 Elenco urna — verifica la tua ricevuta (' . count( $urna ) . ' schede)</summary><div class="gf-tablewrap" style="margin-top:.5rem"><table class="gf-table"><thead><tr><th>Codice ricevuta</th><th>Scelta/e registrata/e</th></tr></thead><tbody>';
            foreach ( $urna as $code => $labels ) {
                $h .= '<tr><td><code>' . esc_html( $code ) . '</code></td><td>' . esc_html( implode( ', ', $labels ) ) . '</td></tr>';
            }
            $h .= '</tbody></table></div><p class="gf-muted" style="font-size:.8em">Trova il tuo codice e controlla che la scelta corrisponda. I nomi non sono associati ai codici: l\'anonimato è preservato.</p></details>';
        }

        return $h . '</article>';
    }

    private static function render_admin( array $all, string $action ): string {
        $convs = class_exists( __NAMESPACE__ . '\\Convocazioni' )
            ? get_posts( [ 'post_type' => Convocazioni::CPT, 'numberposts' => 100, 'post_status' => 'publish' ] ) : [];

        $edit = (int) ( $_GET['voto_edit'] ?? 0 );
        $ev   = $edit ? self::get( $edit ) : null;
        if ( $ev && $ev['stato'] !== 'bozza' ) { $ev = null; }
        $ev_opz = $ev ? implode( "\n", self::options( $ev ) ) : '';
        $dtv    = static fn( $v ) => $v ? esc_attr( str_replace( ' ', 'T', substr( (string) $v, 0, 16 ) ) ) : '';

        ob_start();
        echo '<section class="gf-card" id="voto-admin"><h2 style="margin-top:0">Gestione votazioni (direttivo)</h2>';

        echo '<form method="post" action="' . $action . '" class="gf-form" style="margin-bottom:1.2rem">' . wp_nonce_field( 'gfoss_voto', '_wpnonce', true, false );
        echo '<input type="hidden" name="action" value="' . ( $ev ? 'gfoss_voto_edit' : 'gfoss_voto_create' ) . '">';
        if ( $ev ) { echo '<input type="hidden" name="id" value="' . (int) $ev['id'] . '">'; }
        echo '<h3 style="margin:.2rem 0 .7rem">' . ( $ev ? 'Modifica votazione (bozza)' : 'Nuova votazione' ) . '</h3><div class="gf-grid">';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Quesito / titolo *</span><input type="text" name="titolo" value="' . ( $ev ? esc_attr( $ev['titolo'] ) : '' ) . '" required></label>';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Descrizione</span><textarea name="descrizione" rows="2">' . ( $ev ? esc_textarea( (string) $ev['descrizione'] ) : '' ) . '</textarea></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Tipo</span><select name="tipo"><option value="palese" ' . selected( $ev['tipo'] ?? '', 'palese', false ) . '>Palese (delibera)</option><option value="segreto" ' . selected( $ev['tipo'] ?? '', 'segreto', false ) . '>Segreto (elezione)</option></select></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Preferenze esprimibili</span><input type="number" name="max_scelte" min="1" value="' . ( $ev ? (int) $ev['max_scelte'] : 1 ) . '"><small class="gf-muted">1 = scelta singola. Per il Consiglio: n. seggi.</small></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Quorum (% elettorato)</span><input type="number" name="quorum" min="0" max="100" value="' . ( $ev ? (int) $ev['quorum'] : 0 ) . '"><small class="gf-muted">0 = nessun quorum.</small></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Convocazione</span><select name="convocazione_id"><option value="">— nessuna —</option>';
        foreach ( $convs as $c ) { echo '<option value="' . (int) $c->ID . '" ' . selected( (int) ( $ev['convocazione_id'] ?? 0 ), (int) $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>'; }
        echo '</select></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Apertura programmata</span><input type="datetime-local" name="apertura_prog" value="' . ( $ev ? $dtv( $ev['apertura_prog'] ) : '' ) . '"><small class="gf-muted">Ora italiana (' . esc_html( wp_timezone_string() ) . '). Vuoto = apertura manuale.</small></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Chiusura programmata</span><input type="datetime-local" name="chiusura_prog" value="' . ( $ev ? $dtv( $ev['chiusura_prog'] ) : '' ) . '"><small class="gf-muted">Vuoto = chiusura manuale.</small></label>';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Opzioni / candidati (una per riga)</span><textarea name="opzioni" rows="4" placeholder="Favorevole&#10;Contrario&#10;Astenuto">' . esc_textarea( $ev_opz ) . '</textarea><small class="gf-muted">Vuoto = Favorevole/Contrario/Astenuto. Per le elezioni: i nomi dei candidati, uno per riga.</small></label>';
        echo '</div><p class="gf-actions"><button class="gf-btn gf-btn--primary">' . ( $ev ? 'Salva modifiche' : 'Crea votazione' ) . '</button>';
        if ( $ev ) { echo ' <a class="gf-btn gf-btn--ghost" href="' . esc_url( remove_query_arg( 'voto_edit' ) ) . '">Annulla</a>'; }
        echo '</p></form>';

        echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>Quesito</th><th>Tipo</th><th>Stato</th><th></th></tr></thead><tbody>';
        if ( ! $all ) { echo '<tr><td colspan="4" class="gf-muted">Nessuna votazione.</td></tr>'; }
        foreach ( $all as $vz ) {
            echo '<tr><td><strong>' . esc_html( $vz['titolo'] ) . '</strong></td><td>' . esc_html( $vz['tipo'] ) . ( (int) ( $vz['max_scelte'] ?? 1 ) > 1 ? ' · ' . (int) $vz['max_scelte'] . ' seggi' : '' ) . '</td><td>' . esc_html( $vz['stato'] ) . '</td><td style="white-space:nowrap">';
            $state_btn = static function ( $op, $lbl, $confirm = '' ) use ( $vz, $action ) {
                $onsubmit = $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\')"' : '';
                return '<form method="post" action="' . $action . '" style="display:inline"' . $onsubmit . '>' . wp_nonce_field( 'gfoss_voto', '_wpnonce', true, false )
                    . '<input type="hidden" name="action" value="gfoss_voto_state"><input type="hidden" name="id" value="' . (int) $vz['id'] . '"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><button class="gf-btn gf-btn--ghost gf-btn--sm">' . esc_html( $lbl ) . '</button></form> ';
            };
            if ( $vz['stato'] === 'bozza' ) {
                echo '<a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( add_query_arg( 'voto_edit', (int) $vz['id'] ) ) . '#voto-admin">Modifica</a> ';
                echo $state_btn( 'apri', 'Apri' );
            }
            if ( $vz['stato'] === 'aperta' ) { echo $state_btn( 'chiudi', 'Chiudi', 'Chiudere la votazione? Non si potrà più votare.' ); }
            echo $state_btn( 'elimina', 'Elimina', 'Eliminare definitivamente questa votazione e tutti i suoi voti? L\'operazione non è reversibile.' );
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section>';
        return (string) ob_get_clean();
    }

    /** @return string|\WP_Error PDF del verbale risultati. */
    public static function generate_pdf( array $vz ) {
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            $autoload = GFOSS_MEMBERS_DIR . 'vendor/autoload.php';
            if ( is_file( $autoload ) ) { require_once $autoload; }
        }
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) { return new \WP_Error( 'no_mpdf', 'mPDF non disponibile.' ); }

        $opzioni = self::options( $vz );
        $res     = self::results( (int) $vz['id'] );
        $max     = max( 1, (int) ( $vz['max_scelte'] ?? 1 ) );
        $rank = [];
        foreach ( $opzioni as $i => $opt ) { $rank[ $i ] = $res['by'][ $i ] ?? 0; }
        arsort( $rank );
        $eletti = $max > 1 ? array_slice( array_keys( $rank ), 0, $max, true ) : [];
        $e = static fn( $v ) => esc_html( (string) $v );

        $righe = '';
        $pos = 0;
        foreach ( $rank as $i => $peso ) {
            $pos++;
            $badge = ( $max > 1 && in_array( $i, $eletti, true ) && $peso > 0 ) ? ' <strong>ELETTO</strong>' : '';
            $righe .= '<tr><td>' . ( $max > 1 ? $pos . '. ' : '' ) . $e( $opzioni[ $i ] ) . $badge . '</td><td style="text-align:right">' . (int) $peso . '</td></tr>';
        }
        $when   = date_i18n( 'd/m/Y H:i', strtotime( (string) ( $vz['chiusura'] ?: current_time( 'mysql' ) ) ) );
        $conv   = $vz['convocazione_id'] ? get_the_title( (int) $vz['convocazione_id'] ) : '';
        $modo   = $max > 1 ? 'Elezione a ' . $max . ' seggi' : ( $vz['tipo'] === 'segreto' ? 'Elezione' : 'Delibera' );

        $html = '<style>body{font-family:sans-serif;color:#10242f;font-size:11pt}h1{font-size:14pt;color:#1A6FA0}
            table{width:100%;border-collapse:collapse;margin:8px 0} td,th{border-bottom:1px solid #ddd;padding:5px 6px;text-align:left}
            .meta{color:#4A5C6A;font-size:9.5pt}.sign{margin-top:34px}.sign td{border:0;padding-top:26px;border-top:1px solid #333;text-align:center;color:#4A5C6A;font-size:9pt}</style>';
        $html .= '<h1>Verbale di votazione</h1>';
        $html .= '<p><strong>' . $e( $vz['titolo'] ) . '</strong><br><span class="meta">' . $e( $modo ) . ' · voto ' . $e( $vz['tipo'] ) . ( $conv ? ' · ' . $e( $conv ) : '' ) . ' · chiusa il ' . $e( $when ) . '</span></p>';
        if ( $vz['descrizione'] ) { $html .= '<p>' . nl2br( $e( $vz['descrizione'] ) ) . '</p>'; }
        $html .= '<p class="meta">Votanti: ' . (int) $res['turnout'] . ' · preferenze pesate: ' . (int) $res['tot_peso'] . ' · schede bianche: ' . (int) $res['bianche'] . '</p>';
        $elw = self::electorate_weight( $vz );
        if ( (int) $vz['quorum'] > 0 && $elw > 0 ) {
            $req = (int) ceil( (int) $vz['quorum'] / 100 * $elw );
            $html .= '<p class="meta">Quorum richiesto: ' . (int) $vz['quorum'] . '% (' . $req . '/' . $elw . ' pesato) — partecipazione ' . (int) $res['turnout_peso'] . ' → ' . ( $res['turnout_peso'] >= $req ? 'RAGGIUNTO' : 'NON raggiunto' ) . '</p>';
        }
        $html .= '<table><thead><tr><th>' . ( $max > 1 ? 'Candidato' : 'Opzione' ) . '</th><th style="text-align:right">Voti (pesati)</th></tr></thead><tbody>' . $righe . '</tbody></table>';
        if ( $vz['hash_urna'] ) { $html .= '<p class="meta">Impronta urna (SHA-256): ' . $e( $vz['hash_urna'] ) . '</p>'; }
        $html .= '<table class="sign"><tr><td>Il Segretario</td><td>Il Presidente</td></tr></table>';

        try {
            $tmp = WP_CONTENT_DIR . '/uploads/gfoss-tmp';
            if ( ! is_dir( $tmp ) ) { wp_mkdir_p( $tmp ); }
            $mpdf = new \Mpdf\Mpdf( [ 'mode' => 'utf-8', 'format' => 'A4', 'tempDir' => $tmp ] );
            $mpdf->SetTitle( 'Verbale votazione ' . (int) $vz['id'] );
            $mpdf->WriteHTML( $html );
            return $mpdf->Output( '', 'S' );
        } catch ( \Throwable $ex ) {
            return new \WP_Error( 'mpdf_fail', 'Errore PDF: ' . $ex->getMessage() );
        }
    }
}
