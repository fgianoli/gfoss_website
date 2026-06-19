<?php
namespace GFOSS_Accounting;
if ( ! defined( 'ABSPATH' ) ) { exit; }

$id   = (int) ( $_GET['id'] ?? 0 );
$mov  = $id ? Movement::get( $id ) : [
    'data' => gmdate( 'Y-m-d' ), 'tipo' => 'entrata', 'categoria_slug' => '',
    'importo' => 0, 'descrizione' => '', 'metodo' => '', 'note' => '',
    'socio_id' => null, 'quota_id' => null, 'documento_url' => '',
];
$cats = Movement::categories();
?>
<div class="wrap">
    <h1><?php echo $id ? 'Modifica movimento' : 'Aggiungi movimento'; ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="gfoss_movement_save">
        <?php wp_nonce_field( 'gfoss_movement_save' ); ?>
        <?php if ( $id ) : ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="data">Data</label></th>
                <td><input type="date" name="data" id="data" value="<?php echo esc_attr( $mov['data'] ); ?>" required></td>
            </tr>
            <tr>
                <th>Tipo</th>
                <td>
                    <label><input type="radio" name="tipo" value="entrata" <?php checked( $mov['tipo'], 'entrata' ); ?>> Entrata</label> &nbsp;
                    <label><input type="radio" name="tipo" value="uscita"  <?php checked( $mov['tipo'], 'uscita' ); ?>>  Uscita</label>
                </td>
            </tr>
            <tr>
                <th><label for="categoria_slug">Categoria</label></th>
                <td>
                    <select name="categoria_slug" id="categoria_slug" required>
                        <option value="">— scegli —</option>
                        <?php foreach ( $cats as $c ) : ?>
                            <option value="<?php echo esc_attr( $c['slug'] ); ?>" data-tipo="<?php echo esc_attr( $c['tipo'] ); ?>"
                                <?php selected( $c['slug'], $mov['categoria_slug'] ); ?>>
                                [<?php echo esc_html( $c['tipo'] ); ?>] <?php echo esc_html( $c['label'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="importo">Importo (€)</label></th>
                <td><input type="number" name="importo" id="importo" step="0.01" min="0" required value="<?php echo esc_attr( (string) $mov['importo'] ); ?>"></td>
            </tr>
            <tr>
                <th><label for="descrizione">Descrizione</label></th>
                <td><input type="text" name="descrizione" id="descrizione" class="regular-text" value="<?php echo esc_attr( $mov['descrizione'] ); ?>" required></td>
            </tr>
            <tr>
                <th><label for="metodo">Metodo</label></th>
                <td>
                    <input type="text" name="metodo" id="metodo" class="regular-text" placeholder="bonifico, paypal, contanti, carta…" value="<?php echo esc_attr( (string) $mov['metodo'] ); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="documento_url">URL documento (ricevuta, fattura)</label></th>
                <td><input type="url" name="documento_url" id="documento_url" class="regular-text" value="<?php echo esc_attr( (string) $mov['documento_url'] ); ?>"></td>
            </tr>
            <tr>
                <th><label for="note">Note</label></th>
                <td><textarea name="note" id="note" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $mov['note'] ?? '' ) ); ?></textarea></td>
            </tr>
        </table>

        <p class="submit">
            <button class="button button-primary"><?php echo $id ? 'Salva modifiche' : 'Aggiungi movimento'; ?></button>
            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gfoss-movimenti' ) ); ?>">Annulla</a>
        </p>
    </form>
</div>
