<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Scheda dettaglio socio: anagrafica, stato quota e azioni (segna pagata/non
 * pagata, numero socio). Inclusa da soci.php quando è presente ?id.
 */

$uid = (int) ( $_GET['id'] ?? 0 );
$u   = get_userdata( $uid );
if ( ! $u ) { echo '<div class="wrap"><h1>Socio non trovato</h1></div>'; return; }

// --- Azioni POST -----------------------------------------------------------
if ( ! empty( $_POST['_action'] ) && current_user_can( Roles::CAP_MANAGE_SOCI ) ) {
    check_admin_referer( 'gfoss_socio_' . $uid );
    $action = sanitize_key( (string) $_POST['_action'] );

    if ( $action === 'save_meta' ) {
        $num = sanitize_text_field( wp_unslash( $_POST['gf_numero_socio'] ?? '' ) );
        if ( $num === '' ) {
            $num = Candidatura::next_numero_socio(); // auto se lasciato vuoto
        } elseif ( Candidatura::numero_in_use( $num, $uid ) ) {
            wp_safe_redirect( add_query_arg( 'msg', 'dup_numero', wp_get_referer() ) ); exit;
        }
        update_user_meta( $uid, 'gf_numero_socio', $num );
        update_user_meta( $uid, 'gf_volontario', empty( $_POST['gf_volontario'] ) ? '0' : '1' );
        wp_safe_redirect( add_query_arg( 'msg', 'saved', wp_get_referer() ) ); exit;
    }
    if ( $action === 'mark_paid' ) {
        $anno   = (int) ( $_POST['anno'] ?? gmdate( 'Y' ) );
        $amount = (float) str_replace( ',', '.', (string) ( $_POST['importo'] ?? '' ) );
        if ( $amount <= 0 ) { $amount = Quote::default_amount(); }
        $metodo = sanitize_key( (string) ( $_POST['metodo'] ?? 'bonifico' ) );
        $ref    = sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) ) ?: null;
        Quote::mark_paid( $uid, $anno, $metodo, $ref, 'Registrato a mano (scheda socio)', $amount );
        wp_safe_redirect( add_query_arg( 'msg', 'paid', wp_get_referer() ) ); exit;
    }
    if ( $action === 'mark_unpaid' ) {
        Quote::mark_unpaid( $uid, (int) ( $_POST['anno'] ?? gmdate( 'Y' ) ) );
        wp_safe_redirect( add_query_arg( 'msg', 'unpaid', wp_get_referer() ) ); exit;
    }
    if ( $action === 'archive' ) {
        Archivio::archive( $uid );
        wp_safe_redirect( add_query_arg( 'msg', 'archived', wp_get_referer() ) ); exit;
    }
    if ( $action === 'reactivate' ) {
        Archivio::reactivate( $uid );
        wp_safe_redirect( add_query_arg( 'msg', 'reactivated', wp_get_referer() ) ); exit;
    }
    if ( $action === 'delete_all' && current_user_can( 'delete_users' ) ) {
        Archivio::delete_with_data( $uid );
        wp_safe_redirect( add_query_arg( 'msg', 'deleted', admin_url( 'admin.php?page=gfoss-soci' ) ) ); exit;
    }
}

$msg     = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
$year    = (int) gmdate( 'Y' );
$status  = Quote::status_for( $uid, $year );
$storico = Quote::for_user( $uid );
$numero  = (string) get_user_meta( $uid, 'gf_numero_socio', true );
$metodi  = [ 'bonifico' => 'Bonifico', 'contanti' => 'Contanti', 'paypal' => 'PayPal', 'altro' => 'Altro' ];
$chip = match ( $status ) {
    'paid'     => [ 'IN REGOLA', '#5DA34D', '#E5F2DF' ],
    'expiring' => [ 'IN SCADENZA', '#B26A00', '#FFEFD6' ],
    'pending'  => [ 'DA INCASSARE', '#B26A00', '#FFEFD6' ],
    'expired'  => [ 'SCADUTA', '#C0392B', '#FCE3DF' ],
    default    => [ 'N.D.', '#4A5C6A', '#E2E8EC' ],
};
$card = 'background:#fff;padding:20px;border:1px solid #e2e8ec;border-radius:8px';
?>
<div class="wrap">
    <h1>
        <?php echo esc_html( $u->display_name ); ?>
        <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=gfoss-soci' ) ); ?>">← Tutti i soci</a>
        <a class="page-title-action" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $uid ) ); ?>">Modifica profilo completo</a>
    </h1>

    <?php
    $notes = [ 'saved' => 'Dati salvati.', 'paid' => 'Quota segnata come pagata.', 'unpaid' => 'Quota segnata come non pagata.', 'archived' => 'Socio archiviato.', 'reactivated' => 'Socio riabilitato.' ];
    if ( isset( $notes[ $msg ] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notes[ $msg ] ) . '</p></div>';
    }
    if ( $msg === 'dup_numero' ) {
        echo '<div class="notice notice-error is-dismissible"><p>Numero socio già assegnato a un altro socio: scegline uno diverso, oppure lascia il campo vuoto per generarlo automaticamente.</p></div>';
    }
    ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:1rem">

        <!-- DATI SOCIO -->
        <div class="card" style="<?php echo $card; ?>">
            <h2 style="margin-top:0">Dati socio</h2>
            <table class="widefat striped"><tbody>
                <tr><th>Email</th><td><a href="mailto:<?php echo esc_attr( $u->user_email ); ?>"><?php echo esc_html( $u->user_email ); ?></a></td></tr>
                <tr><th>Ruoli</th><td><?php echo esc_html( implode( ', ', $u->roles ) ); ?></td></tr>
                <tr><th>Codice fiscale</th><td><code><?php echo esc_html( (string) get_user_meta( $uid, 'gf_codice_fiscale', true ) ); ?></code></td></tr>
                <tr><th>Città</th><td><?php echo esc_html( trim( get_user_meta( $uid, 'gf_citta', true ) . ' ' . ( get_user_meta( $uid, 'gf_provincia', true ) ? '(' . get_user_meta( $uid, 'gf_provincia', true ) . ')' : '' ) ) ); ?></td></tr>
                <tr><th>Iscritto dal</th><td><?php echo esc_html( (string) get_user_meta( $uid, 'gf_data_ammissione', true ) ?: '—' ); ?></td></tr>
            </tbody></table>

            <form method="post" style="margin-top:14px">
                <?php wp_nonce_field( 'gfoss_socio_' . $uid ); ?>
                <input type="hidden" name="_action" value="save_meta">
                <p><label><strong>Numero socio</strong><br>
                    <input type="text" name="gf_numero_socio" value="<?php echo esc_attr( $numero ); ?>" placeholder="es. <?php echo esc_attr( $year ); ?>-00001"></label></p>
                <p><label><input type="checkbox" name="gf_volontario" value="1" <?php checked( get_user_meta( $uid, 'gf_volontario', true ), '1' ); ?>> Iscritto al registro volontari</label></p>
                <button type="submit" class="button">Salva dati</button>
            </form>

            <p style="margin-top:14px">
                <a class="button" href="<?php echo esc_url( Tessera::download_url( $uid ) ); ?>">⬇ Tessera</a>
                <?php if ( $status === 'paid' || $status === 'expiring' ) : ?>
                    <a class="button" href="<?php echo esc_url( Ricevuta::download_url( $uid, $year ) ); ?>">⬇ Ricevuta <?php echo esc_html( (string) $year ); ?></a>
                <?php endif; ?>
            </p>
        </div>

        <!-- QUOTA -->
        <div class="card" style="<?php echo $card; ?>">
            <h2 style="margin-top:0">Quota <?php echo esc_html( (string) $year ); ?>
                <span style="display:inline-block;padding:.15rem .55rem;border-radius:999px;background:<?php echo esc_attr( $chip[2] ); ?>;color:<?php echo esc_attr( $chip[1] ); ?>;font-weight:600;font-size:.75rem;vertical-align:middle"><?php echo esc_html( $chip[0] ); ?></span>
            </h2>

            <form method="post" style="display:flex;gap:.4rem;align-items:end;flex-wrap:wrap">
                <?php wp_nonce_field( 'gfoss_socio_' . $uid ); ?>
                <input type="hidden" name="_action" value="mark_paid">
                <label>Anno<br><input type="number" name="anno" value="<?php echo esc_attr( (string) $year ); ?>" style="width:80px"></label>
                <label>Metodo<br><select name="metodo"><?php foreach ( $metodi as $k => $v ) echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>'; ?></select></label>
                <label>Importo<br><input type="text" name="importo" value="<?php echo esc_attr( number_format( Quote::default_amount(), 2, '.', '' ) ); ?>" style="width:70px"></label>
                <label>Rif.<br><input type="text" name="ref" style="width:110px"></label>
                <button type="submit" class="button button-primary">Segna pagata</button>
            </form>
            <form method="post" style="margin-top:8px">
                <?php wp_nonce_field( 'gfoss_socio_' . $uid ); ?>
                <input type="hidden" name="_action" value="mark_unpaid">
                <input type="hidden" name="anno" value="<?php echo esc_attr( (string) $year ); ?>">
                <button type="submit" class="button" onclick="return confirm('Segnare la quota <?php echo esc_attr( (string) $year ); ?> come NON pagata?')">Segna non pagata (<?php echo esc_html( (string) $year ); ?>)</button>
            </form>

            <h3>Storico</h3>
            <table class="widefat striped">
                <thead><tr><th>Anno</th><th>Importo</th><th>Metodo</th><th>Stato</th><th>Data</th></tr></thead>
                <tbody>
                <?php if ( ! $storico ) : ?>
                    <tr><td colspan="5">Nessun pagamento registrato.</td></tr>
                <?php else : foreach ( $storico as $q ) : ?>
                    <tr>
                        <td><?php echo esc_html( $q['anno'] ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( (float) $q['importo'], 2 ) ); ?> €</td>
                        <td><?php echo esc_html( $metodi[ $q['metodo'] ] ?? $q['metodo'] ); ?></td>
                        <td><?php echo $q['stato'] === 'paid' ? '✓ pagata' : esc_html( $q['stato'] ); ?></td>
                        <td><?php echo esc_html( $q['data_pagamento'] ? mysql2date( 'd/m/Y', $q['data_pagamento'] ) : '—' ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- STATO / ARCHIVIAZIONE -->
    <div class="card" style="<?php echo $card; ?>;margin-top:24px;max-width:760px">
        <h2 style="margin-top:0">Stato e archiviazione</h2>
        <?php if ( Archivio::is_archived( $uid ) ) : ?>
            <p>Socio <strong>archiviato</strong> il <?php echo esc_html( (string) get_user_meta( $uid, Archivio::META_DATE, true ) ); ?>.</p>
            <form method="post" style="display:inline">
                <?php wp_nonce_field( 'gfoss_socio_' . $uid ); ?>
                <input type="hidden" name="_action" value="reactivate">
                <button type="submit" class="button button-primary">Riabilita socio</button>
            </form>
        <?php else : ?>
            <?php if ( Archivio::is_lapsed( $uid ) ) : ?>
                <p class="description" style="color:#B26A00"><strong>Risulta decaduto</strong>: quota non rinnovata entro i termini (fine marzo dell'anno successivo all'ultima quota).</p>
            <?php endif; ?>
            <form method="post" style="display:inline">
                <?php wp_nonce_field( 'gfoss_socio_' . $uid ); ?>
                <input type="hidden" name="_action" value="archive">
                <button type="submit" class="button" onclick="return confirm('Archiviare questo socio? Uscirà dal registro attivo ma potrai riabilitarlo.')">Archivia socio</button>
            </form>
        <?php endif; ?>

        <?php if ( current_user_can( 'delete_users' ) ) : ?>
            <hr style="margin:18px 0">
            <p style="color:#C0392B"><strong>Zona pericolosa</strong> — elimina l'utente e <strong>tutti i suoi dati</strong> (anagrafica, quote, candidature). Operazione irreversibile (GDPR). Le eventuali news pubblicate vengono riassegnate a un amministratore.</p>
            <form method="post" style="display:inline">
                <?php wp_nonce_field( 'gfoss_socio_' . $uid ); ?>
                <input type="hidden" name="_action" value="delete_all">
                <button type="submit" class="button" style="border-color:#C0392B;color:#C0392B" onclick="return confirm('ELIMINARE definitivamente <?php echo esc_attr( $u->display_name ); ?> e tutti i suoi dati? Non si può annullare.')">Elimina definitivamente</button>
            </form>
        <?php endif; ?>
    </div>
</div>
