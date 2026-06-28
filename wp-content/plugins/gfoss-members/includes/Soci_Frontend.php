<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Console front-end Soci & quote (riservata al direttivo: CAP_MANAGE_SOCI).
 * Panoramica soci con stato quota e gestione rapida pagato/non pagato
 * (quest'ultima richiede CAP_MANAGE_QUOTE). Shortcode [gfoss_gestione_soci].
 */
class Soci_Frontend {

    public static function init(): void {
        add_shortcode( 'gfoss_gestione_soci', [ __CLASS__, 'render' ] );
        add_action( 'admin_post_gfoss_soci_quota', [ __CLASS__, 'handle_quota' ] );
    }

    private static function can(): bool {
        return is_user_logged_in() && current_user_can( Roles::CAP_MANAGE_SOCI );
    }

    public static function handle_quota(): void {
        if ( ! self::can() || ! current_user_can( Roles::CAP_MANAGE_QUOTE ) ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_soci' );
        $uid  = (int) ( $_POST['uid'] ?? 0 );
        $anno = (int) gmdate( 'Y' );
        if ( ( $_POST['op'] ?? '' ) === 'paid' ) {
            Quote::mark_paid( $uid, $anno, 'bonifico', null, 'Registrato dalla console soci (front-end)', Quote::default_amount() );
        } else {
            Quote::mark_unpaid( $uid, $anno );
        }
        $url = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'msg', 'quota', remove_query_arg( 'msg', $url ) ) );
        exit;
    }

    public static function render(): string {
        if ( ! self::can() ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata al Consiglio Direttivo.</div>';
        }

        $action  = esc_url( admin_url( 'admin-post.php' ) );
        $nonce   = wp_nonce_field( 'gfoss_soci', '_wpnonce', true, false );
        $year    = (int) gmdate( 'Y' );
        $can_q   = current_user_can( Roles::CAP_MANAGE_QUOTE );
        $q       = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        $msg     = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );

        $soci = get_users( [
            'role__in' => [ 'gfoss_socio','gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_revisore','gfoss_comunicazione','gfoss_segreteria' ],
            'orderby'  => 'display_name', 'order' => 'ASC',
        ] );

        $chip = static function ( string $st ): string {
            return match ( $st ) {
                'paid'     => '<span class="chip chip--ok">IN REGOLA</span>',
                'expiring' => '<span class="chip chip--warn">IN SCADENZA</span>',
                'pending'  => '<span class="chip chip--warn">DA INCASSARE</span>',
                'expired'  => '<span class="chip chip--bad">SCADUTA</span>',
                default    => '<span class="chip">N.D.</span>',
            };
        };

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Consiglio Direttivo</p><h1 class="gf-area__title">Soci e quote ' . $year . '</h1><p class="gf-area__sub">Panoramica dei soci e gestione rapida delle quote.</p></div></header>';
        if ( $msg === 'quota' ) { echo '<div class="gf-card gf-card--success">Quota aggiornata.</div>'; }

        echo '<section class="gf-card">';
        echo '<form method="get" style="margin-bottom:.8rem"><input type="text" class="gf-select" name="q" value="' . esc_attr( $q ) . '" placeholder="Cerca per nome o email…"> <button class="gf-btn gf-btn--ghost gf-btn--sm">Cerca</button></form>';
        echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>N.</th><th>Socio</th><th>Quota ' . $year . '</th>' . ( $can_q ? '<th></th>' : '' ) . '</tr></thead><tbody>';

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
            echo '<td>' . $chip( $st ) . '</td>';
            if ( $can_q ) {
                $op  = in_array( $st, [ 'paid', 'expiring' ], true ) ? 'unpaid' : 'paid';
                $lbl = $op === 'paid' ? 'Segna pagata' : 'Segna non pagata';
                echo '<td><form method="post" action="' . $action . '" style="margin:0">' . $nonce . '<input type="hidden" name="action" value="gfoss_soci_quota"><input type="hidden" name="uid" value="' . (int) $s->ID . '"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><button class="gf-btn gf-btn--ghost gf-btn--sm">' . esc_html( $lbl ) . '</button></form></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<p class="gf-muted" style="margin-top:.6rem">' . $shown . ' soci' . ( $q !== '' ? ' (filtrati)' : '' ) . '. Per la scheda completa di un socio usa il backend.</p>';
        echo '</section></div>';
        return (string) ob_get_clean();
    }
}
