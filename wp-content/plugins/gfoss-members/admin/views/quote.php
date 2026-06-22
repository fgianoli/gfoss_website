<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Console quote (tesoriere). Cap: CAP_MANAGE_QUOTE.
 * Registra a mano i pagamenti (tipicamente bonifici: il PayPal arriva da solo
 * via IPN) e mostra lo stato di incasso quote per l'anno selezionato.
 */

// Gestione azione POST: segna quota come pagata (pattern PRG con redirect).
if ( ! empty( $_POST['_action'] ) && $_POST['_action'] === 'mark_paid' && current_user_can( Roles::CAP_MANAGE_QUOTE ) ) {
    $u_id = (int) ( $_POST['user_id'] ?? 0 );
    $anno = (int) ( $_POST['anno'] ?? 0 );
    check_admin_referer( 'gfoss_quote_pay_' . $u_id . '_' . $anno );

    if ( $u_id && $anno ) {
        $metodo = sanitize_key( (string) ( $_POST['metodo'] ?? 'bonifico' ) );
        $ref    = sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) );
        $raw    = str_replace( ',', '.', (string) ( $_POST['importo'] ?? '' ) );
        $amount = is_numeric( $raw ) ? (float) $raw : null;
        Quote::mark_paid( $u_id, $anno, $metodo, $ref ?: null, 'Registrato a mano dal tesoriere', $amount );
        wp_safe_redirect( add_query_arg( [ 'anno' => $anno, 'msg' => 'paid' ], admin_url( 'admin.php?page=gfoss-quote' ) ) );
        exit;
    }
}

$year_now = (int) gmdate( 'Y' );
$year     = isset( $_GET['anno'] ) ? (int) $_GET['anno'] : $year_now;
$msg      = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );

$users = get_users( [
    'role__in' => [ 'gfoss_socio', 'gfoss_consigliere', 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore', 'gfoss_comunicazione', 'gfoss_segreteria' ],
    'orderby'  => 'display_name',
] );
$quote   = Quote::all_for_year( $year );
$default = Quote::default_amount();

// Riepilogo.
$tot = count( $users );
$paid = 0; $incasso = 0.0;
foreach ( $users as $u ) {
    $row = $quote[ $u->ID ] ?? null;
    if ( $row && $row['stato'] === 'paid' ) { $paid++; $incasso += (float) $row['importo']; }
}
$metodi = [ 'bonifico' => 'Bonifico', 'contanti' => 'Contanti', 'paypal' => 'PayPal', 'altro' => 'Altro' ];
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Quote associative', 'gfoss-members' ); ?></h1>

    <?php if ( $msg === 'paid' ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pagamento registrato.', 'gfoss-members' ); ?></p></div>
    <?php endif; ?>

    <form method="get" style="margin:1rem 0;display:flex;gap:.5rem;align-items:center">
        <input type="hidden" name="page" value="gfoss-quote">
        <label for="anno"><strong><?php esc_html_e( 'Anno', 'gfoss-members' ); ?></strong></label>
        <select name="anno" id="anno" onchange="this.form.submit()">
            <?php for ( $y = $year_now + 1; $y >= $year_now - 8; $y-- ) : ?>
                <option value="<?php echo esc_attr( (string) $y ); ?>" <?php selected( $year, $y ); ?>><?php echo esc_html( (string) $y ); ?></option>
            <?php endfor; ?>
        </select>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gfoss-export' ) ); ?>"><?php esc_html_e( 'Esporta quote', 'gfoss-members' ); ?></a>
    </form>

    <p style="display:flex;gap:2rem;font-size:.95rem">
        <span><strong><?php echo esc_html( (string) $paid ); ?></strong> / <?php echo esc_html( (string) $tot ); ?> <?php esc_html_e( 'soci in regola', 'gfoss-members' ); ?></span>
        <span><?php esc_html_e( 'Incassato', 'gfoss-members' ); ?>: <strong><?php echo esc_html( number_format_i18n( $incasso, 2 ) ); ?> €</strong></span>
    </p>

    <table class="widefat striped">
        <thead>
        <tr>
            <th>#</th>
            <th><?php esc_html_e( 'Socio', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Stato', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Importo', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Pagamento', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Azione', 'gfoss-members' ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ( $users as $u ) :
            $row    = $quote[ $u->ID ] ?? null;
            $status = Quote::status_for( $u->ID, $year );
            $chip = match ( $status ) {
                'paid'     => [ 'IN REGOLA', '#5DA34D', '#E5F2DF' ],
                'expiring' => [ 'IN SCADENZA', '#B26A00', '#FFEFD6' ],
                'pending'  => [ 'DA INCASSARE', '#B26A00', '#FFEFD6' ],
                'expired'  => [ 'SCADUTA', '#C0392B', '#FCE3DF' ],
                default    => [ 'N.D.', '#4A5C6A', '#E2E8EC' ],
            };
            $is_paid = $row && $row['stato'] === 'paid';
            ?>
            <tr>
                <td><?php echo esc_html( (string) get_user_meta( $u->ID, 'gf_numero_socio', true ) ); ?></td>
                <td>
                    <strong><?php echo esc_html( $u->display_name ); ?></strong><br>
                    <small><a href="mailto:<?php echo esc_attr( $u->user_email ); ?>"><?php echo esc_html( $u->user_email ); ?></a></small>
                </td>
                <td><span style="display:inline-block;padding:.15rem .55rem;border-radius:999px;background:<?php echo esc_attr( $chip[2] ); ?>;color:<?php echo esc_attr( $chip[1] ); ?>;font-weight:600;font-size:.75rem"><?php echo esc_html( $chip[0] ); ?></span></td>
                <td><?php echo $row ? esc_html( number_format_i18n( (float) $row['importo'], 2 ) ) . ' €' : '—'; ?></td>
                <td>
                    <?php if ( $is_paid ) : ?>
                        ✓ <?php echo esc_html( $metodi[ $row['metodo'] ] ?? $row['metodo'] ); ?>
                        <?php if ( ! empty( $row['data_pagamento'] ) ) : ?>
                            <br><small class="description"><?php echo esc_html( mysql2date( 'd/m/Y', $row['data_pagamento'] ) ); ?>
                            <?php if ( ! empty( $row['transaction_ref'] ) ) : ?>· <code><?php echo esc_html( $row['transaction_ref'] ); ?></code><?php endif; ?></small>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="description">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" style="display:flex;gap:.35rem;align-items:center;flex-wrap:wrap">
                        <?php wp_nonce_field( 'gfoss_quote_pay_' . $u->ID . '_' . $year ); ?>
                        <input type="hidden" name="_action" value="mark_paid">
                        <input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>">
                        <input type="hidden" name="anno" value="<?php echo (int) $year; ?>">
                        <select name="metodo" aria-label="<?php esc_attr_e( 'Metodo', 'gfoss-members' ); ?>">
                            <?php foreach ( $metodi as $k => $label ) : ?>
                                <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $is_paid ? $row['metodo'] : 'bonifico', $k ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="importo" value="<?php echo esc_attr( $is_paid ? number_format( (float) $row['importo'], 2, '.', '' ) : number_format( $default, 2, '.', '' ) ); ?>" size="5" aria-label="<?php esc_attr_e( 'Importo', 'gfoss-members' ); ?>">
                        <input type="text" name="ref" placeholder="<?php esc_attr_e( 'rif. (opz.)', 'gfoss-members' ); ?>" size="8">
                        <button type="submit" class="button button-small"><?php echo $is_paid ? esc_html__( 'Aggiorna', 'gfoss-members' ) : esc_html__( 'Segna pagata', 'gfoss-members' ); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ( ! $users ) : ?>
            <tr><td colspan="6"><?php esc_html_e( 'Nessun socio.', 'gfoss-members' ); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <p class="description" style="margin-top:1rem">
        <?php esc_html_e( 'I pagamenti PayPal vengono registrati automaticamente. Qui si registrano i bonifici e i contanti, e si correggono gli importi (es. quote ridotte/onorarie).', 'gfoss-members' ); ?>
    </p>
</div>
