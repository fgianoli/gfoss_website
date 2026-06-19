<?php
namespace GFOSS_Accounting;
if ( ! defined( 'ABSPATH' ) ) { exit; }
$year = (int) gmdate( 'Y' );
?>
<div class="wrap">
    <h1>Esporta CSV per il commercialista</h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1rem">
        <input type="hidden" name="action" value="gfoss_acc_export">
        <?php wp_nonce_field( 'gfoss_acc_export' ); ?>
        <select name="anno">
            <?php for ( $y = $year + 1; $y >= 2007; $y-- ) : ?>
                <option <?php selected( $y, $year ); ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <button class="button button-primary">⬇ Scarica</button>
    </form>
    <p class="description" style="margin-top:1rem">CSV separato da <code>;</code>, codifica UTF-8 con BOM. Apri direttamente in Excel o invia al commercialista via email.</p>
</div>
