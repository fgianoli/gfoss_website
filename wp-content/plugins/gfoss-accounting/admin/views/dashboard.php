<?php
namespace GFOSS_Accounting;
if ( ! defined( 'ABSPATH' ) ) { exit; }

$year = (int) ( $_GET['anno'] ?? gmdate( 'Y' ) );
$t    = Movement::totals_year( $year );
$cats = wp_list_pluck( Movement::categories(), 'label', 'slug' );
?>
<div class="wrap">
    <h1>Contabilità — anno <?php echo (int) $year; ?>
        <form method="get" style="display:inline-block;margin-left:1rem">
            <input type="hidden" name="page" value="gfoss-contabilita">
            <select name="anno" onchange="this.form.submit()">
                <?php for ( $y = (int) gmdate( 'Y' ) + 1; $y >= 2007; $y-- ) : ?>
                    <option <?php selected( $y, $year ); ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </h1>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:1rem 0">
        <div style="background:#fff;padding:18px;border:1px solid #e2e8ec;border-radius:8px">
            <p style="margin:0;color:#4A5C6A;text-transform:uppercase;font-size:12px">Entrate</p>
            <p style="margin:.25rem 0 0;font-size:1.8rem;font-weight:700;color:#5DA34D">+<?php echo number_format_i18n( $t['tot_entrate'], 2 ); ?> €</p>
        </div>
        <div style="background:#fff;padding:18px;border:1px solid #e2e8ec;border-radius:8px">
            <p style="margin:0;color:#4A5C6A;text-transform:uppercase;font-size:12px">Uscite</p>
            <p style="margin:.25rem 0 0;font-size:1.8rem;font-weight:700;color:#C0392B">−<?php echo number_format_i18n( $t['tot_uscite'], 2 ); ?> €</p>
        </div>
        <div style="background:#fff;padding:18px;border:1px solid #e2e8ec;border-radius:8px">
            <p style="margin:0;color:#4A5C6A;text-transform:uppercase;font-size:12px">Saldo</p>
            <p style="margin:.25rem 0 0;font-size:1.8rem;font-weight:700;color:<?php echo $t['saldo'] >= 0 ? '#1A6FA0' : '#C0392B'; ?>"><?php echo ( $t['saldo'] >= 0 ? '+' : '' ) . number_format_i18n( $t['saldo'], 2 ); ?> €</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <div>
            <h2>Entrate per categoria</h2>
            <table class="widefat striped"><thead><tr><th>Categoria</th><th style="text-align:right">Totale</th></tr></thead><tbody>
                <?php if ( ! $t['entrate'] ) : ?><tr><td colspan="2">—</td></tr><?php endif; ?>
                <?php foreach ( $t['entrate'] as $slug => $imp ) : ?>
                    <tr><td><?php echo esc_html( $cats[ $slug ] ?? $slug ); ?></td>
                        <td style="text-align:right"><?php echo number_format_i18n( $imp, 2 ); ?> €</td></tr>
                <?php endforeach; ?>
            </tbody></table>
        </div>
        <div>
            <h2>Uscite per categoria</h2>
            <table class="widefat striped"><thead><tr><th>Categoria</th><th style="text-align:right">Totale</th></tr></thead><tbody>
                <?php if ( ! $t['uscite'] ) : ?><tr><td colspan="2">—</td></tr><?php endif; ?>
                <?php foreach ( $t['uscite'] as $slug => $imp ) : ?>
                    <tr><td><?php echo esc_html( $cats[ $slug ] ?? $slug ); ?></td>
                        <td style="text-align:right"><?php echo number_format_i18n( $imp, 2 ); ?> €</td></tr>
                <?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
</div>
