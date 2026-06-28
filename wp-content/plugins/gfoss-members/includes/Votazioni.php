<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Voto online d'assemblea, agganciato alle Convocazioni.
 *
 *  - Quesiti (votazioni) creati dal direttivo (CAP_MANAGE_ASSEMBLEE), tipo
 *    PALESE (delibere) o SEGRETO (elezioni).
 *  - Voto riservato ai soci in regola; chi ha delegato non vota (vota il delegato).
 *  - Peso del voto = 1 + deleghe ricevute per quella convocazione (deleganti in regola).
 *  - Voti append-only (mai modificati/cancellati). Segreto: non si salva chi ha
 *    votato cosa (solo il fatto che ha votato, per evitare il doppio voto).
 *
 * Shortcode [gfoss_votazioni].
 */
class Votazioni {

    const DEFAULT_OPZIONI = [ 'Favorevole', 'Contrario', 'Astenuto' ];

    public static function init(): void {
        add_shortcode( 'gfoss_votazioni', [ __CLASS__, 'render' ] );
        add_action( 'admin_post_gfoss_voto_create', [ __CLASS__, 'handle_create' ] );
        add_action( 'admin_post_gfoss_voto_state',  [ __CLASS__, 'handle_state' ] );
        add_action( 'admin_post_gfoss_voto_cast',   [ __CLASS__, 'handle_cast' ] );
    }

    private static function can_manage(): bool {
        return current_user_can( Roles::CAP_MANAGE_ASSEMBLEE );
    }

    // --- Lettura -----------------------------------------------------------

    public static function get( int $id ): ?array {
        global $wpdb;
        $t = Schema::table_votazioni();
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    /** @return array<int,array> tutte le votazioni (più recenti prima). */
    public static function all(): array {
        global $wpdb;
        $t = Schema::table_votazioni();
        return (array) $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC", ARRAY_A );
    }

    public static function options( array $vz ): array {
        $o = json_decode( (string) $vz['opzioni'], true );
        if ( ! is_array( $o ) || ! $o ) { return self::DEFAULT_OPZIONI; }
        return array_values( array_map( 'strval', $o ) );
    }

    // --- Ammissibilità e peso ---------------------------------------------

    private static function in_regola( int $uid ): bool {
        return gfoss_members_is_socio( $uid )
            && in_array( Quote::status_for( $uid, (int) gmdate( 'Y' ) ), [ 'paid', 'expiring' ], true );
    }

    /** Il socio ha delegato qualcuno per quella convocazione? (allora non vota). */
    private static function has_delegated( int $conv_id, int $uid ): bool {
        if ( ! $conv_id || ! class_exists( __NAMESPACE__ . '\\Convocazioni' ) ) { return false; }
        return isset( Convocazioni::deleghe( $conv_id )[ $uid ] );
    }

    /** Peso = 1 + deleghe ricevute (da deleganti in regola). */
    public static function weight( int $conv_id, int $uid ): int {
        $w = 1;
        if ( $conv_id && class_exists( __NAMESPACE__ . '\\Convocazioni' ) ) {
            foreach ( Convocazioni::deleghe( $conv_id ) as $delegante => $delegato ) {
                if ( (int) $delegato === $uid && self::in_regola( (int) $delegante ) ) { $w++; }
            }
        }
        return $w;
    }

    public static function has_voted( int $vid, int $uid ): bool {
        global $wpdb;
        $t = Schema::table_votanti();
        return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $t WHERE votazione_id = %d AND user_id = %d", $vid, $uid ) );
    }

    /** @return string '' se può votare, altrimenti il motivo. */
    public static function vote_block_reason( array $vz, int $uid ): string {
        if ( $vz['stato'] !== 'aperta' ) { return 'Votazione non aperta.'; }
        if ( ! self::in_regola( $uid ) )  { return 'Devi essere in regola con la quota per votare.'; }
        if ( self::has_delegated( (int) $vz['convocazione_id'], $uid ) ) { return 'Hai delegato un altro socio: voterà lui per te.'; }
        if ( self::has_voted( (int) $vz['id'], $uid ) ) { return 'Hai già votato.'; }
        return '';
    }

    public static function results( int $vid ): array {
        global $wpdb;
        $tv = Schema::table_voti_assemblea();
        $tn = Schema::table_votanti();
        $rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT opzione, SUM(peso) peso, COUNT(*) n FROM $tv WHERE votazione_id = %d GROUP BY opzione", $vid ), ARRAY_A );
        $by = [];
        foreach ( $rows as $r ) { $by[ (int) $r['opzione'] ] = [ 'peso' => (int) $r['peso'], 'n' => (int) $r['n'] ]; }
        $turnout = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tn WHERE votazione_id = %d", $vid ) );
        $tot_peso = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(peso),0) FROM $tv WHERE votazione_id = %d", $vid ) );
        return [ 'by' => $by, 'turnout' => $turnout, 'tot_peso' => $tot_peso ];
    }

    // --- Handlers ----------------------------------------------------------

    private static function back( string $msg ): void {
        $url = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'msg', $msg, remove_query_arg( 'msg', $url ) ) );
        exit;
    }

    public static function handle_create(): void {
        if ( ! self::can_manage() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_voto' );
        global $wpdb;

        $titolo = sanitize_text_field( wp_unslash( $_POST['titolo'] ?? '' ) );
        if ( $titolo === '' ) { self::back( 'err' ); }
        $tipo = in_array( $_POST['tipo'] ?? '', [ 'palese', 'segreto' ], true ) ? $_POST['tipo'] : 'palese';
        $opz_raw = (string) wp_unslash( $_POST['opzioni'] ?? '' );
        $opz = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $opz_raw ) ) ) );
        if ( ! $opz ) { $opz = self::DEFAULT_OPZIONI; }

        $wpdb->insert( Schema::table_votazioni(), [
            'convocazione_id' => (int) ( $_POST['convocazione_id'] ?? 0 ) ?: null,
            'titolo'          => $titolo,
            'descrizione'     => sanitize_textarea_field( wp_unslash( $_POST['descrizione'] ?? '' ) ),
            'tipo'            => $tipo,
            'opzioni'         => wp_json_encode( array_map( 'sanitize_text_field', $opz ), JSON_UNESCAPED_UNICODE ),
            'stato'           => 'bozza',
            'created_by'      => get_current_user_id(),
        ] );
        self::back( 'created' );
    }

    public static function handle_state(): void {
        if ( ! self::can_manage() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_voto' );
        global $wpdb;
        $id = (int) ( $_POST['id'] ?? 0 );
        $op = sanitize_key( (string) ( $_POST['op'] ?? '' ) );
        $t  = Schema::table_votazioni();

        if ( $op === 'apri' ) {
            $wpdb->update( $t, [ 'stato' => 'aperta', 'apertura' => current_time( 'mysql' ) ], [ 'id' => $id ] );
        } elseif ( $op === 'chiudi' ) {
            $wpdb->update( $t, [ 'stato' => 'chiusa', 'chiusura' => current_time( 'mysql' ) ], [ 'id' => $id ] );
        } elseif ( $op === 'elimina' ) {
            // Solo bozze senza voti: niente cancellazioni di votazioni svolte.
            $vz = self::get( $id );
            if ( $vz && $vz['stato'] === 'bozza' ) { $wpdb->delete( $t, [ 'id' => $id ] ); }
        }
        self::back( 'state' );
    }

    public static function handle_cast(): void {
        if ( ! is_user_logged_in() ) { wp_die( 'Login richiesto.' ); }
        check_admin_referer( 'gfoss_voto_cast' );
        global $wpdb;

        $uid = get_current_user_id();
        $vid = (int) ( $_POST['votazione_id'] ?? 0 );
        $opz = (int) ( $_POST['opzione'] ?? -1 );
        $vz  = self::get( $vid );
        if ( ! $vz ) { self::back( 'err' ); }

        if ( self::vote_block_reason( $vz, $uid ) !== '' ) { self::back( 'voto_no' ); }
        $opzioni = self::options( $vz );
        if ( $opz < 0 || $opz >= count( $opzioni ) ) { self::back( 'voto_no' ); }

        // Turnout prima (UNIQUE evita il doppio voto anche in gara).
        $ins = $wpdb->query( $wpdb->prepare(
            'INSERT IGNORE INTO ' . Schema::table_votanti() . ' (votazione_id, user_id) VALUES (%d, %d)', $vid, $uid ) );
        if ( ! $ins ) { self::back( 'voto_no' ); } // già votato

        $peso = self::weight( (int) $vz['convocazione_id'], $uid );
        $wpdb->insert( Schema::table_voti_assemblea(), [
            'votazione_id' => $vid,
            'opzione'      => $opz,
            'peso'         => $peso,
            'user_id'      => $vz['tipo'] === 'segreto' ? null : $uid, // segreto: niente identità
        ] );
        do_action( 'gfoss_voto_cast', $vid, $uid, $peso );
        self::back( 'voto_ok' );
    }

    // --- Render ------------------------------------------------------------

    public static function render(): string {
        if ( ! is_user_logged_in() || ( ! gfoss_members_is_socio( get_current_user_id() ) && ! self::can_manage() ) ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata ai soci. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Accedi</a>.</div>';
        }
        $uid    = get_current_user_id();
        $action = esc_url( admin_url( 'admin-post.php' ) );
        $msg    = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Assemblea</p><h1 class="gf-area__title">Votazioni</h1><p class="gf-area__sub">Esprimi il tuo voto sulle votazioni aperte. Il peso tiene conto delle deleghe.</p></div></header>';

        $notes = [ 'voto_ok' => [ 'success', 'Voto registrato. Grazie!' ], 'voto_no' => [ 'warn', 'Non è stato possibile registrare il voto.' ], 'created' => [ 'success', 'Votazione creata (in bozza).' ], 'state' => [ 'success', 'Stato aggiornato.' ], 'err' => [ 'warn', 'Dati non validi.' ] ];
        if ( isset( $notes[ $msg ] ) ) { echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] ) . '">' . esc_html( $notes[ $msg ][1] ) . '</div>'; }

        $all = self::all();

        // Cabina di voto: votazioni aperte
        $aperte = array_filter( $all, static fn( $v ) => $v['stato'] === 'aperta' );
        echo '<section class="gf-card"><h2 style="margin-top:0">Votazioni aperte</h2>';
        if ( ! $aperte ) { echo '<p class="gf-muted">Nessuna votazione aperta in questo momento.</p>'; }
        foreach ( $aperte as $vz ) {
            $opzioni = self::options( $vz );
            $block   = self::vote_block_reason( $vz, $uid );
            echo '<article class="gf-voto"><h3>' . esc_html( $vz['titolo'] ) . ' <small class="gf-muted">(' . esc_html( $vz['tipo'] ) . ')</small></h3>';
            if ( $vz['descrizione'] ) { echo '<p class="gf-muted">' . nl2br( esc_html( $vz['descrizione'] ) ) . '</p>'; }
            if ( $block ) {
                echo '<p class="gf-muted">▸ ' . esc_html( $block ) . '</p>';
            } else {
                $peso = self::weight( (int) $vz['convocazione_id'], $uid );
                echo '<form method="post" action="' . $action . '">' . wp_nonce_field( 'gfoss_voto_cast', '_wpnonce', true, false );
                echo '<input type="hidden" name="action" value="gfoss_voto_cast"><input type="hidden" name="votazione_id" value="' . (int) $vz['id'] . '">';
                foreach ( $opzioni as $i => $opt ) {
                    echo '<label class="gf-voto__opt"><input type="radio" name="opzione" value="' . (int) $i . '" required> ' . esc_html( $opt ) . '</label>';
                }
                echo '<p class="gf-muted">Il tuo voto vale <strong>' . (int) $peso . '</strong>' . ( $peso > 1 ? ' (incluse le deleghe ricevute)' : '' ) . '. ' . ( $vz['tipo'] === 'segreto' ? 'Voto segreto: non sarà collegato al tuo nome.' : '' ) . '</p>';
                echo '<p><button class="gf-btn gf-btn--primary" onclick="return confirm(\'Confermi il voto? Non sarà modificabile.\')">Vota</button></p></form>';
            }
            echo '</article>';
        }
        echo '</section>';

        // Risultati: votazioni chiuse
        $chiuse = array_filter( $all, static fn( $v ) => $v['stato'] === 'chiusa' );
        if ( $chiuse ) {
            echo '<section class="gf-card"><h2 style="margin-top:0">Risultati</h2>';
            foreach ( $chiuse as $vz ) {
                echo self::render_results( $vz );
            }
            echo '</section>';
        }

        // Gestione (solo direttivo)
        if ( self::can_manage() ) {
            echo self::render_admin( $all, $action );
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function render_results( array $vz ): string {
        $opzioni = self::options( $vz );
        $res     = self::results( (int) $vz['id'] );
        $tot     = max( 1, $res['tot_peso'] );
        $h = '<article class="gf-voto"><h3>' . esc_html( $vz['titolo'] ) . ' <small class="gf-muted">(' . esc_html( $vz['tipo'] ) . ')</small></h3>';
        $h .= '<p class="gf-muted">Votanti: ' . (int) $res['turnout'] . ' · voti totali (pesati): ' . (int) $res['tot_peso'] . '</p>';
        foreach ( $opzioni as $i => $opt ) {
            $peso = $res['by'][ $i ]['peso'] ?? 0;
            $pct  = round( $peso / $tot * 100 );
            $h .= '<div class="gf-voto__bar"><span>' . esc_html( $opt ) . ' — <strong>' . (int) $peso . '</strong> (' . $pct . '%)</span>'
                . '<div class="gf-voto__track"><div class="gf-voto__fill" style="width:' . (int) $pct . '%"></div></div></div>';
        }
        return $h . '</article>';
    }

    private static function render_admin( array $all, string $action ): string {
        $convs = class_exists( __NAMESPACE__ . '\\Convocazioni' )
            ? get_posts( [ 'post_type' => Convocazioni::CPT, 'numberposts' => 100, 'post_status' => 'publish' ] ) : [];

        ob_start();
        echo '<section class="gf-card"><h2 style="margin-top:0">Gestione votazioni (direttivo)</h2>';

        // Form crea
        echo '<form method="post" action="' . $action . '" class="gf-form" style="margin-bottom:1.2rem">' . wp_nonce_field( 'gfoss_voto', '_wpnonce', true, false );
        echo '<input type="hidden" name="action" value="gfoss_voto_create"><div class="gf-grid">';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Quesito / titolo *</span><input type="text" name="titolo" required></label>';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Descrizione</span><textarea name="descrizione" rows="2"></textarea></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Tipo</span><select name="tipo"><option value="palese">Palese (delibera)</option><option value="segreto">Segreto (elezione)</option></select></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Convocazione</span><select name="convocazione_id"><option value="">— nessuna —</option>';
        foreach ( $convs as $c ) { echo '<option value="' . (int) $c->ID . '">' . esc_html( $c->post_title ) . '</option>'; }
        echo '</select></label>';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Opzioni (una per riga)</span><textarea name="opzioni" rows="3" placeholder="Favorevole&#10;Contrario&#10;Astenuto"></textarea><small class="gf-muted">Vuoto = Favorevole/Contrario/Astenuto. Per le elezioni inserisci i nomi dei candidati.</small></label>';
        echo '</div><p class="gf-actions"><button class="gf-btn gf-btn--primary">Crea votazione</button></p></form>';

        // Elenco con azioni
        echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>Quesito</th><th>Tipo</th><th>Stato</th><th></th></tr></thead><tbody>';
        if ( ! $all ) { echo '<tr><td colspan="4" class="gf-muted">Nessuna votazione.</td></tr>'; }
        foreach ( $all as $vz ) {
            echo '<tr><td><strong>' . esc_html( $vz['titolo'] ) . '</strong></td><td>' . esc_html( $vz['tipo'] ) . '</td><td>' . esc_html( $vz['stato'] ) . '</td><td style="white-space:nowrap">';
            $btn = static function ( $op, $lbl ) use ( $vz, $action ) {
                return '<form method="post" action="' . $action . '" style="display:inline">' . wp_nonce_field( 'gfoss_voto', '_wpnonce', true, false )
                    . '<input type="hidden" name="action" value="gfoss_voto_state"><input type="hidden" name="id" value="' . (int) $vz['id'] . '"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><button class="gf-btn gf-btn--ghost gf-btn--sm">' . esc_html( $lbl ) . '</button></form> ';
            };
            if ( $vz['stato'] === 'bozza' )  { echo $btn( 'apri', 'Apri' ) . $btn( 'elimina', 'Elimina' ); }
            if ( $vz['stato'] === 'aperta' ) { echo $btn( 'chiudi', 'Chiudi' ); }
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section>';
        return (string) ob_get_clean();
    }
}
