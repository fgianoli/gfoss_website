<?php
namespace GFOSS_Accounting;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Riconciliazione: quote pagate (PayPal/bonifico/contanti) ↔ movimenti contabili. */

$year     = isset( $_GET['anno'] ) ? (int) $_GET['anno'] : (int) gmdate( 'Y' );
$year_now = (int) gmdate( 'Y' );
$msg      = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );

if ( ! class_exists( '\\GFOSS_Members\\Quote' ) ) {
    echo '<div class="wrap"><h1>Riconciliazione</h1><p>Plugin soci non attivo.</p></div>'; return;
}

$quote   = \GFOSS_Members\Quote::all_for_year( $year );
$paid    = array_filter( $quote, static fn( $q ) => ( $q['stato'] ?? '' ) === 'paid' );
$missing = 0;
foreach ( $paid as $q ) { if ( ! Movement::exists_for_quota( (int) $q['id'] ) ) { $missing++; } }
$eur = static fn( $n ) => number_format_i18n( (float) $n, 2 ) . ' €';
?>
<div class="wrap">
    <h1>Riconciliazione quote ↔ contabilità</h1>

    <?php if ( $msg === 'reconciled' ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php printf( esc_html__( 'Creati %d movimenti mancanti.', 'gfoss-accounting' ), (int) ( $_GET['n'] ?? 0 ) ); ?></p></div>
    <?php endif; ?>

    <form method="get" style="margin:1rem 0">
        <input type="hidden" name="page" value="gfoss-riconciliazione">
        <label><strong>Anno</strong>
            <select name="anno" onchange="this.form.submit()">
                <?php for ( $y = $year_now + 1; $y >= $year_now - 8; $y-- ) : ?>
                    <option value="<?php echo esc_attr( (string) $y ); ?>" <?php selected( $year, $y ); ?>><?php echo esc_html( (string) $y ); ?></option>
                <?php endfor; ?>
            </select>
        </label>
    </form>

    <p>
        Quote pagate nel <?php echo esc_html( (string) $year ); ?>: <strong><?php echo (int) count( $paid ); ?></strong> ·
        Movimenti mancanti: <strong style="color:<?php echo $missing ? '#C0392B' : '#5DA34D'; ?>"><?php echo (int) $missing; ?></strong>
    </p>

    <?php if ( $missing && current_user_can( \GFOSS_Members\Roles::CAP_MANAGE_ACCOUNTING ) ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1rem">
            <?php wp_nonce_field( 'gfoss_acc_reconcile' ); ?>
            <input type="hidden" name="action" value="gfoss_acc_reconcile">
            <input type="hidden" name="anno" value="<?php echo esc_attr( (string) $year ); ?>">
            <button type="submit" class="button button-primary" onclick="return confirm('Creare i <?php echo (int) $missing; ?> movimenti di entrata mancanti?')">Crea i movimenti mancanti</button>
            <span class="description">Genera l'entrata in contabilità per ogni quota pagata che non ha ancora un movimento collegato.</span>
        </form>
    <?php endif; ?>

    <table class="widefat striped">
        <thead><tr><th>Socio</th><th>Importo</th><th>Metodo</th><th>Data pagamento</th><th>Rif.</th><th>Movimento</th></tr></thead>
        <tbody>
        <?php if ( ! $paid ) : ?>
            <tr><td colspan="6">Nessuna quota pagata per l'anno selezionato.</td></tr>
        <?php else : foreach ( $paid as $uid => $q ) :
            $u = get_userdata( (int) $uid );
            $ok = Movement::exists_for_quota( (int) $q['id'] );
            ?>
            <tr>
                <td><?php echo esc_html( $u ? $u->display_name : ( 'Socio #' . (int) $uid ) ); ?></td>
                <td><?php echo esc_html( $eur( $q['importo'] ) ); ?></td>
                <td><?php echo esc_html( (string) ( $q['metodo'] ?? '' ) ); ?></td>
                <td><?php echo esc_html( ! empty( $q['data_pagamento'] ) ? mysql2date( 'd/m/Y', $q['data_pagamento'] ) : '—' ); ?></td>
                <td><?php echo esc_html( (string) ( $q['transaction_ref'] ?? '' ) ); ?></td>
                <td>
                    <?php if ( $ok ) : ?>
                        <span style="color:#5DA34D">✓ collegato</span>
                    <?php else : ?>
                        <span style="color:#C0392B">✗ mancante</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
