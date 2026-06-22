<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shortcode [gfoss_area_personale] — dashboard del socio loggato.
 * Sezioni: tessera digitale + stato quota / rinnovo, dati personali editabili,
 * storico quote, link documenti riservati.
 */
class Area_Personale {

    public static function init(): void {
        add_shortcode( 'gfoss_area_personale', [ __CLASS__, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ] );
    }

    public static function maybe_enqueue(): void {
        if ( ! is_singular() ) { return; }
        global $post;
        if ( ! $post ) { return; }
        // Sul singolo progetto la pagina è renderizzata via the_content (no shortcode).
        if ( is_singular( 'gfoss_progetto' ) ) {
            wp_enqueue_style( 'gfoss-members-form', GFOSS_MEMBERS_URL . 'assets/css/form.css', [], GFOSS_MEMBERS_VERSION );
            wp_enqueue_style( 'gfoss-members-area', GFOSS_MEMBERS_URL . 'assets/css/area.css', [ 'gfoss-members-form' ], GFOSS_MEMBERS_VERSION );
            return;
        }
        $shortcodes = [
            'gfoss_area_personale', 'gfoss_iscrizione_form', 'gfoss_verifica_tessera',
            'gfoss_eventi', 'gfoss_materiali', 'gfoss_mappa_soci', 'gfoss_convocazioni',
            'gfoss_documenti_riservati', 'gfoss_progetti', 'gfoss_sondaggi',
        ];
        foreach ( $shortcodes as $sc ) {
            if ( has_shortcode( $post->post_content, $sc ) ) {
                wp_enqueue_style( 'gfoss-members-form', GFOSS_MEMBERS_URL . 'assets/css/form.css',  [], GFOSS_MEMBERS_VERSION );
                wp_enqueue_style( 'gfoss-members-area', GFOSS_MEMBERS_URL . 'assets/css/area.css',  [ 'gfoss-members-form' ], GFOSS_MEMBERS_VERSION );
                return;
            }
        }
    }

    public static function render( $atts = [], $content = null ): string {
        if ( ! is_user_logged_in() ) {
            return self::login_panel();
        }

        $user = wp_get_current_user();
        if ( ! gfoss_members_is_socio( $user->ID ) && ! current_user_can( Roles::CAP_VIEW_OWN_QUOTA ) ) {
            return '<div class="gf-card gf-card--warn">Il tuo account non è ancora associato a un profilo socio. Contatta <a href="mailto:info@gfoss.it">info@gfoss.it</a>.</div>';
        }

        $year      = (int) gmdate( 'Y' );
        $status    = Quote::status_for( $user->ID, $year );
        $storico   = Quote::for_user( $user->ID );
        $msg       = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );

        ob_start();
        ?>
        <div class="gf-area">
            <header class="gf-area__head">
                <div>
                    <p class="gf-area__eyebrow">Area soci</p>
                    <h1 class="gf-area__title">Ciao, <?php echo esc_html( $user->first_name ?: $user->display_name ); ?>!</h1>
                    <p class="gf-area__sub">
                        Numero socio <code><?php echo esc_html( (string) get_user_meta( $user->ID, 'gf_numero_socio', true ) ?: '—' ); ?></code>
                        · iscritto/a dal <?php echo esc_html( (string) get_user_meta( $user->ID, 'gf_data_ammissione', true ) ?: '—' ); ?>
                    </p>
                </div>
                <a class="gf-btn gf-btn--ghost" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Esci</a>
            </header>

            <?php if ( $msg === 'profile_saved' ) : ?>
                <div class="gf-card gf-card--success">Dati personali aggiornati.</div>
            <?php elseif ( $msg === 'paypal_ok' ) : ?>
                <div class="gf-card gf-card--success">Pagamento ricevuto. Lo stato della quota verrà aggiornato a breve (al ricevimento della notifica PayPal).</div>
            <?php elseif ( $msg === 'paypal_cancel' ) : ?>
                <div class="gf-card gf-card--warn">Pagamento annullato. Puoi riprovare quando vuoi.</div>
            <?php elseif ( $msg === 'gis_ok' ) : ?>
                <div class="gf-card gf-card--success">Spazio dati GIS attivato. Trovi i parametri di connessione qui sotto.</div>
            <?php elseif ( $msg === 'gis_partial' ) : ?>
                <div class="gf-card gf-card--warn">PostGIS attivato, ma GeoServer ha segnalato un problema (dettagli nella card GIS).</div>
            <?php elseif ( $msg === 'gis_err' ) : ?>
                <div class="gf-card gf-card--warn">Attivazione dello spazio GIS non riuscita (dettagli nella card GIS).</div>
            <?php elseif ( $msg === 'gis_not_eligible' ) : ?>
                <div class="gf-card gf-card--warn">Lo spazio GIS è riservato ai soci con quota in regola.</div>
            <?php elseif ( $msg === 'gis_unconfigured' ) : ?>
                <div class="gf-card gf-card--warn">Il servizio GIS non è ancora configurato. Riprova più tardi.</div>
            <?php endif; ?>

            <div class="gf-area__grid">

                <!-- STRUMENTI DI GESTIONE (in base ai permessi) ------- -->
                <?php
                $tools = [];
                if ( current_user_can( 'publish_posts' ) ) {
                    $tools[] = [ admin_url( 'post-new.php' ),                       '✍️', 'Crea una news',        'Scrivi e pubblica una notizia sul sito.' ];
                    $tools[] = [ admin_url( 'edit.php' ),                           '📰', 'Gestisci le news',     'Modifica o elimina le notizie pubblicate.' ];
                }
                if ( current_user_can( Roles::CAP_MANAGE_SOCI ) ) {
                    $tools[] = [ admin_url( 'admin.php?page=gfoss-soci' ),          '👥', 'Amministra soci',      'Registro soci, quote, ruoli e archiviazione.' ];
                }
                if ( current_user_can( Roles::CAP_REVIEW_CANDIDATURE ) ) {
                    $tools[] = [ admin_url( 'admin.php?page=gfoss-candidature' ),    '📝', 'Candidature',          'Approva o rifiuta le richieste di nuovi soci.' ];
                }
                if ( current_user_can( Roles::CAP_MANAGE_QUOTE ) ) {
                    $tools[] = [ admin_url( 'admin.php?page=gfoss-quote' ),          '💶', 'Quote',                'Stato dei versamenti e solleciti.' ];
                }
                if ( current_user_can( Roles::CAP_VIEW_ACCOUNTING ) ) {
                    $tools[] = [ admin_url( 'admin.php?page=gfoss-contabilita' ),    '📊', 'Contabilità',          'Movimenti, rendiconto e riconciliazione.' ];
                }
                if ( current_user_can( Roles::CAP_MANAGE_SOCI ) ) {
                    $tools[] = [ admin_url( 'admin.php?page=gfoss-comunicazioni' ),  '📣', 'Comunicazioni ai soci', 'Invia un messaggio o una convocazione ai soci.' ];
                }
                if ( current_user_can( Roles::CAP_EXPORT_REGISTRO ) ) {
                    $tools[] = [ admin_url( 'admin.php?page=gfoss-export' ),         '⬇️', 'Esporta registro',     'Scarica il registro soci in CSV.' ];
                }
                if ( $tools ) :
                ?>
                <section class="gf-area__card gf-area__card--wide">
                    <header class="gf-area__card-head"><h2>Strumenti di gestione</h2></header>
                    <p class="gf-muted">In base al tuo ruolo hai accesso a queste funzioni di amministrazione.</p>
                    <div class="gf-tools">
                        <?php foreach ( $tools as $t ) : ?>
                            <a class="gf-tool" href="<?php echo esc_url( $t[0] ); ?>">
                                <span class="gf-tool__ico" aria-hidden="true"><?php echo $t[1]; ?></span>
                                <span class="gf-tool__txt">
                                    <strong><?php echo esc_html( $t[2] ); ?></strong>
                                    <small><?php echo esc_html( $t[3] ); ?></small>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-top:12px"><a class="gf-btn gf-btn--ghost" href="<?php echo esc_url( admin_url() ); ?>">Vai al pannello WordPress →</a></p>
                </section>
                <?php endif; ?>

                <!-- TESSERA DIGITALE ----------------------------------- -->
                <section class="gf-area__card">
                    <header class="gf-area__card-head">
                        <h2>Tessera digitale</h2>
                        <?php echo self::status_chip( $status ); ?>
                    </header>
                    <div class="gf-tessera-mini">
                        <?php echo Tessera::render_inline( $user->ID, $year ); ?>
                    </div>
                    <p>
                        <?php if ( in_array( $status, [ 'paid', 'expiring' ], true ) ) : ?>
                            <a class="gf-btn gf-btn--primary" href="<?php echo esc_url( Tessera::download_url( $user->ID ) ); ?>">⬇ Scarica PDF</a>
                        <?php else : ?>
                            <button class="gf-btn gf-btn--primary" disabled title="Disponibile dopo il pagamento della quota">⬇ Scarica PDF</button>
                        <?php endif; ?>
                    </p>
                    <p class="gf-muted">Il QR sulla tessera permette a chiunque di verificare in tempo reale che sei un socio in regola.</p>
                </section>

                <!-- QUOTA / RINNOVO ----------------------------------- -->
                <section class="gf-area__card">
                    <header class="gf-area__card-head"><h2>Quota associativa <?php echo esc_html( (string) $year ); ?></h2></header>
                    <?php if ( $status === 'paid' ) : ?>
                        <p>Quota di <strong><?php echo esc_html( number_format_i18n( Quote::default_amount(), 2 ) ); ?> €</strong> regolarmente versata. Puoi votare in assemblea ✓</p>
                    <?php elseif ( $status === 'expiring' ) : ?>
                        <p>Sei in regola per il <?php echo esc_html( (string) $year ); ?>. Il rinnovo per il <?php echo esc_html( (string) ( $year + 1 ) ); ?> sarà disponibile a partire da gennaio.</p>
                    <?php else : ?>
                        <p>Quota <?php echo esc_html( (string) $year ); ?>: <strong><?php echo esc_html( number_format_i18n( Quote::default_amount(), 2 ) ); ?> €</strong></p>
                        <p><a class="gf-btn gf-btn--orange gf-btn--lg" href="<?php echo esc_url( Rinnovo::paypal_url( $user->ID, $year ) ); ?>">Rinnova ora con PayPal</a></p>
                        <details class="gf-muted">
                            <summary>Preferisci il bonifico?</summary>
                            <p>IBAN <code><?php echo esc_html( defined( 'GFOSS_ASSOC_IBAN' ) ? GFOSS_ASSOC_IBAN : '' ); ?></code><br>
                            Beneficiario: <em>Associazione Italiana per l'Informazione Geografica Libera</em><br>
                            Causale: <code>Rinnovo iscrizione anno <?php echo esc_html( (string) $year ); ?> — <?php echo esc_html( $user->display_name ); ?></code></p>
                        </details>
                    <?php endif; ?>

                    <h3>Storico</h3>
                    <div class="gf-tablewrap">
                    <table class="gf-table">
                        <thead><tr><th>Anno</th><th>Importo</th><th>Metodo</th><th>Stato</th><th>Ricevuta</th></tr></thead>
                        <tbody>
                            <?php if ( ! $storico ) : ?>
                                <tr><td colspan="5" class="gf-muted">Nessun pagamento registrato.</td></tr>
                            <?php else : foreach ( $storico as $q ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $q['anno'] ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( (float) $q['importo'], 2 ) ); ?> €</td>
                                    <td><?php echo esc_html( $q['metodo'] ); ?></td>
                                    <td><?php echo $q['stato'] === 'paid' ? '✓ pagata' : esc_html( $q['stato'] ); ?></td>
                                    <td>
                                        <?php if ( $q['stato'] === 'paid' ) : ?>
                                            <a href="<?php echo esc_url( Ricevuta::download_url( $user->ID, (int) $q['anno'] ) ); ?>">⬇ PDF</a>
                                        <?php else : ?>
                                            <span class="gf-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    </div>
                </section>

                <!-- DOCUMENTI RISERVATI ------------------------------- -->
                <section class="gf-area__card">
                    <header class="gf-area__card-head"><h2>Documenti riservati</h2></header>
                    <?php $docs_id = (int) get_option( 'gfoss_page_documenti_soci' ); ?>
                    <?php if ( $docs_id ) : ?>
                        <p><a class="gf-btn gf-btn--ghost" href="<?php echo esc_url( get_permalink( $docs_id ) ); ?>">Apri documenti riservati →</a></p>
                    <?php endif; ?>
                    <p class="gf-muted">Verbali del CD, bozze di bilancio, materiali di lavoro: visibili solo ai soci in regola.</p>
                </section>

                <!-- SERVIZIO METADATI RNDT ---------------------------- -->
                <?php
                $rndt_pages = get_posts( [ 'post_type' => 'page', 'name' => 'metadati-rndt', 'post_status' => 'publish', 'numberposts' => 1 ] );
                $rndt_page  = $rndt_pages ? $rndt_pages[0] : null;
                ?>
                <?php if ( $rndt_page && current_user_can( 'manage_rndt_metadata' ) ) : ?>
                <section class="gf-area__card">
                    <header class="gf-area__card-head"><h2>Metadati RNDT</h2></header>
                    <p><a class="gf-btn gf-btn--ghost" href="<?php echo esc_url( get_permalink( $rndt_page->ID ) ); ?>">Apri l'editor metadati →</a></p>
                    <p class="gf-muted">Crea ed esporta i tuoi metadati territoriali conformi al profilo RNDT 2020 (INSPIRE / ISO 19139).</p>
                </section>
                <?php endif; ?>

                <!-- ALTRI SERVIZI SOCI -------------------------------- -->
                <?php
                $servizi = [
                    'materiali-soci' => [ 'Materiali e risorse', 'Presentazioni, template, documentazione condivisa.' ],
                    'convocazioni'   => [ 'Convocazioni e deleghe', 'Assemblee convocate e gestione delle deleghe.' ],
                    'mappa-soci'     => [ 'Mappa dei soci', 'La community sulla mappa (attiva il consenso nei tuoi dati).' ],
                    'sondaggi'       => [ 'Sondaggi', 'Esprimi la tua opinione nei sondaggi tra soci.' ],
                ];
                foreach ( $servizi as $slug => $info ) :
                    $pgs = get_posts( [ 'post_type' => 'page', 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1 ] );
                    if ( ! $pgs ) { continue; }
                    ?>
                    <section class="gf-area__card">
                        <header class="gf-area__card-head"><h2><?php echo esc_html( $info[0] ); ?></h2></header>
                        <p><a class="gf-btn gf-btn--ghost" href="<?php echo esc_url( get_permalink( $pgs[0]->ID ) ); ?>">Apri →</a></p>
                        <p class="gf-muted"><?php echo esc_html( $info[1] ); ?></p>
                    </section>
                <?php endforeach; ?>

                <?php if ( class_exists( __NAMESPACE__ . '\\Forum' ) && Forum::is_active() ) : ?>
                <section class="gf-area__card">
                    <header class="gf-area__card-head"><h2>Forum soci</h2></header>
                    <p><a class="gf-btn gf-btn--ghost" href="<?php echo esc_url( home_url( '/forums/' ) ); ?>">Apri il forum →</a></p>
                    <p class="gf-muted">Spazio di discussione riservato ai soci.</p>
                </section>
                <?php endif; ?>

                <!-- SPAZIO DATI GIS (PostGIS + GeoServer) ------------- -->
                <?php
                if ( class_exists( __NAMESPACE__ . '\\Gis' ) ) {
                    echo Gis::render_area_card( $user ); // markup già escapato internamente
                }
                ?>

                <!-- DATI PERSONALI ------------------------------------ -->
                <section class="gf-area__card gf-area__card--wide">
                    <header class="gf-area__card-head"><h2>I tuoi dati</h2></header>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gf-form">
                        <input type="hidden" name="action" value="gfoss_update_profile">
                        <?php wp_nonce_field( 'gfoss_profile_' . $user->ID ); ?>

                        <div class="gf-grid">
                            <label class="gf-field">
                                <span class="gf-field__lbl">Nome</span>
                                <input type="text" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>">
                            </label>
                            <label class="gf-field">
                                <span class="gf-field__lbl">Cognome</span>
                                <input type="text" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>">
                            </label>
                            <label class="gf-field">
                                <span class="gf-field__lbl">Email</span>
                                <input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled>
                                <small class="gf-muted">Per cambiare email scrivi a <a href="mailto:info@gfoss.it">info@gfoss.it</a>.</small>
                            </label>
                            <label class="gf-field">
                                <span class="gf-field__lbl">Telefono</span>
                                <input type="tel" name="gf_telefono" value="<?php echo esc_attr( get_user_meta( $user->ID, 'gf_telefono', true ) ); ?>">
                            </label>
                            <label class="gf-field gf-col-2">
                                <span class="gf-field__lbl">Indirizzo</span>
                                <input type="text" name="gf_indirizzo" value="<?php echo esc_attr( get_user_meta( $user->ID, 'gf_indirizzo', true ) ); ?>">
                            </label>
                            <label class="gf-field">
                                <span class="gf-field__lbl">CAP</span>
                                <input type="text" name="gf_cap" maxlength="5" value="<?php echo esc_attr( get_user_meta( $user->ID, 'gf_cap', true ) ); ?>">
                            </label>
                            <label class="gf-field">
                                <span class="gf-field__lbl">Città</span>
                                <input type="text" name="gf_citta" value="<?php echo esc_attr( get_user_meta( $user->ID, 'gf_citta', true ) ); ?>">
                            </label>
                            <label class="gf-field">
                                <span class="gf-field__lbl">Provincia</span>
                                <input type="text" name="gf_provincia" maxlength="2" value="<?php echo esc_attr( get_user_meta( $user->ID, 'gf_provincia', true ) ); ?>">
                            </label>
                            <label class="gf-field">
                                <span class="gf-field__lbl">Professione</span>
                                <input type="text" name="gf_professione" value="<?php echo esc_attr( get_user_meta( $user->ID, 'gf_professione', true ) ); ?>">
                            </label>
                            <label class="gf-field gf-col-2">
                                <span class="gf-field__lbl">Aree di competenza</span>
                                <textarea name="gf_competenze" rows="3"><?php echo esc_textarea( get_user_meta( $user->ID, 'gf_competenze', true ) ); ?></textarea>
                            </label>
                            <label class="gf-check gf-col-2">
                                <input type="checkbox" name="gf_volontario" value="1" <?php checked( get_user_meta( $user->ID, 'gf_volontario', true ), '1' ); ?>>
                                Sono iscritto/a al registro volontari (art. 18 Statuto)
                            </label>
                            <label class="gf-check gf-col-2">
                                <input type="checkbox" name="gf_mappa_consenso" value="1" <?php checked( get_user_meta( $user->ID, 'gf_mappa_consenso', true ), '1' ); ?>>
                                Localizzami nella mappa dei soci (mostra nome, città e competenze agli altri soci)
                            </label>
                        </div>

                        <p class="gf-actions">
                            <button type="submit" class="gf-btn gf-btn--primary">Salva modifiche</button>
                        </p>

                        <p class="gf-muted" style="text-align:center">
                            Codice fiscale, data e luogo di nascita non sono modificabili. Per correzioni contatta <a href="mailto:info@gfoss.it">info@gfoss.it</a>.
                        </p>
                    </form>
                </section>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function login_panel(): string {
        $login_url = wp_login_url( get_permalink() );
        $iscrivi   = gfoss_members_iscrizione_url();
        return '<div class="gf-card">
            <h2 style="margin-top:0">Accesso area soci</h2>
            <p>Per accedere alla tua area personale devi effettuare il login con le credenziali ricevute via email al momento dell\'attivazione dell\'iscrizione.</p>
            <p><a class="gf-btn gf-btn--primary" href="' . esc_url( $login_url ) . '">Accedi</a> &nbsp;
            <a class="gf-btn gf-btn--ghost" href="' . esc_url( $iscrivi ) . '">Non sei socio? Iscriviti</a></p>
            <p class="gf-muted"><a href="' . esc_url( wp_lostpassword_url( get_permalink() ) ) . '">Password dimenticata?</a></p>
        </div>';
    }

    private static function status_chip( string $status ): string {
        return match ( $status ) {
            'paid'     => '<span class="chip chip--ok">IN REGOLA</span>',
            'expiring' => '<span class="chip chip--warn">IN SCADENZA</span>',
            'pending'  => '<span class="chip chip--warn">DA RINNOVARE</span>',
            'expired'  => '<span class="chip chip--bad">SCADUTA</span>',
            default    => '<span class="chip">N.D.</span>',
        };
    }
}
