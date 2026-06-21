<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PayPal IPN listener.
 *
 *   POST /wp-json/gfoss/v1/paypal-ipn
 *
 * Verifica l'IPN rispedendolo a PayPal con cmd=_notify-validate (best practice
 * documentata da PayPal). Se VERIFIED + payment_status=Completed + currency
 * coerente, registra il pagamento sulla candidatura identificata da
 * `custom=cand_<token>` e fa scattare la state machine.
 *
 * Per attivare la sandbox imposta GFOSS_PAYPAL_SANDBOX=true in wp-config.
 */
class Paypal {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'gfoss/v1', '/paypal-ipn', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => [ __CLASS__, 'handle_ipn' ],
        ] );
    }

    public static function handle_ipn( \WP_REST_Request $req ): \WP_REST_Response {
        $raw  = $req->get_body();
        $data = $req->get_body_params();

        if ( ! is_array( $data ) || empty( $data ) ) {
            return new \WP_REST_Response( 'no-body', 400 );
        }

        if ( ! self::verify( $raw ) ) {
            self::log( 'IPN INVALID', $data );
            return new \WP_REST_Response( 'invalid', 400 );
        }

        // Gating sui campi essenziali.
        $status   = (string) ( $data['payment_status'] ?? '' );
        $currency = (string) ( $data['mc_currency']   ?? '' );
        $amount   = (float)  ( $data['mc_gross']      ?? 0 );
        $txn_id   = (string) ( $data['txn_id']        ?? '' );
        $custom   = (string) ( $data['custom']        ?? '' );

        if ( $currency !== 'EUR' ) {
            self::log( 'IPN ignored: wrong currency', $data );
            return new \WP_REST_Response( 'ignored', 200 );
        }
        if ( $status !== 'Completed' ) {
            self::log( "IPN ignored: status=$status", $data );
            return new \WP_REST_Response( 'ignored', 200 );
        }
        if ( $txn_id === '' || $custom === '' ) {
            return new \WP_REST_Response( 'missing-fields', 400 );
        }

        // Anti-frode: il pagamento deve essere arrivato SUL conto dell'associazione.
        // Senza questo controllo un IPN VERIFIED proveniente da un conto PayPal di
        // un attaccante (stesso schema `custom=cand_<token>`) attiverebbe l'iscrizione.
        $receiver = strtolower( trim( (string) ( $data['receiver_email'] ?? $data['business'] ?? '' ) ) );
        $expected_receiver = defined( 'GFOSS_PAYPAL_RECEIVER' ) ? strtolower( trim( GFOSS_PAYPAL_RECEIVER ) ) : '';
        if ( $expected_receiver === '' ) {
            // Costante non configurata: rifiuta per sicurezza (fail-closed) e segnala.
            self::log( 'IPN rejected: GFOSS_PAYPAL_RECEIVER non configurato', $data );
            return new \WP_REST_Response( 'misconfigured', 200 );
        }
        if ( $receiver !== $expected_receiver ) {
            self::log( "IPN rejected: receiver mismatch ($receiver)", $data );
            return new \WP_REST_Response( 'ignored', 200 );
        }

        // Anti-frode importo: per le QUOTE deve coprire almeno la quota attesa
        // (il flusso /donate consente di modificare l'importo). Le DONAZIONI sono
        // invece a importo libero (custom = dona_…), basta che sia > 0.
        if ( str_starts_with( $custom, 'dona_' ) ) {
            if ( $amount <= 0 ) {
                self::log( "IPN rejected: donazione importo $amount", $data );
                return new \WP_REST_Response( 'ignored', 200 );
            }
        } else {
            $expected_amount = Quote::default_amount();
            if ( $amount + 0.001 < $expected_amount ) {
                self::log( "IPN rejected: importo $amount < quota $expected_amount", $data );
                return new \WP_REST_Response( 'ignored', 200 );
            }
        }

        // Idempotenza: ignora replay dello stesso txn_id.
        $seen = get_transient( 'gfoss_ipn_' . $txn_id );
        if ( $seen ) { return new \WP_REST_Response( 'dup', 200 ); }
        set_transient( 'gfoss_ipn_' . $txn_id, 1, MONTH_IN_SECONDS );

        // custom = "cand_<token>" → candidatura
        if ( str_starts_with( $custom, 'cand_' ) ) {
            $token = substr( $custom, 5 );
            $cand = Candidatura::get_by_token( $token );
            if ( ! $cand ) {
                self::log( 'IPN: candidatura not found', $data );
                return new \WP_REST_Response( 'not-found', 200 );
            }
            Candidatura::record_payment( (int) $cand['id'], $amount, 'paypal', $txn_id );
            self::log( 'IPN: candidatura paid', [ 'cand_id' => $cand['id'], 'amount' => $amount ] );
            return new \WP_REST_Response( 'ok', 200 );
        }

        // custom = "renewal_<user_id>_<year>" → rinnovo quota
        if ( str_starts_with( $custom, 'renewal_' ) ) {
            $parts = explode( '_', $custom );
            if ( count( $parts ) === 3 ) {
                $user_id = (int) $parts[1];
                $year    = (int) $parts[2];
                if ( $user_id && $year ) {
                    Quote::mark_paid( $user_id, $year, 'paypal', $txn_id, 'IPN PayPal' );
                    self::log( 'IPN: renewal paid', [ 'user' => $user_id, 'year' => $year ] );
                    return new \WP_REST_Response( 'ok', 200 );
                }
            }
        }

        // custom = "dona_<token>" → donazione a un progetto (crowdfunding)
        if ( str_starts_with( $custom, 'dona_' ) ) {
            $token = substr( $custom, 5 );
            $don   = Donazioni::find_by_token( $token );
            if ( ! $don ) {
                self::log( 'IPN: donazione token not found', $data );
                return new \WP_REST_Response( 'not-found', 200 );
            }
            Donazioni::mark_paid( (int) $don['id'], $amount, 'paypal', $txn_id );
            self::log( 'IPN: donazione paid', [ 'don_id' => $don['id'], 'amount' => $amount ] );
            return new \WP_REST_Response( 'ok', 200 );
        }

        self::log( 'IPN: unknown custom', $data );
        return new \WP_REST_Response( 'unknown', 200 );
    }

    /** Re-POSTa il messaggio a PayPal per validarlo. */
    private static function verify( string $raw ): bool {
        $endpoint = ( defined( 'GFOSS_PAYPAL_SANDBOX' ) && GFOSS_PAYPAL_SANDBOX )
            ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.paypal.com/cgi-bin/webscr';

        $body = 'cmd=_notify-validate&' . $raw;

        $resp = wp_remote_post( $endpoint, [
            'body'        => $body,
            'timeout'     => 30,
            'httpversion' => '1.1',
            'headers'     => [ 'Connection' => 'close' ],
            'user-agent'  => 'GFOSS-IPN/1.0',
        ] );

        if ( is_wp_error( $resp ) ) {
            self::log( 'IPN verify error: ' . $resp->get_error_message() );
            return false;
        }
        return trim( wp_remote_retrieve_body( $resp ) ) === 'VERIFIED';
    }

    private static function log( string $msg, array $data = [] ): void {
        $line = '[gfoss-ipn] ' . $msg;
        if ( $data ) { $line .= ' ' . wp_json_encode( $data ); }
        error_log( $line );
    }
}
