<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Console front-end del Registro Volontari (riservata al direttivo).
 * Shortcode [gfoss_registro_volontari] — stessa logica del backend (classe
 * Volontari) ma con la grafica del sito, multi-selezione soci e aggiunta esterni.
 */
class Volontari_Frontend {

    public static function init(): void {
        add_shortcode( 'gfoss_registro_volontari', [ __CLASS__, 'render' ] );
        add_action( 'admin_post_gfoss_vol_add_soci',     [ __CLASS__, 'handle_add_soci' ] );
        add_action( 'admin_post_gfoss_vol_add_external', [ __CLASS__, 'handle_add_external' ] );
        add_action( 'admin_post_gfoss_vol_remove',       [ __CLASS__, 'handle_remove' ] );
    }

    private static function guard(): void {
        if ( ! is_user_logged_in() || ! Volontari::can_manage() ) { wp_die( 'Riservato al Consiglio Direttivo.' ); }
    }

    public static function handle_add_soci(): void {
        self::guard();
        check_admin_referer( 'gfoss_vol_front' );
        $evento_id = (int) ( $_POST['evento_id'] ?? 0 );
        $soci      = array_map( 'absint', (array) ( $_POST['soci'] ?? [] ) );
        $mancanti  = 0;
        foreach ( $soci as $uid ) {
            $vid = Volontari::ensure_from_socio( $uid );
            if ( is_wp_error( $vid ) || ! $vid ) { $mancanti++; continue; }
            if ( $evento_id ) { Volontari::add_to_event( (int) $vid, $evento_id ); }
        }
        self::back( $evento_id, $mancanti ? 'soci_partial' : 'soci_added', $mancanti );
    }

    public static function handle_add_external(): void {
        self::guard();
        check_admin_referer( 'gfoss_vol_front' );
        $evento_id = (int) ( $_POST['evento_id'] ?? 0 );
        $data = $_POST;
        $data['tipo'] = 'occasionale';
        $res = Volontari::insert( $data );
        if ( is_wp_error( $res ) ) {
            self::back( $evento_id, 'ext_err', 0, $res->get_error_message() );
        }
        if ( $evento_id ) { Volontari::add_to_event( (int) $res, $evento_id ); }
        self::back( $evento_id, 'ext_added' );
    }

    public static function handle_remove(): void {
        self::guard();
        check_admin_referer( 'gfoss_vol_front' );
        $evento_id = (int) ( $_POST['evento_id'] ?? 0 );
        Volontari::remove_from_event( (int) ( $_POST['id'] ?? 0 ), $evento_id );
        self::back( $evento_id, 'removed' );
    }

    private static function back( int $evento_id, string $msg, int $n = 0, string $err = '' ): void {
        $url = wp_get_referer() ?: home_url( '/' );
        $args = [ 'ev' => $evento_id, 'msg' => $msg ];
        if ( $n ) { $args['n'] = $n; }
        if ( $err ) { $args['err'] = rawurlencode( $err ); }
        wp_safe_redirect( add_query_arg( $args, remove_query_arg( [ 'msg', 'n', 'err' ], $url ) ) );
        exit;
    }

    public static function render(): string {
        if ( ! is_user_logged_in() || ! Volontari::can_manage() ) {
            return '<div class="gf-card gf-card--warn">Questa sezione è riservata ai membri del Consiglio Direttivo.</div>';
        }

        $ev_sel  = (int) ( $_GET['ev'] ?? 0 );
        $eventi  = Volontari::eventi_list();
        $msg     = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
        $nonce   = wp_nonce_field( 'gfoss_vol_front', '_wpnonce', true, false );
        $action  = esc_url( admin_url( 'admin-post.php' ) );
        $fmt     = static fn( $d ) => $d ? date_i18n( 'd/m/Y', strtotime( (string) $d ) ) : '—';

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Consiglio Direttivo</p><h1 class="gf-area__title">Registro volontari</h1><p class="gf-area__sub">Componi la lista dei volontari presenti a ogni evento e genera il documento per l\'assicurazione.</p></div></header>';

        // Avvisi
        $notes = [
            'soci_added' => [ 'success', 'Soci aggiunti alla lista dell\'evento.' ],
            'soci_partial' => [ 'warn', 'Alcuni soci non sono stati aggiunti: dati anagrafici incompleti (CF/nascita o residenza mancanti). Completa il loro profilo.' ],
            'ext_added'  => [ 'success', 'Volontario esterno aggiunto.' ],
            'ext_err'    => [ 'warn', 'Impossibile aggiungere l\'esterno: ' . esc_html( rawurldecode( (string) ( $_GET['err'] ?? '' ) ) ) ],
            'removed'    => [ 'success', 'Volontario rimosso dalla lista.' ],
        ];
        if ( isset( $notes[ $msg ] ) ) {
            echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] === 'success' ? 'success' : 'warn' ) . '">' . $notes[ $msg ][1] . '</div>';
        }

        // Selettore evento
        echo '<section class="gf-card"><h2 style="margin-top:0">Scegli l\'evento</h2>';
        if ( ! $eventi ) {
            echo '<p class="gf-muted">Non ci sono eventi. Crea prima un evento nella sezione Eventi del sito.</p>';
        } else {
            echo '<select class="gf-select" onchange="location.href=location.pathname+(this.value?(\'?ev=\'+this.value):\'\')">';
            echo '<option value="">— seleziona —</option>';
            foreach ( $eventi as $ev ) {
                $em  = Volontari::event_meta( $ev->ID );
                $lbl = $ev->post_title . ( $em['inizio'] ? ' (' . esc_html( Volontari::format_periodo( $em['inizio'], $em['fine'] ) ) . ')' : '' );
                echo '<option value="' . (int) $ev->ID . '" ' . selected( $ev_sel, $ev->ID, false ) . '>' . esc_html( $lbl ) . '</option>';
            }
            echo '</select>';
        }
        echo '</section>';

        if ( $ev_sel ) {
            $em     = Volontari::event_meta( $ev_sel );
            $membri = Volontari::volontari_for_event( $ev_sel );
            $in_ids = Volontari::ids_in_event( $ev_sel );

            echo '<section class="gf-card">';
            echo '<div class="gf-vol__evhead"><div><h2 style="margin:0">' . esc_html( $em['titolo'] ) . '</h2><p class="gf-muted" style="margin:.2rem 0 0">' . esc_html( Volontari::format_periodo( $em['inizio'], $em['fine'] ) ) . ( $em['luogo'] ? ' · ' . esc_html( $em['luogo'] ) : '' ) . ' — <strong>' . count( $membri ) . '</strong> in lista</p></div>';
            // PDF
            echo '<form method="post" action="' . $action . '" style="margin:0">';
            echo '<input type="hidden" name="action" value="gfoss_volontari_pdf"><input type="hidden" name="evento_id" value="' . (int) $ev_sel . '">';
            echo wp_nonce_field( 'gfoss_volontari_pdf', '_wpnonce', true, false );
            echo '<button class="gf-btn gf-btn--primary"' . ( $membri ? '' : ' disabled' ) . '>⬇ Genera PDF assicurazione</button>';
            echo '</form></div>';

            // Lista attuale
            if ( ! $membri ) {
                echo '<p class="gf-muted">Nessun volontario in lista. Aggiungili qui sotto.</p>';
            } else {
                echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>N.</th><th>Nominativo</th><th>CF / Nascita</th><th>Tipo</th><th></th></tr></thead><tbody>';
                foreach ( $membri as $v ) {
                    echo '<tr><td>' . ( $v['n_registro'] ? '#' . (int) $v['n_registro'] : '—' ) . '</td>';
                    echo '<td><strong>' . esc_html( $v['cognome'] . ' ' . $v['nome'] ) . '</strong></td>';
                    echo '<td>' . ( $v['codice_fiscale'] ? '<code>' . esc_html( $v['codice_fiscale'] ) . '</code>' : esc_html( trim( $v['luogo_nascita'] . ' ' . $fmt( $v['data_nascita'] ) ) ?: '—' ) ) . '</td>';
                    echo '<td>' . ( $v['tipo'] === 'occasionale' ? 'Occasionale' : 'Continuativo' ) . '</td>';
                    echo '<td><form method="post" action="' . $action . '" style="margin:0">' . $nonce . '<input type="hidden" name="action" value="gfoss_vol_remove"><input type="hidden" name="evento_id" value="' . (int) $ev_sel . '"><input type="hidden" name="id" value="' . (int) $v['id'] . '"><button class="gf-btn gf-btn--ghost gf-btn--sm">Rimuovi</button></form></td></tr>';
                }
                echo '</tbody></table></div>';
            }
            echo '</section>';

            // Aggiungi soci (multi-selezione)
            $soci = get_users( [ 'role__in' => [ 'gfoss_socio','gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_revisore','gfoss_comunicazione','gfoss_segreteria' ], 'orderby' => 'display_name', 'order' => 'ASC' ] );
            echo '<section class="gf-card"><h2 style="margin-top:0">Aggiungi soci alla lista</h2>';
            echo '<p class="gf-muted">Seleziona uno o più soci. I dati anagrafici vengono presi dal loro profilo.</p>';
            echo '<input type="text" class="gf-select" placeholder="Filtra per nome…" onkeyup="var q=this.value.toLowerCase();this.closest(\'section\').querySelectorAll(\'.gf-pick\').forEach(function(l){l.style.display=l.textContent.toLowerCase().indexOf(q)<0?\'none\':\'\'})" style="margin-bottom:.6rem">';
            echo '<form method="post" action="' . $action . '">' . $nonce . '<input type="hidden" name="action" value="gfoss_vol_add_soci"><input type="hidden" name="evento_id" value="' . (int) $ev_sel . '">';
            echo '<div class="gf-picklist">';
            foreach ( $soci as $s ) {
                echo '<label class="gf-pick"><input type="checkbox" name="soci[]" value="' . (int) $s->ID . '"> ' . esc_html( $s->display_name ) . '</label>';
            }
            echo '</div><p style="margin-top:.7rem"><button class="gf-btn gf-btn--primary">Aggiungi selezionati</button></p></form></section>';

            // Aggiungi esterno occasionale
            echo '<section class="gf-card"><h2 style="margin-top:0">Aggiungi un esterno (occasionale)</h2>';
            echo '<p class="gf-muted">Per chi non è socio. Servono: CF <em>oppure</em> luogo e data di nascita, più la residenza.</p>';
            echo '<form method="post" action="' . $action . '" class="gf-form">' . $nonce . '<input type="hidden" name="action" value="gfoss_vol_add_external"><input type="hidden" name="evento_id" value="' . (int) $ev_sel . '">';
            echo '<div class="gf-grid">';
            echo self::field( 'nome', 'Nome *' ) . self::field( 'cognome', 'Cognome *' );
            echo self::field( 'codice_fiscale', 'Codice fiscale' ) . self::field( 'luogo_nascita', 'Luogo di nascita' );
            echo '<label class="gf-field"><span class="gf-field__lbl">Data di nascita</span><input type="date" name="data_nascita"></label>';
            echo self::field( 'indirizzo', 'Indirizzo (residenza) *' ) . self::field( 'citta', 'Città *' );
            echo self::field( 'cap', 'CAP' ) . self::field( 'provincia', 'Provincia' );
            echo '</div><p class="gf-actions"><button class="gf-btn gf-btn--primary">Aggiungi all\'evento</button></p></form></section>';
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function field( string $name, string $label ): string {
        return '<label class="gf-field"><span class="gf-field__lbl">' . esc_html( $label ) . '</span><input type="text" name="' . esc_attr( $name ) . '"></label>';
    }
}
