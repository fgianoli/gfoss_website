<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rinnovo quota — costruisce l'URL del bottone PayPal hosted con custom field
 * "renewal_<userid>_<year>" che il listener IPN (Paypal::handle_ipn) riconosce
 * e converte in Quote::mark_paid.
 *
 * Crea anche, in stato "pending", il record di quota dell'anno target così
 * appare nello storico anche prima che PayPal confermi.
 */
class Rinnovo {

    public static function init(): void {
        // Niente di particolare: tutto avviene client-side via URL PayPal e poi via IPN.
    }

    public static function paypal_url( int $user_id, int $year ): string {
        // Pre-registra una quota "pending" così è visibile nello storico.
        Quote::upsert( $user_id, $year, [
            'importo' => Quote::default_amount(),
            'metodo'  => 'paypal',
            'stato'   => 'pending',
            'note'    => 'In attesa di conferma PayPal',
        ] );

        $bid    = defined( 'GFOSS_PAYPAL_BUTTON_ID' ) ? GFOSS_PAYPAL_BUTTON_ID : '';
        $amount = Quote::default_amount();
        $params = http_build_query( [
            'hosted_button_id' => $bid,
            'amount'           => number_format( $amount, 2, '.', '' ),
            'currency_code'    => 'EUR',
            'item_name'        => "Rinnovo quota GFOSS.it APS {$year}",
            'custom'           => "renewal_{$user_id}_{$year}",
            'no_shipping'      => 1,
            'return'           => add_query_arg( [ 'msg' => 'paypal_ok' ],     home_url( '/area-soci/' ) ),
            'cancel_return'    => add_query_arg( [ 'msg' => 'paypal_cancel' ], home_url( '/area-soci/' ) ),
            'notify_url'       => rest_url( 'gfoss/v1/paypal-ipn' ),
        ] );

        return 'https://www.paypal.com/donate/?' . $params;
    }
}
