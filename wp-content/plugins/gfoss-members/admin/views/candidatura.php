<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

$id = (int) ( $_GET['id'] ?? 0 );
$cand = Candidatura::get( $id );
if ( ! $cand ) {
    echo '<div class="wrap"><h1>Candidatura non trovata</h1></div>'; return;
}

// Gestione azioni POST (approva / respingi).
if ( ! empty( $_POST['_action'] ) && current_user_can( Roles::CAP_REVIEW_CANDIDATURE ) ) {
    check_admin_referer( 'gfoss_cand_' . $id );
    $note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
    if ( $_POST['_action'] === 'approve' ) {
        Candidatura::approve( $id, get_current_user_id(), $note );
        wp_safe_redirect( add_query_arg( 'msg', 'approved', wp_get_referer() ) );
        exit;
    }
    if ( $_POST['_action'] === 'reject' ) {
        if ( $note === '' ) {
            wp_safe_redirect( add_query_arg( 'msg', 'reject_needs_note', wp_get_referer() ) );
            exit;
        }
        Candidatura::reject( $id, get_current_user_id(), $note );
        wp_safe_redirect( add_query_arg( 'msg', 'rejected', wp_get_referer() ) );
        exit;
    }
    if ( $_POST['_action'] === 'record_payment' ) {
        $amount = (float) str_replace( ',', '.', (string) ( $_POST['importo'] ?? '' ) );
        if ( $amount <= 0 ) { $amount = Quote::default_amount(); }
        $method = sanitize_key( (string) ( $_POST['metodo'] ?? 'bonifico' ) );
        $ref    = sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) ) ?: 'registrato a mano';
        Candidatura::record_payment( $id, $amount, $method, $ref );
        wp_safe_redirect( add_query_arg( 'msg', 'payment_recorded', wp_get_referer() ) );
        exit;
    }
}

$msg = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
$cand = Candidatura::get( $id ); // ricarica dopo eventuale aggiornamento
$can_review = current_user_can( Roles::CAP_REVIEW_CANDIDATURE );
?>
<div class="wrap">
    <h1>
        Candidatura #<?php echo (int) $cand['id']; ?> — <?php echo esc_html( $cand['nome'] . ' ' . $cand['cognome'] ); ?>
        <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=gfoss-candidature' ) ); ?>">← Tutte</a>
    </h1>

    <?php if ( $msg === 'approved' ) : ?><div class="notice notice-success is-dismissible"><p>Candidatura approvata. Se il pagamento è già registrato, l'utente è stato creato automaticamente.</p></div><?php endif; ?>
    <?php if ( $msg === 'rejected' ) : ?><div class="notice notice-warning is-dismissible"><p>Candidatura respinta. Email di notifica inviata al candidato.</p></div><?php endif; ?>
    <?php if ( $msg === 'reject_needs_note' ) : ?><div class="notice notice-error"><p>La motivazione è obbligatoria per respingere.</p></div><?php endif; ?>
    <?php if ( $msg === 'payment_recorded' ) : ?><div class="notice notice-success is-dismissible"><p>Pagamento registrato. Se la candidatura è approvata, l'utente socio è stato creato con la quota in regola.</p></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-top:1rem">
        <div class="card" style="background:#fff;padding:20px;border:1px solid #e2e8ec;border-radius:8px">
            <h2 style="margin-top:0">Dati anagrafici</h2>
            <table class="widefat striped">
                <tbody>
                    <tr><th>Email</th><td><?php echo esc_html( $cand['email'] ); ?></td></tr>
                    <tr><th>Codice fiscale</th><td><code><?php echo esc_html( (string) $cand['codice_fiscale'] ); ?></code></td></tr>
                    <tr><th>Nato/a il</th><td><?php echo esc_html( (string) $cand['data_nascita'] ); ?> a <?php echo esc_html( (string) $cand['comune_nascita'] ); ?></td></tr>
                    <tr><th>Residenza</th><td><?php echo esc_html( $cand['indirizzo'] . ', ' . $cand['cap'] . ' ' . $cand['citta'] . ' (' . $cand['provincia'] . ')' ); ?></td></tr>
                    <tr><th>Telefono</th><td><?php echo esc_html( (string) $cand['telefono'] ); ?></td></tr>
                    <tr><th>Professione</th><td><?php echo esc_html( (string) $cand['professione'] ); ?></td></tr>
                    <tr><th>Competenze</th><td><?php echo nl2br( esc_html( (string) $cand['competenze'] ) ); ?></td></tr>
                    <tr><th>Motivazione</th><td><?php echo nl2br( esc_html( (string) $cand['motivazione'] ) ); ?></td></tr>
                    <tr><th>Volontario</th><td><?php echo $cand['volontario'] ? '✓ Sì (registro volontari art. 18)' : 'No'; ?></td></tr>
                    <tr><th>Consensi</th><td>Statuto: <?php echo $cand['consenso_statuto'] ? '✓' : '✗'; ?> · Privacy: <?php echo $cand['consenso_privacy'] ? '✓' : '✗'; ?></td></tr>
                    <tr><th>IP / data invio</th><td><code><?php echo esc_html( (string) $cand['ip'] ); ?></code> · <?php echo esc_html( mysql2date( 'd/m/Y H:i', $cand['created_at'] ) ); ?></td></tr>
                </tbody>
            </table>
        </div>

        <div>
            <div class="card" style="background:#fff;padding:20px;border:1px solid #e2e8ec;border-radius:8px;margin-bottom:16px">
                <h2 style="margin-top:0">Stato</h2>
                <p><strong>Stato corrente:</strong> <code><?php echo esc_html( $cand['stato'] ); ?></code></p>
                <p><strong>Decisione CD:</strong> <?php echo $cand['cd_decision'] ? esc_html( $cand['cd_decision'] ) : '— in attesa —'; ?>
                    <?php if ( $cand['reviewed_at'] ) : ?>
                        <br><small>il <?php echo esc_html( mysql2date( 'd/m/Y H:i', $cand['reviewed_at'] ) ); ?>
                            <?php if ( $cand['reviewed_by'] ) : $u = get_userdata( (int) $cand['reviewed_by'] ); ?>
                                da <?php echo esc_html( $u ? $u->display_name : '#' . (int) $cand['reviewed_by'] ); ?>
                            <?php endif; ?>
                        </small>
                    <?php endif; ?>
                </p>
                <p><strong>Pagamento:</strong>
                    <?php if ( $cand['payment_status'] === 'paid' ) : ?>
                        ✓ <?php echo esc_html( number_format_i18n( (float) $cand['payment_amount'], 2 ) ); ?> € via <?php echo esc_html( (string) $cand['payment_method'] ); ?><br>
                        <small>txn: <code><?php echo esc_html( (string) $cand['payment_txn_ref'] ); ?></code> · <?php echo esc_html( mysql2date( 'd/m/Y H:i', $cand['payment_at'] ) ); ?></small>
                    <?php else : ?>
                        — non ancora ricevuto —
                    <?php endif; ?>
                </p>
                <?php if ( $cand['user_id'] ) : ?>
                    <p><strong>Utente WP:</strong> <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $cand['user_id'] ) ); ?>">#<?php echo (int) $cand['user_id']; ?></a></p>
                <?php endif; ?>
            </div>

            <?php if ( $can_review && ! in_array( $cand['stato'], [ Candidatura::STATO_EFFECTIVE, Candidatura::STATO_REJECTED ], true ) ) : ?>
                <div class="card" style="background:#fff;padding:20px;border:1px solid #e2e8ec;border-radius:8px">
                    <h2 style="margin-top:0">Delibera CD</h2>
                    <form method="post">
                        <?php wp_nonce_field( 'gfoss_cand_' . $id ); ?>
                        <p>
                            <label for="note"><strong>Note / motivazione</strong> <small>(obbligatoria per il rigetto)</small></label>
                            <textarea name="note" id="note" rows="4" class="large-text"></textarea>
                        </p>
                        <p style="display:flex;gap:8px">
                            <button type="submit" name="_action" value="approve" class="button button-primary">Approva</button>
                            <button type="submit" name="_action" value="reject" class="button" onclick="return confirm('Confermi il rigetto?')">Respingi</button>
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ( $can_review && $cand['payment_status'] !== 'paid' && ! in_array( $cand['stato'], [ Candidatura::STATO_REJECTED, Candidatura::STATO_WITHDRAWN ], true ) ) : ?>
                <div class="card" style="background:#fff;padding:20px;border:1px solid #e2e8ec;border-radius:8px;margin-top:16px">
                    <h2 style="margin-top:0">Registra pagamento ricevuto</h2>
                    <p class="description">Per bonifici o contanti già incassati. Registrandolo, se la candidatura è approvata l'utente socio viene creato con la quota in regola.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'gfoss_cand_' . $id ); ?>
                        <input type="hidden" name="_action" value="record_payment">
                        <p>
                            <label>Metodo
                                <select name="metodo">
                                    <option value="bonifico">Bonifico</option>
                                    <option value="contanti">Contanti</option>
                                    <option value="altro">Altro</option>
                                </select>
                            </label>
                            &nbsp; <label>Importo €
                                <input type="text" name="importo" value="<?php echo esc_attr( number_format( Quote::default_amount(), 2, '.', '' ) ); ?>" size="6">
                            </label>
                            &nbsp; <label>Rif. <input type="text" name="ref" placeholder="es. CRO bonifico" size="14"></label>
                        </p>
                        <button type="submit" class="button button-primary">Registra pagamento</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ( $cand['note_review'] ) : ?>
                <div class="card" style="background:#fff;padding:20px;border:1px solid #e2e8ec;border-radius:8px;margin-top:16px">
                    <h2 style="margin-top:0">Note delibera</h2>
                    <p><?php echo nl2br( esc_html( (string) $cand['note_review'] ) ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
