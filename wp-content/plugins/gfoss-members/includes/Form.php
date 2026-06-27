<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shortcode [gfoss_iscrizione_form] — form pubblico di candidatura.
 *
 * Misure di hardening:
 *   - WP nonce
 *   - honeypot field "website"
 *   - timestamp (form scartato se inviato in <4 secondi)
 *   - rate-limit per IP (3 invii/ora) gestito in Submission
 *   - validazione server-side (vedi Validator)
 *   - asset enqueued solo dove c'è lo shortcode
 */
class Form {

    public const NONCE_ACTION = 'gfoss_iscrizione';
    public const NONCE_FIELD  = '_gfoss_nonce';

    public static function init(): void {
        add_shortcode( 'gfoss_iscrizione_form', [ __CLASS__, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ] );
    }

    public static function maybe_enqueue(): void {
        if ( is_singular() ) {
            global $post;
            if ( $post && has_shortcode( $post->post_content, 'gfoss_iscrizione_form' ) ) {
                wp_enqueue_style( 'gfoss-members-form',
                    GFOSS_MEMBERS_URL . 'assets/css/form.css',
                    [], GFOSS_MEMBERS_VERSION );
            }
        }
    }

    public static function render( $atts = [], $content = null ): string {
        // Riprendi eventuali errori salvati dal Submission handler.
        $state = self::flash_get();
        $errors = $state['errors'] ?? [];
        $values = $state['values'] ?? [];
        $success = $state['success'] ?? null;

        ob_start();

        if ( $success ) :
            $cand = $success['cand'];
            $url  = Candidatura::paypal_url( $cand );
            ?>
            <div class="gf-card gf-card--success" role="status">
                <h2>✓ Domanda ricevuta</h2>
                <p>Grazie <?php echo esc_html( $cand['nome'] ); ?>! Abbiamo registrato la tua domanda di iscrizione.</p>
                <p>I prossimi passi:</p>
                <ol>
                    <li>Il <strong>Consiglio Direttivo</strong> esaminerà la tua candidatura (art. 6 dello Statuto).</li>
                    <li>Versa subito la <strong>quota associativa di <?php echo esc_html( number_format_i18n( (float) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 ), 2 ) ); ?> €</strong> (potrai farlo anche dopo l'approvazione, ma anticipare velocizza l'attivazione).</li>
                    <li>Quando entrambe le condizioni sono soddisfatte ti arriverà un'email con le credenziali per accedere all'area soci.</li>
                </ol>
                <p>
                    <a class="gf-btn gf-btn--primary" href="<?php echo esc_url( $url ); ?>" rel="noopener">
                        Paga ora con PayPal
                    </a>
                </p>
                <p class="gf-muted">
                    Preferisci il bonifico? Causale: <code>Nuova iscrizione anno <?php echo esc_html( gmdate( 'Y' ) ); ?> — <?php echo esc_html( $cand['nome'] . ' ' . $cand['cognome'] ); ?></code><br>
                    IBAN: <code><?php echo esc_html( defined( 'GFOSS_ASSOC_IBAN' ) ? GFOSS_ASSOC_IBAN : '' ); ?></code> — Beneficiario: <em>Associazione Italiana per l'Informazione Geografica Libera</em>
                </p>
            </div>
            <?php
            return ob_get_clean();
        endif;
        ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gf-form" novalidate>
            <input type="hidden" name="action" value="gfoss_iscrizione_submit">
            <input type="hidden" name="_t" value="<?php echo esc_attr( (string) time() ); ?>">
            <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
            <div class="gf-hp" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <?php if ( $errors ) : ?>
                <div class="gf-card gf-card--error" role="alert">
                    <strong>Controlla i campi evidenziati:</strong>
                    <ul><?php foreach ( $errors as $msg ) : ?><li><?php echo esc_html( $msg ); ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <fieldset class="gf-fset">
                <legend>Dati anagrafici</legend>
                <div class="gf-grid">
                    <?php self::input( 'nome', 'Nome *', $values, $errors ); ?>
                    <?php self::input( 'cognome', 'Cognome *', $values, $errors ); ?>
                    <?php self::input( 'email', 'Email *', $values, $errors, [ 'type' => 'email', 'autocomplete' => 'email' ] ); ?>
                    <?php self::input( 'codice_fiscale', 'Codice fiscale *', $values, $errors, [ 'maxlength' => 16, 'class' => 'gf-mono' ] ); ?>
                    <?php self::input( 'data_nascita', 'Data di nascita', $values, $errors, [ 'type' => 'date' ] ); ?>
                    <?php self::input( 'comune_nascita', 'Comune di nascita', $values, $errors ); ?>
                </div>
            </fieldset>

            <fieldset class="gf-fset">
                <legend>Residenza</legend>
                <div class="gf-grid">
                    <?php self::input( 'indirizzo', 'Indirizzo *', $values, $errors, [ 'class' => 'gf-col-2' ] ); ?>
                    <?php self::input( 'cap', 'CAP *', $values, $errors, [ 'maxlength' => 5, 'inputmode' => 'numeric' ] ); ?>
                    <?php self::input( 'citta', 'Città *', $values, $errors ); ?>
                    <?php self::input( 'provincia', 'Provincia *', $values, $errors, [ 'maxlength' => 2, 'placeholder' => 'PD' ] ); ?>
                    <?php self::input( 'telefono', 'Telefono *', $values, $errors, [ 'type' => 'tel', 'autocomplete' => 'tel' ] ); ?>
                </div>
            </fieldset>

            <fieldset class="gf-fset">
                <legend>Profilo</legend>
                <div class="gf-grid">
                    <?php self::input( 'professione', 'Professione *', $values, $errors ); ?>
                    <?php self::textarea( 'competenze', 'Aree di competenza / strumenti GIS', $values, $errors ); ?>
                    <?php self::textarea( 'motivazione', 'Perché vuoi iscriverti? (facoltativo)', $values, $errors ); ?>
                    <label class="gf-check gf-col-2">
                        <input type="checkbox" name="volontario" value="1" <?php checked( ! empty( $values['volontario'] ) ); ?>>
                        Desidero rendermi disponibile a svolgere attività di volontariato per GFOSS.it APS.
                        <small class="gf-muted" style="display:block">L'eventuale iscrizione al registro dei volontari (a fini assicurativi) avviene a cura del Consiglio Direttivo solo per chi opera effettivamente nelle manifestazioni.</small>
                    </label>
                </div>
            </fieldset>

            <fieldset class="gf-fset">
                <legend>Consensi</legend>
                <label class="gf-check">
                    <input type="checkbox" name="consenso_statuto" value="1" required <?php checked( ! empty( $values['consenso_statuto'] ) ); ?>>
                    Dichiaro di aver letto e accettato lo <a href="<?php echo esc_url( home_url( '/associazione/statuto/' ) ); ?>" target="_blank" rel="noopener">Statuto dell'Associazione</a> *
                </label>
                <label class="gf-check">
                    <input type="checkbox" name="consenso_privacy" value="1" required <?php checked( ! empty( $values['consenso_privacy'] ) ); ?>>
                    Acconsento al trattamento dei miei dati personali secondo la <a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>" target="_blank" rel="noopener">Privacy Policy</a> per le finalità associative (Reg. UE 2016/679) *
                </label>
            </fieldset>

            <p class="gf-actions">
                <button type="submit" class="gf-btn gf-btn--primary gf-btn--lg">Invia domanda di iscrizione</button>
            </p>
            <p class="gf-muted">
                * Campi obbligatori. La domanda viene esaminata dal Consiglio Direttivo (art. 6 dello Statuto) e diventa effettiva dopo l'approvazione del CD <strong>e</strong> il pagamento della quota di <?php echo esc_html( number_format_i18n( (float) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 ), 2 ) ); ?> €.
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    // -- Helpers di rendering ---------------------------------------------

    private static function input( string $name, string $label, array $values, array $errors, array $attrs = [] ): void {
        $type = $attrs['type'] ?? 'text';
        $val = (string) ( $values[ $name ] ?? '' );
        $err = isset( $errors[ $name ] );
        $extra = '';
        foreach ( $attrs as $k => $v ) {
            if ( in_array( $k, [ 'type', 'class' ], true ) ) { continue; }
            $extra .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
        }
        $cls = 'gf-field' . ( $err ? ' gf-field--err' : '' ) . ( ! empty( $attrs['class'] ) ? ' ' . $attrs['class'] : '' );
        echo '<label class="' . esc_attr( $cls ) . '"><span class="gf-field__lbl">' . esc_html( $label ) . '</span>'
           . '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '"' . $extra . '></label>';
    }

    private static function textarea( string $name, string $label, array $values, array $errors ): void {
        $val = (string) ( $values[ $name ] ?? '' );
        $err = isset( $errors[ $name ] );
        $cls = 'gf-field gf-col-2' . ( $err ? ' gf-field--err' : '' );
        echo '<label class="' . esc_attr( $cls ) . '"><span class="gf-field__lbl">' . esc_html( $label ) . '</span>'
           . '<textarea name="' . esc_attr( $name ) . '" rows="3">' . esc_textarea( $val ) . '</textarea></label>';
    }

    // -- Flash storage (per ripopolare il form dopo POST) ------------------

    public static function flash_set( array $data ): void {
        $key = 'gfoss_form_flash_' . self::flash_id();
        set_transient( $key, $data, 60 );
    }
    public static function flash_get(): array {
        $key = 'gfoss_form_flash_' . self::flash_id();
        $data = get_transient( $key );
        if ( $data ) { delete_transient( $key ); }
        return is_array( $data ) ? $data : [];
    }
    private static function flash_id(): string {
        if ( empty( $_COOKIE['gfoss_flash'] ) ) {
            $id = bin2hex( random_bytes( 8 ) );
            setcookie( 'gfoss_flash', $id, [
                'expires'  => time() + 120,
                'path'     => COOKIEPATH ?: '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ] );
            $_COOKIE['gfoss_flash'] = $id;
        }
        return preg_replace( '/[^a-f0-9]/', '', (string) $_COOKIE['gfoss_flash'] );
    }
}
