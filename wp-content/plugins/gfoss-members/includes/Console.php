<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Hub "Console del Direttivo": cruscotto unico con numeri a colpo d'occhio
 * (soci in regola, quote da incassare, prossimo evento, volontari in lista) e
 * le scorciatoie a tutti gli strumenti, in base ai permessi del ruolo.
 * Shortcode [gfoss_console_direttivo].
 */
class Console {

    public static function init(): void {
        add_shortcode( 'gfoss_console_direttivo', [ __CLASS__, 'render' ] );
    }

    private static function can(): bool {
        return is_user_logged_in() && ( current_user_can( Roles::CAP_MANAGE_SOCI )
            || current_user_can( Roles::CAP_MANAGE_VOLONTARI )
            || current_user_can( Roles::CAP_MANAGE_QUOTE )
            || current_user_can( 'edit_posts' ) );
    }

    private static function page_url( string $slug, string $fallback = '' ): string {
        $pg = get_posts( [ 'post_type' => 'page', 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1 ] );
        return $pg ? get_permalink( $pg[0] ) : $fallback;
    }

    public static function render(): string {
        if ( ! self::can() ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata al Consiglio Direttivo.</div>';
        }
        $year = (int) gmdate( 'Y' );

        // --- KPI -----------------------------------------------------------
        $soci = get_users( [ 'role__in' => [ 'gfoss_socio','gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_revisore','gfoss_comunicazione','gfoss_segreteria' ], 'fields' => [ 'ID' ] ] );
        $in_regola = 0; $da_incassare = 0;
        foreach ( $soci as $s ) {
            $st = Quote::status_for( (int) $s->ID, $year );
            if ( in_array( $st, [ 'paid', 'expiring' ], true ) ) { $in_regola++; }
            else { $da_incassare++; }
        }

        $next = get_posts( [ 'post_type' => Eventi::CPT, 'numberposts' => 1, 'post_status' => 'publish',
            'meta_key' => '_gf_ev_data', 'orderby' => 'meta_value', 'order' => 'ASC',
            'meta_query' => [ [ 'key' => '_gf_ev_data', 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ] ] ] );
        $next_ev = $next ? $next[0] : null;
        $next_lbl = '—'; $next_sub = 'Nessun evento in programma';
        if ( $next_ev ) {
            $ini = (string) get_post_meta( $next_ev->ID, '_gf_ev_data', true );
            $fin = (string) get_post_meta( $next_ev->ID, '_gf_ev_data_fine', true );
            $next_lbl = $next_ev->post_title;
            $next_sub = Volontari::format_periodo( $ini, $fin );
        }

        $vol_attivi = class_exists( __NAMESPACE__ . '\\Volontari' ) ? Volontari::count_attivi() : 0;

        // --- Strumenti (in base ai permessi) -------------------------------
        $tools = [];
        if ( current_user_can( Roles::CAP_MANAGE_VOLONTARI ) ) {
            $tools[] = [ self::page_url( 'registro-volontari', admin_url( 'admin.php?page=gfoss-volontari' ) ), '🦺', 'Registro volontari', 'Liste per evento e PDF assicurazione.' ];
        }
        if ( current_user_can( 'edit_posts' ) ) {
            $tools[] = [ self::page_url( 'gestione-eventi', admin_url( 'edit.php?post_type=' . Eventi::CPT ) ), '📅', 'Eventi', 'Crea e gestisci gli eventi.' ];
        }
        if ( current_user_can( Roles::CAP_MANAGE_SOCI ) || current_user_can( Roles::CAP_MANAGE_QUOTE ) ) {
            $tools[] = [ self::page_url( 'gestione-soci', admin_url( 'admin.php?page=gfoss-soci' ) ), '👥', 'Soci e quote', 'Quote, ricevute, anagrafiche, ruoli.' ];
        }
        if ( current_user_can( Roles::CAP_VIEW_ACCOUNTING ) ) {
            $tools[] = [ self::page_url( 'contabilita', admin_url( 'admin.php?page=gfoss-contabilita' ) ), '📊', 'Contabilità', 'Movimenti, saldo e rendiconto (tesoreria).' ];
        }
        if ( current_user_can( 'publish_posts' ) ) {
            $tools[] = [ self::page_url( 'scrivi-news', admin_url( 'post-new.php' ) ), '✍️', 'Scrivi una news', 'Pubblica notizie sul sito.' ];
        }
        $conv = self::page_url( 'convocazioni' );
        if ( $conv && current_user_can( Roles::CAP_MANAGE_ASSEMBLEE ) ) {
            $tools[] = [ $conv, '🏛️', 'Convocazioni e deleghe', 'Assemblee e gestione deleghe.' ];
        }
        $vdir = self::page_url( 'verbali-direttivo' );
        if ( $vdir && Trasparenza::user_is_board() ) {
            $tools[] = [ $vdir, '📕', 'Verbali del direttivo', 'Verbali del CD, riservati.' ];
        }
        $voti = self::page_url( 'votazioni' );
        if ( $voti && current_user_can( Roles::CAP_MANAGE_ASSEMBLEE ) ) {
            $tools[] = [ $voti, '🗳️', 'Votazioni', 'Crea e gestisci le votazioni d\'assemblea.' ];
        }
        if ( current_user_can( Roles::CAP_MANAGE_VOLONTARI ) ) {
            $nc = defined( 'GFOSS_NEXTCLOUD_URL' ) ? GFOSS_NEXTCLOUD_URL : ( getenv( 'GFOSS_NEXTCLOUD_URL' ) ?: '' );
            if ( $nc ) { $tools[] = [ $nc, '🗂️', 'Documenti del Direttivo', 'Verbali e file riservati (Nextcloud).' ]; }
        }
        $comun = self::page_url( 'comunicazioni-soci' );
        if ( $comun && current_user_can( Roles::CAP_MANAGE_SOCI ) ) {
            $tools[] = [ $comun, '📣', 'Comunicazioni ai soci', 'Invia avvisi via email ai soci.' ];
        }

        $kpis = [
            [ $in_regola,    'Soci in regola ' . $year, '#5DA34D' ],
            [ $da_incassare, 'Quote da incassare',      '#B26A00' ],
            [ $vol_attivi,   'Volontari nel registro',  '#1A6FA0' ],
        ];

        ob_start();
        echo '<div class="gf-area gf-console">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Consiglio Direttivo</p><h1 class="gf-area__title">Console del Direttivo</h1><p class="gf-area__sub">Tutto sotto controllo: numeri chiave e strumenti in un colpo d\'occhio.</p></div></header>';

        // Notifica: candidature nuovi soci in attesa di approvazione
        if ( current_user_can( Roles::CAP_REVIEW_CANDIDATURE ) ) {
            $da_app = Candidatura::count_da_approvare();
            if ( $da_app > 0 ) {
                echo '<div class="gf-card gf-card--warn" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem">'
                    . '<span>🔔 <strong>' . (int) $da_app . '</strong> ' . ( $da_app === 1 ? 'nuova candidatura socio è in attesa' : 'nuove candidature soci sono in attesa' ) . ' di approvazione.</span>'
                    . '<a class="gf-btn gf-btn--primary" href="' . esc_url( admin_url( 'admin.php?page=gfoss-candidature' ) ) . '">Esamina le candidature →</a></div>';
            }
        }

        // KPI
        echo '<div class="gf-kpis">';
        foreach ( $kpis as $k ) {
            echo '<div class="gf-kpi"><div class="gf-kpi__num" style="color:' . esc_attr( $k[2] ) . '">' . (int) $k[0] . '</div><div class="gf-kpi__lbl">' . esc_html( $k[1] ) . '</div></div>';
        }
        // Prossimo evento (card larga)
        echo '<div class="gf-kpi gf-kpi--wide"><div class="gf-kpi__lbl">Prossimo evento</div><div class="gf-kpi__ev"><strong>' . esc_html( $next_lbl ) . '</strong><br><small>' . esc_html( $next_sub ) . '</small></div></div>';
        echo '</div>';

        // Strumenti
        echo '<section class="gf-card"><h2 style="margin-top:0">Strumenti</h2><div class="gf-tools">';
        foreach ( $tools as $t ) {
            echo '<a class="gf-tool" href="' . esc_url( $t[0] ) . '"><span class="gf-tool__ico" aria-hidden="true">' . $t[1] . '</span><span class="gf-tool__txt"><strong>' . esc_html( $t[2] ) . '</strong><small>' . esc_html( $t[3] ) . '</small></span></a>';
        }
        echo '</div>';
        echo '<p style="margin-top:14px"><a class="gf-btn gf-btn--ghost" href="' . esc_url( admin_url() ) . '">⚙️ Vai al pannello WordPress (wp-admin)</a></p>';
        echo '</section></div>';
        return (string) ob_get_clean();
    }
}
