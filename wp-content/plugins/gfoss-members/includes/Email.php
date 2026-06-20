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
        add_action( 'admin_post_gfoss_email_settings_save', [ __CLASS__, 'save_settings' ] );
    }

    /** Impostazioni email personalizzabili da admin (Associazione → Email). */
    public static function settings(): array {
        $s = get_option( 'gfoss_email_settings', [] );
        return is_array( $s ) ? $s : [];
    }

    private static function tpl_setting( string $key, string $field ): string {
        $s = self::settings();
        return (string) ( $s['tpl'][ $key ][ $field ] ?? '' );
    }

    private static function button( string $url, string $label, string $color = '#F39200' ): string {
        return '<a href="' . esc_url( $url ) . '" style="display:inline-block;background:' . $color
             . ';color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600">'
             . esc_html( $label ) . '</a>';
    }

    /**
     * Registro dei template email editabili: chiave => [label, subject, body, placeholders].
     * Il corpo è HTML; i segnaposto {…} vengono sostituiti all'invio.
     */
    public static function templates(): array {
        $iban  = defined( 'GFOSS_ASSOC_IBAN' ) ? GFOSS_ASSOC_IBAN : '';
        return [
            'candidatura_received' => [
                'label'        => 'Conferma domanda di iscrizione (al candidato)',
                'subject'      => '[GFOSS.it] Domanda di iscrizione ricevuta',
                'body'         => "<h2 style=\"margin-top:0\">Domanda di iscrizione ricevuta</h2>\n<p>Ciao <strong>{nome}</strong>,</p>\n<p>abbiamo ricevuto la tua domanda di iscrizione a GFOSS.it APS. Sarà esaminata dal Consiglio Direttivo (art. 6 dello Statuto) e diventerà effettiva dopo l'approvazione e il pagamento della quota.</p>\n<p>Puoi versare la quota annuale di {importo} € fin da subito:</p>\n<p>{bottone_pagamento}</p>\n<p style=\"color:#4A5C6A\">Oppure tramite bonifico — IBAN <code>{iban}</code>, causale \"Nuova iscrizione anno {anno}\".</p>\n<p>Ti ricontatteremo a breve. Grazie!</p>",
                'placeholders' => [ 'nome', 'cognome', 'importo', 'anno', 'iban', 'link_pagamento', 'bottone_pagamento' ],
            ],
            'candidatura_approved_pay_now' => [
                'label'        => 'Domanda approvata, da pagare (al candidato)',
                'subject'      => '[GFOSS.it] La tua domanda è stata approvata — completa il pagamento',
                'body'         => "<h2 style=\"margin-top:0\">Domanda approvata 🎉</h2>\n<p>Ciao {nome}, il Consiglio Direttivo ha <strong>approvato</strong> la tua domanda.</p>\n<p>Per completare l'iscrizione, versa la quota associativa annuale ({importo} €):</p>\n<p>{bottone_pagamento}</p>\n<p>Riceverai le credenziali per accedere all'area soci appena risulterà il pagamento.</p>",
                'placeholders' => [ 'nome', 'importo', 'anno', 'link_pagamento', 'bottone_pagamento' ],
            ],
            'candidatura_rejected' => [
                'label'        => 'Domanda respinta (al candidato)',
                'subject'      => '[GFOSS.it] Esito della tua domanda di iscrizione',
                'body'         => "<h2 style=\"margin-top:0\">Esito della tua domanda</h2>\n<p>Ciao {nome}, il Consiglio Direttivo ha esaminato la tua domanda.</p>\n<p>Purtroppo non è stato possibile accoglierla. Ai sensi dell'art. 6 dello Statuto puoi richiedere che sull'istanza si pronunci l'Assemblea, scrivendo a info@gfoss.it entro 60 giorni.</p>\n{motivo_box}",
                'placeholders' => [ 'nome', 'cognome', 'motivo_box' ],
            ],
            'candidatura_welcome' => [
                'label'        => 'Benvenuto socio effettivo',
                'subject'      => '[GFOSS.it] Benvenuto/a — la tua iscrizione è effettiva',
                'body'         => "<h2 style=\"margin-top:0\">Benvenuto/a in GFOSS.it APS 🌍</h2>\n<p>Ciao <strong>{nome}</strong>, la tua iscrizione è ora <strong>effettiva</strong>.</p>\n<p>Numero socio: <code>{numero_socio}</code></p>\n<p>Riceverai un'email separata da WordPress per impostare la tua password. Una volta fatto, accedi all'area riservata:</p>\n<p>{bottone_area}</p>\n<p>Da lì potrai scaricare la tessera digitale, vedere lo storico quote, iscriverti agli eventi e accedere ai documenti riservati ai soci.</p>\n<p>Grazie per supportare il software libero geografico in Italia!</p>",
                'placeholders' => [ 'nome', 'numero_socio', 'anno', 'link_area', 'bottone_area' ],
            ],
            'quota_renewal_reminder' => [
                'label'        => 'Promemoria rinnovo quota',
                'subject'      => '[GFOSS.it] Promemoria rinnovo quota {anno}',
                'body'         => "<h2 style=\"margin-top:0\">Promemoria quota associativa {anno}</h2>\n<p>Ciao <strong>{nome}</strong>,</p>\n<p>{scadenza}</p>\n<p>Quota: <strong>{importo} €</strong></p>\n<p>{bottone_pagamento}</p>\n<p style=\"color:#4A5C6A\">Oppure bonifico — IBAN <code>{iban}</code>, causale \"Rinnovo iscrizione anno {anno}\".</p>\n<p>Grazie per il tuo supporto al software libero geografico in Italia!</p>",
                'placeholders' => [ 'nome', 'anno', 'importo', 'iban', 'scadenza', 'link_pagamento', 'bottone_pagamento' ],
            ],
            'quota_last_call' => [
                'label'        => 'Ultima chiamata rinnovo quota',
                'subject'      => '[GFOSS.it] Ultima chiamata — rinnovo quota {anno}',
                'body'         => "<h2 style=\"margin-top:0\">Ultima chiamata: quota {anno}</h2>\n<p>Ciao {nome},</p>\n<p>Risulta che non hai ancora rinnovato la quota associativa per l'anno in corso. Senza il rinnovo, ai sensi dell'art. 9 dello Statuto la qualità di associato si perde per mancato pagamento entro i termini.</p>\n<p>Se vuoi mantenere l'iscrizione e tornare in regola:</p>\n<p>{bottone_pagamento}</p>\n<p style=\"color:#4A5C6A\">Per qualsiasi problema scrivici a info@gfoss.it.</p>",
                'placeholders' => [ 'nome', 'anno', 'link_pagamento', 'bottone_pagamento' ],
            ],
        ];
    }

    /** @return array{subject:string,body:string} con segnaposto sostituiti. */
    public static function render( string $key, array $vars ): array {
        $tpls = self::templates();
        $def  = $tpls[ $key ] ?? [ 'subject' => '', 'body' => '' ];
        $subject = self::tpl_setting( $key, 'subject' );
        $body    = self::tpl_setting( $key, 'body' );
        if ( trim( $subject ) === '' ) { $subject = $def['subject']; }
        if ( trim( $body ) === '' )    { $body    = $def['body']; }
        $search = []; $replace = [];
        foreach ( $vars as $k => $v ) { $search[] = '{' . $k . '}'; $replace[] = (string) $v; }
        return [
            'subject' => str_replace( $search, $replace, $subject ),
            'body'    => str_replace( $search, $replace, $body ),
        ];
    }

    public static function save_settings(): void {
        if ( ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) { wp_die( 'forbidden', 403 ); }
        check_admin_referer( 'gfoss_email_settings' );
        $posted = ( isset( $_POST['tpl'] ) && is_array( $_POST['tpl'] ) ) ? wp_unslash( $_POST['tpl'] ) : [];
        $s = [
            'brand_color' => sanitize_hex_color( (string) ( $_POST['brand_color'] ?? '' ) ) ?: '',
            'footer'      => sanitize_text_field( wp_unslash( $_POST['footer'] ?? '' ) ),
            'tpl'         => [],
        ];
        foreach ( array_keys( self::templates() ) as $key ) {
            $s['tpl'][ $key ] = [
                'subject' => sanitize_text_field( (string) ( $posted[ $key ]['subject'] ?? '' ) ),
                'body'    => wp_kses_post( (string) ( $posted[ $key ]['body'] ?? '' ) ),
            ];
        }
        update_option( 'gfoss_email_settings', $s, false );
        wp_safe_redirect( add_query_arg( 'msg', 'saved', admin_url( 'admin.php?page=gfoss-email' ) ) );
        exit;
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
        // Personalizzabili via filtro senza modificare il plugin:
        //   add_filter('gfoss_members_email_brand_color', fn() => '#XXXXXX');
        //   add_filter('gfoss_members_email_footer', fn() => 'Testo footer...');
        //   add_filter('gfoss_members_email_wrap', fn($html,$title,$inner) => ..., 10, 3);
        $cfg   = self::settings();
        $brand = ! empty( $cfg['brand_color'] ) ? $cfg['brand_color'] : apply_filters( 'gfoss_members_email_brand_color', '#1A6FA0' );

        // Header: logo del sito se impostato, altrimenti il nome del sito.
        $logo_id  = (int) get_theme_mod( 'custom_logo' );
        $logo_src = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        $header   = $logo_src
            ? '<img src="' . esc_url( $logo_src ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" style="height:42px;width:auto;display:block">'
            : '<span style="font-weight:700;font-size:18px;color:' . esc_attr( $brand ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';

        $footer = ! empty( $cfg['footer'] ) ? $cfg['footer'] : apply_filters(
            'gfoss_members_email_footer',
            "Associazione Italiana per l'Informazione Geografica Libera APS · Padova"
        );

        $html = '<!doctype html><html lang="it"><body style="margin:0;background:#FAFBFC;font-family:Inter,Arial,sans-serif;color:#0F2330">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px">'
            . '<div style="background:#fff;border-radius:12px;overflow:hidden;border:1px solid #E2E8EC;border-top:4px solid ' . esc_attr( $brand ) . '">'
            . '<div style="background:#fff;padding:18px 24px;border-bottom:1px solid #E2E8EC">' . $header . '</div>'
            . '<div style="padding:24px;line-height:1.55;font-size:15px">' . $inner . '</div>'
            . '</div>'
            . '<p style="text-align:center;color:#7A8A95;font-size:12px;margin-top:16px">'
            . esc_html( $footer ) . ' · '
            . '<a href="' . esc_url( home_url() ) . '" style="color:#7A8A95">' . esc_html( home_url() ) . '</a></p>'
            . '</div></body></html>';

        return (string) apply_filters( 'gfoss_members_email_wrap', $html, $title, $inner );
    }

    // ---------------------------------------------------------------------
    // Triggered emails

    public static function candidatura_received( array $cand ): void {
        $pay = Candidatura::paypal_url( $cand );
        $r = self::render( 'candidatura_received', [
            'nome'              => esc_html( $cand['nome'] ),
            'cognome'           => esc_html( (string) ( $cand['cognome'] ?? '' ) ),
            'importo'           => esc_html( number_format_i18n( (float) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 ), 2 ) ),
            'anno'              => esc_html( gmdate( 'Y' ) ),
            'iban'              => esc_html( defined( 'GFOSS_ASSOC_IBAN' ) ? GFOSS_ASSOC_IBAN : '' ),
            'link_pagamento'    => esc_url( $pay ),
            'bottone_pagamento' => self::button( $pay, 'Paga ora con PayPal' ),
        ] );
        self::send( $cand['email'], $r['subject'], $r['body'] );
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
        $pay = Candidatura::paypal_url( $cand );
        $r = self::render( 'candidatura_approved_pay_now', [
            'nome'              => esc_html( $cand['nome'] ),
            'importo'           => esc_html( number_format_i18n( (float) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 ), 2 ) ),
            'anno'              => esc_html( gmdate( 'Y' ) ),
            'link_pagamento'    => esc_url( $pay ),
            'bottone_pagamento' => self::button( $pay, 'Paga ora con PayPal' ),
        ] );
        self::send( $cand['email'], $r['subject'], $r['body'] );
    }

    public static function candidatura_rejected( array $cand ): void {
        $motivo = $cand['note_review']
            ? '<p style="background:#FFF6E5;padding:10px;border-radius:6px"><strong>Motivazione:</strong><br>' . nl2br( esc_html( (string) $cand['note_review'] ) ) . '</p>'
            : '';
        $r = self::render( 'candidatura_rejected', [
            'nome'       => esc_html( $cand['nome'] ),
            'cognome'    => esc_html( (string) ( $cand['cognome'] ?? '' ) ),
            'motivo_box' => $motivo,
        ] );
        self::send( $cand['email'], $r['subject'], $r['body'] );
    }

    public static function quota_renewal_reminder( int $user_id, int $year, int $days_to_eoy ): void {
        $u = get_userdata( $user_id );
        if ( ! $u ) { return; }
        $url  = Rinnovo::paypal_url( $user_id, $year );
        $when = $days_to_eoy > 0
            ? "Mancano <strong>{$days_to_eoy} giorni</strong> alla scadenza del 31 dicembre."
            : 'La quota dell\'anno è ora <strong>scaduta</strong>: regolarizza al più presto per non perdere il diritto di voto in assemblea (art. 11 Statuto).';
        $r = self::render( 'quota_renewal_reminder', [
            'nome'              => esc_html( $u->first_name ?: $u->display_name ),
            'anno'              => esc_html( (string) $year ),
            'importo'           => esc_html( number_format_i18n( Quote::default_amount(), 2 ) ),
            'iban'              => esc_html( defined( 'GFOSS_ASSOC_IBAN' ) ? GFOSS_ASSOC_IBAN : '' ),
            'scadenza'          => $when,
            'link_pagamento'    => esc_url( $url ),
            'bottone_pagamento' => self::button( $url, 'Rinnova ora con PayPal' ),
        ] );
        self::send( $u->user_email, $r['subject'], $r['body'] );
    }

    public static function quota_last_call( int $user_id, int $year ): void {
        $u = get_userdata( $user_id );
        if ( ! $u ) { return; }
        $url = Rinnovo::paypal_url( $user_id, $year );
        $r = self::render( 'quota_last_call', [
            'nome'              => esc_html( $u->first_name ?: $u->display_name ),
            'anno'              => esc_html( (string) $year ),
            'link_pagamento'    => esc_url( $url ),
            'bottone_pagamento' => self::button( $url, 'Rinnova ora con PayPal', '#1A6FA0' ),
        ] );
        self::send( $u->user_email, $r['subject'], $r['body'] );
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
        $r = self::render( 'candidatura_welcome', [
            'nome'         => esc_html( $u->display_name ),
            'numero_socio' => esc_html( (string) get_user_meta( $user_id, 'gf_numero_socio', true ) ),
            'anno'         => esc_html( gmdate( 'Y' ) ),
            'link_area'    => esc_url( $area ),
            'bottone_area' => self::button( $area, 'Apri area soci', '#1A6FA0' ),
        ] );
        self::send( $u->user_email, $r['subject'], $r['body'] );
    }
}
