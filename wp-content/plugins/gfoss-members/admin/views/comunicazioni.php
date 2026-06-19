<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Comunicazioni ai soci — invio email nativo (wp_mail), senza dipendenze.
 * Cap: CAP_MANAGE_SOCI.
 */

$ruoli = [ 'gfoss_socio', 'gfoss_consigliere', 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore' ];
$sent  = null;

if ( ! empty( $_POST['_action'] ) && $_POST['_action'] === 'invia' && current_user_can( Roles::CAP_MANAGE_SOCI ) ) {
    check_admin_referer( 'gfoss_comunicazioni' );

    $oggetto = sanitize_text_field( wp_unslash( $_POST['oggetto'] ?? '' ) );
    $corpo   = trim( (string) wp_unslash( $_POST['corpo'] ?? '' ) );
    $solo_reg = ! empty( $_POST['solo_regola'] );
    $year     = (int) gmdate( 'Y' );

    if ( $oggetto === '' || $corpo === '' ) {
        echo '<div class="notice notice-error"><p>Oggetto e messaggio sono obbligatori.</p></div>';
    } else {
        $users = get_users( [ 'role__in' => $ruoli ] );
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $count = 0;
        $body_html = wpautop( wp_kses_post( $corpo ) )
            . '<hr><p style="font-size:12px;color:#777">GFOSS.it APS — Associazione Italiana per l\'Informazione Geografica Libera. '
            . 'Ricevi questa email come socio dell\'associazione.</p>';

        foreach ( $users as $u ) {
            if ( $solo_reg && ! in_array( Quote::status_for( $u->ID, $year ), [ 'paid', 'expiring' ], true ) ) {
                continue;
            }
            if ( ! is_email( $u->user_email ) ) { continue; }
            if ( wp_mail( $u->user_email, $oggetto, $body_html, $headers ) ) { $count++; }
        }
        $sent = $count;
    }
}

$tot_soci = count( get_users( [ 'role__in' => $ruoli, 'fields' => 'ID' ] ) );
$in_regola = 0;
$year = (int) gmdate( 'Y' );
foreach ( get_users( [ 'role__in' => $ruoli, 'fields' => 'ID' ] ) as $uid ) {
    if ( in_array( Quote::status_for( (int) $uid, $year ), [ 'paid', 'expiring' ], true ) ) { $in_regola++; }
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Comunicazioni ai soci', 'gfoss-members' ); ?></h1>

    <?php if ( $sent !== null ) : ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php printf( esc_html__( 'Email inviata a %d soci.', 'gfoss-members' ), (int) $sent ); ?>
        </p></div>
    <?php endif; ?>

    <p class="description">
        <?php printf( esc_html__( 'Soci totali: %1$d · in regola con la quota %2$d: %3$d.', 'gfoss-members' ), $tot_soci, $year, $in_regola ); ?>
    </p>

    <form method="post" style="max-width:760px;margin-top:1rem">
        <?php wp_nonce_field( 'gfoss_comunicazioni' ); ?>
        <input type="hidden" name="_action" value="invia">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="oggetto"><?php esc_html_e( 'Oggetto', 'gfoss-members' ); ?></label></th>
                <td><input type="text" name="oggetto" id="oggetto" class="regular-text" style="width:100%" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="corpo"><?php esc_html_e( 'Messaggio', 'gfoss-members' ); ?></label></th>
                <td>
                    <textarea name="corpo" id="corpo" rows="10" class="large-text" required></textarea>
                    <p class="description"><?php esc_html_e( 'È consentito HTML semplice (grassetto, link, elenchi).', 'gfoss-members' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Destinatari', 'gfoss-members' ); ?></th>
                <td><label><input type="checkbox" name="solo_regola" value="1" checked> <?php esc_html_e( 'Solo soci in regola con la quota', 'gfoss-members' ); ?></label></td>
            </tr>
        </table>
        <p>
            <button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e( 'Inviare l\'email ai soci selezionati?', 'gfoss-members' ); ?>')"><?php esc_html_e( 'Invia comunicazione', 'gfoss-members' ); ?></button>
        </p>
    </form>
    <p class="description"><?php esc_html_e( 'Invio diretto via email del sito. Per grandi volumi valuta un servizio SMTP dedicato.', 'gfoss-members' ); ?></p>
</div>
