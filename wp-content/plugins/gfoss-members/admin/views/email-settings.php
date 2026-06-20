<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Impostazioni email: aspetto (colore, footer) + testi dei singoli template. */

$cfg  = Email::settings();
$tpls = Email::templates();
$msg  = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );

$eff = static function ( $key, $field ) use ( $cfg, $tpls ) {
    $saved = (string) ( $cfg['tpl'][ $key ][ $field ] ?? '' );
    return $saved !== '' ? $saved : (string) ( $tpls[ $key ][ $field ] ?? '' );
};
?>
<div class="wrap">
    <h1>Email — aspetto e testi</h1>

    <?php if ( $msg === 'saved' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Impostazioni email salvate.</p></div>
    <?php endif; ?>

    <p class="description" style="max-width:780px">
        Qui personalizzi le email automatiche del sito (conferma iscrizione, benvenuto, promemoria quota…).
        Il <strong>logo</strong> nell'intestazione è quello del sito. Svuotando un campo si torna al testo predefinito.
        Nei testi puoi usare i <strong>segnaposto</strong> indicati sotto ogni email (es. <code>{nome}</code>), HTML consentito.
    </p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'gfoss_email_settings' ); ?>
        <input type="hidden" name="action" value="gfoss_email_settings_save">

        <h2>Aspetto</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="brand_color">Colore intestazione</label></th>
                <td><input type="text" name="brand_color" id="brand_color" value="<?php echo esc_attr( (string) ( $cfg['brand_color'] ?? '' ) ); ?>" placeholder="#1A6FA0" class="regular-text" style="max-width:140px">
                    <span class="description">Esadecimale, es. <code>#1A6FA0</code>. Vuoto = colore GFOSS.</span></td>
            </tr>
            <tr>
                <th scope="row"><label for="footer">Footer</label></th>
                <td><input type="text" name="footer" id="footer" value="<?php echo esc_attr( (string) ( $cfg['footer'] ?? '' ) ); ?>" class="regular-text" style="width:100%;max-width:560px"
                    placeholder="Associazione Italiana per l'Informazione Geografica Libera APS · Padova"></td>
            </tr>
        </table>

        <h2>Testi delle email</h2>
        <?php foreach ( $tpls as $key => $t ) : ?>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;margin:0 0 16px;max-width:900px">
                <h3 style="margin-top:0"><?php echo esc_html( $t['label'] ); ?></h3>
                <p>
                    <label><strong>Oggetto</strong><br>
                    <input type="text" name="tpl[<?php echo esc_attr( $key ); ?>][subject]" value="<?php echo esc_attr( $eff( $key, 'subject' ) ); ?>" class="large-text"></label>
                </p>
                <p>
                    <label><strong>Corpo (HTML)</strong><br>
                    <textarea name="tpl[<?php echo esc_attr( $key ); ?>][body]" rows="8" class="large-text" style="font-family:monospace;font-size:12px"><?php echo esc_textarea( $eff( $key, 'body' ) ); ?></textarea></label>
                </p>
                <p class="description">Segnaposto disponibili:
                    <?php foreach ( $t['placeholders'] as $ph ) : ?><code>{<?php echo esc_html( $ph ); ?>}</code> <?php endforeach; ?>
                </p>
            </div>
        <?php endforeach; ?>

        <p class="submit"><button type="submit" class="button button-primary">Salva impostazioni email</button></p>
    </form>
</div>
