<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Console front-end Soci & quote (riservata al direttivo: CAP_MANAGE_SOCI).
 * Panoramica con stato quota + scheda completa del socio (anagrafica, ruoli,
 * archiviazione). Shortcode [gfoss_gestione_soci].
 *
 * Permessi azioni:
 *  - quota: CAP_MANAGE_QUOTE
 *  - ruoli: promote_users OPPURE CAP_MANAGE_ASSEMBLEE (presidente/segreteria/admin)
 *  - elimina: delete_users (admin)
 */
class Soci_Frontend {

    const ANAG = [ 'gf_codice_fiscale', 'gf_indirizzo', 'gf_cap', 'gf_citta', 'gf_provincia', 'gf_telefono' ];

    public static function init(): void {
        add_shortcode( 'gfoss_gestione_soci', [ __CLASS__, 'render' ] );
        add_action( 'admin_post_gfoss_soci_quota',   [ __CLASS__, 'handle_quota' ] );
        add_action( 'admin_post_gfoss_soci_meta',    [ __CLASS__, 'handle_meta' ] );
        add_action( 'admin_post_gfoss_soci_roles',   [ __CLASS__, 'handle_roles' ] );
        add_action( 'admin_post_gfoss_soci_archive', [ __CLASS__, 'handle_archive' ] );
        add_action( 'admin_post_gfoss_soci_delete',  [ __CLASS__, 'handle_delete' ] );
    }

    private static function can(): bool {
        return is_user_logged_in() && current_user_can( Roles::CAP_MANAGE_SOCI );
    }

    private static function can_roles(): bool {
        return current_user_can( 'promote_users' ) || current_user_can( Roles::CAP_MANAGE_ASSEMBLEE );
    }

    private static function back( int $uid, string $msg ): void {
        $url = wp_get_referer() ?: home_url( '/' );
        $base = remove_query_arg( [ 'msg', 'socio' ], $url );
        wp_safe_redirect( add_query_arg( $uid ? [ 'socio' => $uid, 'msg' => $msg ] : [ 'msg' => $msg ], $base ) );
        exit;
    }

    // --- Handlers ----------------------------------------------------------

    public static function handle_quota(): void {
        if ( ! self::can() || ! current_user_can( Roles::CAP_MANAGE_QUOTE ) ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_soci' );
        $uid  = (int) ( $_POST['uid'] ?? 0 );
        $anno = (int) gmdate( 'Y' );
        if ( ( $_POST['op'] ?? '' ) === 'paid' ) {
            Quote::mark_paid( $uid, $anno, 'bonifico', null, 'Console soci (front-end)', Quote::default_amount() );
        } else {
            Quote::mark_unpaid( $uid, $anno );
        }
        self::back( (int) ( $_POST['detail'] ?? 0 ) ? $uid : 0, 'quota' );
    }

    public static function handle_meta(): void {
        if ( ! self::can() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_soci' );
        $uid = (int) ( $_POST['uid'] ?? 0 );
        $num = sanitize_text_field( wp_unslash( $_POST['gf_numero_socio'] ?? '' ) );
        if ( $num === '' ) {
            $num = Candidatura::next_numero_socio();
        } elseif ( Candidatura::numero_in_use( $num, $uid ) ) {
            self::back( $uid, 'dup' );
        }
        update_user_meta( $uid, 'gf_numero_socio', $num );
        update_user_meta( $uid, 'gf_volontario', empty( $_POST['gf_volontario'] ) ? '0' : '1' );
        foreach ( self::ANAG as $k ) {
            update_user_meta( $uid, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ) );
        }
        self::back( $uid, 'saved' );
    }

    public static function handle_roles(): void {
        if ( ! self::can() || ! self::can_roles() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_soci' );
        $uid = (int) ( $_POST['uid'] ?? 0 );
        $u   = get_userdata( $uid );
        if ( $u ) {
            $wanted = array_map( 'sanitize_key', (array) ( $_POST['gf_roles'] ?? [] ) );
            foreach ( Roles::assignable_roles() as $slug => $label ) {
                $has = in_array( $slug, (array) $u->roles, true );
                if ( in_array( $slug, $wanted, true ) && ! $has ) { $u->add_role( $slug ); }
                elseif ( ! in_array( $slug, $wanted, true ) && $has ) { $u->remove_role( $slug ); }
            }
        }
        self::back( $uid, 'roles' );
    }

    public static function handle_archive(): void {
        if ( ! self::can() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_soci' );
        $uid = (int) ( $_POST['uid'] ?? 0 );
        if ( ( $_POST['op'] ?? '' ) === 'reactivate' ) { Archivio::reactivate( $uid ); }
        else { Archivio::archive( $uid ); }
        self::back( $uid, 'archived' );
    }

    public static function handle_delete(): void {
        if ( ! self::can() || ! current_user_can( 'delete_users' ) ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_soci' );
        Archivio::delete_with_data( (int) ( $_POST['uid'] ?? 0 ) );
        self::back( 0, 'deleted' );
    }

    // --- Render ------------------------------------------------------------

    public static function render(): string {
        if ( ! self::can() ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata al Consiglio Direttivo.</div>';
        }
        $socio = (int) ( $_GET['socio'] ?? 0 );
        return $socio ? self::render_detail( $socio ) : self::render_list();
    }

    private static function chip( string $st ): string {
        return match ( $st ) {
            'paid'     => '<span class="chip chip--ok">IN REGOLA</span>',
            'expiring' => '<span class="chip chip--warn">IN SCADENZA</span>',
            'pending'  => '<span class="chip chip--warn">DA INCASSARE</span>',
            'expired'  => '<span class="chip chip--bad">SCADUTA</span>',
            default    => '<span class="chip">N.D.</span>',
        };
    }

    private static function render_list(): string {
        $action = esc_url( admin_url( 'admin-post.php' ) );
        $nonce  = wp_nonce_field( 'gfoss_soci', '_wpnonce', true, false );
        $year   = (int) gmdate( 'Y' );
        $can_q  = current_user_can( Roles::CAP_MANAGE_QUOTE );
        $q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        $msg    = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );

        $soci = get_users( [
            'role__in' => [ 'gfoss_socio','gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_revisore','gfoss_comunicazione','gfoss_segreteria' ],
            'orderby'  => 'display_name', 'order' => 'ASC',
        ] );

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Consiglio Direttivo</p><h1 class="gf-area__title">Soci e quote ' . $year . '</h1><p class="gf-area__sub">Panoramica dei soci. Apri la scheda per anagrafica, ruoli e archiviazione.</p></div></header>';
        $notes = [ 'quota' => 'Quota aggiornata.', 'deleted' => 'Socio eliminato.', 'saved' => 'Dati salvati.', 'roles' => 'Ruoli aggiornati.', 'archived' => 'Stato aggiornato.' ];
        if ( isset( $notes[ $msg ] ) ) { echo '<div class="gf-card gf-card--success">' . esc_html( $notes[ $msg ] ) . '</div>'; }

        echo '<section class="gf-card">';
        echo '<form method="get" style="margin-bottom:.8rem"><input type="text" class="gf-select" name="q" value="' . esc_attr( $q ) . '" placeholder="Cerca per nome o email…"> <button class="gf-btn gf-btn--ghost gf-btn--sm">Cerca</button></form>';
        echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>N.</th><th>Socio</th><th>Quota ' . $year . '</th><th></th></tr></thead><tbody>';
        $shown = 0;
        foreach ( $soci as $s ) {
            $hay = strtolower( $s->display_name . ' ' . $s->user_email );
            if ( $q !== '' && strpos( $hay, strtolower( $q ) ) === false ) { continue; }
            $shown++;
            $num = (string) get_user_meta( $s->ID, 'gf_numero_socio', true );
            $st  = Quote::status_for( $s->ID, $year );
            echo '<tr>';
            echo '<td>' . ( $num ? esc_html( $num ) : '—' ) . '</td>';
            echo '<td><strong>' . esc_html( $s->display_name ) . '</strong><br><small class="gf-muted">' . esc_html( $s->user_email ) . '</small></td>';
            echo '<td>' . self::chip( $st ) . '</td>';
            echo '<td style="white-space:nowrap">';
            if ( $can_q ) {
                $op  = in_array( $st, [ 'paid', 'expiring' ], true ) ? 'unpaid' : 'paid';
                $lbl = $op === 'paid' ? 'Segna pagata' : 'Segna non pagata';
                echo '<form method="post" action="' . $action . '" style="display:inline">' . $nonce . '<input type="hidden" name="action" value="gfoss_soci_quota"><input type="hidden" name="uid" value="' . (int) $s->ID . '"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><button class="gf-btn gf-btn--ghost gf-btn--sm">' . esc_html( $lbl ) . '</button></form> ';
            }
            echo '<a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( add_query_arg( 'socio', $s->ID, remove_query_arg( [ 'q', 'msg' ] ) ) ) . '">Scheda →</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<p class="gf-muted" style="margin-top:.6rem">' . $shown . ' soci' . ( $q !== '' ? ' (filtrati)' : '' ) . '.</p>';
        echo '</section></div>';
        return (string) ob_get_clean();
    }

    private static function render_detail( int $uid ): string {
        $u = get_userdata( $uid );
        if ( ! $u ) { return '<div class="gf-card gf-card--warn">Socio non trovato.</div>'; }

        $action = esc_url( admin_url( 'admin-post.php' ) );
        $nonce  = wp_nonce_field( 'gfoss_soci', '_wpnonce', true, false );
        $year   = (int) gmdate( 'Y' );
        $msg    = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
        $back   = esc_url( remove_query_arg( [ 'socio', 'msg' ] ) );
        $st     = Quote::status_for( $uid, $year );
        $can_q  = current_user_can( Roles::CAP_MANAGE_QUOTE );
        $meta   = static fn( string $k ) => esc_attr( (string) get_user_meta( $uid, $k, true ) );

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow"><a href="' . $back . '">← Tutti i soci</a></p><h1 class="gf-area__title">' . esc_html( $u->display_name ) . '</h1><p class="gf-area__sub">' . esc_html( $u->user_email ) . ' · ' . self::chip( $st ) . '</p></div></header>';

        $notes = [ 'saved' => [ 'success', 'Dati salvati.' ], 'roles' => [ 'success', 'Ruoli aggiornati.' ], 'quota' => [ 'success', 'Quota aggiornata.' ], 'archived' => [ 'success', 'Stato aggiornato.' ], 'dup' => [ 'warn', 'Numero socio già assegnato a un altro socio.' ] ];
        if ( isset( $notes[ $msg ] ) ) { echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] ) . '">' . esc_html( $notes[ $msg ][1] ) . '</div>'; }

        // Anagrafica
        echo '<section class="gf-card"><h2 style="margin-top:0">Dati socio</h2>';
        echo '<form method="post" action="' . $action . '" class="gf-form">' . $nonce . '<input type="hidden" name="action" value="gfoss_soci_meta"><input type="hidden" name="uid" value="' . $uid . '">';
        echo '<div class="gf-grid">';
        echo '<label class="gf-field"><span class="gf-field__lbl">Numero socio</span><input type="text" name="gf_numero_socio" value="' . $meta( 'gf_numero_socio' ) . '" placeholder="auto se vuoto"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Codice fiscale</span><input type="text" name="gf_codice_fiscale" value="' . $meta( 'gf_codice_fiscale' ) . '"></label>';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Indirizzo</span><input type="text" name="gf_indirizzo" value="' . $meta( 'gf_indirizzo' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">CAP</span><input type="text" name="gf_cap" value="' . $meta( 'gf_cap' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Città</span><input type="text" name="gf_citta" value="' . $meta( 'gf_citta' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Provincia</span><input type="text" name="gf_provincia" value="' . $meta( 'gf_provincia' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Telefono</span><input type="text" name="gf_telefono" value="' . $meta( 'gf_telefono' ) . '"></label>';
        echo '<label class="gf-check gf-col-2"><input type="checkbox" name="gf_volontario" value="1" ' . checked( get_user_meta( $uid, 'gf_volontario', true ), '1', false ) . '> Disponibile ad attività di volontariato</label>';
        echo '</div><p class="gf-actions"><button class="gf-btn gf-btn--primary">Salva dati</button></p></form></section>';

        // Quota
        echo '<section class="gf-card"><h2 style="margin-top:0">Quota ' . $year . ' ' . self::chip( $st ) . '</h2>';
        if ( $can_q ) {
            $op  = in_array( $st, [ 'paid', 'expiring' ], true ) ? 'unpaid' : 'paid';
            $lbl = $op === 'paid' ? 'Segna pagata' : 'Segna non pagata';
            echo '<form method="post" action="' . $action . '" style="margin-bottom:1rem">' . $nonce . '<input type="hidden" name="action" value="gfoss_soci_quota"><input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="detail" value="1"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><button class="gf-btn gf-btn--primary">' . esc_html( $lbl ) . '</button></form>';
        }
        $storico = Quote::for_user( $uid );
        echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>Anno</th><th>Importo</th><th>Metodo</th><th>Stato</th></tr></thead><tbody>';
        if ( ! $storico ) { echo '<tr><td colspan="4" class="gf-muted">Nessun pagamento.</td></tr>'; }
        else { foreach ( $storico as $r ) { echo '<tr><td>' . esc_html( $r['anno'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $r['importo'], 2 ) ) . ' €</td><td>' . esc_html( $r['metodo'] ) . '</td><td>' . ( $r['stato'] === 'paid' ? '✓ pagata' : esc_html( $r['stato'] ) ) . '</td></tr>'; } }
        echo '</tbody></table></div></section>';

        // Ruoli
        echo '<section class="gf-card"><h2 style="margin-top:0">Ruoli</h2>';
        if ( self::can_roles() ) {
            echo '<form method="post" action="' . $action . '">' . $nonce . '<input type="hidden" name="action" value="gfoss_soci_roles"><input type="hidden" name="uid" value="' . $uid . '"><div class="gf-picklist">';
            foreach ( Roles::assignable_roles() as $slug => $label ) {
                echo '<label class="gf-pick"><input type="checkbox" name="gf_roles[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, (array) $u->roles, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
            }
            echo '</div>';
            if ( in_array( 'administrator', (array) $u->roles, true ) ) { echo '<p class="gf-muted">Questo utente è anche Amministratore: il ruolo resta invariato.</p>'; }
            echo '<p class="gf-actions"><button class="gf-btn gf-btn--primary">Salva ruoli</button></p></form>';
        } else {
            $labels = Roles::assignable_roles();
            $names  = array_map( static fn( $r ) => $labels[ $r ] ?? $r, (array) $u->roles );
            echo '<p>Ruoli: <strong>' . esc_html( implode( ', ', $names ) ) . '</strong></p><p class="gf-muted">Solo Presidente o amministratore può modificare i ruoli.</p>';
        }
        echo '</section>';

        // Archiviazione
        echo '<section class="gf-card"><h2 style="margin-top:0">Stato e archiviazione</h2>';
        if ( Archivio::is_archived( $uid ) ) {
            echo '<p>Socio <strong>archiviato</strong>.</p><form method="post" action="' . $action . '" style="display:inline">' . $nonce . '<input type="hidden" name="action" value="gfoss_soci_archive"><input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="op" value="reactivate"><button class="gf-btn gf-btn--primary">Riabilita socio</button></form>';
        } else {
            echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'Archiviare questo socio?\')">' . $nonce . '<input type="hidden" name="action" value="gfoss_soci_archive"><input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="op" value="archive"><button class="gf-btn gf-btn--ghost">Archivia socio</button></form>';
        }
        if ( current_user_can( 'delete_users' ) ) {
            echo '<hr style="margin:14px 0"><p class="gf-muted">Eliminazione definitiva (GDPR): rimuove l\'utente e tutti i suoi dati. Irreversibile.</p>';
            echo '<form method="post" action="' . $action . '" onsubmit="return confirm(\'ELIMINARE definitivamente ' . esc_attr( $u->display_name ) . ' e tutti i suoi dati?\')">' . $nonce . '<input type="hidden" name="action" value="gfoss_soci_delete"><input type="hidden" name="uid" value="' . $uid . '"><button class="gf-btn gf-btn--ghost" style="border-color:#C0392B;color:#C0392B">Elimina definitivamente</button></form>';
        }
        echo '</section></div>';
        return (string) ob_get_clean();
    }
}
