<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rubrica dei soci: mostra ai SOLI soci le "bio" che i colleghi hanno scelto di
 * condividere (campo gf_bio, compilato nel profilo). Chi non scrive una bio non
 * compare. Shortcode [gfoss_elenco_soci].
 */
class Elenco_Soci {

    public static function init(): void {
        add_shortcode( 'gfoss_elenco_soci', [ __CLASS__, 'render' ] );
    }

    public static function render(): string {
        if ( ! is_user_logged_in() || ! gfoss_members_is_socio( get_current_user_id() ) ) {
            return '<div class="gf-card gf-card--warn">La rubrica dei soci è riservata ai soci. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Accedi</a>.</div>';
        }

        $q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

        $soci = get_users( [
            'role__in'   => [ 'gfoss_socio','gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_revisore','gfoss_comunicazione','gfoss_segreteria' ],
            'orderby'    => 'display_name',
            'order'      => 'ASC',
            'meta_query' => [ [ 'key' => 'gf_bio', 'value' => '', 'compare' => '!=' ] ],
        ] );

        ob_start();
        echo '<div class="gf-area">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Community</p><h1 class="gf-area__title">Rubrica soci</h1><p class="gf-area__sub">Le presentazioni che i soci hanno scelto di condividere con gli altri soci.</p></div></header>';

        echo '<form method="get" style="margin-bottom:1rem"><input type="text" class="gf-select" name="q" value="' . esc_attr( $q ) . '" placeholder="Cerca per nome, città, competenze…"> <button class="gf-btn gf-btn--ghost gf-btn--sm">Cerca</button></form>';

        $cards = '';
        $shown = 0;
        foreach ( $soci as $u ) {
            $bio  = trim( (string) get_user_meta( $u->ID, 'gf_bio', true ) );
            if ( $bio === '' ) { continue; }
            $prof = (string) get_user_meta( $u->ID, 'gf_professione', true );
            $citta= (string) get_user_meta( $u->ID, 'gf_citta', true );
            $prov = (string) get_user_meta( $u->ID, 'gf_provincia', true );
            $comp = (string) get_user_meta( $u->ID, 'gf_competenze', true );

            $hay = strtolower( $u->display_name . ' ' . $prof . ' ' . $citta . ' ' . $comp . ' ' . $bio );
            if ( $q !== '' && strpos( $hay, strtolower( $q ) ) === false ) { continue; }
            $shown++;

            $meta = array_filter( [ $prof, trim( $citta . ( $prov ? ' (' . $prov . ')' : '' ) ) ] );
            $cards .= '<section class="gf-area__card gf-socio">';
            $cards .= '<div class="gf-socio__ava">' . esc_html( strtoupper( mb_substr( $u->display_name, 0, 1 ) ) ) . '</div>';
            $cards .= '<div class="gf-socio__body"><h2 style="margin:0 0 .15rem">' . esc_html( $u->display_name ) . '</h2>';
            if ( $meta ) { $cards .= '<p class="gf-muted" style="margin:0 0 .4rem">' . esc_html( implode( ' · ', $meta ) ) . '</p>'; }
            $cards .= '<p style="margin:0">' . nl2br( esc_html( $bio ) ) . '</p>';
            if ( $comp !== '' ) { $cards .= '<p class="gf-muted" style="margin:.4rem 0 0"><strong>Competenze:</strong> ' . esc_html( $comp ) . '</p>'; }
            $cards .= '</div></section>';
        }

        if ( ! $shown ) {
            echo '<div class="gf-card gf-muted">Nessuna bio ancora' . ( $q !== '' ? ' per questa ricerca' : '' ) . '. Scrivi la tua nell\'<a href="' . esc_url( home_url( '/area-soci/' ) ) . '">area personale</a> per comparire qui!</div>';
        } else {
            echo '<div class="gf-area__grid">' . $cards . '</div>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}
