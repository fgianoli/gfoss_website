<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Notifiche transazionali.
 *
 * Indirizzi:
 *   - Mittente: SMTP_FROM (env) o admin_email — gestito da WP Mail SMTP in produzione.
 *   - Destinatario CD: get_option('gfoss_cd_recipients') oppure admin_email.
 */
class Email {

    public static function init(): void {
        add_filter( 'wp_mail_content_type', [ __CLASS__, 'html_content_type' ] );
    }

    public static function html_content_type(): string { return 'text/html'; }

    private static function from(): string {
        return defined( 'SMTP_FROM' ) && SMTP_FROM ? SMTP_FROM : (string) get_option( 'admin_email' );
    }

    private static function cd_recipients(): array {
        $opt = get_option( 'gfoss_cd_recipients' );
        if ( is_string( $opt ) && $opt !== '' ) {
            return array_filter( array_map( 'trim', explode( ',', $opt ) ) );
        }
        // Fallback: admin email + tutti gli utenti con capability di review.
        $reviewers = get_users( [ 'capability' => Roles::CAP_REVIEW_CANDIDATURE, 'fields' => [ 'user_email' ] ] );
        $emails = array_map( static fn( $u ) => $u->user_email, $reviewers );
        $emails[] = (string) get_option( 'admin_email' );
        return array_values( array_unique( array_filter( $emails ) ) );
    }

    private static function send( $to, string $subject, string $body, array $headers = [] ): bool {
        $headers = array_merge( [
            'Content-Type: text/html; charset=UTF-8',
            'From: GFOSS.it APS <' . self::from() . '>',
        ], $headers );
        return wp_mail( $to, $subject, self::wrap( $subject, $body ), $headers );
    }

    private static function wrap( string $title, string $inner ): string {
        $brand = '#1A6FA0';
        $logo  = esc_html( get_bloginfo( 'name' ) );
        return '<!doctype html><html lang="it"><body style="margin:0;background:#FAFBFC;font-family:Inter,Arial,sans-serif;color:#0F2330">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px">'
            . '<div style="background:#fff;border-radius:12px;overflow:hidden;border:1px solid #E2E8EC">'
            . '<div style="background:' . $brand . ';color:#fff;padding:16px 24px;font-weight:700;font-size:16px">' . $logo . '</div>'
            . '<div style="padding:24px;line-height:1.55;font-size:15px">' . $inner . '</div>'
            . '</div>'
            . '<p style="text-align:center;color:#7A8A95;font-size:12px;margin-top:16px">'
            . "Associazione Italiana per l'Informazione Geografica Libera APS · Padova · "
            . '<a href="' . esc_url( home_url() ) . '" style="color:#7A8A95">' . esc_html( home_url() ) . '</a></p>'
            . '</div></body></html>';
    }

    // ---------------------------------------------------------------------
    // Triggered emails

    public static function candidatura_received( array $cand ): void {
        $body = '<h2 style="margin-top:0">Domanda di iscrizione ricevuta</h2>'
              . '<p>Ciao <strong>' . esc_html( $cand['nome'] ) . '</strong>,</p>'
              . '<p>abbiamo ricevuto la tua domanda di iscrizione a GFOSS.it APS. Sarà esaminata dal Consiglio Direttivo (art. 6 dello Statuto) e diventerà effettiva dopo l\'approvazione e il pagamento della quota.</p>'
              . '<p>Puoi versare la quota annuale di ' . esc_html( number_format_i18n( (float) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 ), 2 ) ) . ' € fin da subito:</p>'
              . '<p><a href="' . esc_url( Candidatura::paypal_url( $cand ) ) . '" style="display:inline-block;background:#F39200;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600">Paga ora con PayPal</a></p>'
              . '<p style="color:#4A5C6A">Oppure tramite bonifico — IBAN <code>' . esc_html( defined( 'GFOSS_ASSOC_IBAN' ) ? GFOSS_ASSOC_IBAN : '' ) . '</code>, beneficiario "Associazione Italiana per l\'Informazione Geografica Libera", causale "Nuova iscrizione anno ' . esc_html( gmdate( 'Y' ) ) . '".</p>'
              . '<p>Ti ricontatteremo a breve. Grazie!</p>';
        self::send( $cand['email'], '[GFOSS.it] Domanda di iscrizione ricevuta', $body );
    }

    public static function cd_new_candidatura( array $cand ): void {
        $admin_url = admin_url( 'admin.php?page=gfoss-candidature&id=' . (int) $cand['id'] );
        $body = '<h2 style="margin-top:0">Nuova candidatura</h2>'
              . '<p><strong>' . esc_html( $cand['nome'] . ' ' . $cand['cognome'] ) . '</strong> (' . esc_html( $cand['email'] ) . ') ha richiesto l\'iscrizione.</p>'
              . '<ul>'
              . '<li>Codice fiscale: <code>' . esc_html( (string) $cand['codice_fiscale'] ) . '</code></li>'
              . '<li>Città: ' . esc_html( $cand['citta'] . ' (' . $cand['provincia'] . ')' ) . '</li>'
              . '<li>Professione: ' . esc_html( (string) $cand['professione'] ) . '</li>'
              . '<li>Volontario: ' . ( $cand['volontario'] ? 'sì' : 'no' ) . '</li>'
              . '</ul>'
              . '<p><a href="' . esc_url( $admin_url ) . '" style="background:#1A6FA0;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600">Apri nella dashboard</a></p>';
        self::send( self::cd_recipients(), '[GFOSS.it] Nuova candidatura: ' . $cand['nome'] . ' ' . $cand['cognome'], $body );
    }

    public static function candidatura_approved_pay_now( array $cand ): void {
        if ( $cand['payment_status'] === 'paid' ) { return; }
        $body = '<h2 style="margin-top:0">Domanda approvata 🎉</h2>'
              . '<p>Ciao ' . esc_html( $cand['nome'] ) . ', il Consiglio Direttivo ha <strong>approvato</strong> la tua domanda.</p>'
              . '<p>Per completare l\'iscrizione, versa la quota associativa annuale (' . esc_html( number_format_i18n( (float) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 ), 2 ) ) . ' €):</p>'
              . '<p><a href="' . esc_url( Candidatura::paypal_url( $cand ) ) . '" style="background:#F39200;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600">Paga ora con PayPal</a></p>'
              . '<p>Riceverai le credenziali per accedere all\'area soci appena risulterà il pagamento.</p>';
        self::send( $cand['email'], '[GFOSS.it] La tua domanda è stata approvata — completa il pagamento', $body );
    }

    public static function candidatura_rejected( array $cand ): void {
        $body = '<h2 style="margin-top:0">Esito della tua domanda</h2>'
              . '<p>Ciao ' . esc_html( $cand['nome'] ) . ', il Consiglio Direttivo ha esaminato la tua domanda.</p>'
              . '<p>Purtroppo non è stato possibile accoglierla. Ai sensi dell\'art. 6 dello Statuto puoi richiedere che sull\'istanza si pronunci l\'Assemblea, scrivendo a <a href="mailto:info@gfoss.it">info@gfoss.it</a> entro 60 giorni.</p>'
              . ( $cand['note_review'] ? '<p style="background:#FFF6E5;padding:10px;border-radius:6px"><strong>Motivazione:</strong><br>' . nl2br( esc_html( (string) $cand['note_review'] ) ) . '</p>' : '' );
        self::send( $cand['email'], '[GFOSS.it] Esito della tua domanda di iscrizione', $body );
    }

    public static function quota_renewal_reminder( int $user_id, int $year, int $days_to_eoy ): void {
        $u = get_userdata( $user_id );
        if ( ! $u ) { return; }
        $url = Rinnovo::paypal_url( $user_id, $year );
        $when = $days_to_eoy > 0
            ? "Mancano <strong>{$days_to_eoy} giorni</strong> alla scadenza del 31 dicembre."
            : 'La quota dell\'anno è ora <strong>scaduta</strong>: regolarizza al più presto per non perdere il diritto di voto in assemblea (art. 11 Statuto).';
        $body = '<h2 style="margin-top:0">Promemoria quota associativa ' . esc_html( (string) $year ) . '</h2>'
              . '<p>Ciao <strong>' . esc_html( $u->first_name ?: $u->display_name ) . '</strong>,</p>'
              . '<p>' . $when . '</p>'
              . '<p>Quota: <strong>' . esc_html( number_format_i18n( Quote::default_amount(), 2 ) ) . ' €</strong></p>'
              . '<p><a href="' . esc_url( $url ) . '" style="background:#F39200;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600;display:inline-block">Rinnova ora con PayPal</a></p>'
              . '<p style="color:#4A5C6A">Oppure bonifico — IBAN <code>' . esc_html( defined( 'GFOSS_ASSOC_IBAN' ) ? GFOSS_ASSOC_IBAN : '' ) . '</code>, causale "Rinnovo iscrizione anno ' . esc_html( (string) $year ) . '".</p>'
              . '<p>Grazie per il tuo supporto al software libero geografico in Italia!</p>';
        self::send( $u->user_email, '[GFOSS.it] Promemoria rinnovo quota ' . $year, $body );
    }

    public static function quota_last_call( int $user_id, int $year ): void {
        $u = get_userdata( $user_id );
        if ( ! $u ) { return; }
        $url = Rinnovo::paypal_url( $user_id, $year );
        $body = '<h2 style="margin-top:0">Ultima chiamata: quota ' . esc_html( (string) $year ) . '</h2>'
              . '<p>Ciao ' . esc_html( $u->first_name ?: $u->display_name ) . ',</p>'
              . '<p>Risulta che non hai ancora rinnovato la quota associativa per l\'anno in corso. Senza il rinnovo, ai sensi dell\'<strong>art. 9</strong> dello Statuto la qualità di associato si perde per "<em>mancato pagamento della quota associativa entro i termini</em>".</p>'
              . '<p>Se vuoi mantenere l\'iscrizione e tornare in regola:</p>'
              . '<p><a href="' . esc_url( $url ) . '" style="background:#1A6FA0;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600">Rinnova ora con PayPal</a></p>'
              . '<p style="color:#4A5C6A">Per qualsiasi problema scrivici a <a href="mailto:info@gfoss.it">info@gfoss.it</a>.</p>';
        self::send( $u->user_email, '[GFOSS.it] Ultima chiamata — rinnovo quota ' . $year, $body );
    }

    public static function admin_quota_summary( int $year, array $unpaid ): void {
        if ( ! $unpaid ) { return; }
        $rows = '';
        foreach ( $unpaid as $u ) {
            $rows .= '<tr>'
                  . '<td style="padding:6px;border-bottom:1px solid #eee">' . esc_html( $u['display_name'] ) . '</td>'
                  . '<td style="padding:6px;border-bottom:1px solid #eee">' . esc_html( $u['user_email'] ) . '</td>'
                  . '</tr>';
        }
        $count = count( $unpaid );
        $body = '<h2 style="margin-top:0">Riepilogo settimanale: ' . $count . ' soci da rinnovare per il ' . $year . '</h2>'
              . '<p>I soci elencati non hanno ancora versato la quota dell\'anno in corso.</p>'
              . '<table style="width:100%;border-collapse:collapse;font-size:14px"><thead><tr>'
              . '<th align="left" style="padding:6px;border-bottom:2px solid #1A6FA0">Nome</th>'
              . '<th align="left" style="padding:6px;border-bottom:2px solid #1A6FA0">Email</th>'
              . '</tr></thead><tbody>' . $rows . '</tbody></table>'
              . '<p style="margin-top:1rem"><a href="' . esc_url( admin_url( 'admin.php?page=gfoss-associazione' ) ) . '" style="background:#1A6FA0;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600">Apri dashboard associazione</a></p>';
        self::send( self::cd_recipients(), '[GFOSS.it] Riepilogo soci da rinnovare (' . $count . ')', $body );
    }

    public static function candidatura_welcome( int $user_id, array $cand ): void {
        $u = get_userdata( $user_id );
        if ( ! $u ) { return; }
        $area = home_url( '/area-soci/' );
        $body = '<h2 style="margin-top:0">Benvenuto/a in GFOSS.it APS 🌍</h2>'
              . '<p>Ciao <strong>' . esc_html( $u->display_name ) . '</strong>, la tua iscrizione è ora <strong>effettiva</strong>.</p>'
              . '<p>Numero socio: <code>' . esc_html( (string) get_user_meta( $user_id, 'gf_numero_socio', true ) ) . '</code></p>'
              . '<p>Riceverai un\'email separata da WordPress per impostare la tua password. Una volta fatto, accedi all\'area riservata:</p>'
              . '<p><a href="' . esc_url( $area ) . '" style="background:#1A6FA0;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600">Apri area soci</a></p>'
              . '<p>Da lì potrai scaricare la <strong>tessera digitale</strong>, vedere lo storico quote, iscriverti agli eventi e accedere ai documenti riservati ai soci.</p>'
              . '<p>Grazie per supportare il software libero geografico in Italia!</p>';
        self::send( $u->user_email, '[GFOSS.it] Benvenuto/a — la tua iscrizione è effettiva', $body );
    }
}
