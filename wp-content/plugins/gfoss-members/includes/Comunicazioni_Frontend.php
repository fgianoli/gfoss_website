<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Console front-end per inviare comunicazioni email ai soci (CAP_MANAGE_SOCI).
 * Destinatari: tutti, solo in regola, o per singolo ruolo.
 * Shortcode [gfoss_comunicazioni_soci].
 */
class Comunicazioni_Frontend {

    const RUOLI = [ 'gfoss_socio','gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_revisore','gfoss_comunicazione','gfoss_segreteria' ];

    public static function init(): void {
        add_shortcode( 'gfoss_comunicazioni_soci', [ __CLASS__, 'render' ] );
        add_action( 'admin_post_gfoss_comunica_send', [ __CLASS__, 'handle_send' ] );
    }

    private static function can(): bool {
        return is_user_logged_in() && current_user_can( Roles::CAP_MANAGE_SOCI );
    }

    /** Risolve i destinatari in base al gruppo scelto. @return \WP_User[] */
    private static function recipients( string $gruppo ): array {
        $year = (int) gmdate( 'Y' );
        if ( in_array( $gruppo, self::RUOLI, true ) ) {
            return get_users( [ 'role' => $gruppo ] );
        }
        $users = get_users( [ 'role__in' => self::RUOLI ] );
        if ( $gruppo === 'regola' ) {
            return array_values( array_filter( $users, static fn( $u ) => in_array( Quote::status_for( $u->ID, $year ), [ 'paid', 'expiring' ], true ) ) );
        }
        return $users; // tutti
    }

    public static function handle_send(): void {
        if ( ! self::can() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_comunica' );

        $oggetto = sanitize_text_field( wp_unslash( $_POST['oggetto'] ?? '' ) );
        $corpo   = trim( (string) wp_unslash( $_POST['corpo'] ?? '' ) );
        $gruppo  = sanitize_text_field( wp_unslash( $_POST['gruppo'] ?? 'all' ) );
        $url     = wp_get_referer() ?: home_url( '/' );

        if ( $oggetto === '' || $corpo === '' ) {
            wp_safe_redirect( add_query_arg( 'msg', 'empty', remove_query_arg( [ 'msg', 'n' ], $url ) ) ); exit;
        }

        $body = wpautop( wp_kses_post( $corpo ) )
            . '<hr><p style="font-size:12px;color:#777">GFOSS.it APS — Associazione Italiana per l\'Informazione Geografica Libera. Ricevi questa email come socio dell\'associazione.</p>';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $count = 0;
        foreach ( self::recipients( $gruppo ) as $u ) {
            if ( ! is_email( $u->user_email ) ) { continue; }
            if ( wp_mail( $u->user_email, $oggetto, $body, $headers ) ) { $count++; }
        }
        wp_safe_redirect( add_query_arg( [ 'msg' => 'sent', 'n' => $count ], remove_query_arg( [ 'msg', 'n' ], $url ) ) ); exit;
    }

    public static function render(): string {
        if ( ! self::can() ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata al Consiglio Direttivo.</div>';
        }
        $action = esc_url( admin_url( 'admin-post.php' ) );
        $msg    = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
        $n      = (int) ( $_GET['n'] ?? 0 );
        $year   = (int) gmdate( 'Y' );

        $labels = [ 'gfoss_socio'=>'Socio','gfoss_consigliere'=>'Consigliere','gfoss_presidente'=>'Presidente','gfoss_tesoriere'=>'Tesoriere','gfoss_revisore'=>'Revisore','gfoss_comunicazione'=>'Comunicazione','gfoss_segreteria'=>'Segreteria' ];

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Consiglio Direttivo</p><h1 class="gf-area__title">Comunicazioni ai soci</h1><p class="gf-area__sub">Invia un avviso via email a tutti i soci o a un gruppo.</p></div></header>';

        if ( $msg === 'sent' ) { echo '<div class="gf-card gf-card--success">Email inviata a ' . $n . ' destinatari.</div>'; }
        elseif ( $msg === 'empty' ) { echo '<div class="gf-card gf-card--warn">Oggetto e messaggio sono obbligatori.</div>'; }

        echo '<section class="gf-card">';
        echo '<form method="post" action="' . $action . '" class="gf-form" onsubmit="return confirm(\'Inviare l\\\'email al gruppo selezionato?\')">';
        echo wp_nonce_field( 'gfoss_comunica', '_wpnonce', true, false );
        echo '<input type="hidden" name="action" value="gfoss_comunica_send">';
        echo '<div class="gf-grid">';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Destinatari</span><select name="gruppo" class="gf-select">';
        echo '<option value="all">Tutti i soci</option>';
        echo '<option value="regola">Solo in regola ' . $year . '</option>';
        foreach ( $labels as $slug => $lab ) {
            $c = count( get_users( [ 'role' => $slug, 'fields' => 'ID' ] ) );
            echo '<option value="' . esc_attr( $slug ) . '">Ruolo: ' . esc_html( $lab ) . ' (' . $c . ')</option>';
        }
        echo '</select></label>';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Oggetto *</span><input type="text" name="oggetto" required></label>';
        echo '</div>';
        echo '<div class="gf-field gf-news-editor"><span class="gf-field__lbl">Messaggio *</span>';
        wp_editor( '', 'corpo', [ 'textarea_name' => 'corpo', 'media_buttons' => false, 'teeny' => true, 'textarea_rows' => 10, 'quicktags' => true ] );
        echo '</div>';
        echo '<p class="gf-actions"><button class="gf-btn gf-btn--primary">Invia comunicazione</button></p>';
        echo '</form></section></div>';
        return (string) ob_get_clean();
    }
}
