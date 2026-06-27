<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Registro volontari — vista admin (lista + form + audit + generazione PDF).
 * Accesso: CAP_MANAGE_VOLONTARI (presidente, consigliere, tesoriere, segreteria).
 */

if ( ! current_user_can( Roles::CAP_MANAGE_VOLONTARI ) ) {
    wp_die( esc_html__( 'Non hai i permessi per gestire il registro volontari.', 'gfoss-members' ) );
}

$base_url = admin_url( 'admin.php?page=gfoss-volontari' );

// --- Azioni POST (pattern PRG) --------------------------------------------
if ( ! empty( $_POST['_action'] ) ) {
    check_admin_referer( 'gfoss_volontari' );
    $action = sanitize_key( (string) $_POST['_action'] );

    if ( $action === 'save' ) {
        $id  = (int) ( $_POST['id'] ?? 0 );
        $res = $id ? Volontari::update( $id, $_POST ) : Volontari::insert( $_POST );
        if ( is_wp_error( $res ) ) {
            wp_safe_redirect( add_query_arg( [ 'msg' => 'err', 'err' => rawurlencode( $res->get_error_message() ) ], $base_url ) ); exit;
        }
        wp_safe_redirect( add_query_arg( 'msg', $id ? 'updated' : 'created', $base_url ) ); exit;
    }
    if ( $action === 'cessa' ) {
        Volontari::cessa( (int) ( $_POST['id'] ?? 0 ), (string) ( $_POST['data_cessazione'] ?? '' ) );
        wp_safe_redirect( add_query_arg( 'msg', 'cessato', $base_url ) ); exit;
    }
    if ( $action === 'riattiva' ) {
        Volontari::riattiva( (int) ( $_POST['id'] ?? 0 ) );
        wp_safe_redirect( add_query_arg( 'msg', 'riattivato', $base_url ) ); exit;
    }
}

$msg     = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
$edit_id = (int) ( $_GET['edit'] ?? 0 );
$editing = $edit_id ? Volontari::get( $edit_id ) : null;

$f = static fn( string $k, $def = '' ) => esc_attr( (string) ( $editing[ $k ] ?? $def ) );

$lista  = Volontari::all();
$soci   = get_users( [ 'role__in' => [ 'gfoss_socio','gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_revisore','gfoss_comunicazione','gfoss_segreteria' ], 'orderby' => 'display_name', 'order' => 'ASC' ] );

// Dati dei soci per l'autocompilazione lato client del form.
$soci_data = [];
foreach ( $soci as $s ) {
    $soci_data[ (int) $s->ID ] = [
        'nome'           => $s->first_name,
        'cognome'        => $s->last_name,
        'codice_fiscale' => (string) get_user_meta( $s->ID, 'gf_codice_fiscale', true ),
        'luogo_nascita'  => (string) get_user_meta( $s->ID, 'gf_comune_nascita', true ),
        'data_nascita'   => (string) get_user_meta( $s->ID, 'gf_data_nascita', true ),
        'indirizzo'      => (string) get_user_meta( $s->ID, 'gf_indirizzo', true ),
        'cap'            => (string) get_user_meta( $s->ID, 'gf_cap', true ),
        'citta'          => (string) get_user_meta( $s->ID, 'gf_citta', true ),
        'provincia'      => (string) get_user_meta( $s->ID, 'gf_provincia', true ),
    ];
}
$card   = 'background:#fff;padding:20px;border:1px solid #e2e8ec;border-radius:8px;margin-bottom:20px';
$fmt    = static fn( $d ) => $d ? date_i18n( 'd/m/Y', strtotime( (string) $d ) ) : '—';
?>
<div class="wrap">
    <h1>Registro volontari</h1>
    <p class="description" style="max-width:820px">Contiene <strong>solo i volontari che operano nelle manifestazioni</strong> (soci od occasionali), ai fini della copertura assicurativa (art. 18 D.Lgs. 117/2017) — non tutti i soci. Le righe non si eliminano: si registra la <em>data di cessazione</em>. Ogni modifica è tracciata (audit log) e versionata.</p>

    <details class="card" style="<?php echo $card; ?>;max-width:920px">
        <summary style="cursor:pointer;font-weight:600;font-size:14px">📖 Come funziona il registro volontari (guida)</summary>
        <div style="margin-top:12px;font-size:13px;line-height:1.6;color:#3a4a55">
            <p><strong>A cosa serve.</strong> È il registro dei volontari previsto dall'art. 17 del D.Lgs. 117/2017, necessario per la <strong>copertura assicurativa obbligatoria</strong> (art. 18). Va tenuto a cura del Consiglio Direttivo.</p>
            <p><strong>Chi va inserito.</strong> Solo chi opera effettivamente nelle manifestazioni pubbliche: i soci “soliti noti” (continuativi) e gli eventuali <strong>occasionali, anche non soci</strong>. <em>Non</em> tutti i soci. Ricorda: l'assicurazione deve coprire anche gli occasionali, quindi meglio abbondare nelle coperture rispetto ai nominativi.</p>
            <p><strong>Dati minimi richiesti.</strong> Codice fiscale <em>oppure</em>, in alternativa, generalità complete (nome, cognome, luogo e data di nascita); residenza o domicilio; data di inizio e di cessazione dell'attività.</p>
            <p><strong>Cessazione.</strong> Una persona non si “cancella”: si imposta la <strong>data di cessazione</strong> (pulsante <em>Cessa</em>). La riga resta nel registro e nello storico.</p>
            <p><strong>Inalterabilità.</strong> Ogni inserimento/modifica/cessazione è registrato nell'<strong>audit log</strong> (chi, cosa, quando) e la tabella usa il <em>system versioning</em> del database: nessun dato viene perso.</p>
            <p style="margin-bottom:0"><strong>Workflow “data certa” prima di una manifestazione:</strong></p>
            <ol style="margin-top:4px">
                <li>Aggiorna il registro (aggiungi i presenti, anche occasionali).</li>
                <li>Premi <strong>Genera PDF</strong>: ottieni l'elenco con logo GFOSS e <strong>impronta SHA-256</strong> dei dati.</li>
                <li>Riporta il PDF e il suo hash in un <strong>verbale del Consiglio Direttivo</strong>.</li>
                <li>Invia il tutto via <strong>PEC</strong> a voi stessi → questo dà <em>data certa</em> opponibile a terzi (assicurazione, Agenzia Entrate).</li>
                <li>Conserva il PDF firmato (firma del Presidente) nel faldone insieme a registro soci e verbali.</li>
            </ol>
        </div>
    </details>

    <?php
    $notes = [ 'created' => 'Volontario inserito nel registro.', 'updated' => 'Dati aggiornati.', 'cessato' => 'Cessazione registrata.', 'riattivato' => 'Volontario riattivato.', 'pdf_empty' => 'Seleziona almeno un volontario per il PDF.', 'pdf_err' => 'Errore nella generazione del PDF (mPDF installato?).' ];
    if ( isset( $notes[ $msg ] ) ) {
        $cls = in_array( $msg, [ 'pdf_empty', 'pdf_err', 'err' ], true ) ? 'error' : 'success';
        echo '<div class="notice notice-' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $notes[ $msg ] ) . '</p></div>';
    }
    if ( $msg === 'err' && isset( $_GET['err'] ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( rawurldecode( (string) $_GET['err'] ) ) . '</p></div>';
    }
    ?>

    <!-- FORM INSERIMENTO / MODIFICA -->
    <div class="card" style="<?php echo $card; ?>;max-width:920px">
        <h2 style="margin-top:0"><?php echo $editing ? 'Modifica volontario' : 'Aggiungi volontario'; ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'gfoss_volontari' ); ?>
            <input type="hidden" name="_action" value="save">
            <?php if ( $editing ) : ?><input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>"><?php endif; ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="gfv_socio">Socio</label></th>
                    <td>
                        <select name="user_id" id="gfv_socio" style="min-width:340px">
                            <option value="">— occasionale / non socio —</option>
                            <?php foreach ( $soci as $s ) : ?>
                                <option value="<?php echo (int) $s->ID; ?>" <?php selected( (int) $f( 'user_id' ), $s->ID ); ?>><?php echo esc_html( $s->display_name . ' (' . $s->user_email . ')' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Scegli un socio per <strong>compilare automaticamente</strong> i campi qui sotto. Lascia “occasionale / non socio” per inserire una persona a mano.</p>
                    </td>
                </tr>
                <tr><th><label>Nome *</label></th><td><input type="text" name="nome" value="<?php echo $f( 'nome' ); ?>" required class="regular-text"></td></tr>
                <tr><th><label>Cognome *</label></th><td><input type="text" name="cognome" value="<?php echo $f( 'cognome' ); ?>" required class="regular-text"></td></tr>
                <tr><th><label>Codice fiscale</label></th><td><input type="text" name="codice_fiscale" value="<?php echo $f( 'codice_fiscale' ); ?>" maxlength="16" class="regular-text" style="text-transform:uppercase"><p class="description">Obbligatorio <strong>oppure</strong> luogo + data di nascita qui sotto.</p></td></tr>
                <tr><th><label>Luogo di nascita</label></th><td><input type="text" name="luogo_nascita" value="<?php echo $f( 'luogo_nascita' ); ?>" class="regular-text"></td></tr>
                <tr><th><label>Data di nascita</label></th><td><input type="date" name="data_nascita" value="<?php echo $f( 'data_nascita' ); ?>"></td></tr>
                <tr><th><label>Indirizzo (residenza/domicilio) *</label></th><td><input type="text" name="indirizzo" value="<?php echo $f( 'indirizzo' ); ?>" class="regular-text"></td></tr>
                <tr><th><label>CAP / Città / Prov. *</label></th><td>
                    <input type="text" name="cap" value="<?php echo $f( 'cap' ); ?>" maxlength="10" style="width:80px" placeholder="CAP">
                    <input type="text" name="citta" value="<?php echo $f( 'citta' ); ?>" placeholder="Città">
                    <input type="text" name="provincia" value="<?php echo $f( 'provincia' ); ?>" maxlength="4" style="width:60px" placeholder="Prov.">
                </td></tr>
                <tr><th><label>Tipo</label></th><td>
                    <select name="tipo">
                        <option value="continuativo" <?php selected( $f( 'tipo', 'continuativo' ), 'continuativo' ); ?>>Continuativo</option>
                        <option value="occasionale" <?php selected( $f( 'tipo' ), 'occasionale' ); ?>>Occasionale</option>
                    </select>
                </td></tr>
                <tr><th><label>Inizio attività *</label></th><td><input type="date" name="data_inizio" value="<?php echo $f( 'data_inizio', current_time( 'Y-m-d' ) ); ?>" required></td></tr>
                <?php if ( $editing ) : ?>
                <tr><th><label>Cessazione</label></th><td><input type="date" name="data_cessazione" value="<?php echo $f( 'data_cessazione' ); ?>"><p class="description">Lascia vuoto se ancora attivo.</p></td></tr>
                <?php endif; ?>
                <tr><th><label>Note</label></th><td><input type="text" name="note" value="<?php echo $f( 'note' ); ?>" class="large-text"></td></tr>
            </table>
            <p>
                <button type="submit" class="button button-primary"><?php echo $editing ? 'Salva modifiche' : 'Aggiungi al registro'; ?></button>
                <?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( $base_url ); ?>">Annulla</a><?php endif; ?>
            </p>
        </form>
        <script>
        (function(){
            var data = <?php echo wp_json_encode( $soci_data ); ?>;
            var sel  = document.getElementById('gfv_socio');
            if ( ! sel ) { return; }
            sel.addEventListener('change', function(){
                var d = data[ this.value ];
                if ( ! d ) { return; } // "occasionale": non tocca i campi compilati a mano
                var form = sel.closest('form');
                ['nome','cognome','codice_fiscale','luogo_nascita','data_nascita','indirizzo','cap','citta','provincia'].forEach(function(k){
                    var inp = form.querySelector('[name="'+k+'"]');
                    if ( inp && d[k] ) { inp.value = d[k]; }
                });
            });
        })();
        </script>
    </div>

    <!-- LISTA -->
    <div class="card" style="<?php echo $card; ?>">
        <h2 style="margin-top:0">Volontari iscritti (<?php echo count( $lista ); ?>) · attivi: <?php echo Volontari::count_attivi(); ?></h2>
        <table class="widefat striped">
            <thead><tr><th>Cognome e nome</th><th>CF / Nascita</th><th>Residenza</th><th>Tipo</th><th>Inizio</th><th>Cessazione</th><th>Stato</th><th></th></tr></thead>
            <tbody>
            <?php if ( ! $lista ) : ?>
                <tr><td colspan="8">Nessun volontario nel registro.</td></tr>
            <?php else : foreach ( $lista as $v ) :
                $attivo = empty( $v['data_cessazione'] ); ?>
                <tr>
                    <td><strong><?php echo esc_html( $v['cognome'] . ' ' . $v['nome'] ); ?></strong></td>
                    <td><?php echo $v['codice_fiscale'] ? '<code>' . esc_html( $v['codice_fiscale'] ) . '</code>' : esc_html( trim( $v['luogo_nascita'] . ' ' . $fmt( $v['data_nascita'] ) ) ?: '—' ); ?></td>
                    <td><?php echo esc_html( trim( $v['citta'] . ( $v['provincia'] ? ' (' . $v['provincia'] . ')' : '' ) ) ?: '—' ); ?></td>
                    <td><?php echo $v['tipo'] === 'occasionale' ? 'Occasionale' : 'Continuativo'; ?></td>
                    <td><?php echo esc_html( $fmt( $v['data_inizio'] ) ); ?></td>
                    <td><?php echo esc_html( $fmt( $v['data_cessazione'] ) ); ?></td>
                    <td><?php echo $attivo ? '<span style="color:#5DA34D;font-weight:600">attivo</span>' : '<span style="color:#B26A00">cessato</span>'; ?></td>
                    <td style="white-space:nowrap">
                        <a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', (int) $v['id'], $base_url ) ); ?>">Modifica</a>
                        <?php if ( $attivo ) : ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Registrare la cessazione di oggi per questo volontario?')">
                                <?php wp_nonce_field( 'gfoss_volontari' ); ?>
                                <input type="hidden" name="_action" value="cessa"><input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>">
                                <button class="button button-small">Cessa</button>
                            </form>
                        <?php else : ?>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field( 'gfoss_volontari' ); ?>
                                <input type="hidden" name="_action" value="riattiva"><input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>">
                                <button class="button button-small">Riattiva</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- GENERA ELENCO PDF (data certa) -->
    <div class="card" style="<?php echo $card; ?>;max-width:920px">
        <h2 style="margin-top:0">Genera elenco PDF per manifestazione</h2>
        <p class="description">Seleziona i volontari presenti, indica la manifestazione e genera il PDF con impronta SHA-256. Poi mettilo a verbale e invialo via PEC per la data certa.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="gfoss_volontari_pdf">
            <?php wp_nonce_field( 'gfoss_volontari_pdf' ); ?>
            <p>
                <label>Manifestazione <input type="text" name="manifestazione" class="regular-text" placeholder="es. FOSS4G-it 2026"></label>
                &nbsp; <label>Data <input type="date" name="data_manifestazione"></label>
            </p>
            <table class="widefat striped">
                <thead><tr><th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('.gfv-cb').forEach(c=>c.checked=this.checked)"></th><th>Volontario</th><th>Tipo</th><th>Stato</th></tr></thead>
                <tbody>
                <?php foreach ( $lista as $v ) : $attivo = empty( $v['data_cessazione'] ); ?>
                    <tr>
                        <td><input type="checkbox" class="gfv-cb" name="ids[]" value="<?php echo (int) $v['id']; ?>" <?php checked( $attivo ); ?>></td>
                        <td><?php echo esc_html( $v['cognome'] . ' ' . $v['nome'] ); ?></td>
                        <td><?php echo $v['tipo'] === 'occasionale' ? 'Occasionale' : 'Continuativo'; ?></td>
                        <td><?php echo $attivo ? 'attivo' : 'cessato'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:12px"><button type="submit" class="button button-primary">⬇ Genera PDF</button></p>
        </form>
    </div>

    <!-- AUDIT LOG -->
    <div class="card" style="<?php echo $card; ?>">
        <h2 style="margin-top:0">Registro delle modifiche (audit log)</h2>
        <table class="widefat striped">
            <thead><tr><th>Data/ora</th><th>Azione</th><th>Utente</th><th>Volontario</th><th>Dettaglio</th></tr></thead>
            <tbody>
            <?php $aud = Volontari::audit( 0, 100 ); if ( ! $aud ) : ?>
                <tr><td colspan="5">Nessuna registrazione.</td></tr>
            <?php else : foreach ( $aud as $a ) : ?>
                <tr>
                    <td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $a['created_at'] ) ); ?></td>
                    <td><code><?php echo esc_html( $a['azione'] ); ?></code></td>
                    <td><?php echo esc_html( $a['user_login'] ?: '—' ); ?><?php echo $a['ip'] ? '<br><small style="color:#888">' . esc_html( $a['ip'] ) . '</small>' : ''; ?></td>
                    <td><?php echo $a['volontario_id'] ? '#' . (int) $a['volontario_id'] : '—'; ?></td>
                    <td><small style="color:#4A5C6A;word-break:break-all"><?php echo esc_html( mb_strimwidth( (string) $a['dettaglio'], 0, 160, '…' ) ); ?></small></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
