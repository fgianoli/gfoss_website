<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }
$year = (int) gmdate( 'Y' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Esporta registri', 'gfoss-members' ); ?></h1>
    <p class="description"><?php esc_html_e( "Tutti gli export sono CSV UTF-8 con BOM (Excel li apre nativamente). Adempimenti art. 18 Statuto e D.Lgs. 117/2017.", 'gfoss-members' ); ?></p>

    <table class="form-table" style="max-width:760px">
        <tbody>
            <tr>
                <th><label for="tipo">Tipo di export</label></th>
                <td>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                        <input type="hidden" name="action" value="gfoss_export">
                        <?php wp_nonce_field( 'gfoss_export' ); ?>
                        <label>
                            <span style="display:block;font-weight:500;margin-bottom:.25rem">Tipo</span>
                            <select name="tipo" id="tipo">
                                <option value="registro_soci">Libro soci (con stato quota)</option>
                                <option value="registro_volontari">Registro volontari (art. 18)</option>
                                <option value="quote_anno">Storico quote di un anno</option>
                            </select>
                        </label>
                        <label>
                            <span style="display:block;font-weight:500;margin-bottom:.25rem">Anno</span>
                            <select name="anno">
                                <?php for ( $y = $year + 1; $y >= 2007; $y-- ) : ?>
                                    <option value="<?php echo $y; ?>" <?php selected( $y, $year ); ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <button type="submit" class="button button-primary">⬇ Esporta CSV</button>
                    </form>
                </td>
            </tr>
        </tbody>
    </table>

    <h2 style="margin-top:2rem"><?php esc_html_e( 'Note', 'gfoss-members' ); ?></h2>
    <ul style="list-style:disc;padding-left:1.4rem">
        <li>Il <strong>libro soci</strong> include CF, residenza, stato quota dell'anno selezionato — è il documento da conservare ai sensi dell'art. 18 dello Statuto.</li>
        <li>Il <strong>registro volontari</strong> filtra solo chi ha dichiarato volontariato (art. 8) — necessario per la copertura assicurativa (art. 26).</li>
        <li>Lo <strong>storico quote</strong> elenca tutti i pagamenti registrati nell'anno (utile al commercialista).</li>
    </ul>
</div>
