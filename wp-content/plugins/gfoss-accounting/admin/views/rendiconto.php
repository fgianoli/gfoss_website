<?php
namespace GFOSS_Accounting;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Rendiconto annuale entrate/uscite per categoria + sezione 5×1000. */

$year     = isset( $_GET['anno'] ) ? (int) $_GET['anno'] : (int) gmdate( 'Y' );
$year_now = (int) gmdate( 'Y' );
$t        = Movement::totals_year( $year );

// Mappa slug => label.
$labels = [];
foreach ( Movement::categories() as $c ) { $labels[ $c['slug'] ] = $c['label']; }
$lbl = static fn( $slug ) => $labels[ $slug ] ?? $slug;
$eur = static fn( $n ) => number_format_i18n( (float) $n, 2 ) . ' €';

// 5×1000.
$ricevuto_5 = (float) ( $t['entrate']['cinque_per_mille'] ?? 0 );
$spese_5    = Movement::spese_5x1000( $year );
$speso_5    = array_sum( array_map( static fn( $r ) => (float) $r['importo'], $spese_5 ) );
?>
<div class="wrap">
    <h1>Rendiconto <?php echo esc_html( (string) $year ); ?></h1>

    <form method="get" style="margin:1rem 0">
        <input type="hidden" name="page" value="gfoss-rendiconto">
        <label><strong>Anno</strong>
            <select name="anno" onchange="this.form.submit()">
                <?php for ( $y = $year_now + 1; $y >= $year_now - 8; $y-- ) : ?>
                    <option value="<?php echo esc_attr( (string) $y ); ?>" <?php selected( $year, $y ); ?>><?php echo esc_html( (string) $y ); ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <button type="button" class="button" onclick="window.print()">Stampa</button>
    </form>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:900px">
        <div>
            <h2>Entrate</h2>
            <table class="widefat striped">
                <tbody>
                <?php if ( ! $t['entrate'] ) : ?>
                    <tr><td colspan="2">Nessuna entrata.</td></tr>
                <?php else : foreach ( $t['entrate'] as $slug => $imp ) : ?>
                    <tr><td><?php echo esc_html( $lbl( $slug ) ); ?></td><td style="text-align:right"><?php echo esc_html( $eur( $imp ) ); ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
                <tfoot><tr><th>Totale entrate</th><th style="text-align:right"><?php echo esc_html( $eur( $t['tot_entrate'] ) ); ?></th></tr></tfoot>
            </table>
        </div>
        <div>
            <h2>Uscite</h2>
            <table class="widefat striped">
                <tbody>
                <?php if ( ! $t['uscite'] ) : ?>
                    <tr><td colspan="2">Nessuna uscita.</td></tr>
                <?php else : foreach ( $t['uscite'] as $slug => $imp ) : ?>
                    <tr><td><?php echo esc_html( $lbl( $slug ) ); ?></td><td style="text-align:right"><?php echo esc_html( $eur( $imp ) ); ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
                <tfoot><tr><th>Totale uscite</th><th style="text-align:right"><?php echo esc_html( $eur( $t['tot_uscite'] ) ); ?></th></tr></tfoot>
            </table>
        </div>
    </div>

    <p style="font-size:1.2rem;margin-top:1rem">
        <strong>Saldo dell'esercizio <?php echo esc_html( (string) $year ); ?>:</strong>
        <span style="color:<?php echo $t['saldo'] >= 0 ? '#5DA34D' : '#C0392B'; ?>;font-weight:700"><?php echo esc_html( $eur( $t['saldo'] ) ); ?></span>
    </p>

    <hr style="margin:1.5rem 0">

    <h2>Rendiconto 5×1000 <?php echo esc_html( (string) $year ); ?></h2>
    <p>
        Ricevuto: <strong><?php echo esc_html( $eur( $ricevuto_5 ) ); ?></strong> ·
        Speso (movimenti contrassegnati «5×1000»): <strong><?php echo esc_html( $eur( $speso_5 ) ); ?></strong> ·
        Residuo: <strong><?php echo esc_html( $eur( $ricevuto_5 - $speso_5 ) ); ?></strong>
    </p>
    <table class="widefat striped" style="max-width:900px">
        <thead><tr><th>Data</th><th>Descrizione</th><th>Categoria</th><th style="text-align:right">Importo</th></tr></thead>
        <tbody>
        <?php if ( ! $spese_5 ) : ?>
            <tr><td colspan="4">Nessuna spesa contrassegnata come finanziata dal 5×1000. <span class="description">(spunta «5×1000» sui movimenti di uscita pertinenti)</span></td></tr>
        <?php else : foreach ( $spese_5 as $r ) : ?>
            <tr>
                <td><?php echo esc_html( mysql2date( 'd/m/Y', $r['data'] ) ); ?></td>
                <td><?php echo esc_html( $r['descrizione'] ); ?></td>
                <td><?php echo esc_html( $lbl( $r['categoria_slug'] ) ); ?></td>
                <td style="text-align:right"><?php echo esc_html( $eur( $r['importo'] ) ); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <p class="description">Il rendiconto del 5×1000 va trasmesso al Ministero per importi ≥ 20.000 € e comunque pubblicato sul sito (sezione Trasparenza).</p>
</div>
