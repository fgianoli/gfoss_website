<?php
namespace GFOSS_Accounting;
if ( ! defined( 'ABSPATH' ) ) { exit; }

$year      = (int) ( $_GET['anno']     ?? gmdate( 'Y' ) );
$tipo      = sanitize_key( (string) ( $_GET['tipo']     ?? '' ) );
$categoria = sanitize_key( (string) ( $_GET['categoria']?? '' ) );
$page      = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$result    = Movement::paginated( compact( 'year', 'tipo', 'categoria' ) + [ 'anno' => $year ], $page, 50 );
$cats      = wp_list_pluck( Movement::categories(), 'label', 'slug' );
$msg       = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
?>
<div class="wrap">
    <h1>Movimenti — anno <?php echo (int) $year; ?>
        <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=gfoss-movimento-edit' ) ); ?>">Aggiungi</a>
    </h1>
    <?php if ( $msg === 'saved' ) : ?><div class="notice notice-success is-dismissible"><p>Movimento salvato.</p></div><?php endif; ?>
    <?php if ( $msg === 'deleted' ) : ?><div class="notice notice-warning is-dismissible"><p>Movimento eliminato.</p></div><?php endif; ?>

    <form method="get" style="margin:1rem 0">
        <input type="hidden" name="page" value="gfoss-movimenti">
        <select name="anno">
            <?php for ( $y = (int) gmdate( 'Y' ) + 1; $y >= 2007; $y-- ) : ?>
                <option <?php selected( $y, $year ); ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <select name="tipo">
            <option value="">Entrate + uscite</option>
            <option value="entrata" <?php selected( $tipo, 'entrata' ); ?>>Solo entrate</option>
            <option value="uscita"  <?php selected( $tipo, 'uscita' ); ?>>Solo uscite</option>
        </select>
        <select name="categoria">
            <option value="">Tutte le categorie</option>
            <?php foreach ( $cats as $slug => $label ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $categoria, $slug ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button">Filtra</button>
    </form>

    <table class="widefat striped">
        <thead><tr><th>Data</th><th>Tipo</th><th>Categoria</th><th>Descrizione</th><th style="text-align:right">Importo</th><th>Metodo</th><th></th></tr></thead>
        <tbody>
            <?php if ( ! $result['rows'] ) : ?><tr><td colspan="7">Nessun movimento.</td></tr><?php endif; ?>
            <?php foreach ( $result['rows'] as $r ) :
                $delete_url = wp_nonce_url(
                    admin_url( 'admin-post.php?action=gfoss_movement_delete&id=' . (int) $r['id'] ),
                    'gfoss_movement_delete_' . (int) $r['id']
                ); ?>
                <tr>
                    <td><?php echo esc_html( $r['data'] ); ?></td>
                    <td><?php echo $r['tipo'] === 'entrata'
                        ? '<span style="color:#5DA34D">↗ entrata</span>'
                        : '<span style="color:#C0392B">↘ uscita</span>'; ?></td>
                    <td><?php echo esc_html( $cats[ $r['categoria_slug'] ] ?? $r['categoria_slug'] ); ?></td>
                    <td><?php echo esc_html( $r['descrizione'] ); ?></td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums"><?php echo number_format_i18n( (float) $r['importo'], 2 ); ?> €</td>
                    <td><?php echo esc_html( (string) $r['metodo'] ); ?></td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=gfoss-movimento-edit&id=' . (int) $r['id'] ) ); ?>">Modifica</a>
                        <a class="button button-small" style="color:#C0392B" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('Eliminare?')">Elimina</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
