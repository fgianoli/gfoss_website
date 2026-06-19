<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Verifica pubblica della tessera digitale.
 *
 *   QR → /verifica-tessera/?t=<token>
 *   Token = base64url( "user_id|hmac" ), HMAC con wp_salt('auth') troncato a 24 hex.
 *
 *   La pagina mostra in tempo reale: nome socio, numero, stato quota dell'anno corrente.
 *   Non rivela email, indirizzo o altri dati personali.
 */
class Verify {

    public static function init(): void {
        add_shortcode( 'gfoss_verifica_tessera', [ __CLASS__, 'render' ] );
    }

    public static function token_for( int $user_id ): string {
        $sig = self::sign( $user_id );
        $raw = $user_id . '|' . $sig;
        return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
    }

    public static function url_for( int $user_id ): string {
        $page_id = (int) get_option( 'gfoss_page_verifica_tessera' );
        $base    = $page_id ? get_permalink( $page_id ) : home_url( '/verifica-tessera/' );
        return add_query_arg( 't', self::token_for( $user_id ), $base );
    }

    private static function sign( int $user_id ): string {
        return substr( hash_hmac( 'sha256', 'gfoss-tessera-' . $user_id, wp_salt( 'auth' ) ), 0, 24 );
    }

    /** @return int|null user_id valido oppure null. */
    public static function decode( string $token ): ?int {
        $token = preg_replace( '/[^A-Za-z0-9_\-]/', '', $token );
        $raw = base64_decode( strtr( $token, '-_', '+/' ), true );
        if ( ! $raw || ! str_contains( $raw, '|' ) ) { return null; }
        [ $uid_str, $sig ] = explode( '|', $raw, 2 );
        $uid = (int) $uid_str;
        if ( $uid < 1 ) { return null; }
        return hash_equals( self::sign( $uid ), $sig ) ? $uid : null;
    }

    public static function render(): string {
        $token   = isset( $_GET['t'] ) ? (string) $_GET['t'] : '';
        $user_id = $token ? self::decode( $token ) : null;

        if ( ! $token ) {
            return '<div class="gf-card"><h2 style="margin-top:0">Verifica tessera GFOSS.it</h2>'
                 . '<p>Questa pagina mostra lo stato di una tessera socio quando viene aperta scansionando il QR sul retro della tessera digitale.</p></div>';
        }

        if ( ! $user_id ) {
            return '<div class="gf-card gf-card--error"><h2 style="margin-top:0">Tessera non valida</h2>'
                 . '<p>Il codice non è riconosciuto. Potrebbe essere stato manomesso o la tessera non esiste.</p></div>';
        }

        $u = get_userdata( $user_id );
        if ( ! $u || ! gfoss_members_is_socio( $user_id ) ) {
            return '<div class="gf-card gf-card--error"><h2 style="margin-top:0">Tessera non valida</h2>'
                 . '<p>L\'utente non risulta iscritto come socio.</p></div>';
        }

        $year   = (int) gmdate( 'Y' );
        $status = Quote::status_for( $user_id, $year );
        $numero = (string) get_user_meta( $user_id, 'gf_numero_socio', true );
        $regola = in_array( $status, [ 'paid', 'expiring' ], true );

        $cls   = $regola ? 'gf-card--success' : 'gf-card--error';
        $emoji = $regola ? '✓' : '✗';
        $msg   = $regola
            ? sprintf( 'Socio in regola con la quota %d', $year )
            : sprintf( 'Quota %d non risulta versata', $year );

        return '<div class="gf-card ' . $cls . '">'
            . '<h2 style="margin-top:0">' . esc_html( $emoji ) . ' ' . esc_html( $msg ) . '</h2>'
            . '<p style="font-size:1.2rem"><strong>' . esc_html( $u->display_name ) . '</strong>'
            . ( $numero ? ' — socio n° <code>' . esc_html( $numero ) . '</code>' : '' ) . '</p>'
            . '<p class="gf-muted">Verifica eseguita il ' . esc_html( wp_date( 'd/m/Y H:i', null, wp_timezone() ) ) . ' su ' . esc_html( home_url() ) . '</p>'
            . '</div>';
    }
}
